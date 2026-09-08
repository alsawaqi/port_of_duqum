<?php

namespace App\Controllers;

use App\Libraries\Payments\Eservice_payment_manager;

/** Public hosted handoff and encrypted return; this controller has no session-based settlement authority. */
final class Eservice_payment extends \CodeIgniter\Controller
{
    // A cross-site bank POST does not carry a SameSite=Lax login cookie.
    // Keep the public callback stateless so it cannot replace that login session.
    protected $helpers = ['url', 'form', 'general', 'date_time'];

    public function index()
    {
        return $this->response->setStatusCode(404);
    }

    public function checkout(string $publicId = '')
    {
        $payment = (new Eservice_payment_manager())->smartpayPaymentByPublicId($publicId);
        if (!$payment) {
            return $this->unavailablePayment();
        }
        return $this->response->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer')
            ->setBody(view('eservice_payment/checkout', ['payment' => $payment]));
    }

    public function handoff(string $publicId = '')
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST');
        }
        $result = (new Eservice_payment_manager())->prepareSmartpayHandoff($publicId);
        if (!$result['success']) {
            return $this->response->setStatusCode($result['status_code'])->setHeader('Cache-Control', 'no-store')
                ->setBody(view('eservice_payment/result', ['payment' => null, 'message' => $result['message']]));
        }
        return $this->response->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'origin')
            ->setBody(view('eservice_payment/handoff', $result));
    }

    public function return_from_bank()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST');
        }
        try {
            [$encoded, $orderId] = $this->bankReturnFields();
        } catch (\InvalidArgumentException $exception) {
            return $this->response->setStatusCode(400)->setHeader('Cache-Control', 'no-store')->setBody('Invalid bank response.');
        }
        $result = (new Eservice_payment_manager())->processSmartpayReturn($encoded, $orderId);
        if (!empty($result['payment_id'])) {
            return redirect()->to(get_uri('eservice_payment/result/' . $result['payment_id']))->setStatusCode(303)->setHeader('Cache-Control', 'no-store');
        }
        return $this->response->setStatusCode($result['status_code'])->setHeader('Cache-Control', 'no-store')
            ->setBody(view('eservice_payment/result', ['payment' => null, 'message' => $result['message']]));
    }

    public function result(string $publicId = '')
    {
        $payment = (new Eservice_payment_manager())->smartpayPaymentByPublicId($publicId);
        if (!$payment) {
            return $this->unavailablePayment();
        }
        return $this->response->setHeader('Cache-Control', 'no-store')->setHeader('Referrer-Policy', 'no-referrer')
            ->setBody(view('eservice_payment/result', ['payment' => $payment, 'message' => '']));
    }

    private function unavailablePayment()
    {
        return $this->response->setStatusCode(404)->setHeader('Cache-Control', 'no-store')
            ->setBody(view('eservice_payment/result', ['payment' => null,
                'message' => 'This payment link is invalid or no longer available. Return to your application to check the fee.']));
    }

    /** Only the POST body is accepted. The outer order remains an untrusted audit hint. */
    private function bankReturnFields(): array
    {
        // encResp/orderNo are the field names observed on Bank Muscat UAT.
        // Retain the aliases used by the merchant integration example.
        $groups = [[131072, 'encResp', 'encResponse', 'enc_response'], [128, 'orderNo', 'order_id', 'orderId']];
        $names = ['encResp', 'encResponse', 'enc_response', 'orderNo', 'order_id', 'orderId'];
        // PHP normally keeps the final repeated form field. Reject conflicting
        // duplicates before accepting that parsed representation.
        if (str_starts_with(strtolower($this->request->getHeaderLine('Content-Type')), 'application/x-www-form-urlencoded')) {
            $raw = (string)$this->request->getBody();
            if (strlen($raw) > 263168) {
                throw new \InvalidArgumentException('Invalid bank return size.');
            }
            $seen = [];
            foreach (explode('&', $raw) as $pair) {
                $parts = explode('=', $pair, 2);
                $name = urldecode($parts[0]);
                if (!in_array($name, $names, true)) {
                    continue;
                }
                $value = urldecode($parts[1] ?? '');
                if (isset($seen[$name]) && !hash_equals($seen[$name], $value)) {
                    throw new \InvalidArgumentException('Conflicting bank return fields.');
                }
                $seen[$name] = $value;
            }
        }
        $values = [];
        foreach ($groups as $group) {
            $limit = array_shift($group);
            $selected = null;
            foreach ($group as $name) {
                $value = $this->request->getPost($name);
                if ($value === null) {
                    continue;
                }
                if (!is_string($value) || strlen($value) > $limit) {
                    throw new \InvalidArgumentException('Invalid bank return field.');
                }
                if ($selected !== null && !hash_equals($selected, $value)) {
                    throw new \InvalidArgumentException('Conflicting bank return aliases.');
                }
                $selected = $value;
            }
            $values[] = $selected ?? '';
        }
        return $values;
    }
}
