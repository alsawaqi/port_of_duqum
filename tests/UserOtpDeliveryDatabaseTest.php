<?php
namespace App\Libraries\Auth {
    // Capture the application's mail-helper call without contacting SMTP.
    function send_app_mail($to, $subject, $message) {
        $GLOBALS['otp_captured_mail'] = compact('to', 'subject', 'message');
        return $GLOBALS['otp_mail_result'] ?? true;
    }
}
namespace {
// Real controller, password verifier and database; isolated fixtures, no delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class OtpDeliveryTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
OtpDeliveryTestBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form','email']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language'=>'english','sms_notifications_enabled'=>'0'];
putenv('PODC_RECAPTCHA_ENABLED=false');
$mysqli = new mysqli('127.0.0.1','root','','',3306);
$name = 'codex_otp_delivery_' . bin2hex(random_bytes(6));
$mysqli->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysqli,$name): void {
    if (preg_match('/^codex_otp_delivery_[a-f0-9]{12}$/D',$name)) { $mysqli->query("DROP DATABASE `$name`"); }
});
$mysqli->select_db($name);
foreach (['users','settings','auth_login_security','auth_audit_events','auth_mfa_challenges','verification','gate_pass_rop_users','activity_logs'] as $table) {
    $mysqli->query("CREATE TABLE `pod_$table` LIKE bedotscpanel_poderp.`pod_$table`");
}
config('Database')->default = array_merge(config('Database')->default,
    ['hostname'=>'127.0.0.1','username'=>'root','password'=>'','database'=>$name,'DBPrefix'=>'pod_','DBDebug'=>true]);
$db = db_connect('default');
$checks = 0;
$check = static function (bool $ok,string $label) use (&$checks): void {
    $checks++; if (!$ok) { throw new RuntimeException($label); }
};
$install = static function () use ($mysqli, $db): void {
    $mysqli->multi_query(file_get_contents(FCPATH . 'documentation/USER_LOGIN_OTP_CHANNEL.sql'));
    do { if ($r = $mysqli->store_result()) { $r->free(); } } while ($mysqli->more_results() && $mysqli->next_result());
    $db->resetDataCache();
};
$install(); $install();
$preferences = new App\Libraries\Auth\UserOtpPreference($db);
$admin = (object) ['id'=>999,'is_admin'=>1];
$manager = (object) ['id'=>998,'is_admin'=>0];
$password = 'OtpSelection!2026';
$users = new App\Models\Users_model();
// Operational controllers provide a database connection; declare it on the
// fixture too, instead of creating a deprecated dynamic property on the base.
class OtpAssignmentTestController extends App\Controllers\Security_Controller { public $db; }
$save = static function (string $email, string $channel, ?object $target=null, ?object $actor=null, string $phone='+96896915872') use ($db,$admin,$password): array {
    $post=['email'=>$email,'first_name'=>'OTP','last_name'=>'Test','phone'=>$phone,'status'=>'active',
        'password'=>$target?'':$password,'password_confirm'=>$target?'':$password,'otp_delivery_channel'=>$channel];
    $app=config('App');
    $request=new CodeIgniter\HTTP\IncomingRequest($app,new CodeIgniter\HTTP\SiteURI($app),null,new CodeIgniter\HTTP\UserAgent());
    $request->setMethod('POST'); $request->setGlobal('post',$post); $request->setGlobal('request',$post);
    $request->setLocale('english'); Config\Services::injectMock('request',$request);
    $controller=(new ReflectionClass(OtpAssignmentTestController::class))->newInstanceWithoutConstructor();
    $controller->initController($request,new CodeIgniter\HTTP\Response($app),service('logger'));
    $controller->login_user=$actor??$admin; $controller->Users_model=new App\Models\Users_model();
    $controller->db=$db;
    $method=new ReflectionMethod($controller,'resolve_operational_assignment_user');$method->setAccessible(true);
    return $method->invoke($controller,['job_title'=>'OTP Test'],$target);
};
$auth=new App\Models\Auth_security_model();
$config=$auth->config();$config->mfaEnabled=true;$config->mfaRequiredUserTypes=['*'];$config->mfaProvider='ismartsms';
$config->mfaProvidersByUserType=[];$config->mfaHmacKey=str_repeat('test-only-key-',4);
$verification=new App\Models\Verification_model();
foreach (['email','sms',''] as $choice) {
    $email=($choice?:'default').'@example.test';
    $result=$save($email,$choice,null,null,$choice==='email'?'':'+96896915872');
    $check($result['success'], 'Operational user creation: '.($result['message']??''));
    $id=(int)$result['user_id']; $user=$users->get_one($id);
    $check($preferences->get($id)===$choice,'Preference persisted');
    $provider=$auth->mfa_provider($user);
    $check($provider->name()===($choice==='email'?'email':'ismartsms'),'Selected provider resolves before delivery');
    $check($auth->mfa_is_required($user),'OTP still mandatory');
    $check($auth->mfa_destination($user,$provider)===($choice==='email'?$email:'+96896915872'),'Correct saved destination');
    $challenge=$verification->issue_mfa_challenge($id,$provider->name(),$auth->mfa_destination_hint($user,$provider),str_repeat('a',64),str_repeat('b',64),$config->mfaHmacKey,6,300,5);
    $check($challenge!==null,'Challenge generated');
    if ($choice === 'email') {
        $check($provider->send($email, $challenge['code'], 300), 'Email provider calls configured application mail helper');
        $check($GLOBALS['otp_captured_mail']['to'] === $email
            && str_contains($GLOBALS['otp_captured_mail']['message'], $challenge['code']), 'Email payload contains the intended code and destination');
        $GLOBALS['otp_mail_result'] = false;
        $check(!$provider->send($email, $challenge['code'], 300), 'Mail-helper refusal propagates as failed delivery');
        $GLOBALS['otp_mail_result'] = true;
    }
    $row=$verification->get_active_mfa_challenge($challenge['challenge_id'],$id);
    $check($row->provider===$provider->name(),'Challenge retains delivery provider');
    $check(!str_contains($row->destination_hint,$auth->mfa_destination($user,$provider)),'Destination masked');
    $check($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$id,$challenge['code'],$config->mfaHmacKey)['status']==='verified','Correct OTP verifies');
    $check($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$id,$challenge['code'],$config->mfaHmacKey)['status']!=='verified','OTP cannot replay');
}
$emailUser=$db->table('users')->where('email','email@example.test')->get()->getRow();
$smsUser=$db->table('users')->where('email','sms@example.test')->get()->getRow();
$before=(array)$smsUser;
$result=$save('sms@example.test','email',$smsUser);
$check($result['success'],'Admin can change existing method');
$after=(array)$users->get_one($smsUser->id);
$check($after['otp_delivery_channel']==='email','Existing choice updated');
foreach (['password','role_id','is_admin','phone'] as $field) { $check($before[$field]===$after[$field], 'Preference preserves '.$field); }
$check($auth->mfa_provider($smsUser)->name()==='email','Fresh preference overrides stale authenticated user object');
$before=$db->table('users')->orderBy('id')->get()->getResultArray();
$check(!$save('email@example.test','sms',$emailUser,$manager)['success'],'Non-admin cannot change existing choice');
$check($before===$db->table('users')->orderBy('id')->get()->getResultArray(),'Unauthorized change writes no rows');
$check(!$save('invalid@example.test','sms',null,null,'')['success'],'SMS choice requires mobile');
$check(!$save('invalid@example.test','',null,null,'')['success'],'System-default SMS choice also requires mobile');
$defaultUser=$db->table('users')->where('email','default@example.test')->get()->getRow();
$check(!$save('default@example.test','',$defaultUser,null,'')['success'],'Existing default SMS user cannot lose mobile');
$check(!$save('sms@example.test','sms',$smsUser,null,'')['success'],'Changing to SMS with no mobile is refused');
$check(!$save('invalid@example.test','disabled')['success'],'No disable-MFA option or arbitrary provider');
$check($before===$db->table('users')->orderBy('id')->get()->getResultArray(),'Invalid selections write no users');
$check($save('manager-created@example.test','email',null,$manager,'')['success'],'Authorized creator can choose email for a new user');
$check($save('email@example.test','email',null,$admin,'')['success'],'Linking an existing email preserves the account');
$check($preferences->fields(null,$admin,$emailUser,[])===[],'Older forms preserve preference');
$policy=new App\Libraries\Auth\SmsLoginSettings($db);
$check(count($policy->accountsMissingMobile())===0,'Email accounts with no phone excluded from SMS readiness');
$check($policy->accountsRequiringSms()===1,'Only default SMS account still requires SMS connection');
$config->mfaEnabled=false;$check(!$auth->mfa_is_required($emailUser),'Global disable remains effective');$config->mfaEnabled=true;
$oldRows=$db->table('users')->orderBy('id')->get()->getResultArray();$install();
$check($oldRows===$db->table('users')->orderBy('id')->get()->getResultArray(),'SQL rerun preserves all selections');
$markup=view('includes/login_otp_delivery_field',['otp_user_id'=>(int)$emailUser->id,'login_user'=>$admin]);
$check(str_contains($markup,'value="email" selected'),'Form reload shows saved selection');
$check(str_contains($markup,'name="otp_delivery_channel"'),'Form submits choice');
$restricted=view('includes/login_otp_delivery_field',['otp_user_id'=>(int)$emailUser->id,'login_user'=>$manager]);
$check(str_contains($restricted,'disabled'),'Existing choice read-only for delegated manager');
// The shared form and save handler cover every operational user assignment screen.
$modules = ['gate_pass_department_users','gate_pass_commercial_users','gate_pass_security_users','gate_pass_rop_users',
    'ptw_hsse_users','ptw_hmo_users','ptw_terminal_users','ptw_applicant_users','tender_commercial_users',
    'tender_technical_users','tender_finance_users','tender_procurement_users','tender_procurement_manager_users',
    'tender_department_users','tender_department_manager_users','tender_committee_users'];
foreach ($modules as $module) {
    $check(str_contains(file_get_contents(APPPATH.'Views/'.$module.'/modal_form.php'),'operational_user_identity_fields')
        && str_contains(file_get_contents(APPPATH.'Controllers/'.ucfirst($module).'.php'),'save_operational_user_assignment'), $module.' uses shared OTP form and save handler');
}
$english=file_get_contents(APPPATH.'Language/english/custom_lang.php');$arabic=file_get_contents(APPPATH.'Language/arabic/custom_lang.php');
$check(str_contains($english,'login_otp_delivery')&&str_contains($arabic,'login_otp_delivery'),'Both languages provide field labels');
// Real account editors: absent checkbox/password fields must be safe in strict SQL.
$clientId=$users->ci_save(['first_name'=>'Client','last_name'=>'QA','email'=>'client-edit@example.test',
    'user_type'=>'client','password'=>password_hash($password,PASSWORD_DEFAULT),'phone'=>'+96890000000']);
foreach ([App\Controllers\Team_members::class=>(int)$defaultUser->id,App\Controllers\Clients::class=>(int)$clientId] as $class=>$targetId) {
    $original=(array)$users->get_one($targetId);
    foreach ([1,0] as $disabled) {
        $post=['email'=>$original['email'],'role'=>'0'];
        if ($disabled) { $post['disable_login']='1'; }
        $app=config('App');
        $request=new CodeIgniter\HTTP\IncomingRequest($app,new CodeIgniter\HTTP\SiteURI($app),null,new CodeIgniter\HTTP\UserAgent());
        $request->setMethod('POST');$request->setGlobal('post',$post);$request->setGlobal('request',$post);$request->setLocale('english');
        Config\Services::injectMock('request',$request);
        $controller=(new ReflectionClass($class))->newInstanceWithoutConstructor();
        $controller->initController($request,new CodeIgniter\HTTP\Response($app),service('logger'));
        $controller->login_user=(object)['id'=>999,'is_admin'=>1,'user_type'=>'staff','permissions'=>[]];
        $controller->Users_model=$users;
        $access=new ReflectionProperty(App\Controllers\Security_Controller::class,'access_type');$access->setAccessible(true);$access->setValue($controller,'all');
        ob_start();$controller->save_account_settings($targetId);$reply=json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
        $after=(array)$users->get_one($targetId);
        $check(!empty($reply['success']),$class.' saves with omitted password');
        $check((int)$after['disable_login']===$disabled,$class.' writes checkbox as integer');
        $check($original['password']===$after['password'] && $original['auth_session_version']===$after['auth_session_version'],$class.' preserves password and sessions');
    }
}
echo "Login OTP delivery: $checks database checks passed; no email or SMS sent.\n";

}
