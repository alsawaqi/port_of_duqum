<?php

namespace App\Libraries\Payments;

use Config\EservicesPayments;
use RuntimeException;

final class Stripe_payment_gateway implements Payment_gateway_interface
{
    private EservicesPayments $config;
    private \Stripe\StripeClient $client;

    public function __construct(?EservicesPayments $config = null)
    {
        $this->config = $config ?: config('EservicesPayments');
        if (!$this->config->isReady()) {
            throw new RuntimeException('The e-service payment provider is not configured.');
        }

        require_once APPPATH . 'ThirdParty/Stripe/vendor/autoload.php';
        $this->client = new \Stripe\StripeClient([
            'api_key' => $this->config->stripeSecretKey,
            'stripe_version' => '2022-11-15',
        ]);
    }

    public function createCheckout(array $payment): array
    {
        $currency = strtolower((string)$payment['currency']);
        $expiresAt = time() + $this->config->checkoutTtlSeconds;
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'client_reference_id' => (string)$payment['public_id'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'unit_amount' => (int)$payment['amount_minor'],
                    'currency' => $currency,
                    'product_data' => [
                        'name' => mb_substr((string)$payment['description'], 0, 120),
                    ],
                ],
            ]],
            'metadata' => [
                'eservice_payment_id' => (string)$payment['public_id'],
                'subject_type' => (string)$payment['subject_type'],
                'subject_id' => (string)$payment['subject_id'],
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'eservice_payment_id' => (string)$payment['public_id'],
                    'subject_type' => (string)$payment['subject_type'],
                    'subject_id' => (string)$payment['subject_id'],
                ],
            ],
            'success_url' => (string)$payment['success_url'],
            'cancel_url' => (string)$payment['cancel_url'],
            'expires_at' => $expiresAt,
        ], [
            'idempotency_key' => (string)$payment['idempotency_key'],
        ]);

        if (empty($session->id) || empty($session->url)) {
            throw new RuntimeException('The payment provider did not create a checkout session.');
        }

        return [
            'checkout_id' => (string)$session->id,
            'checkout_url' => (string)$session->url,
            'expires_at' => $expiresAt,
        ];
    }

    public function verifyWebhook(string $rawBody, string $signature): object
    {
        require_once APPPATH . 'ThirdParty/Stripe/vendor/autoload.php';
        return \Stripe\Webhook::constructEvent(
            $rawBody,
            $signature,
            $this->config->stripeWebhookSecret,
            $this->config->webhookToleranceSeconds
        );
    }

    public function minorUnitExponent(string $currency): int
    {
        $zeroDecimal = [
            'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
            'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
        ];

        return in_array(strtoupper($currency), $zeroDecimal, true) ? 0 : 2;
    }
}
