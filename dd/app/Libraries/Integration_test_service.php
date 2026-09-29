<?php

namespace App\Libraries;

use App\Libraries\Auth\OmanMobileNumber;
use App\Libraries\Payments\Eservice_payment_manager;
use App\Libraries\Sms\IsmartSmsGateway;
use App\Libraries\Sms\SmsConnectionSettings;
use Config\EservicesPayments;
use Config\Sms;

/** Explicit, audited admin tests. Never runs the workflow queue or changes settings. */
final class Integration_test_service
{
    private $db;
    private Sms $sms;
    private $smsTransport;
    private $bankTransport;

    public function __construct($db = null, ?Sms $sms = null, ?callable $smsTransport = null, ?callable $bankTransport = null)
    {
        $this->db = $db ?? db_connect();
        $this->sms = clone ($sms ?? SmsConnectionSettings::load());
        $this->smsTransport = $smsTransport;
        $this->bankTransport = $bankTransport;
    }

    public static function assertAdmin(object $actor): void
    {
        if (empty($actor->is_admin) || (int)($actor->id ?? 0) < 1) {
            throw new \DomainException('Only administrators can run integration tests.');
        }
    }

    public static function token($token): string
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new \InvalidArgumentException('Reload this page before starting another test.');
        }
        return $token;
    }

    public static function smsInput(array $input): array
    {
        $mobile = $input['mobile'] ?? null;
        $message = $input['message'] ?? null;
        $language = $input['language'] ?? null;
        if (!is_string($mobile) || strlen($mobile) > 40 || !($mobile = OmanMobileNumber::forGateway($mobile))) {
            throw new \InvalidArgumentException('Enter one valid Oman mobile number, for example +968 9XXXXXXX.');
        }
        if (!is_string($message) || !in_array($language, ['0', '64'], true)) {
            throw new \InvalidArgumentException('Enter a message and select its language.');
        }
        $message = trim($message);
        if ($message === '' || !mb_check_encoding($message, 'UTF-8')
            || mb_strlen($message, 'UTF-8') > ($language === '64' ? 335 : 765)
            || ($language === '0' && preg_match('/[^\x20-\x7E\r\n]/', $message))) {
            throw new \InvalidArgumentException('Use English text up to 765 characters, or select Arabic / Unicode for up to 335 characters.');
        }
        return [$mobile, $message, (int)$language, self::token($input['request_token'] ?? null)];
    }

    public function smsReady(): bool
    {
        $config = clone $this->sms;
        $config->enabled = true; // One deliberate manual test; saved policy stays intact.
        return (new IsmartSmsGateway($config))->isConfigured();
    }

    public function sendSms(object $actor, array $input): array
    {
        self::assertAdmin($actor);
        [$mobile, $message, $language, $token] = self::smsInput($input);
        if (!$this->smsReady()) {
            throw new \InvalidArgumentException('Save the iSmartSMS credentials and approved sender in Settings > SMS first.');
        }
        if (!$this->db->tableExists('sms_outbox')) {
            throw new \RuntimeException('The SMS history table is missing. Ask your administrator to complete the database setup.');
        }
        $key = hash('sha256', 'manual-sms:' . (int)$actor->id . ':' . $token);
        $previous = $this->db->table('sms_outbox')->where('event_key', $key)->get()->getRowArray();
        if ($previous) { return $this->smsResult($previous); }
        $now = gmdate('Y-m-d H:i:s');
        $row = [
            'event_key' => $key, 'module' => 'system', 'subject_id' => (int)$actor->id,
            'source_table' => 'users', 'source_id' => (int)$actor->id,
            'reference' => 'SMS-TEST-' . strtoupper(substr($token, 0, 12)), 'reason' => 'integration_test',
            'recipient_user_id' => null, 'recipient_name' => 'Manual test recipient', 'mobile' => $mobile,
            'message' => $message, 'language' => $language, 'status' => 'processing',
            'is_preview' => 0, 'created_at' => $now, 'claimed_at' => $now,
        ];
        // Persist before contacting the provider. The unique event key prevents
        // duplicate submissions from sending twice; a crashed send stays uncertain.
        try {
            if (!$this->db->table('sms_outbox')->insert($row)) { throw new \RuntimeException('Unable to record the SMS test.'); }
        } catch (\Throwable $e) {
            $previous = $this->db->table('sms_outbox')->where('event_key', $key)->get()->getRowArray();
            if ($previous) { return $this->smsResult($previous); }
            throw new \RuntimeException('Unable to record the SMS test. No message was sent.');
        }
        $id = (int)$this->db->insertID();
        $config = clone $this->sms;
        $config->enabled = true;
        $result = (new IsmartSmsGateway($config, $this->smsTransport))->send($mobile, $message, $language);
        $update = ['status' => $result['status'], 'provider_code' => $result['code'], 'processed_at' => gmdate('Y-m-d H:i:s')];
        if (!$this->db->table('sms_outbox')->where('id', $id)->update($update)) {
            throw new \RuntimeException('The test was attempted but its result could not be saved. Check with the provider before resending.');
        }
        return $this->smsResult(array_merge($row, $update));
    }

    private function smsResult(array $row): array
    {
        $code = isset($row['provider_code']) ? (int)$row['provider_code'] : null;
        return ['success' => $row['status'] === 'accepted', 'message' => IsmartSmsGateway::describe($row['status'], $code),
            'reference' => $row['reference'], 'provider_code' => $code];
    }

    public function startPayment(object $actor, $token, EservicesPayments $config): array
    {
        self::assertAdmin($actor);
        $token = self::token($token);
        // A stable subject per submitted test makes retries idempotent. This ID
        // is only in the integration_test namespace, never a business record ID.
        $subject = (int)hexdec(substr(hash('sha256', 'bank-test:' . (int)$actor->id . ':' . $token), 0, 13)) + 1;
        return (new Eservice_payment_manager($this->db, $config))->start(
            Eservice_payment_manager::INTEGRATION_TEST, $subject, null, (int)$actor->id,
            '0.100', 'Bank Muscat UAT integration test', get_uri('integration_tests'), get_uri('integration_tests'),
            ['environment' => 'uat', 'test_request_token' => $token]
        );
    }

    public function checkBank(object $actor, EservicesPayments $config): array
    {
        self::assertAdmin($actor);
        if (!in_array($config->smartpayEnvironment, ['uat', 'production'], true)) {
            throw new \InvalidArgumentException('Set a supported SMARTPAY_GATEWAY_URL in .env first.');
        }
        $checks = [];
        foreach (['Checkout' => $config->smartpayGatewayUrl(), 'Status API' => $config->smartpayStatusApiUrl()] as $label => $url) {
            // URL comes only from the application's fixed bank allowlist. No
            // credentials, payment data, response bodies or redirects are used.
            $result = $this->bankTransport ? ($this->bankTransport)($url) : self::probe($url);
            $checks[] = self::describeProbe($label, $url, $result);
        }
        return ['success' => !in_array(false, array_column($checks, 'reached'), true),
            'message' => 'Connection checks completed. A server response does not confirm the credentials or a successful payment.', 'checks' => $checks];
    }

    private static function probe(string $url): array
    {
        if (!function_exists('curl_init')) { return ['http' => 0, 'error' => -1]; }
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        curl_exec($curl);
        $result = ['http' => (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'error' => curl_errno($curl)];
        curl_close($curl);
        return $result;
    }

    public static function describeProbe(string $label, string $url, array $result): array
    {
        $http = (int)($result['http'] ?? 0);
        $error = (int)($result['error'] ?? 0);
        $reached = $http > 0 && $error === 0;
        $message = $reached ? 'Server responded (HTTP ' . $http . '). This was an unauthenticated connection check.'
            : ([-1 => 'PHP cURL is not installed.', 6 => 'The bank hostname could not be resolved.',
                7 => 'The server could not connect to the bank.', 28 => 'The connection timed out. Ask hosting and the bank to check network access.',
                35 => 'A secure connection could not be established.', 60 => 'The bank certificate could not be verified. Check the server certificate store.'][$error]
                ?? 'No usable bank response was received. Ask hosting to check the connection.');
        return ['name' => $label, 'host' => parse_url($url, PHP_URL_HOST), 'reached' => $reached,
            'http' => $http, 'error' => $error, 'message' => $message];
    }
}
