<?php

namespace App\Controllers;

use App\Libraries\Payments\Eservice_payment_manager;

/** Public only for signed provider callbacks; no login/session authority is used. */
final class Eservice_payment_webhook extends App_Controller
{
    private Eservice_payment_manager $payments;

    public function __construct()
    {
        parent::__construct();
        $this->payments = new Eservice_payment_manager();
    }

    public function index()
    {
        return $this->response->setStatusCode(404);
    }

    public function stripe()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setHeader('Allow', 'POST');
        }

        $rawBody = (string)$this->request->getBody();
        $signature = trim((string)$this->request->getHeaderLine('Stripe-Signature'));
        if ($rawBody === '' || strlen($rawBody) > 262144 || $signature === '') {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid payment notification.',
            ]);
        }

        $result = $this->payments->processStripeWebhook($rawBody, $signature);
        $statusCode = (int)($result['status_code'] ?? 500);
        unset($result['status_code']);

        return $this->response
            ->setStatusCode($statusCode)
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($result);
    }
}
