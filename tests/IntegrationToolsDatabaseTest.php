<?php

// Isolated local database; both providers are intercepted in-process.
namespace App\Libraries\Payments {
    function curl_init($url) { if (!str_contains($url, 'spayuatapi.bmtest.om')) { throw new \RuntimeException('Unexpected bank host in test.'); } return (object)['options' => []]; }
    function curl_setopt_array($handle, $options) { $handle->options = $options; return true; }
    function curl_exec($handle) { $body = ($GLOBALS['testBankReply'])($handle->options); ($handle->options[CURLOPT_WRITEFUNCTION])($handle, $body); return true; }
    function curl_getinfo($handle, $option) { return 200; }
    function curl_close($handle) {}
}

namespace {
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
require FCPATH . 'app/Config/Paths.php';
require FCPATH . 'system/Boot.php';
class IntegrationToolsBootstrap extends \CodeIgniter\Boot {
    public static function init(): void {
        $paths = new \Config\Paths();
        static::definePathConstants($paths); static::loadConstants(); static::loadDotEnv($paths);
        static::defineEnvironment(); static::loadCommonFunctions(); static::loadAutoloader();
    }
}
IntegrationToolsBootstrap::init();
$database = (new \Config\Database())->default;
if (ENVIRONMENT === 'production' || !in_array($database['hostname'], ['localhost', '127.0.0.1'], true)) {
    throw new \RuntimeException('Run only against a local development database.');
}
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
helper(['general', 'plugin', 'date_time', 'url', 'language', 'safe_serialization']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language' => 'english', 'sms_notifications_enabled' => '0'];
$source = db_connect($database, false);
$testDbName = 'codex_integration_' . bin2hex(random_bytes(6));
$source->query('CREATE DATABASE `' . $testDbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
register_shutdown_function(static function () use ($source, $testDbName) {
    if (preg_match('/^codex_integration_[a-f0-9]{12}$/D', $testDbName)) { $source->query('DROP DATABASE `' . $testDbName . '`'); }
});
$isolated = $database;
$isolated['database'] = $testDbName;
$db = db_connect($isolated, false);
foreach (['users', 'settings', 'sms_outbox', 'eservice_payments', 'eservice_payment_events', 'vendors', 'gate_pass_requests', 'tenders', 'ptw_applications'] as $table) {
    $name = $db->prefixTable($table);
    $db->query('CREATE TABLE `' . $name . '` LIKE `' . $database['database'] . '`.`' . $name . '`');
}
$adminRow = $source->table('users')->where('is_admin', 1)->get(1)->getRowArray();
if (!$adminRow) { throw new \RuntimeException('A local admin template is required.'); }
unset($adminRow['id']);
$db->table('users')->insert(array_replace($adminRow, ['first_name' => 'Integration', 'last_name' => 'Test',
    'email' => 'integration-test@example.invalid', 'status' => 'active', 'deleted' => 0, 'disable_login' => 0, 'is_admin' => 1]));
$admin = (object)['id' => (int)$db->insertID(), 'is_admin' => 1];
$checks = 0;
$check = static function ($condition, $label) use (&$checks) { if (!$condition) { throw new \RuntimeException($label); } $checks++; };
$rejects = static function (callable $call, $label) use ($check) { try { $call(); } catch (\Throwable $e) { $check(true, $label); return; } $check(false, $label); };
$sms = new \Config\Sms();
$sms->enabled = false; $sms->userId = 'dummy-test-user'; $sms->password = 'dummy-test-password'; $sms->header = 'Port Duqm';
$sent = [];
$smsCode = '1';
$service = new \App\Libraries\Integration_test_service($db, $sms, static function ($url) use (&$sent, &$smsCode) {
    parse_str(parse_url($url, PHP_URL_QUERY), $fields); $sent[] = $fields;
    return ['ok' => true, 'http' => 200, 'body' => $smsCode];
}, static fn($url) => ['http' => 403, 'error' => 0]);
$input = ['mobile' => '+968 96915872', 'message' => 'Test & + = message', 'language' => '0', 'request_token' => str_repeat('a', 32)];
$rejects(fn() => $service->sendSms((object)['id' => 99, 'is_admin' => 0], $input), 'Non-admin cannot send.');
foreach ([['mobile' => '123'], ['mobile' => ['bad']], ['message' => ['bad']], ['message' => ''], ['message' => str_repeat('x', 766)], ['language' => '4'], ['message' => 'عربي'], ['request_token' => 'bad']] as $bad) {
    $rejects(fn() => $service->sendSms($admin, array_replace($input, $bad)), 'Invalid input rejected before delivery.');
}
$check(count($sent) === 0 && $db->table('sms_outbox')->countAllResults() === 0, 'Refusals do not send or record fake attempts.');
$result = $service->sendSms($admin, $input);
$check($result['success'] && $result['provider_code'] === 1 && count($sent) === 1, 'Manual SMS accepted and recorded.');
$check($sent[0]['MobileNo'] === '96896915872' && $sent[0]['Message'] === $input['message'], 'Number strips plus and message survives URL encoding.');
$check($sms->enabled === false && $db->table('settings')->countAllResults() === 0, 'Manual test does not enable any saved automatic SMS/OTP setting.');
$service->sendSms($admin, $input);
$check(count($sent) === 1, 'Repeated POST token never sends twice.');
$smsCode = '20';
$result = $service->sendSms($admin, array_replace($input, ['request_token' => str_repeat('b', 32)]));
$check(!$result['success'] && str_contains($result['message'], 'IP address is blocked'), 'Provider refusal is readable.');
$smsCode = 'not a provider code';
$result = $service->sendSms($admin, array_replace($input, ['language' => '64', 'message' => 'رسالة اختبار', 'request_token' => str_repeat('c', 32)]));
$check(!$result['success'] && str_contains($result['message'], 'uncertain'), 'Unknown reply never claims delivery.');
$check(end($sent)['Lang'] === '64', 'Arabic uses Unicode mode.');
$before = count($sent);
$service->sendSms($admin, array_replace($input, ['request_token' => str_repeat('c', 32)]));
$check(count($sent) === $before, 'Uncertain send is not retried.');
$audit = $db->table('sms_outbox')->orderBy('id')->get()->getRowArray();
$check((int)$audit['source_id'] === $admin->id && $audit['reason'] === 'integration_test', 'SMS audit identifies initiating admin and purpose.');
$check(!str_contains(json_encode($db->table('sms_outbox')->get()->getResultArray()), $sms->password), 'History never contains provider password.');

$bank = new \Config\EservicesPayments();
$bank->provider = 'bank_muscat'; $bank->smartpayEnvironment = 'uat'; $bank->smartpayMerchantId = '162';
$bank->smartpayAccessCode = 'dummy-access'; $bank->smartpayWorkingKey = str_repeat('a', 32);
$bank->smartpayApiAccessCode = ''; $bank->smartpayApiWorkingKey = ''; $bank->smartpayPublicBaseUrl = 'http://www.localhost:1044';
$probe = $service->checkBank($admin, $bank);
$check($probe['success'] && count($probe['checks']) === 2 && str_contains($probe['message'], 'does not confirm'), 'HTTP 403 means reached, not authenticated.');
$timeout = \App\Libraries\Integration_test_service::describeProbe('Status API', $bank->smartpayStatusApiUrl(), ['http' => 0, 'error' => 28]);
$check(!$timeout['reached'] && str_contains($timeout['message'], 'timed out'), 'Network timeout stays a failure.');
$manager = new \App\Libraries\Payments\Eservice_payment_manager($db, $bank);
$live = clone $bank; $live->smartpayEnvironment = 'production'; $live->smartpayPublicBaseUrl = 'https://portal.example.om';
$check(!$service->startPayment($admin, str_repeat('d', 32), $live)['success'], 'Live test charge refused.');
$check(!$manager->start('integration_test', 1, null, 999999, '0.100', 'test', '/', '/')['success'], 'Payment manager independently checks real admin.');
$check(!$manager->start('integration_test', 1, null, $admin->id, '1.000', 'test', '/', '/')['success'], 'Client cannot change fixed test amount.');
$check($db->table('eservice_payments')->countAllResults() === 0, 'Refused checkouts write no payment rows.');
$result = $service->startPayment($admin, str_repeat('d', 32), $bank);
$check($result['success'], 'UAT test checkout created.');
$repeat = $service->startPayment($admin, str_repeat('d', 32), $bank);
$check($repeat['payment_id'] === $result['payment_id'] && $db->table('eservice_payments')->countAllResults() === 1, 'Double submit reuses checkout.');
$payment = $manager->smartpayPaymentByPublicId($result['payment_id']);
$check($payment->subject_type === 'integration_test' && (int)$payment->user_id === $admin->id && $payment->vendor_id === null, 'Test is linked only to initiating admin.');
$check(!$manager->prepareSmartpayHandoff('bad')['success'], 'Invalid checkout ID refused.');
$liveManager = new \App\Libraries\Payments\Eservice_payment_manager($db, $live);
$check(!$liveManager->prepareSmartpayHandoff($payment->public_id)['success'], 'Changing to live before handoff cannot send test to live bank.');
$form = $manager->prepareSmartpayHandoff($payment->public_id);
$check($form['success'] && !str_contains(json_encode($form['form']), $bank->smartpayWorkingKey), 'Handoff exposes no working key.');
$check(!$manager->prepareSmartpayHandoff($payment->public_id)['success'], 'Handoff cannot repeat.');

// An order's timestamp is stable; it is not the time of each status lookup.
$bankOrderTime = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Muscat')))->format('Y-m-d H:i:s');
$GLOBALS['testBankReply'] = static function ($options) use ($bank, &$payment, $bankOrderTime) {
    parse_str($options[CURLOPT_POSTFIELDS], $fields);
    $request = json_decode(\App\Libraries\Payments\Bank_muscat_gateway::decrypt($fields['enc_request'], $bank->smartpayWorkingKey), true);
    if ($request['order_no'] !== $payment->provider_checkout_id) { throw new \RuntimeException('Wrong status order.'); }
    $response = ['order_no' => $payment->provider_checkout_id, 'reference_no' => '123456', 'order_bank_ref_no' => 'REF-123',
        'order_status' => 'Shipped', 'order_currency' => 'OMR', 'order_amt' => '0.100',
        'order_date_time' => $bankOrderTime];
    return 'status=0&enc_response=' . \App\Libraries\Payments\Bank_muscat_gateway::encrypt(json_encode($response), $bank->smartpayWorkingKey);
};
$response = ['order_id' => $payment->provider_checkout_id, 'tracking_id' => '123456', 'bank_ref_no' => 'REF-123',
    'order_status' => 'Success', 'currency' => 'OMR', 'amount' => '0.100',
    'order_date_time' => $bankOrderTime];
$cipher = \App\Libraries\Payments\Bank_muscat_gateway::encrypt(http_build_query($response), $bank->smartpayWorkingKey);
$result = $manager->processSmartpayReturn($cipher, $payment->provider_checkout_id);
$check($result['success'], 'Encrypted callback plus independent status response verifies test.');
$verified = $manager->smartpayPaymentByPublicId($payment->public_id);
$check($verified->status === 'paid' && $verified->settlement_status === 'not_applicable' && $verified->verified_at, 'Bank evidence saved without business settlement.');
$manager->processSmartpayReturn($cipher, $payment->provider_checkout_id);
$manager->recheckSmartpayPayment((int)$payment->id);
$check($db->table('eservice_payment_events')->where('event_type', 'integration_test.completed')->countAllResults() === 1, 'Repeated callback and recheck do not repeat completion.');
foreach (['vendors', 'gate_pass_requests', 'tenders', 'ptw_applications'] as $table) {
    $check($db->table($table)->countAllResults() === 0, 'Payment test does not create or update business records.');
}
$check($service->startPayment($admin, str_repeat('e', 32), $bank)['success'], 'A new deliberate UAT test can run after a successful test.');
$check(!$liveManager->recheckSmartpayPayment((int)$payment->id)['success'], 'UAT history cannot be rechecked against live bank.');
$check(!str_contains(json_encode($db->table('eservice_payment_events')->get()->getResultArray()), $bank->smartpayWorkingKey), 'No working key in bank event history.');
foreach (['Failure' => 'failed', 'Aborted' => 'cancelled', 'Success' => 'verification_required'] as $bankStatus => $expected) {
    $attempt = $service->startPayment($admin, bin2hex(random_bytes(16)), $bank);
    $payment = $manager->smartpayPaymentByPublicId($attempt['payment_id']);
    $manager->prepareSmartpayHandoff($payment->public_id);
    $bankOrderTime = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Muscat')))->format('Y-m-d H:i:s');
    $GLOBALS['testBankReply'] = static function ($options) use ($bank, &$payment, $bankStatus, $expected, $bankOrderTime) {
        // A wrong independently reported amount must never be accepted.
        $api = ['order_no' => $payment->provider_checkout_id, 'reference_no' => '123456', 'order_bank_ref_no' => 'REF-123',
            'order_status' => $bankStatus, 'order_currency' => 'OMR', 'order_amt' => $expected === 'verification_required' ? '0.200' : '0.100',
            'order_date_time' => $bankOrderTime];
        return 'status=0&enc_response=' . \App\Libraries\Payments\Bank_muscat_gateway::encrypt(json_encode($api), $bank->smartpayWorkingKey);
    };
    $callback = array_replace($response, ['order_id' => $payment->provider_checkout_id, 'order_status' => $bankStatus, 'order_date_time' => $bankOrderTime]);
    $manager->processSmartpayReturn(\App\Libraries\Payments\Bank_muscat_gateway::encrypt(http_build_query($callback), $bank->smartpayWorkingKey), $payment->provider_checkout_id);
    $saved = $manager->smartpayPaymentByPublicId($payment->public_id);
    $check($saved->status === $expected, 'Bank ' . $bankStatus . ' expected ' . $expected . ', got ' . $saved->status);
    $check(empty($saved->paid_at) && $saved->settlement_status !== 'applied', 'Unsuccessful test never settles a fee.');
    $check($saved->response_json && $saved->status_response_json, 'Return and independent response remain auditable.');
}
echo "Integration tools: {$checks} isolated database checks passed; no provider requests sent.\n";
}
