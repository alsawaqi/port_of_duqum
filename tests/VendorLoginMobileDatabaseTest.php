<?php
if(PHP_SAPI !== 'cli'){http_response_code(404);exit;}
chdir(dirname(__DIR__));
define('FCPATH',getcwd().DIRECTORY_SEPARATOR);require FCPATH.'app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';
class UatDbBootstrap extends CodeIgniter\Boot{static function init($p){static::definePathConstants($p);static::loadConstants();static::loadDotEnv($p);static::defineEnvironment();static::loadCommonFunctions();static::loadAutoloader();}}
UatDbBootstrap::init($paths);$cfg=(new Config\Database())->default;if(ENVIRONMENT==='production'||$cfg['database']!=='bedotscpanel_poderp'||!in_array($cfg['hostname'],['localhost','127.0.0.1'],true))throw new RuntimeException('Not authorized local DB');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$m=mysqli_init();$m->options(MYSQLI_OPT_CONNECT_TIMEOUT,3);$m->real_connect($cfg['hostname'],$cfg['username'],$cfg['password'],$cfg['database'],(int)($cfg['port']??3306));

if(!defined('CI_DEBUG'))define('CI_DEBUG',true);
helper(['general','plugin','date_time','safe_serialization']);
require_once APPPATH.'ThirdParty/PHP-Hooks/php-hooks.php';
$db=db_connect(); $db->transException(true);
foreach($db->table('settings')->where('type','app')->where('deleted',0)->get()->getResult() as $s){config('Rise')->app_settings_array[$s->setting_name]=$s->setting_value;}

$checkCount=0;$assert=function($ok,$label)use(&$checkCount){$checkCount++;if(!$ok)throw new RuntimeException($label);};
$db->transBegin();
try{
 $stamp=bin2hex(random_bytes(5));$mobile=new App\Libraries\Vendor_login_mobile($db);$access=new App\Libraries\Vendor_contact_access($db);
 $v=$db->table('vendors')->where('id',34)->get()->getRowArray();unset($v['id'],$v['cr_number_identity']);$v['vendor_name']='MOBILE UNIT '.$stamp;$v['cr_number']='MOB'.$stamp;$v['status']='new';$db->table('vendors')->insert($v);$vid=(int)$db->insertID();
 $db->table('vendor_contacts')->insert(['vendor_id'=>$vid,'contacts_name'=>'Mobile Unit','email'=>'mobile-unit-'.$stamp.'@example.invalid','mobile'=>'96890000000','phone'=>'24567890','designation'=>'Test','status'=>'pending','is_active'=>1,'deleted'=>0]);$cid=(int)$db->insertID();
 $r=$access->prepareContactAccess($cid,'MobileTest@9076!',1);$uid=$r['user_id'];$u=$db->table('users')->where('id',$uid)->get()->getRow();
 $assert($u->phone==='+96890000000','New contact gets normalized personal mobile');
 $assert($u->status==='inactive' && (int)$u->disable_login===1,'Pending new contact cannot login');
 $db->table('users')->where('id',$uid)->update(['phone'=>'']);
 $assert(!$mobile->fillMissingFromApprovedContacts($uid),'Pending contact cannot supply login mobile');
 $db->table('vendor_contacts')->where('id',$cid)->update(['status'=>'approved']);$access->approveContact($cid,1);
 $u=$db->table('users')->where('id',$uid)->get()->getRow();$assert($u->phone==='+96890000000','Approval repairs missing login phone');$assert($u->status==='active','Approval activates contact');
 $db->table('vendor_contacts')->where('id',$cid)->update(['mobile'=>'90000001']);$assert(!$mobile->fillMissingFromApprovedContacts($uid),'Established mobile never replaced');
 $assert($db->table('users')->where('id',$uid)->get()->getRow()->phone==='+96890000000','Global mobile preserved across contact edits');
 $db->table('users')->where('id',$uid)->update(['phone'=>'invalid']);$assert(!$mobile->fillMissingFromApprovedContacts($uid),'Invalid nonblank mobile requires explicit correction');
 $db->table('users')->where('id',$uid)->update(['phone'=>'','is_admin'=>1]);$assert(!$mobile->fillMissingFromApprovedContacts($uid),'Contacts cannot provision admin OTP destinations');
 $db->table('users')->where('id',$uid)->update(['is_admin'=>0]);
 $v['cr_number'].='B';$db->table('vendors')->insert($v);$vid2=(int)$db->insertID();$db->table('vendor_users')->insert(['vendor_id'=>$vid2,'user_id'=>$uid,'vendor_role_id'=>1,'is_owner'=>1,'status'=>'active','deleted'=>0]);
 $copy=$db->table('vendor_contacts')->where('id',$cid)->get()->getRowArray();unset($copy['id'],$copy['live_email_identity'],$copy['live_user_identity']);$copy['vendor_id']=$vid2;$copy['mobile']='90000002';$db->table('vendor_contacts')->insert($copy);$cid2=(int)$db->insertID();
 $assert(!$mobile->fillMissingFromApprovedContacts($uid),'Conflicting approved personal numbers need review');
 $db->table('vendor_contacts')->where('id',$cid2)->update(['status'=>'pending']);$assert($mobile->fillMissingFromApprovedContacts($uid),'Unapproved numbers cannot override approved candidate');
 $db->table('users')->where('id',$uid)->update(['phone'=>'']);$db->table('vendor_contacts')->where('id',$cid)->update(['email'=>'another@example.invalid']);$assert(!$mobile->fillMissingFromApprovedContacts($uid),'Mismatched contact email excluded');
 $db->table('vendor_contacts')->where('id',$cid)->update(['email'=>$copy['email'],'mobile'=>'24567890']);$assert(!$mobile->fillMissingFromApprovedContacts($uid),'Landline cannot become SMS login destination');
 $db->table('vendor_contacts')->insert(['vendor_id'=>$vid,'contacts_name'=>'Invalid Mobile','email'=>'bad-mobile-'.$stamp.'@example.invalid','mobile'=>'24567890','phone'=>'90000003','designation'=>'Test','status'=>'pending','is_active'=>1,'deleted'=>0]);$bad=(int)$db->insertID();
 $rejected=false;try{$access->prepareContactAccess($bad,'MobileTest@9076!',1);}catch(RuntimeException $e){$rejected=str_contains($e->getMessage(),'personal Oman mobile');}$assert($rejected,'New contact requires mobile, with no company-phone fallback');
 $assert(!$db->table('users')->where('email','bad-mobile-'.$stamp.'@example.invalid')->countAllResults(),'Invalid mobile creates no identity');
 $db->transRollback();echo 'Vendor login mobile database checks: '.$checkCount.' passed; fixtures rolled back.'.PHP_EOL;
}catch(Throwable $e){$db->transRollback();throw $e;}
