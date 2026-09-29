<?php
// Real models, transactions, SQL constraints and archive restoration. No delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development'); define('CI_DEBUG', true);
require 'app/Config/Paths.php'; require 'system/Boot.php';
class SoftDeleteTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
SoftDeleteTestBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array=['language'=>'english','sms_notifications_enabled'=>'0'];
$mysql=new mysqli('127.0.0.1','root','','',3306);
$name='codex_soft_delete_' . bin2hex(random_bytes(6));
$mysql->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysql,$name): void {
    if (preg_match('/^codex_soft_delete_[a-f0-9]{12}$/D',$name)) { $mysql->query("DROP DATABASE `$name`"); }
});
$mysql->select_db($name);
foreach ($mysql->query('SHOW TABLES FROM bedotscpanel_poderp')->fetch_all() as [$t]) {
    $mysql->query("CREATE TABLE `$t` LIKE bedotscpanel_poderp.`$t`");
}
config('Database')->default=array_merge(config('Database')->default,['hostname'=>'127.0.0.1','username'=>'root','password'=>'','database'=>$name,'DBPrefix'=>'pod_','DBDebug'=>true]);
$db=db_connect();
$checks=0;
$check=static function ($ok,$why) use (&$checks): void { $checks++; if (!$ok) { throw new RuntimeException($why); } };
$insert=static function ($table,$fields) use($db): int {
    // Supply unrelated required fixture fields without relaxing strict SQL mode.
    foreach ($db->query('SHOW COLUMNS FROM `' . $db->prefixTable($table) . '`')->getResultArray() as $column) {
        if (array_key_exists($column['Field'], $fields) || $column['Null']==='YES' || $column['Default']!==null || $column['Extra']!=='') { continue; }
        $type=$column['Type'];
        $fields[$column['Field']]=match(true) {
            str_starts_with($type,'enum(')=>explode("'",$type)[1],
            str_contains($type,'int'),str_starts_with($type,'decimal')=>1,
            str_starts_with($type,'datetime'),str_starts_with($type,'timestamp')=>'2026-10-01 12:00:00',
            $type==='date'=>'2026-10-01',
            default=>'QA',
        };
    }
    $db->table($table)->insert($fields); return (int)$db->insertID();
};
$read=static fn($t,$id)=>$db->table($t)->where('id',$id)->get()->getRowArray();
$model=static fn($t)=>new App\Models\Crud_model($t);
$install=static function () use ($mysql,$db): void {
    $mysql->multi_query(file_get_contents(FCPATH.'documentation/SEPT29_SOFT_DELETE_REPAIR.sql'));
    do {if($r=$mysql->store_result())$r->free();}while($mysql->more_results()&&$mysql->next_result());
    $db->resetDataCache();
};
$install();
$group=$insert('vendor_groups',['name'=>'QA','code'=>'QA']);
$role=$insert('vendor_roles',['name'=>'QA','code'=>'QA']);
$company=$insert('companies',['name'=>'QA company','code'=>'QA']);
$department=$insert('departments',['company_id'=>$company,'name'=>'QA department','code'=>'QA']);
$legal=new App\Models\Legal_types_model();
$first=$legal->ci_save(['name'=>'Limited','code'=>'llc']);
$check((bool)$first,'Legal type creates');
$check(!$legal->ci_save(['name'=>'Duplicate','code'=>' LLC ']) && $legal->save_error==='master_code_duplicate','Active duplicates are readable refusals');
$check((bool)$legal->delete($first),'Legal type archives');
$second=$legal->ci_save(['name'=>'New legal type','code'=>'LLC']);
$check($second && $second!==$first,'Archived code reusable as distinct row');
$before=$db->table('legal_types')->orderBy('id')->get()->getResultArray();
$check(!$legal->delete($first,true) && $legal->delete_error==='soft_delete_restore_conflict','Restore refuses active collision');
$check($before===$db->table('legal_types')->orderBy('id')->get()->getResultArray(),'Refusal changes nothing');
for($i=0;$i<3;$i++) {
    $check((bool)$legal->delete($second),'Archive another instance');
    $second=$legal->ci_save(['name'=>'Replacement','code'=>'LLC']);
    $check((bool)$second,'Repeated archived copies allowed');
}
$check((bool)$legal->delete($second),'Archive last instance');
$check((bool)$legal->delete($first,true),'Undo succeeds when value is free');
$check(count($legal->get_details()->getResult())===1,'Normal list excludes every archive');
try { $mysql->query("INSERT INTO pod_legal_types(name,code) VALUES ('SQL duplicate','LLC')"); $check(false,'Database must enforce active uniqueness'); }
catch(mysqli_sql_exception $e) { $check($e->getCode()===1062,'SQL constraint still rejects live duplicates'); }

$country=$insert('country',['name'=>'Test country','code'=>'ZQ']);
$region=$insert('regions',['name'=>'Test region','code'=>'ZQR','country_id'=>$country]);
$city=$insert('cities',['name'=>'Test city','code'=>'ZQC','regions_id'=>$region]);
$oldCity=$insert('cities',['name'=>'Already archived','code'=>'ZQOLD','regions_id'=>$region,'deleted'=>1]);
$check((bool)$model('country')->delete($country),'Delete parent with owned region/city');
foreach (['country'=>$country,'regions'=>$region,'cities'=>$city] as $t=>$id) { $check((int)$read($t,$id)['deleted']===1,'Owned cascade '.$t); }
$check((bool)$model('country')->delete($country,true),'Restore parent');
foreach (['country'=>$country,'regions'=>$region,'cities'=>$city] as $t=>$id) { $check((int)$read($t,$id)['deleted']===0,'Owned restore '.$t); }
$check((int)$read('cities',$oldCity)['deleted']===1,'Undo never revives previously deleted child');
$vendor=$insert('vendors',['vendor_name'=>'First vendor','cr_number'=>'TEST-SD-1','legal_type_id'=>$first,'country_id'=>$country,'region_id'=>$region,'city_id'=>$city]);
$guard=$model('country');
$check(!$guard->delete($country) && $guard->delete_error==='soft_delete_in_use','Country referenced by active vendor cannot delete that vendor');
$check((int)$read('regions',$region)['deleted']===0,'Shared-parent refusal rolls back entire graph');
$guard=$model('legal_types');
$check(!$guard->delete($first) && $guard->delete_error==='soft_delete_in_use','Used legal type refuses deletion');

$user=$insert('users',['first_name'=>'Shared','email'=>'shared@example.invalid','password'=>'retained','user_type'=>'client','phone'=>'+96890000000','language'=>'english']);
$vendor2=$insert('vendors',['vendor_name'=>'Second vendor','cr_number'=>'TEST-SD-2']);
$contact1=$insert('vendor_contacts',['vendor_id'=>$vendor,'user_id'=>$user,'contacts_name'=>'Shared','email'=>'shared@example.invalid']);
$contact2=$insert('vendor_contacts',['vendor_id'=>$vendor2,'user_id'=>$user,'contacts_name'=>'Shared','email'=>'shared@example.invalid']);
$member1=$insert('vendor_users',['vendor_id'=>$vendor,'user_id'=>$user]);
$member2=$insert('vendor_users',['vendor_id'=>$vendor2,'user_id'=>$user]);
$bank=$insert('vendor_bank_accounts',['vendor_id'=>$vendor,'bank_name'=>'QA']);
$permission=$insert('user_permissions',['user_id'=>$user,'vendor_id'=>$vendor]);
$check((bool)$model('vendors')->delete($vendor),'Vendor deletion');
foreach (['vendor_contacts'=>$contact1,'vendor_users'=>$member1,'vendor_bank_accounts'=>$bank,'user_permissions'=>$permission] as $t=>$id) { $check((int)$read($t,$id)['deleted']===1,'Vendor owns '.$t); }
foreach (['vendor_contacts'=>$contact2,'vendor_users'=>$member2,'users'=>$user,'vendors'=>$vendor2] as $t=>$id) { $check((int)$read($t,$id)['deleted']===0,'Shared identity and other CR survive '.$t); }
$check((bool)$model('vendors')->delete($vendor,true),'Vendor restore preserves ownership');
$check((bool)$model('vendor_contacts')->delete($contact1),'Archive contact');
$replacementContact=$insert('vendor_contacts',['vendor_id'=>$vendor,'user_id'=>null,'contacts_name'=>'Replacement','email'=>'shared@example.invalid']);
$check(!$model('vendor_contacts')->delete($contact1,true),'Generated email identity also protects contact undo');
$check($db->transStatus(),'Predictable restore conflict does not poison the database transaction');
$check((bool)$model('vendor_contacts')->delete($replacementContact),'Archive replacement contact');
$check((bool)$model('vendor_contacts')->delete($contact1,true),'Contact restore succeeds after collision cleared');
$gp=$insert('gate_pass_requests',['reference'=>'SD-GP','requester_id'=>$user,'company_id'=>1,'department_id'=>1,'visit_from'=>'2026-10-01','visit_to'=>'2026-10-02']);
$visitor=$insert('gate_pass_request_visitors',['gate_pass_request_id'=>$gp,'full_name'=>'QA','id_number'=>'SD-PASS']);
$vehicle=$insert('gate_pass_request_vehicles',['gate_pass_request_id'=>$gp,'plate_no'=>'SS 123']);
$check((bool)$model('gate_pass_requests')->delete($gp),'Gate pass archive');
$check((int)$read('gate_pass_request_visitors',$visitor)['deleted']===1 && (int)$read('gate_pass_request_vehicles',$vehicle)['deleted']===1,'Gate pass people and vehicles archived');
$ptw=$insert('ptw_applications',['reference'=>'SD-PTW','applicant_user_id'=>$user]);
$response=$insert('ptw_requirement_responses',['ptw_application_id'=>$ptw,'ptw_requirement_definition_id'=>null]);
$attachment=$insert('ptw_attachments',['ptw_application_id'=>$ptw,'ptw_requirement_response_id'=>$response,'file_name'=>'sample.pdf','file_path'=>'qa/sample.pdf']);
$check((bool)$model('ptw_applications')->delete($ptw),'PTW archive');
$check((int)$read('ptw_requirement_responses',$response)['deleted']===1 && (int)$read('ptw_attachments',$attachment)['deleted']===1,'PTW nested response and attachment archived');
$tender=$insert('tenders',['reference'=>'SD-TENDER','title'=>'QA']);
$bid=$insert('tender_bids',['tender_id'=>$tender,'vendor_id'=>$vendor2]);
$doc=$insert('tender_bid_documents',['tender_bid_id'=>$bid,'section'=>'technical','disk'=>'local','path'=>'qa/file.pdf','original_name'=>'file.pdf']);
$check((bool)$model('tenders')->delete($tender),'Tender archive');
$check((int)$read('tender_bids',$bid)['deleted']===1 && (int)$read('tender_bid_documents',$doc)['deleted']===1,'Tender bids and documents cascade');
$check((int)$read('vendors',$vendor2)['deleted']===0,'Tender deletion never deletes supplier');
$check((bool)$model('tenders')->delete($tender,true),'Tender nested restore');
$check((int)$read('tender_bid_documents',$doc)['deleted']===0,'Nested restore restores bid document');
$gpMembership=$insert('gate_pass_users',['user_id'=>$user,'username'=>'shared']);
$check((bool)$model('gate_pass_users')->delete($gpMembership),'Remove gate pass registration');
$check((int)$read('users',$user)['deleted']===0 && (int)$read('vendor_users',$member2)['deleted']===0,'Removing registration preserves vendor login');
$check((bool)$model('users')->delete($user),'Delete global account');
$check((int)$read('vendor_users',$member2)['deleted']===1 && (int)$read('vendor_contacts',$contact2)['deleted']===1,'Account-owned access records archive');
$check((int)$read('vendors',$vendor2)['deleted']===0,'Account deletion does not delete shared vendor');
$newUser=$insert('users',['first_name'=>'New','email'=>'shared@example.invalid','password'=>'new','language'=>'english']);
$check($newUser!==$user,'Email can be registered again after global account deletion');
$check(!$model('users')->delete($user,true),'Account restore refuses email collision');
$otherCompany=$insert('companies',['name'=>'Other company','code'=>'OTHER']);
$otherDepartment=$insert('departments',['company_id'=>$otherCompany,'name'=>'Other department','code'=>'OTHER']);
$assignment=$insert('gate_pass_department_users',['user_id'=>$newUser,'company_id'=>$otherCompany,'department_id'=>$otherDepartment]);
$companyPermission=$insert('user_permissions',['user_id'=>$newUser,'company_id'=>$otherCompany]);
$check((bool)$model('companies')->delete($otherCompany),'Unused company and owned assignments archive');
foreach (['departments'=>$otherDepartment,'gate_pass_department_users'=>$assignment,'user_permissions'=>$companyPermission] as $t=>$id) { $check((int)$read($t,$id)['deleted']===1,'Company-owned cascade '.$t); }
$check((int)$read('users',$newUser)['deleted']===0,'Company deletion preserves global user');
$check((bool)$model('companies')->delete($otherCompany,true),'Company and assignments restore');
$check((int)$read('gate_pass_department_users',$assignment)['deleted']===0,'Company restore restores exact assignment');
$rowsBefore=$db->table('soft_delete_items')->orderBy('id')->get()->getResultArray();
$install();
$check($rowsBefore===$db->table('soft_delete_items')->orderBy('id')->get()->getResultArray(),'Repair rerun preserves deletion history');
echo "Soft-delete lifecycle: $checks checks passed; isolated database removed.\n";
