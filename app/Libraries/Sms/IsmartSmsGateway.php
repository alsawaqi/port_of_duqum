<?php

namespace App\Libraries\Sms;

use App\Libraries\Auth\OmanMobileNumber;
use Config\Sms;

/** iSmartSMS Push-Local HTTP GET v1.0. No gateway URLs or response bodies are logged. */
final class IsmartSmsGateway
{
    private Sms $config;
    private $transport;

    public function __construct(?Sms $config = null, ?callable $transport = null)
    {
        $this->config = $config ?? SmsConnectionSettings::load();
        $this->transport = $transport;
    }

    public function isConfigured(): bool
    {
        return $this->config->enabled && $this->config->userId !== ''
            && $this->config->password !== '' && trim($this->config->header) !== ''
            && strlen($this->config->header) <= 11
            && $this->config->endpoint === 'https://www.ismartsms.net/iBulkSMS/HttpWS/SMSDynamicAPI.aspx'
            && function_exists('curl_init');
    }

    public function send(string $mobile, string $message, int $language = 0): array
    {
        $destination = OmanMobileNumber::forGateway($mobile);
        if (!$destination) {
            return self::result('invalid_mobile');
        }
        if (!in_array($language, [0, 64], true) || trim($message) === ''
            || mb_strlen($message, 'UTF-8') > ($language === 64 ? 335 : 765)
            || ($language === 0 && preg_match('/[^\x20-\x7E\r\n]/', $message))) {
            return self::result('invalid_message');
        }
        if (!$this->isConfigured()) {
            return self::result('not_configured');
        }
        // Updated provider format includes language, flash mode and the registered sender.
        // This API has no idempotency key: never automatically retry an uncertain send.
        $url = $this->config->endpoint . '?' . http_build_query([
            'UserId' => $this->config->userId, 'Password' => $this->config->password,
            'MobileNo' => $destination, 'Message' => $message,
            'Lang' => $language, 'FLashSMS' => 'N', 'Header' => $this->config->header,
        ], '', '&', PHP_QUERY_RFC3986);
        try {
            $response = $this->transport ? ($this->transport)($url) : $this->request($url);
        } catch (\Throwable $e) {
            return self::result('unknown');
        }
        if (!($response['ok'] ?? false) || (int) ($response['http'] ?? 0) !== 200) {
            return self::result('unknown');
        }
        $body = trim((string) ($response['body'] ?? ''));
        if (!preg_match('/^(?:[1-9]|1[0-9]|20)$/D', $body)) {
            return self::result('unknown');
        }
        return self::result($body === '1' ? 'accepted' : 'rejected', (int) $body);
    }

    public static function result(string $status, ?int $code = null): array
    {
        return ['status' => $status, 'code' => $code];
    }

    public static function describe(string $status, ?int $code = null): string
    {
        $codes = [2 => 'Company account not found.', 3 => 'User ID or password was rejected.',
            4 => 'SMS credit is low.', 5 => 'Message is empty.', 6 => 'Message is too long.',
            7 => 'SMS account is inactive.', 8 => 'Mobile number is missing.', 9 => 'Mobile number is invalid.',
            10 => 'Language was rejected.', 11 => 'Provider reported an unknown error.',
            12 => 'SMS account is blocked after login failures.', 13 => 'SMS account has expired.',
            14 => 'SMS credit has expired.', 15 => 'Provider rejected the request parameters.',
            16 => 'Schedule date was rejected.', 17 => 'Web service user is not registered.',
            18 => 'Account is not registered for the HTTP GET API.', 19 => 'Sender Header is not registered.',
            20 => 'The server IP address is blocked by the provider.'];
        $states = ['queued' => 'Waiting to send.', 'processing' => 'Sending; do not resend.',
            'accepted' => 'Accepted by SMS provider. Phone delivery is not confirmed.',
            'unknown' => 'Sending result is uncertain. Check with the provider before resending.',
            'dry_run' => 'Test preview recorded. No SMS was sent.',
            'invalid_mobile' => 'No valid Oman mobile number is registered for this recipient.',
            'invalid_message' => 'Message language or length is invalid.',
            'not_configured' => 'SMS is disabled or the account settings are incomplete.',
            'no_recipient' => 'No active portal user is linked to this application.',
            'expired' => 'Notification is more than 24 hours old and was not sent.',
            'disabled' => 'Sending was cancelled because this notification section is disabled.'];
        return $status === 'rejected' ? ($codes[$code] ?? 'SMS provider rejected the message.')
            : ($states[$status] ?? 'Sending result is unavailable.');
    }

    private function request(string $url): array
    {
        $body = '';
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_HTTPGET => true, CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => ['Accept: text/plain'],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 1024) { return 0; }
                $body .= $chunk;
                return strlen($chunk);
            }]);
        $ok = curl_exec($handle);
        $http = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        return ['ok' => $ok === true, 'http' => $http, 'body' => $body];
    }
}
