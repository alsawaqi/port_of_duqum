<?php
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
chdir(dirname(__DIR__));
define('FCPATH',getcwd().DIRECTORY_SEPARATOR);require FCPATH.'app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';
class UatDbBootstrap extends CodeIgniter\Boot{static function init($p){static::definePathConstants($p);static::loadConstants();static::loadDotEnv($p);static::defineEnvironment();static::loadCommonFunctions();static::loadAutoloader();}}
UatDbBootstrap::init($paths);$cfg=(new Config\Database())->default;if(ENVIRONMENT==='production'||$cfg['database']!=='bedotscpanel_poderp'||!in_array($cfg['hostname'],['localhost','127.0.0.1'],true))throw new RuntimeException('Not authorized local DB');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$m=mysqli_init();$m->options(MYSQLI_OPT_CONNECT_TIMEOUT,3);$m->real_connect($cfg['hostname'],$cfg['username'],$cfg['password'],$cfg['database'],(int)($cfg['port']??3306));

if(!defined('CI_DEBUG'))define('CI_DEBUG',true);
helper(['general','plugin']);
require_once APPPATH.'ThirdParty/PHP-Hooks/php-hooks.php';
$db=db_connect(); $db->transException(true);
foreach($db->table('settings')->where('type','app')->where('deleted',0)->get()->getResult() as $s){config('Rise')->app_settings_array[$s->setting_name]=$s->setting_value;}
if(config('Sms')->enabled || config('AuthSecurity')->mfaEnabled || get_setting('sms_live_notifications'))throw new RuntimeException('Tests require disabled delivery and MFA');
$checks=0;
$assert=function($ok,$why)use(&$checks){$checks++;if(!$ok)throw new RuntimeException($why);};
$baseCount=(int)$db->table('sms_outbox')->countAllResults();
if($db->table('sms_outbox')->where('status','queued')->countAllResults())throw new RuntimeException('Process existing notifications before running this isolated test');
$db->transBegin();
try{
$now=gmdate('Y-m-d H:i:s');
$testUser=$db->table('users')->where('id',96)->get()->getRowArray();unset($testUser['id']);$db->table('users')->insert(array_replace($testUser,['first_name'=>'LOCAL SMS TEST','last_name'=>'Recipient','email'=>'sms-preview-'.bin2hex(random_bytes(4)).'@example.invalid','phone'=>'90000000','user_type'=>'staff','password'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT),'status'=>'active','disable_login'=>0,'is_admin'=>0,'role_id'=>0,'created_at'=>$now,'deleted'=>0]));
$uid=(int)$db->insertID();
$clone=function($table,$source,$changes)use($db){$row=$db->table($table)->where('id',$source)->get()->getRowArray();if(!$row)throw new RuntimeException('Missing local fixture '.$table);unset($row['id'],$row['cr_number_identity']);$row=array_replace($row,$changes);$db->table($table)->insert($row);return (int)$db->insertID();};
$vendorId=$clone('vendors',34,['vendor_name'=>'LOCAL SMS TEST Vendor','cr_number'=>'SMS'.bin2hex(random_bytes(6)),'status'=>'submitted','registration_valid_from'=>null,'registration_valid_to'=>null]);
$db->table('vendor_users')->insert(['vendor_id'=>$vendorId,'user_id'=>$uid,'vendor_role_id'=>1,'is_owner'=>1,'status'=>'active','deleted'=>0]);
$vendor=new App\Models\Vendors_model();
$vendor->ci_save(['status'=>'revise'],$vendorId);
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount+1,'Vendor revision queues one message');
$vendor->ci_save(['status'=>'revise'],$vendorId);
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount+1,'Duplicate vendor save does not notify twice');
$gpId=$clone('gate_pass_requests',46,['reference'=>'LOCAL-SMS-GP-'.bin2hex(random_bytes(4)),'requester_id'=>$uid,'status'=>'submitted','stage'=>'department','issued_at'=>null,'issued_by'=>null]);
(new App\Models\Gate_pass_requests_model())->ci_save(['status'=>'returned'],$gpId);
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount+2,'Gate pass return queued');
$ptwId=$clone('ptw_applications',8,['reference'=>'LOCAL-SMS-PTW-'.bin2hex(random_bytes(4)),'applicant_user_id'=>$uid,'status'=>'submitted','stage'=>'hsse','completed_at'=>null,'final_pdf_path'=>null]);
(new App\Models\Ptw_applications_model())->ci_save(['status'=>'revise'],$ptwId);
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount+3,'PTW revision queued');
$tenderId=$clone('tenders',12,['reference'=>'LOCAL-SMS-TN-'.bin2hex(random_bytes(4)),'status'=>'closed','award_vendor_id'=>null,'loa_reference'=>null,'loa_issued_at'=>null]);
$db->table('tender_bids')->insert(['tender_id'=>$tenderId,'vendor_id'=>$vendorId,'status'=>'submitted','created_at'=>$now,'deleted'=>0]);
$bidId=(int)$db->insertID();
(new App\Models\Tender_bids_model())->ci_save(['status'=>'rejected'],$bidId);
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount+4,'Tender rejection queued');
$outbox=new App\Libraries\Sms\WorkflowSmsOutbox($db);
$assert($outbox->process(20)===4,'Four preview records processed');
$assert((int)$db->table('sms_outbox')->where('status','dry_run')->where('recipient_user_id',$uid)->countAllResults()===4,'No external send: four dry runs');
$assert($outbox->process(20)===0,'Repeated processing cannot send again');
foreach($db->table('sms_outbox')->where('recipient_user_id',$uid)->get()->getResultArray() as $r){$assert($r['provider_code']===null && $r['is_preview']==1,'Preview never impersonates provider acceptance');}

$authConfig=config('AuthSecurity');
$authConfig->mfaEnabled=true;
$authConfig->mfaRequiredUserTypes=['*'];
$authConfig->mfaProvider='ismartsms';
$authConfig->mfaProvidersByUserType=[];
$auth=new App\Models\Auth_security_model();
foreach(['staff','vendor','client'] as $type){$assert($auth->mfa_is_required((object)['user_type'=>$type]),'SMS OTP applies to '.$type);}
$testIdentity=(object)['user_type'=>'vendor','phone'=>'90000000','email'=>'unused@example.invalid'];
$assert($auth->mfa_provider($testIdentity)->name()==='ismartsms','Login factory selects documented provider');
$assert($auth->mfa_destination($testIdentity)==='+96890000000','Login sends to registered phone, not email');
$assert($auth->mfa_configuration_error($testIdentity)!==null,'Missing provider credentials fail closed');
$authConfig->mfaEnabled=false;
$verification=new App\Models\Verification_model();
$hmac=bin2hex(random_bytes(32));
$issue=function()use($verification,$uid,$hmac){return $verification->issue_mfa_challenge($uid,'ismartsms','registered test mobile',hash('sha256','test-ip'),hash('sha256','test-agent'),$hmac,6,300,5);};
$challenge=$issue();$assert($challenge!==null,'OTP issued');
$stored=$db->table('auth_mfa_challenges')->where('challenge_id',$challenge['challenge_id'])->get()->getRowArray();
$assert($stored['code_hash']!==$challenge['code'] && strlen($stored['code_hash'])===64,'Only keyed OTP digest is stored');
$wrong=$challenge['code']==='000000'?'111111':'000000';
$assert($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$wrong,$hmac)['status']==='invalid','Wrong OTP denied');
$assert($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$challenge['code'],$hmac)['status']==='verified','Correct OTP verifies');
$assert($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$challenge['code'],$hmac)['status']==='invalid','OTP replay denied');
$challenge=$issue();
$db->table('auth_mfa_challenges')->where('challenge_id',$challenge['challenge_id'])->update(['expires_at'=>gmdate('Y-m-d H:i:s',time()-1)]);
$assert($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$challenge['code'],$hmac)['status']==='expired','Expired OTP denied');
$challenge=$issue();$wrong=$challenge['code']==='000000'?'111111':'000000';
for($i=0;$i<5;$i++){$result=$verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$wrong,$hmac);}
$assert($result['status']==='locked','Five wrong attempts lock OTP');
$assert($verification->verify_and_consume_mfa_challenge($challenge['challenge_id'],$uid,$challenge['code'],$hmac)['status']==='invalid','Correct code cannot bypass exhausted attempts');

$db->transRollback();
$assert((int)$db->table('sms_outbox')->countAllResults()===$baseCount,'Outer rollback removes workflows and notifications');
echo "Database SMS workflow tests: ".$checks." passed; all fixtures rolled back.\n";
}catch(Throwable $e){$db->transRollback();throw $e;}
