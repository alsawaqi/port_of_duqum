<?php

namespace App\Libraries\Payments;

use Config\EservicesPayments;
use DomainException;
use RuntimeException;

/** Hosted SmartPay only. Card details never pass through this application. */
final class Bank_muscat_gateway implements Payment_gateway_interface
{
    private EservicesPayments $config;
    private string $lastStatusResponseDigest = '';

    public function __construct(?EservicesPayments $config = null)
    {
        $this->config = $config ?: config('EservicesPayments');
        if ($this->config->provider !== 'bank_muscat' || !$this->config->isReady()) {
            throw new RuntimeException('Bank Muscat SmartPay is not configured.');
        }
    }

    public function minorUnitExponent(string $currency): int
    {
        if (strtoupper($currency) !== 'OMR') {
            throw new DomainException('This SmartPay integration supports OMR only.');
        }
        return 3;
    }

    public function createCheckout(array $payment): array
    {
        if ((int)$payment['amount_minor'] < 1 || (int)$payment['amount_minor'] > 9999999999) {
            throw new DomainException('The fee exceeds the bank supported OMR amount range.');
        }
        return [
            'checkout_id' => (string)$payment['order_id'],
            'checkout_url' => get_uri('eservice_payment/checkout/' . $payment['public_id']),
            'expires_at' => time() + $this->config->checkoutTtlSeconds,
        ];
    }

    /** The two POST fields required by SmartPay; never return the working key. */
    public function hostedForm(object $payment): array
    {
        $callback = $this->config->smartpayPublicBaseUrl . '/eservice_payment/return_from_bank';
        if (strlen($callback) > 200) {
            throw new DomainException('The registered payment callback URL is too long.');
        }
        $fields = [
            'merchant_id' => (string)$payment->gateway_merchant_id,
            'order_id' => (string)$payment->provider_checkout_id,
            'amount' => Payment_amount::fromMinor((int)$payment->amount_minor, 3),
            'currency' => (string)$payment->currency,
            'cancel_url' => $callback,
            'redirect_url' => $callback,
        ];
        if (!hash_equals($this->config->smartpayMerchantId, $fields['merchant_id'])) {
            throw new DomainException('The merchant configuration changed after this checkout was created.');
        }
        return [
            'gateway_url' => $this->config->smartpayGatewayUrl(),
            'fields' => ['access_code' => $this->config->smartpayAccessCode,
                'encRequest' => self::encrypt(http_build_query($fields, '', '&', PHP_QUERY_RFC3986), $this->config->smartpayWorkingKey)],
            'request_sha256' => hash('sha256', json_encode($fields)),
        ];
    }

    public function decryptResponse(string $encoded): array
    {
        return self::parseResponse(self::decrypt($encoded, $this->config->smartpayWorkingKey));
    }

    /** Authenticated encryption: hex(16 byte IV + ciphertext + 16 byte GCM tag). */
    public static function encrypt(string $plainText, string $workingKey): string
    {
        self::assertKey($workingKey);
        $iv = random_bytes(16);
        $tag = '';
        $ciphertext = openssl_encrypt($plainText, 'aes-256-gcm', $workingKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('SmartPay encryption failed.');
        }
        return bin2hex($iv . $ciphertext . $tag);
    }

    public static function decrypt(string $encoded, string $workingKey): string
    {
        self::assertKey($workingKey);
        $encoded = trim($encoded);
        if (strlen($encoded) < 66 || strlen($encoded) > 131072 || strlen($encoded) % 2 !== 0 || !ctype_xdigit($encoded)) {
            throw new DomainException('Invalid encrypted response.');
        }
        $bytes = hex2bin($encoded);
        $plainText = openssl_decrypt(substr($bytes, 16, -16), 'aes-256-gcm', $workingKey, OPENSSL_RAW_DATA, substr($bytes, 0, 16), substr($bytes, -16), '');
        if ($plainText === false) {
            throw new DomainException('The encrypted response could not be authenticated.');
        }
        return $plainText;
    }

    private static function assertKey(string $workingKey): void
    {
        if (preg_match('/^[a-zA-Z0-9]{32}$/', $workingKey) !== 1) {
            throw new DomainException('A 32 character SmartPay working key is required.');
        }
    }

    /** Reject duplicate/array keys instead of accepting PHP parse_str coercion. */
    public static function parseResponse(string $plainText): array
    {
        $result = [];
        foreach (explode('&', $plainText) as $pair) {
            if ($pair === '') {
                continue;
            }
            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            if (preg_match('/^[A-Za-z0-9_]+$/', $key) !== 1 || array_key_exists($key, $result)) {
                throw new DomainException('Invalid or repeated response field.');
            }
            $result[$key] = urldecode($parts[1] ?? '');
        }
        return $result;
    }

    /** Retain reconciliation fields and card last four only; never PAN, expiry, CVV or tokens. */
    public static function safeResponse(array $response): array
    {
        $safe = [];
        foreach (['merchant_id', 'order_id', 'tracking_id', 'bank_ref_no', 'order_status',
            'failure_message', 'payment_mode', 'card_name', 'card_type', 'status_code',
            'status_message', 'amount', 'currency', 'order_currency', 'order_currncy',
            'order_date_time', 'order_status_date_time', 'order_no', 'reference_no',
            'order_amt', 'order_capt_amt', 'order_gross_amt', 'order_bank_ref_no',
            'order_bank_response', 'order_card_name', 'order_option_type', 'order_fraud_status',
            'status', 'error_code', 'error_desc'] as $key) {
            if (isset($response[$key]) && is_scalar($response[$key])) {
                if (strtolower(trim((string)$response[$key])) === 'null') {
                    continue; // Bank cancellation returns use literal "null" for absent details.
                }
                $value = mb_substr((string)$response[$key], 0, 1000);
                // Free-text bank messages can unexpectedly echo card data. References
                // remain intact because long numeric bank references are legitimate.
                if (in_array($key, ['failure_message', 'status_message', 'error_desc', 'payment_mode',
                    'card_name', 'card_type', 'order_bank_response', 'order_card_name', 'order_option_type'], true)) {
                    $value = self::redactCardText($value);
                }
                $safe[$key] = $value;
            }
        }
        foreach (['masked_card', 'masked_card_number', 'card_number', 'card_no', 'card_num',
            'order_card_number', 'order_card_no', 'card_last_four', 'card_last4', 'last_four', 'merchant_param6'] as $key) {
            if (!isset($response[$key]) || !is_scalar($response[$key])) {
                continue;
            }
            $value = preg_replace('/[\s-]+/u', '', trim((string)$response[$key]));
            if (preg_match('/^[0-9]{9,15}([0-9]{4})$/D', $value, $matches)
                || (preg_match('/[xX*•]/u', $value) && preg_match('/^[0-9xX*•]{4,15}([0-9]{4})$/uD', $value, $matches))
                || (in_array($key, ['card_last_four', 'card_last4', 'last_four'], true)
                    && preg_match('/^([0-9]{4})$/D', $value, $matches))) {
                $safe['masked_card'] = '**** ' . $matches[1];
                break;
            }
            // Accept our canonical output when already-sanitized JSON is read again.
            if ($key === 'masked_card' && preg_match('/^\*{4}([0-9]{4})$/D', $value, $matches)) {
                $safe['masked_card'] = '**** ' . $matches[1];
                break;
            }
        }
        return $safe;
    }

    public static function redactCardText(string $value): string
    {
        $value = preg_replace('/(?<![0-9])(?:[0-9][ -]?){12,18}[0-9](?![0-9])/', '[card number removed]', $value);
        return (string)preg_replace('/\b(?:cvv2?|cvc2?|security[ _-]?code|expir(?:y|es|ation)|card[ _-]?token|working[ _-]?key|access[ _-]?code)\s*[:=]?\s*[^\s,;]+/i', '[sensitive detail removed]', $value);
    }

    public static function normalizedResponse(array $response, bool $api = false): array
    {
        $optional = static function ($value): string {
            $value = trim((string)$value);
            return strtolower($value) === 'null' ? '' : $value;
        };
        return [
            'order_id' => trim((string)($response[$api ? 'order_no' : 'order_id'] ?? '')),
            'amount' => trim((string)($response[$api ? 'order_amt' : 'amount'] ?? '')),
            'currency' => strtoupper(trim((string)($response['currency'] ?? $response['order_currency'] ?? $response['order_currncy'] ?? ''))),
            'order_status' => strtolower(trim((string)($response['order_status'] ?? ''))),
            'tracking_id' => $optional($response[$api ? 'reference_no' : 'tracking_id'] ?? ''),
            'bank_ref_no' => $optional($response[$api ? 'order_bank_ref_no' : 'bank_ref_no'] ?? ''),
            'order_date_time' => $api ? $optional($response['order_date_time'] ?? '')
                : self::normalizeCallbackTimestamp($optional($response['order_date_time'] ?? '')),
        ];
    }

    public static function statusCategory(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['success', 'successful', 'approved', 'shipped'], true)) {
            return 'paid';
        }
        if (in_array($status, ['failure', 'failed', 'unsuccessful', 'invalid'], true)) {
            return 'failed';
        }
        if (in_array($status, ['aborted', 'cancelled', 'canceled', 'auto-cancelled'], true)) {
            return 'cancelled';
        }
        return 'verification_required';
    }

    private static function normalizeCallbackTimestamp(string $value): string
    {
        // Hosted returns use DD/MM/YYYY; Order Status API uses YYYY-MM-DD.
        return (string)preg_replace('/^(\d{2})\/(\d{2})\/(\d{4})( \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?)$/D', '$3-$2-$1$4', $value);
    }

    /** Compare the precision both bank responses provide, retaining exact seconds. */
    public static function timestampsMatch(string $first, string $second): bool
    {
        $normalize = static function (string $value): ?array {
            $value = self::normalizeCallbackTimestamp($value);
            if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?$/D', $value, $parts)) {
                return null;
            }
            return [$parts[1], isset($parts[2]) ? str_pad($parts[2], 6, '0') : null];
        };
        $normalizedFirst = $normalize($first);
        $normalizedSecond = $normalize($second);
        return $normalizedFirst !== null && $normalizedSecond !== null
            && $normalizedFirst[0] === $normalizedSecond[0]
            && ($normalizedFirst[1] === null || $normalizedSecond[1] === null || $normalizedFirst[1] === $normalizedSecond[1]);
    }

    /** Callback currency/time may be absent in the bank's documented response; API requires both. */
    public function bindingIssues(object $payment, array $response, bool $api = false): array
    {
        $value = self::normalizedResponse($response, $api);
        $issues = [];
        if (!hash_equals((string)$payment->provider_checkout_id, $value['order_id'])) {
            $issues[] = 'order_id_mismatch';
        }
        try {
            if (Payment_amount::toMinor($value['amount'], 3) !== (int)$payment->amount_minor) {
                $issues[] = 'amount_mismatch';
            }
        } catch (\Throwable $e) {
            $issues[] = 'amount_missing_or_invalid';
        }
        if (($api || $value['currency'] !== '') && $value['currency'] !== (string)$payment->currency) {
            $issues[] = 'currency_missing_or_mismatch';
        }
        // The key/access-code pair authenticates the merchant; response MID is optional in bank schema.
        if (!hash_equals((string)$payment->gateway_merchant_id, $this->config->smartpayMerchantId)
            || (isset($response['merchant_id']) && !hash_equals((string)$payment->gateway_merchant_id, (string)$response['merchant_id']))) {
            $issues[] = 'merchant_mismatch';
        }
        if ($value['order_status'] === '') {
            $issues[] = 'order_status_missing';
        }
        if ($api && $value['tracking_id'] === '') {
            $issues[] = 'bank_tracking_reference_missing';
        }
        if ($api || $value['order_date_time'] !== '') {
            try {
                if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d{1,6})?$/', $value['order_date_time'])) {
                    throw new DomainException('Invalid bank timestamp.');
                }
                $date = new \DateTimeImmutable($value['order_date_time'], new \DateTimeZone($this->config->smartpayBankTimezone));
                $dateErrors = \DateTimeImmutable::getLastErrors();
                if (is_array($dateErrors) && ($dateErrors['warning_count'] || $dateErrors['error_count'])) {
                    throw new DomainException('Invalid bank timestamp.');
                }
                $initiated = (new \DateTimeImmutable((string)$payment->initiated_at, new \DateTimeZone('UTC')))->getTimestamp();
                if ($date->getTimestamp() < $initiated - 300 || $date->getTimestamp() > time() + 300) {
                    $issues[] = 'order_timestamp_out_of_bounds';
                }
            } catch (\Throwable $e) {
                $issues[] = 'order_timestamp_missing_or_invalid';
            }
        }
        if ($api && isset($response['status']) && (string)$response['status'] !== '0') {
            $issues[] = 'bank_status_query_error';
        }
        return $issues;
    }

    /** TLS verified server-to-server second leg. API source IP must be registered with bank. */
    public function queryOrderStatus(object $payment): array
    {
        $this->lastStatusResponseDigest = '';
        $key = $this->config->smartpayApiWorkingKey ?: $this->config->smartpayWorkingKey;
        $accessCode = $this->config->smartpayApiAccessCode ?: $this->config->smartpayAccessCode;
        $request = ['order_no' => (string)$payment->provider_checkout_id];
        if (!empty($payment->provider_payment_id)) {
            $request['reference_no'] = (string)$payment->provider_payment_id;
        }
        $post = http_build_query([
            'enc_request' => self::encrypt(json_encode($request, JSON_THROW_ON_ERROR), $key),
            'access_code' => $accessCode, 'command' => 'orderStatusTracker',
            'request_type' => 'JSON', 'response_type' => 'JSON', 'version' => '1.2',
        ]);
        $ch = curl_init($this->config->smartpayStatusApiUrl());
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $this->config->smartpayTimeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 262144) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->lastStatusResponseDigest = $body === '' ? '' : hash('sha256', $body);
        if ($ok === false || $http !== 200) {
            throw new RuntimeException('bank_status_api_unavailable');
        }
        $envelope = self::parseResponse($body);
        if ((string)($envelope['status'] ?? '') === '1') {
            return ['status' => '1', 'error_code' => mb_substr((string)($envelope['enc_error_code'] ?? ''), 0, 80),
                'error_desc' => 'Bank rejected the Order Status API request. Check the API credentials and registered server IP.'];
        }
        if ((string)($envelope['status'] ?? '') !== '0' || empty($envelope['enc_response'])) {
            throw new RuntimeException('bank_status_api_rejected');
        }
        $decoded = json_decode(self::decrypt($envelope['enc_response'], $key), true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new DomainException('bank_status_api_invalid_response');
        }
        return $decoded;
    }

    public function statusResponseDigest(): string
    {
        return $this->lastStatusResponseDigest;
    }

    public function verifyWebhook(string $rawBody, string $signature): object
    {
        throw new DomainException('SmartPay uses the authenticated encrypted return and Order Status API.');
    }
}
