<?php

namespace App\Libraries;

use App\Libraries\Payments\Legacy_invoice_payment_manager;
use App\Libraries\Payments\Payment_amount;
use DomainException;
use RuntimeException;
use Throwable;

class Paypal
{
    private $paypal_live_url = 'https://api-m.paypal.com/v1';
    private $paypal_sandbox_url = 'https://api-m.sandbox.paypal.com/v1';
    private $paypal_url = '';
    private $paypal_config;
    private $Payment_methods_model;

    public function __construct()
    {
        $this->Payment_methods_model = model('App\Models\Payment_methods_model');
        $this->paypal_config = $this->Payment_methods_model
            ->get_oneline_payment_method('paypal_payments_standard');
        $this->paypal_url = $this->paypal_config->paypal_live == '1'
            ? $this->paypal_live_url
            : $this->paypal_sandbox_url;
    }

    public function get_paypal_checkout_url($data = [], $login_user = 0)
    {
        $manager = new Legacy_invoice_payment_manager();
        $attempt = $manager->start('paypal', (array)$data, (int)$login_user);

        $paymentData = [
            'intent' => 'sale',
            'payer' => ['payment_method' => 'paypal'],
            'transactions' => [[
                'amount' => [
                    'total' => (string)$attempt->expected_amount,
                    'currency' => (string)$attempt->currency,
                ],
                'description' => 'Invoice ' . (string)$attempt->invoice_display_id,
                'custom' => (string)$attempt->public_id,
                'invoice_number' => (string)$attempt->invoice_display_id . '-' . strtoupper(substr((string)$attempt->public_id, 0, 12)),
                'payment_options' => [
                    'allowed_payment_method' => 'INSTANT_FUNDING_SOURCE',
                ],
            ]],
            'redirect_urls' => [
                'return_url' => get_uri('paypal_redirect/index/' . $attempt->public_id),
                'cancel_url' => $this->invoiceReturnUrl($attempt),
            ],
        ];

        try {
            $checkout = $this->do_request('POST', '/payments/payment', $paymentData);
            $paymentId = trim((string)($checkout->id ?? ''));
            if ($paymentId === '') {
                throw new RuntimeException('PayPal did not create a checkout.');
            }

            $approvalUrl = '';
            foreach ((array)($checkout->links ?? []) as $link) {
                if ((string)($link->rel ?? '') === 'approval_url'
                    && str_starts_with((string)($link->href ?? ''), 'https://')) {
                    $approvalUrl = (string)$link->href;
                    break;
                }
            }
            if ($approvalUrl === '') {
                throw new RuntimeException('PayPal did not return an approval URL.');
            }

            $manager->bindProviderReference((int)$attempt->id, $paymentId);
            return $approvalUrl;
        } catch (Throwable $exception) {
            $manager->markFailed((string)$attempt->public_id, 'paypal', 'checkout_creation_failed');
            throw $exception;
        }
    }

    /**
     * Executes and verifies the exact server-bound PayPal payment, then settles
     * the invoice atomically. A completed attempt is safe to revisit.
     */
    public function settle_invoice_attempt(string $publicId, array $query): array
    {
        $manager = new Legacy_invoice_payment_manager();
        $attempt = $manager->getAttempt($publicId, 'paypal');
        if (!$attempt || empty($attempt->provider_reference)) {
            throw new DomainException('The PayPal payment attempt is invalid.');
        }

        $paymentId = trim((string)($query['paymentId'] ?? ''));
        $payerId = trim((string)($query['PayerID'] ?? ''));
        if (!hash_equals((string)$attempt->provider_reference, $paymentId)
            || preg_match('/^[A-Za-z0-9._:-]{1,191}$/D', $paymentId) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{1,191}$/D', $payerId) !== 1) {
            throw new DomainException('The PayPal callback is not bound to this checkout.');
        }

        if (in_array((string)$attempt->status, ['completed', 'review_required'], true)) {
            return $manager->settle(
                (string)$attempt->public_id,
                'paypal',
                $paymentId,
                (string)$attempt->provider_transaction_id,
                (int)$attempt->expected_amount_minor,
                (string)$attempt->currency
            );
        }

        $payment = $this->do_request(
            'POST',
            '/payments/payment/' . rawurlencode($paymentId) . '/execute',
            ['payer_id' => $payerId]
        );
        if (!hash_equals($paymentId, (string)($payment->id ?? ''))
            || (string)($payment->state ?? '') !== 'approved') {
            throw new DomainException('PayPal did not confirm an approved payment.');
        }

        $transactions = (array)($payment->transactions ?? []);
        if (count($transactions) !== 1) {
            throw new DomainException('PayPal returned an unexpected transaction set.');
        }
        $transaction = reset($transactions);
        $amount = $transaction->amount ?? null;
        $currency = strtoupper(trim((string)($amount->currency ?? '')));
        $total = trim((string)($amount->total ?? ''));
        $custom = trim((string)($transaction->custom ?? ''));
        $amountMinor = Payment_amount::toMinor(
            $total,
            Legacy_invoice_payment_manager::minorUnitExponent($currency)
        );
        if (!hash_equals((string)$attempt->public_id, $custom)
            || !hash_equals((string)$attempt->currency, $currency)
            || (int)$attempt->expected_amount_minor !== $amountMinor) {
            throw new DomainException('PayPal amount, currency, or attempt binding mismatch.');
        }

        $sale = null;
        foreach ((array)($transaction->related_resources ?? []) as $resource) {
            if (isset($resource->sale)) {
                $sale = $resource->sale;
                break;
            }
        }
        $saleId = trim((string)($sale->id ?? ''));
        $saleCurrency = strtoupper(trim((string)($sale->amount->currency ?? '')));
        $saleAmountMinor = Payment_amount::toMinor(
            trim((string)($sale->amount->total ?? '')),
            Legacy_invoice_payment_manager::minorUnitExponent($saleCurrency)
        );
        if ($saleId === ''
            || (string)($sale->state ?? '') !== 'completed'
            || !hash_equals((string)$attempt->currency, $saleCurrency)
            || (int)$attempt->expected_amount_minor !== $saleAmountMinor) {
            throw new DomainException('PayPal did not return a completed sale.');
        }

        return $manager->settle(
            (string)$attempt->public_id,
            'paypal',
            $paymentId,
            $saleId,
            $amountMinor,
            $currency
        );
    }

    private function invoiceReturnUrl(object $attempt): string
    {
        if (!empty($attempt->invoice_verification_code)) {
            return get_uri('pay_invoice/index/' . $attempt->invoice_verification_code);
        }
        return get_uri('invoices/preview/' . (int)$attempt->invoice_id);
    }

    private function get_access_token(): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->paypal_url . '/oauth2/token');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->paypal_config->client_id . ':' . $this->paypal_config->client_secret);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type' => 'client_credentials']));
        $this->applySecureCurlOptions($ch);

        $result = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $payload = $this->decodeResponse($result, $errorNumber, $httpStatus);
        $token = trim((string)($payload->access_token ?? ''));
        if ($token === '') {
            throw new RuntimeException('PayPal authentication failed.');
        }
        return $token;
    }

    private function do_request(string $method, string $path, array $body = []): object
    {
        $method = strtoupper($method);
        if (!in_array($method, ['DELETE', 'PATCH', 'POST', 'PUT', 'GET'], true)
            || !preg_match('#^/[A-Za-z0-9_./:-]+$#D', $path)) {
            throw new DomainException('Invalid PayPal API request.');
        }

        $encodedBody = $body ? json_encode($body, JSON_UNESCAPED_SLASHES) : '';
        if ($encodedBody === false) {
            throw new RuntimeException('Unable to encode the PayPal request.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->paypal_url . $path);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->get_access_token(),
            'Accept: application/json',
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($method !== 'DELETE' && $encodedBody !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedBody);
        }
        $this->applySecureCurlOptions($ch);

        $result = curl_exec($ch);
        $errorNumber = curl_errno($ch);
        $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $this->decodeResponse($result, $errorNumber, $httpStatus);
    }

    private function applySecureCurlOptions($ch): void
    {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if (defined('CURLOPT_SSLVERSION') && defined('CURL_SSLVERSION_TLSv1_2')) {
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PODC-Invoice-Payments/1.0');
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }
    }

    private function decodeResponse($result, int $errorNumber, int $httpStatus): object
    {
        if ($errorNumber !== 0 || !is_string($result) || $result === '') {
            log_message('error', 'PAYPAL TRANSPORT FAILURE: curl={curl} http={http}', [
                'curl' => $errorNumber,
                'http' => $httpStatus,
            ]);
            throw new RuntimeException('The secure payment provider is unavailable.');
        }
        $payload = json_decode($result);
        if (!is_object($payload) || $httpStatus < 200 || $httpStatus >= 300) {
            log_message('warning', 'PAYPAL API REJECTED REQUEST: http={http}', ['http' => $httpStatus]);
            throw new RuntimeException('The secure payment provider rejected the request.');
        }
        return $payload;
    }
}
