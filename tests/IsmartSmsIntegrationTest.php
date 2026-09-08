<?php
namespace CodeIgniter\Config { class BaseConfig { public function __construct() {} } }
namespace {
function env($key, $default = null) { return $default; }
$root = dirname(__DIR__);
require $root . '/app/Config/Sms.php';
require $root . '/app/Libraries/Auth/OmanMobileNumber.php';
require $root . '/app/Libraries/Auth/MfaProviderInterface.php';
require $root . '/app/Libraries/Sms/IsmartSmsGateway.php';
require $root . '/app/Libraries/Auth/IsmartSmsMfaProvider.php';
require $root . '/app/Libraries/Sms/WorkflowSmsPolicy.php';
use App\Libraries\Sms\IsmartSmsGateway;
use App\Libraries\Sms\WorkflowSmsPolicy;
use App\Libraries\Auth\IsmartSmsMfaProvider;
$checks = 0;
function check($condition, $message) { global $checks; $checks++; if (!$condition) { throw new \RuntimeException($message); } }
$config = new \Config\Sms();
$calls = 0;
$transport = function ($url) use (&$calls) {
    $calls++;
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    check($q['UserId'] === 'local-user' && $q['Password'] === 'secret&+=#', 'Credentials are URL encoded');
    check($q['MobileNo'] === '96890000000', 'Local API destination');
    check(array_keys($q) === ['UserId', 'Password', 'MobileNo', 'Message', 'Lang', 'FLashSMS', 'Header'], 'Updated provider parameters are sent');
    check($q['Header'] === 'Port Duqm' && $q['FLashSMS'] === 'N', 'Sender space is encoded and normal delivery selected');
    check($q['Lang'] === (preg_match('/[^\x20-\x7E\r\n]/', $q['Message']) ? '64' : '0'), 'Message language is sent to the provider');
    check(!isset($q['referenceIds']) && !isset($q['PushDateTime']), 'No unsupported reference or schedule parameter');
    return ['ok' => true, 'http' => 200, 'body' => '1'];
};
$gateway = new IsmartSmsGateway($config, $transport);
check($gateway->send('90000000', 'Test')['status'] === 'not_configured' && $calls === 0, 'Disabled mode never calls transport');
$config->enabled = true; $config->userId = 'local-user'; $config->password = 'secret&+=#';
check($gateway->send('90000000', 'Test')['status'] === 'not_configured' && $calls === 0, 'Missing sender prevents network call');
$config->header = str_repeat('x', 12);
check($gateway->send('90000000', 'Test')['status'] === 'not_configured' && $calls === 0, 'Overlong sender prevents network call');
$config->header = 'Port Duqm';
check($gateway->send('+968 9000 0000', 'Test & + #')['status'] === 'accepted', 'Documented code 1 is accepted');
for ($code = 2; $code <= 20; $code++) {
    $g = new IsmartSmsGateway($config, fn($url) => ['ok' => true, 'http' => 200, 'body' => (string) $code]);
    $result = $g->send('90000000', 'Test');
    check($result['status'] === 'rejected' && $result['code'] === $code, 'Provider rejection ' . $code);
    check(IsmartSmsGateway::describe('rejected', $code) !== '', 'Readable rejection ' . $code);
}
foreach ([['ok'=>false,'http'=>0,'body'=>''], ['ok'=>true,'http'=>500,'body'=>'1'], ['ok'=>true,'http'=>200,'body'=>'<html>1</html>']] as $response) {
    $g = new IsmartSmsGateway($config, fn($url) => $response);
    check($g->send('90000000', 'Test')['status'] === 'unknown', 'Uncertain sends are not falsely accepted');
}
check($gateway->send('invalid', 'Test')['status'] === 'invalid_mobile', 'Invalid destination fails');
check($gateway->send('90000000', 'رسالة', 0)['status'] === 'invalid_message', 'Arabic requires language 64');
check($gateway->send('90000000', str_repeat('ع',336), 64)['status'] === 'invalid_message', 'Arabic length limit');
check($gateway->send('90000000', str_repeat('a',766), 0)['status'] === 'invalid_message', 'English length limit');
check($gateway->send('90000000', 'رسالة', 64)['status'] === 'accepted', 'Arabic can be submitted');
$mfa = new IsmartSmsMfaProvider($gateway);
check($mfa->send('90000000', '123456', 300), 'SMS OTP uses accepted transport');
check(!$mfa->send('90000000', '12', 300), 'Malformed OTP rejected');
$config->enabled = false;
check(!$mfa->send('90000000', '123456', 300), 'No OTP bypass when SMS disabled');
$cases = [
 ['vendors', ['status'=>'submitted'], ['status'=>'revise'], 'revise'],
 ['vendors', ['status'=>'submitted'], ['status'=>'approved'], 'approved'],
 ['gate_pass_requests', ['status'=>'security_approved'], ['status'=>'rop_approved'], 'rop_approved'],
 ['gate_pass_requests', ['status'=>'submitted'], ['status'=>'returned'], 'returned'],
 ['ptw_applications', ['status'=>'submitted','stage'=>'hsse'], ['status'=>'submitted','stage'=>'hmo'], 'stage_approved'],
 ['ptw_applications', ['status'=>'submitted'], ['status'=>'rejected'], 'rejected'],
 ['tenders', ['status'=>'closed'], ['status'=>'awarded'], 'awarded'],
 ['tender_bids', ['status'=>'submitted'], ['status'=>'accepted'], 'technical_accepted'],
 ['tender_communications', ['status'=>'pending_procurement'], ['status'=>'published','is_vendor_visible'=>1], 'message'],
 ['tender_requests', ['status'=>'submitted'], ['status'=>'manager_approved'], 'manager_approved'],
];
foreach ($cases as [$table,$before,$after,$reason]) {
    $event=WorkflowSmsPolicy::event($table,$before,$after);
    check($event && $event['reason']===$reason, $table.' event');
    foreach ([0,64] as $language) {
        $text=WorkflowSmsPolicy::message($event['module'],'UAT-123',$reason,$language);
        check(mb_strlen($text) <= ($language===64?335:765), 'Template fits provider limit');
    }
    check(WorkflowSmsPolicy::event($table,$after,$after)===null, 'Repeat save produces no event');
    check(WorkflowSmsPolicy::event($table,$before,$after+['deleted'=>1])===null, 'Deleted entity is not notified');
}
check(WorkflowSmsPolicy::event('tender_communications', [], ['status'=>'internal','is_vendor_visible'=>0]) === null, 'Internal tender messages remain private');
check(WorkflowSmsPolicy::event('vendors', ['status'=>'approved'], ['status'=>'approved','phone'=>'changed']) === null, 'Unrelated profile save produces no SMS');
echo "iSmartSMS integration: {$checks} checks passed. No network requests made.\n";
}
