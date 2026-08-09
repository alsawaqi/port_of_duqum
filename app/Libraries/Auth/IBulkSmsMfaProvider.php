<?php

namespace App\Libraries\Auth;

/**
 * Infocomm/Omantel iBulk SMS HTTP POST provider.
 *
 * The application remains the OTP authority: only the generated code and a
 * short-lived informational message are sent to the gateway. Provider
 * credentials are read by AuthSecurity from deployment secrets.
 */
final class IBulkSmsMfaProvider implements MfaProviderInterface
{
    private string $endpoint;
    private string $userId;
    private string $password;
    private string $header;
    private int $connectTimeout;
    private int $timeout;

    public function __construct(array $settings)
    {
        $this->endpoint = trim((string) ($settings['endpoint'] ?? ''));
        $this->userId = trim((string) ($settings['user_id'] ?? ''));
        $this->password = (string) ($settings['password'] ?? '');
        $this->header = trim((string) ($settings['header'] ?? ''));
        $this->connectTimeout = max(1, min(10, (int) ($settings['connect_timeout'] ?? 3)));
        $this->timeout = max($this->connectTimeout, min(20, (int) ($settings['timeout'] ?? 8)));
    }

    public function name(): string
    {
        return 'ibulk';
    }

    public function isConfigured(): bool
    {
        $url = filter_var($this->endpoint, FILTER_VALIDATE_URL);
        $parts = $url ? parse_url($this->endpoint) : false;

        return function_exists('curl_init')
            && is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && (string) ($parts['host'] ?? '') !== ''
            && $this->userId !== ''
            && $this->password !== ''
            && $this->header !== ''
            && strlen($this->header) <= 11;
    }

    public function normalizeDestination(string $destination): ?string
    {
        return OmanMobileNumber::normalize($destination);
    }

    public function send(string $destination, string $code, int $lifetimeSeconds): bool
    {
        $mobile = OmanMobileNumber::forGateway($destination);
        if (!$this->isConfigured()
            || $mobile === null
            || !preg_match('/^[0-9]{4,10}$/D', $code)) {
            return false;
        }

        $minutes = max(1, (int) ceil($lifetimeSeconds / 60));
        $message = 'Port of Duqm sign-in code: ' . $code
            . '. Expires in ' . $minutes . ' minutes. Do not share this code.';
        $body = http_build_query([
            'UserId' => $this->userId,
            'Password' => $this->password,
            'MobileNo' => $mobile,
            'Message' => $message,
            'Lang' => '0',
            'Header' => $this->header,
            'referenceIds' => (string) random_int(100000, 999999),
        ], '', '&', PHP_QUERY_RFC3986);

        $response = '';
        $handle = curl_init($this->endpoint);
        if ($handle === false) {
            return false;
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: text/plain',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_USERAGENT => 'Port-of-Duqm-MFA/1.0',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$response): int {
                if (strlen($response) + strlen($chunk) > 1024) {
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
        ];
        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($handle, $options);
        $executed = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return $executed === true
            && $status >= 200
            && $status < 300
            && trim($response) === '1';
    }
}
