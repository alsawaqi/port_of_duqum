<?php
// Real controller, password verifier and database; isolated fixtures, no delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class SharedRegistrationBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
SharedRegistrationBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language'=>'english','sms_notifications_enabled'=>'0'];
putenv('PODC_RECAPTCHA_ENABLED=false');
$mysqli = new mysqli('127.0.0.1','root','','',3306);
$name = 'codex_gp_shared_' . bin2hex(random_bytes(6));
$mysqli->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysqli,$name): void {
    if (preg_match('/^codex_gp_shared_[a-f0-9]{12}$/D',$name)) { $mysqli->query("DROP DATABASE `$name`"); }
});
$mysqli->select_db($name);
foreach (['users','gate_pass_users','vendor_users','vendor_contacts','activity_logs'] as $table) {
    $mysqli->query("CREATE TABLE `pod_$table` LIKE bedotscpanel_poderp.`pod_$table`");
}
config('Database')->default = array_merge(config('Database')->default,
    ['hostname'=>'127.0.0.1','username'=>'root','password'=>'','database'=>$name,'DBPrefix'=>'pod_','DBDebug'=>true]);
$db = db_connect('default');
$checks = 0;
$check = static function (bool $ok,string $label) use (&$checks): void {
    $checks++; if (!$ok) { throw new RuntimeException($label); }
};
$password = 'SharedAccess!2026';
$addUser = static function (string $email,array $extra=[]) use ($db,$password): int {
    $db->table('users')->insert(array_merge(['first_name'=>'Original','last_name'=>'Person','email'=>$email,
        'phone'=>'+96800000000','password'=>password_hash($password,PASSWORD_DEFAULT),
        'user_type'=>'staff','role_id'=>0,'is_admin'=>0,'status'=>'active','disable_login'=>0,'deleted'=>0,'language'=>''],$extra));
    return (int)$db->insertID();
};
$save = static function (string $email,array $extra=[]) use ($db,$password): array {
    $post = array_merge(['first_name'=>'Replacement','last_name'=>'Ignored','email'=>$email,
        'phone_country_code'=>'+968','phone_local'=>'00000001','emergency_country_code'=>'+968',
        'emergency_local'=>'00000002','otp_channel'=>'phone','password'=>$password,'password_confirm'=>''],$extra);
    $app = config('App');
    $request = new CodeIgniter\HTTP\IncomingRequest($app,new CodeIgniter\HTTP\SiteURI($app),null,new CodeIgniter\HTTP\UserAgent());
    $request->setMethod('POST'); $request->setHeader('X-Requested-With','XMLHttpRequest');
    $request->setGlobal('post',$post); $request->setGlobal('request',$post); $request->setLocale('english');
    Config\Services::injectMock('request',$request); Config\Services::resetSingle('validation');
    $controller = (new ReflectionClass(App\Controllers\Guest_gate_pass::class))->newInstanceWithoutConstructor();
    $controller->initController($request,new CodeIgniter\HTTP\Response($app),service('logger'));
    $property = new ReflectionProperty($controller,'db'); $property->setAccessible(true); $property->setValue($controller,$db);
    $controller->Users_model = new App\Models\Users_model();
    ob_start();
    try { $controller->save(); $output = ob_get_contents(); } finally { ob_end_clean(); }
    return json_decode($output,true,512,JSON_THROW_ON_ERROR);
};
$snapshot = static function () use ($db): array {
    $result=[];
    foreach (['users','gate_pass_users','vendor_users','vendor_contacts'] as $table) {
        $result[$table]=$db->table($table)->orderBy('id')->get()->getResultArray();
    }
    return $result;
};
foreach (['owner','contact'] as $kind) {
    $email="$kind@example.test"; $id=$addUser($email);
    $db->table('vendor_users')->insert(['vendor_id'=>101,'vendor_role_id'=>1,'user_id'=>$id,'is_owner'=>$kind==='owner'?1:0,'status'=>'active','deleted'=>0]);
    $db->table('vendor_users')->insert(['vendor_id'=>102,'vendor_role_id'=>1,'user_id'=>$id,'is_owner'=>0,'status'=>'active','deleted'=>0]);
    if ($kind==='contact') {
        $db->table('vendor_contacts')->insert(['vendor_id'=>101,'user_id'=>$id,'contacts_name'=>'Contact','email'=>$email,'status'=>'approved','is_active'=>1]);
    }
    $before=$snapshot();
    $check($save($email,['password'=>'Incorrect!2026'])['success']===false,'Wrong password cannot create access');
    $check($before===$snapshot(),'Failed linking leaves every account and membership unchanged');
    $check($save(strtoupper($email))['success']===true,"$kind can link with case-insensitive email");
    $after=$snapshot();
    foreach (['users','vendor_users','vendor_contacts'] as $table) {
        $check($before[$table]===$after[$table],"$kind preserves complete $table rows");
    }
    $membership=$db->table('gate_pass_users')->where('user_id',$id)->get()->getRow();
    $check($membership && $membership->status==='active',"$kind has active Gate Pass access");
    $before=$snapshot();
    $check($save($email,['otp_channel'=>'email'])['success']===true,'Repeat registration succeeds');
    $check($before===$snapshot(),'Repeat changes no rows or preferences');
    $check($save($email,['password'=>'Incorrect!2026'])['success']===false,'Wrong password rejected');
    $check($before===$snapshot(),'Wrong password writes nothing');
}
foreach ([['deleted'=>1],['status'=>'inactive'],['disable_login'=>1],['user_type'=>'client']] as $i=>$state) {
    $email="blocked$i@example.test"; $addUser($email,$state); $before=$snapshot();
    $check($save($email)['success']===false,'Unavailable identity rejected');
    $check($before===$snapshot(),'Unavailable identity remains unchanged');
}
foreach (['suspended','pending','deleted'] as $state) {
    $email="$state@example.test"; $id=$addUser($email);
    $db->table('gate_pass_users')->insert(['user_id'=>$id,'username'=>$state,'status'=>$state==='deleted'?'active':$state,'deleted'=>$state==='deleted'?1:0]);
    $before=$snapshot(); $check($save($email)['success']===false,"$state membership cannot reactivate");
    $check($before===$snapshot(),'Restricted membership writes nothing');
}
$id=$addUser('staff@example.test',['is_admin'=>1,'role_id'=>5]);
$before=$snapshot()['users'];
$check($save('staff@example.test')['success']===true,'Existing staff can add requester access with their password');
$check($before===$snapshot()['users'],'Existing staff permissions and profile remain unchanged');
$addUser('ambiguous@example.test'); $addUser(' ambiguous@example.test');
$before=$snapshot();
$check($save('ambiguous@example.test')['success']===false,'Ambiguous canonical email refused');
$check($before===$snapshot(),'Ambiguous identity writes nothing');
$id=$addUser('legacy@example.test',['password'=>md5('old123')]);
$check($save('legacy@example.test',['password'=>'old123'])['success']===true,'Existing password need not meet new-account policy');
$check(password_verify('old123',$db->table('users')->where('id',$id)->get()->getRow()->password),'Legacy password securely rehashed');
$before=$snapshot();
$check($save('new@example.test')['success']===false,'New account needs password confirmation');
$check($before===$snapshot(),'Missing confirmation writes nothing');
$check($save('new@example.test',['password'=>'weak','password_confirm'=>'weak'])['success']===false,'New password policy enforced');
$check($save('new@example.test',['password_confirm'=>$password])['success']===true,'Brand-new registration still works');
$new=$db->table('users')->where('email','new@example.test')->get()->getRow();
$check($new && $new->phone==='+96800000001' && password_verify($password,$new->password),'New account stores normalized phone and password hash');
echo "Gate Pass shared registration: $checks database checks passed.\n";

