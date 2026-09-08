<?php

// Local integration test: all database changes are rolled back; no SMS is sent.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
class SmsLoginTestBootstrap extends CodeIgniter\Boot {
    public static function init($paths) {
        static::definePathConstants($paths);
        static::loadConstants();
        static::loadDotEnv($paths);
        static::defineEnvironment();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
SmsLoginTestBootstrap::init($paths);
$database = (new Config\Database())->default;
if (ENVIRONMENT === 'production' || $database['database'] !== 'bedotscpanel_poderp'
    || !in_array($database['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new RuntimeException('This test is restricted to the local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
helper(['general', 'plugin', 'date_time', 'safe_serialization']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
$db = db_connect();
$db->transException(true);
$checks = 0;
$assert = static function ($condition, $label) use (&$checks) {
    $checks++;
    if (!$condition) { throw new RuntimeException($label); }
};
$reject = static function ($action, $message) use ($assert) {
    try { $action(); } catch (InvalidArgumentException $e) {
        $assert(str_contains($e->getMessage(), $message), 'Expected validation: ' . $message);
        return;
    }
    throw new RuntimeException('Invalid configuration was accepted: ' . $message);
};
$snapshot = $db->table('settings')->where('setting_name', 'sms_login_otp_required')->get()->getResultArray();
$missingBefore = (new App\Libraries\Auth\SmsLoginSettings($db))->accountsMissingMobile();
$workflowBefore = $db->table('settings')->like('setting_name', 'sms_', 'after')->get()->getResultArray();
$db->transBegin();
try {
    $service = new App\Libraries\Auth\SmsLoginSettings($db);
    $db->table('settings')->where('setting_name', 'sms_login_otp_required')->delete();
    $config = new Config\AuthSecurity();
    $initialEnabled = $config->mfaEnabled;
    $initialProvider = $config->mfaProvider;
    $service->apply($config);
    $assert($config->mfaEnabled === $initialEnabled && $config->mfaProvider === $initialProvider, 'No saved policy preserves environment defaults');
    $connection = new Config\Sms();
    $connection->enabled = true;
    $connection->userId = 'local-test';
    $connection->password = 'test-only';
    $connection->header = 'Port Duqm';
    $config->mfaHmacKey = str_repeat('test-only-secret-', 3);

    $service->save(false, $config, $connection);
    $assert(!(new Config\AuthSecurity())->mfaEnabled, 'Disabled value persists and is loaded by real auth config');
    $config->mfaEnabled = true;
    $service->apply($config);
    $assert(!$config->mfaEnabled, 'Explicit saved off overrides an enabled environment default');

    $disabledConnection = clone $connection;
    $disabledConnection->enabled = false;
    $reject(fn() => $service->save(true, $config, $disabledConnection), 'enabled iSmartSMS connection');
    $shortKey = clone $config;
    $shortKey->mfaHmacKey = 'short';
    $reject(fn() => $service->save(true, $shortKey, $connection), 'security key');
    $shortKey->mfaHmacKey = 'CHANGE_ME_INDEPENDENT_32_BYTE_RANDOM_SECRET';
    $reject(fn() => $service->save(true, $shortKey, $connection), 'security key');

    // Uncommitted fixture state is visible only to this test's DB connection.
    $active = $db->table('users')->where('deleted', 0)->where('status', 'active')->where('disable_login', 0)->get()->getFirstRow('array');
    $assert((bool) $active, 'Local active-account fixture exists');
    $db->table('users')->where('id', $active['id'])->update(['phone' => '']);
    $reject(fn() => $service->save(true, $config, $connection), 'active login accounts need');
    $assert(!(new Config\AuthSecurity())->mfaEnabled, 'Rejected enable leaves the saved policy off');
    foreach ($service->accountsMissingMobile() as $user) {
        $db->table('users')->where('id', $user['id'])->update(['phone' => '+96890000000']);
    }
    $assert($service->enablementError($config, $connection) === null, 'Complete configuration is ready');
    $service->save(true, $config, $connection);
    $fresh = new Config\AuthSecurity();
    $assert($fresh->mfaEnabled && $fresh->mfaProvider === 'ismartsms' && $fresh->mfaRequiredUserTypes === ['*'], 'Saved on reloads as SMS for every login');
    $fresh->mfaProvidersByUserType = ['staff' => 'email', 'vendor' => 'null'];
    $service->apply($fresh);
    $assert($fresh->mfaProvidersByUserType === [], 'Per-type exceptions cannot bypass the all-login SMS switch');
    $auth = new App\Models\Auth_security_model();
    $service->apply($auth->config());
    foreach (['staff', 'client', 'vendor', 'gate_pass', 'unknown-future-type'] as $type) {
        $assert($auth->mfa_is_required((object) ['user_type' => $type]), 'OTP required for ' . $type);
        $assert($auth->config()->mfaProviderForUserType($type) === 'ismartsms', 'SMS provider for ' . $type);
    }
    $service->save(false, $config, $disabledConnection);
    $service->apply($auth->config());
    $assert(!$auth->mfa_is_required((object) ['user_type' => 'staff']), 'Admin can turn OTP off even with unavailable delivery');
    $workflowAfter = $db->table('settings')->like('setting_name', 'sms_', 'after')->where('setting_name !=', 'sms_login_otp_required')->get()->getResultArray();
    $assert($workflowAfter === array_values(array_filter($workflowBefore, fn($row) => $row['setting_name'] !== 'sms_login_otp_required')), 'Login policy never changes workflow settings');
    $db->table('settings')->where('setting_name', 'sms_login_otp_required')->update(['setting_value' => 'corrupt']);
    try {
        new Config\AuthSecurity();
        throw new LogicException('Corrupt policy was accepted');
    } catch (RuntimeException $e) {
        $assert($e->getMessage() === 'Invalid saved SMS login policy.', 'Invalid stored policy fails closed');
    }
} finally {
    $db->transRollback();
}
$assert($snapshot === $db->table('settings')->where('setting_name', 'sms_login_otp_required')->get()->getResultArray(), 'Saved policy restored after test');
$assert($missingBefore === (new App\Libraries\Auth\SmsLoginSettings($db))->accountsMissingMobile(), 'Original personal mobile data restored after test');
echo "SMS login settings: {$checks} checks passed. Database changes rolled back; no SMS sent.\n";
