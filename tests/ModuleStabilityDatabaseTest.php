<?php
// Real database regressions for the September 27 production errors. No delivery.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development'); define('CI_DEBUG', true);
require 'app/Config/Paths.php'; require 'system/Boot.php';
class ModuleStabilityBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
ModuleStabilityBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language'=>'english','timezone'=>'Asia/Muscat','sms_notifications_enabled'=>'0'];
$mysql = new mysqli('127.0.0.1','root','','',3306);
$name = 'codex_stability_' . bin2hex(random_bytes(6));
$mysql->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysql,$name): void {
    if (preg_match('/^codex_stability_[a-f0-9]{12}$/D',$name)) { $mysql->query("DROP DATABASE `$name`"); }
});
$mysql->select_db($name);
foreach ($mysql->query("SHOW TABLES FROM bedotscpanel_poderp")->fetch_all() as [$table]) {
    $mysql->query("CREATE TABLE `$table` LIKE bedotscpanel_poderp.`$table`");
}
config('Database')->default = array_merge(config('Database')->default,
    ['hostname'=>'127.0.0.1','username'=>'root','password'=>'','database'=>$name,'DBPrefix'=>'pod_','DBDebug'=>true]);
$db = db_connect('default');
$db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$n = 0;
$check = static function ($ok,string $message) use (&$n): void { $n++; if (!$ok) throw new RuntimeException($message); };
$rows = static fn($table) => $db->table($table)->orderBy('id')->get()->getResultArray();
$install = static function () use ($mysql,$db): void {
    $mysql->multi_query(file_get_contents(FCPATH.'documentation/MODULE_STABILITY_REPAIR_2026-09-27.sql'));
    do { if ($r=$mysql->store_result()) $r->free(); } while ($mysql->more_results() && $mysql->next_result());
    $db->resetDataCache();
};

// Reproduce the deployed schema missing all four additive fields and the outbox.
$mysql->query('DROP TABLE IF EXISTS pod_gate_pass_notification_outbox');
foreach (['users'=>['auth_session_version','otp_delivery_channel'], 'gate_pass_requests'=>['fee_breakdown'], 'gate_pass_fee_rules'=>['induction_amount']] as $t=>$fields) {
    foreach ($fields as $field) { $mysql->query("ALTER TABLE pod_$t DROP COLUMN $field"); }
}
$db->resetDataCache();
foreach (['ptw_requirement_responses'=>'ptw_requirement_definition_id','ptw_attachments'=>'ptw_requirement_id'] as $t=>$field) {
    $mysql->query("ALTER TABLE pod_$t MODIFY COLUMN $field BIGINT UNSIGNED NOT NULL");
    $mysql->query("ALTER TABLE pod_$t ADD CONSTRAINT qa_{$t}_definition FOREIGN KEY ($field) REFERENCES pod_ptw_requirement_definitions(id)");
}
$db->table('users')->insert(['id'=>1,'first_name'=>'QA','email'=>'stability@example.invalid','password'=>'preserve-existing-hash','status'=>'active','disable_login'=>0,'language'=>'english']);
$db->table('gate_pass_fee_rules')->insert(['id'=>1,'min_days'=>1,'max_days'=>365,'rate_type'=>'flat','amount'=>17.123,'currency'=>'OMR','is_active'=>1]);
$db->table('gate_pass_requests')->insert(['id'=>1,'reference'=>'STABILITY','requester_id'=>1,'company_id'=>1,'department_id'=>1,
    'visit_from'=>'2026-10-01','visit_to'=>'2026-10-01 23:59:59','status'=>'draft','stage'=>'department','request_type'=>'person']);
$db->table('gate_pass_request_visitors')->insert(['id'=>1,'gate_pass_request_id'=>1,'full_name'=>'QA','id_type'=>'Passport','id_number'=>'QA-STABILITY']);
$before=$rows('gate_pass_requests');
try { (new App\Libraries\Gate_pass_notifications($db))->submitted((object)$before[0]); $check(false,'Missing queue must be detected'); }
catch (DomainException $e) { $check($e->getMessage()==='gate_pass_notification_setup_required','Missing outbox gives setup instruction'); }
$check($db->transStatus() && $rows('gate_pass_requests')===$before,'Missing outbox does not run failing SQL or mutate requests');
$legacyUsers=new App\Models\Users_model();
$check($legacyUsers->is_login_enabled(1),'Non-session active-user lookup works without version column');
service('session')->set('user_id',1);
$check(!$legacyUsers->is_login_enabled(1),'Authenticated session fails closed without version column');
service('session')->remove('user_id');
$install(); $install();
$check($db->table('users')->where('id',1)->get()->getRow()->password==='preserve-existing-hash','Repair retains password');
$check((float)$db->table('gate_pass_fee_rules')->where('id',1)->get()->getRow()->amount===17.123,'Repair retains administrator tariff');
$check((int)$db->table('users')->where('id',1)->get()->getRow()->auth_session_version===1,'Repair installs initial session version');
$check($db->fieldExists('otp_delivery_channel','users'),'Repair installs OTP choice');
$check(str_contains($db->query("SHOW COLUMNS FROM pod_gate_pass_request_approvals LIKE 'decision'")->getRow()->Type,'fee_waiver_rejected'),'Repair installs waiver decision');
$requests=new App\Models\Gate_pass_requests_model();
$check((bool)$requests->ci_save(['status'=>'submitted'],1),'Submission succeeds after repair');
$check(count($rows('gate_pass_notification_outbox'))===1,'Submission queues requester notification');
$history=$rows('gate_pass_notification_outbox'); $install();
$check($history===$rows('gate_pass_notification_outbox'),'Repair rerun preserves queued notifications');
$db->table('gate_pass_requests')->where('id',1)->update(['status'=>'draft']);
$mysql->query('DROP TABLE pod_gate_pass_notification_outbox'); $db->resetDataCache();
$before=$rows('gate_pass_requests');
$check(!$requests->ci_save(['status'=>'submitted'],1),'Model refuses missing queue before request update');
$check($requests->tariff_error==='gate_pass_notification_setup_required' && $before===$rows('gate_pass_requests'),'Refusal preserves draft and meaningful message');
$check($db->transStatus(),'Readiness refusal does not poison subsequent database work');
$install();
$check((bool)$requests->ci_save(['status'=>'submitted'],1),'Retry succeeds after installation in same connection');

// Master codes: active uniqueness is case-insensitive; archived codes are reusable.
foreach (['Country'=>'country','Regions'=>'regions','Cities'=>'cities'] as $class=>$table) {
    $class='App\\Models\\'.$class.'_model'; $model=new $class();
    $parent=match($table) {'regions'=>['country_id'=>1],'cities'=>['regions_id'=>1],default=>[]};
    $id=$model->ci_save(['id'=>1,'name'=>'QA','code'=>'qa','is_active'=>1]+$parent);
    $check((bool)$id,"$table create");
    $before=$rows($table);
    $check(!$model->ci_save(['name'=>'Duplicate','code'=>' QA ']+$parent) && $model->save_error==='master_code_duplicate',"$table duplicate gives readable refusal");
    $check($before===$rows($table),"$table duplicate preserves rows");
    $check((bool)$model->ci_save(['name'=>'Renamed','code'=>'qa'],1),"$table same-record edit allowed");
    $check((bool)$model->delete(1), "$table archive");
    $replacement=$model->ci_save(['name'=>'Replacement','code'=>'QA']+$parent);
    $check((bool)$replacement,"$table archived code reusable");
    $check(!$model->delete(1,true),"$table restore refuses active duplicate");
    $check((bool)$model->delete($replacement),"$table archive replacement");
    $check((bool)$model->delete(1,true),"$table restore when value is free");
    $other=$model->ci_save(['name'=>'Other','code'=>'QB']+$parent);
    $before=$rows($table);
    $check(!$model->ci_save(['code'=>'QA'],$other) && $before===$rows($table),"$table duplicate edit is atomic");
    if ($parent) {
        $check(!$model->ci_save(['code'=>'QC','name'=>'Invalid parent',array_key_first($parent)=>999999]),"$table invalid parent rejected");
    }
}

// Execute module listing SQL with no rows under strict mode, including linked masters/inboxes.
$modelFiles=[]; $modelFailures=[];
foreach (glob(APPPATH.'Models/*.php') as $file) {
    $short=basename($file,'.php');
    if (!preg_match('/^(Gate_pass|Gate_passes|Vendor|Vendors|Ptw|Tender)/',$short)) continue;
    $class='App\\Models\\'.$short;
    $r=new ReflectionClass($class);
    if (!$r->isInstantiable() || ($r->getConstructor()?->getNumberOfRequiredParameters() ?? 0)>0) continue;
    try {
        $model=new $class();
        $check(true,"$short initializes with schema requirements");
        if ($r->hasMethod('get_details') && $r->getMethod('get_details')->getNumberOfRequiredParameters()===0) {
            $result=$model->get_details();
            $check($result!==false,"$short listing query");
        }
    } catch (Throwable $e) { $modelFailures[$short]=$e->getMessage(); continue; }
    $modelFiles[]=$short;
}
foreach ([new App\Models\Gate_pass_requests_model(),new App\Models\Vendors_model(),new App\Models\Ptw_applications_model(),new App\Models\Tender_requests_model()] as $model) {
    foreach ([['search'=>"O'Reilly %_\\"],['company_ids'=>[]],['id'=>999999999],['status'=>'invalid','date_from'=>'2026-09-01','date_to'=>'2026-09-30']] as $options) {
        $check($model->get_details($options)!==false,get_class($model).' filter query');
    }
}
if ($modelFailures) { throw new RuntimeException(json_encode($modelFailures,JSON_PRETTY_PRINT)); }
$ptwResponses=new App\Models\Ptw_requirement_responses_model();
$check((bool)$ptwResponses->ci_save(['ptw_application_id'=>1,'ptw_requirement_definition_id'=>null,'is_checked'=>1,'value_text'=>'Other safety precaution']),'PTW virtual Other response saves under strict SQL');
$check(count($ptwResponses->get_by_application(1)->getResult())===1,'PTW virtual Other response reloads');
$tenders=new App\Models\Tenders_model();
$check($tenders->get_vendor_visible_tenders(999999)!==false,'Vendor tender visibility query runs');

// Add Member has no language selector and permits a blank employment date.
$newUsers=new App\Models\Users_model();
$member=$newUsers->ci_save(['email'=>'new-member@example.invalid','user_type'=>'staff','first_name'=>'QA','otp_delivery_channel'=>'email']);
$check((bool)$member,'New account saves without a language field under strict SQL');
$check($db->table('users')->where('id',$member)->get()->getRow()->language==='','New account inherits portal language');
$check((bool)$newUsers->save_job_info(['user_id'=>$member,'date_of_hire'=>'','salary'=>0,'salary_term'=>'']),'Optional blank employment date saves');
$check($db->table('team_member_job_info')->where('user_id',$member)->get()->getRow()->date_of_hire===null,'Blank employment date remains SQL NULL');
$check($newUsers->is_login_enabled((int)$member),'Login query uses users after employment save');
$check((int)$newUsers->get_job_info($member)->user_id===(int)$member,'Employment details reload');
$check($newUsers->is_login_enabled((int)$member),'Login query uses users after employment read');
$explicit=$newUsers->ci_save(['email'=>'arabic-member@example.invalid','language'=>'arabic']);
$check($db->table('users')->where('id',$explicit)->get()->getRow()->language==='arabic','Explicit new-user language retained');
$newUsers->ci_save(['first_name'=>'Renamed'],$explicit);
$check($db->table('users')->where('id',$explicit)->get()->getRow()->language==='arabic','Unrelated update retains existing language');
// Tender approval notices must pass send-time ownership revalidation against
// requester_id, the same identity used at capture time (there is no created_by).
config('Rise')->app_settings_array['sms_notifications_enabled']='1';
config('Rise')->app_settings_array['sms_tender_enabled']='1';
$db->table('users')->where('id',$member)->update(['phone'=>'96890000000']);
$db->table('tender_requests')->insert(['reference'=>'SMS-REQUESTER-QA','subject'=>'QA','requester_id'=>$member,'status'=>'submitted']);
$tenderRequestId=(int)$db->insertID();
$before=$db->table('tender_requests')->where('id',$tenderRequestId)->get()->getRowArray();
$db->table('tender_requests')->where('id',$tenderRequestId)->update(['status'=>'manager_approved']);
$after=$db->table('tender_requests')->where('id',$tenderRequestId)->get()->getRowArray();
$outbox=new App\Libraries\Sms\WorkflowSmsOutbox($db);
$outbox->capture('tender_requests',$before,$after);
$notice=$db->table('sms_outbox')->where('source_table','tender_requests')->where('source_id',$tenderRequestId)->get()->getRowArray();
$check($notice && (int)$notice['recipient_user_id']===(int)$member,'Tender requester is captured as SMS recipient');
$recipientCheck=new ReflectionMethod($outbox,'recipientStillAllowed');
$check($recipientCheck->invoke($outbox,$notice),'Tender approval SMS passes ownership revalidation');
$check(!$recipientCheck->invoke($outbox,array_replace($notice,['recipient_user_id'=>$explicit])),'Unrelated user cannot receive tender request SMS');
$db->table('tender_requests')->where('id',$tenderRequestId)->update(['requester_id'=>$explicit]);
$check(!$recipientCheck->invoke($outbox,$notice),'Former requester cannot receive delayed tender request SMS');
$db->table('tender_requests')->where('id',$tenderRequestId)->update(['requester_id'=>$member,'deleted'=>1]);
$check(!$recipientCheck->invoke($outbox,$notice),'Deleted tender request does not release a queued SMS');

echo "ModuleStabilityDatabaseTest: $n checks passed; ".count($modelFiles)." module/master models; isolated database removed; no messages sent.\n";
