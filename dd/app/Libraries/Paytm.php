<?php

namespace App\Libraries;

use App\Libraries\Payments\Legacy_invoice_payment_manager;
use App\Libraries\Payments\Payment_amount;
use DomainException;
use RuntimeException;
use Throwable;

class Paytm
{
    private $paytm_url = '';
    private $paytm_secret_key = '';
    private $paytm_config;

    public function __construct()
    {
        $paymentMethods = model('App\Models\Payment_methods_model');
        $this->paytm_config = $paymentMethods->get_oneline_payment_method('paytm');
        $this->paytm_secret_key = (string)$this->paytm_config->secret_key;
        $this->paytm_url = $this->paytm_config->paytm_testing_environment == '1'
            ? 'https://securegw-stage.paytm.in/theia/processTransaction'
            : 'https://securegw.paytm.in/theia/processTransaction';

        require_once APPPATH . 'ThirdParty/Paytm/encdec_paytm.php';
    }

    public function get_paytm_url()
    {
        return $this->paytm_url;
    }

    /**
     * Creates a checksum only over server-generated invoice fields. This method
     * intentionally has no API that accepts arbitrary fields to sign.
     */
    public function get_paytm_checksum_hash(array $requestData, int $loginUserId = 0)
    {
        $this->assertConfigured();
        $manager = new Legacy_invoice_payment_manager();
        $attempt = $manager->start('paytm', $requestData, $loginUserId);
        $orderId = 'PODINV-' . (int)$attempt->invoice_id . '-' . substr((string)$attempt->public_id, 0, 20);
        $fields = [
            'MID' => (string)$this->paytm_config->merchant_id,
            'ORDER_ID' => $orderId,
            'CUST_ID' => 'CLIENT-' . (int)$attempt->client_id,
            'INDUSTRY_TYPE_ID' => (string)$this->paytm_config->industry_type,
            'CHANNEL_ID' => 'WEB',
            'TXN_AMOUNT' => (string)$attempt->expected_amount,
            'WEBSITE' => (string)$this->paytm_config->merchant_website,
            'CALLBACK_URL' => get_uri('paytm_redirect/index/' . $attempt->public_id),
        ];

        foreach ($fields as $field => $value) {
            if ($value === '' || strlen($value) > 255) {
                $manager->markFailed((string)$attempt->public_id, 'paytm', 'invalid_provider_configuration');
                throw new RuntimeException('Paytm is not configured correctly.');
            }
        }

        $providerReferenceBound = false;
        try {
            $manager->bindProviderReference((int)$attempt->id, $orderId);
            $providerReferenceBound = true;
            $checksum = getChecksumFromArray($fields, $this->paytm_secret_key);
            if (!is_string($checksum) || $checksum === '') {
                throw new RuntimeException('Paytm did not create a checksum.');
            }
            return [
                'payment_verification_code' => (string)$attempt->public_id,
                'checksum_hash' => $checksum,
                'input_data' => $fields,
            ];
        } catch (Throwable $exception) {
            $manager->markFailed(
                (string)$attempt->public_id,
                'paytm',
                'checkout_creation_failed',
                $providerReferenceBound ? $orderId : ''
            );
            throw $exception;
        }
    }

    /**
     * Verifies the signed callback and every immutable attempt field before the
     * shared manager performs a single atomic settlement.
     */
    public function settle_invoice_attempt(string $publicId, array $postData): array
    {
        $this->assertConfigured();
        $manager = new Legacy_invoice_payment_manager();
        $attempt = $manager->getAttempt($publicId, 'paytm');
        if (!$attempt || empty($attempt->provider_reference)) {
            throw new DomainException('The Paytm payment attempt is invalid.');
        }
        if (!$this->isBoundedScalarPayload($postData)) {
            throw new DomainException('The Paytm callback payload is invalid.');
        }

        $checksum = trim((string)($postData['CHECKSUMHASH'] ?? ''));
        if ($checksum === ''
            || verifychecksum_e($postData, $this->paytm_secret_key, $checksum) !== 'TRUE') {
            throw new DomainException('The Paytm callback signature is invalid.');
        }

        $orderId = trim((string)($postData['ORDERID'] ?? ''));
        $merchantId = trim((string)($postData['MID'] ?? ''));
        $currency = strtoupper(trim((string)($postData['CURRENCY'] ?? '')));
        $amountString = trim((string)($postData['TXNAMOUNT'] ?? ''));
        $amountMinor = Payment_amount::toMinor(
            $amountString,
            Legacy_invoice_payment_manager::minorUnitExponent($currency)
        );
        if (!hash_equals((string)$attempt->provider_reference, $orderId)
            || !hash_equals((string)$this->paytm_config->merchant_id, $merchantId)
            || !hash_equals((string)$attempt->currency, $currency)
            || (int)$attempt->expected_amount_minor !== $amountMinor) {
            throw new DomainException('Paytm amount, currency, merchant, or order binding mismatch.');
        }

        if ((string)($postData['STATUS'] ?? '') !== 'TXN_SUCCESS') {
            $manager->markFailed((string)$attempt->public_id, 'paytm', 'provider_payment_failed', $orderId);
            return [
                'success' => false,
                'idempotent' => false,
                'invoice_id' => (int)$attempt->invoice_id,
                'invoice_payment_id' => 0,
                'contact_user_id' => (int)$attempt->contact_user_id,
                'verification_code' => (string)($attempt->invoice_verification_code ?? ''),
                'message' => 'The payment provider did not approve the transaction.',
            ];
        }

        $transactionId = trim((string)($postData['TXNID'] ?? ''));
        return $manager->settle(
            (string)$attempt->public_id,
            'paytm',
            $orderId,
            $transactionId,
            $amountMinor,
            $currency
        );
    }

    private function isBoundedScalarPayload(array $postData): bool
    {
        if (count($postData) < 1 || count($postData) > 40) {
            return false;
        }
        foreach ($postData as $key => $value) {
            if (!is_string($key)
                || preg_match('/^[A-Z0-9_]{1,40}$/D', $key) !== 1
                || !is_scalar($value)
                || strlen((string)$value) > 1024) {
                return false;
            }
        }
        return true;
    }

    private function assertConfigured(): void
    {
        if (strlen($this->paytm_secret_key) < 16
            || trim((string)$this->paytm_config->merchant_id) === ''
            || trim((string)$this->paytm_config->merchant_website) === ''
            || trim((string)$this->paytm_config->industry_type) === '') {
            throw new RuntimeException('Paytm is not configured correctly.');
        }
    }
}
