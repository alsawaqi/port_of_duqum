<?php

namespace App\Libraries\Auth;

use App\Libraries\Sms\IsmartSmsGateway;
use Config\AuthSecurity;
use Config\Sms;

/** Admin-managed login policy, kept out of public application settings. */
final class SmsLoginSettings
{
    private const KEY = 'sms_login_otp_required';
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function apply(AuthSecurity $config): void
    {
        // Fresh installations can construct configuration before migrations.
        if (!$this->db->tableExists('settings')) {
            return;
        }
        $row = $this->db->table('settings')->where('setting_name', self::KEY)
            ->where('type', 'sms_private')->where('deleted', 0)->get()->getRowArray();
        if (!$row) {
            return; // Until first saved, retain the deployment's .env policy.
        }
        if (!in_array($row['setting_value'], ['0', '1'], true)) {
            throw new \RuntimeException('Invalid saved SMS login policy.');
        }
        $config->mfaEnabled = $row['setting_value'] === '1';
        $config->mfaProvider = 'ismartsms';
        $config->mfaRequiredUserTypes = ['*'];
        $config->mfaProvidersByUserType = [];
    }

    public function accountsMissingMobile(): array
    {
        $users = $this->db->table('users')->select('id, first_name, last_name, email, user_type, phone')
            ->where('deleted', 0)->where('status', 'active')->where('disable_login', 0)
            ->orderBy('first_name')->orderBy('id')->get()->getResultArray();
        return array_values(array_filter($users, static fn($user) =>
            !OmanMobileNumber::normalize((string) $user['phone'])));
    }

    public function enablementError(AuthSecurity $config, Sms $connection): ?string
    {
        if (!(new IsmartSmsGateway($connection))->isConfigured()) {
            return 'Save an enabled iSmartSMS connection with valid account credentials and sender name before requiring login OTP.';
        }
        if (strlen($config->mfaHmacKey) < 32 || stripos($config->mfaHmacKey, 'CHANGE_ME') !== false) {
            return 'Your system administrator must configure the login verification security key before OTP can be enabled.';
        }
        $missing = count($this->accountsMissingMobile());
        if ($missing > 0) {
            return "Cannot enable mandatory OTP yet: {$missing} active login accounts need a valid personal Oman mobile number. Open Show accounts to update below.";
        }
        return null;
    }

    public function save(bool $enabled, AuthSecurity $config, Sms $connection): void
    {
        if ($enabled && ($error = $this->enablementError($config, $connection))) {
            throw new \InvalidArgumentException($error);
        }
        $builder = $this->db->table('settings');
        $row = $builder->where('setting_name', self::KEY)->get()->getRowArray();
        $data = ['setting_value' => $enabled ? '1' : '0', 'type' => 'sms_private', 'deleted' => 0];
        $saved = $row
            ? $builder->where('setting_name', self::KEY)->update($data)
            : $builder->insert($data + ['setting_name' => self::KEY]);
        if (!$saved) {
            throw new \RuntimeException('Unable to save the SMS login policy.');
        }
    }
}
