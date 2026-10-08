<?php
// Real models and SQL against an isolated local database; no messages or gateway calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class GatePassDiscussionTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        $paths = new Config\Paths();
        static::definePathConstants($paths); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
GatePassDiscussionTestBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language'=>'english','sms_notifications_enabled'=>'0'];
$mysqli = new mysqli('127.0.0.1', 'root', '', '', 3306);
$name = 'codex_gp_discussion_' . bin2hex(random_bytes(6));
$mysqli->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysqli, $name): void {
    if (preg_match('/^codex_gp_discussion_[a-f0-9]{12}$/D', $name)) { $mysqli->query("DROP DATABASE `{$name}`"); }
});
$mysqli->select_db($name);
foreach (['gate_pass_requests','gate_pass_request_visitors','gate_pass_fee_rules','activity_logs','gate_pass_blocked_visitors','users','gate_pass_department_users','gate_pass_request_approvals','gate_pass_blocked_visitor_logs','gate_passes','gate_pass_scan_log','companies','departments','gate_pass_purposes','gate_pass_request_vehicles'] as $table) {
    $mysqli->query("CREATE TABLE `pod_{$table}` LIKE `bedotscpanel_poderp`.`pod_{$table}`");
}
$mysqli->query("CREATE TABLE pod_eservice_payments (id INT AUTO_INCREMENT PRIMARY KEY,subject_type VARCHAR(50),subject_id BIGINT,status VARCHAR(40),deleted INT DEFAULT 0)");
$install = static function () use ($mysqli): void {
    $mysqli->multi_query(file_get_contents(FCPATH . 'documentation/GATE_PASS_PER_PERSON_TARIFF.sql'));
    do { if ($r=$mysqli->store_result()) { $r->free(); } } while ($mysqli->more_results() && $mysqli->next_result());
};
$install();
$mysqli->query(file_get_contents(FCPATH . 'documentation/GATE_PASS_DISCUSSION_FIX.sql'));
$cfg = config('Database');
$cfg->default = array_merge($cfg->default, ['hostname'=>'127.0.0.1','username'=>'root','password'=>'','database'=>$name,'DBPrefix'=>'pod_','DBDebug'=>true]);
$db = db_connect('default');
$db->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
$requests = new App\Models\Gate_pass_requests_model();
$visitors = new App\Models\Gate_pass_request_visitors_model();
$tariff = new App\Libraries\Gate_pass_tariff($db);
$checks = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { $checks++; if (!$ok) throw new RuntimeException($label); };
$requestData = static fn(int $days): array => ['reference'=>'TARIFF-'.bin2hex(random_bytes(6)), 'requester_id'=>1,'company_id'=>1,
    'gate_pass_purpose_id'=>1,'visit_from'=>'2026-10-01 00:00:00','visit_to'=>date('Y-m-d',strtotime('2026-10-01 +'.($days-1).' days')).' 23:59:59',
    'currency'=>'OMR','status'=>'draft','stage'=>'department','request_type'=>'person'];
$visitorData = static fn(int $id, string $role='visitor'): array => ['gate_pass_request_id'=>$id,'full_name'=>'Tariff QA',
    'id_type'=>'Passport','id_number'=>bin2hex(random_bytes(5)),'nationality'=>'Omani','phone'=>'96800000000',
    'visitor_company'=>'QA','role'=>$role,'is_primary'=>0,'id_attachment_path'=>'qa/not-delivered.pdf'];
// All identities are synthetic. Transports are injected; no network or real messages.
$eligibility = new App\Libraries\Gate_pass_eligibility($db);
$notices = new App\Libraries\Gate_pass_notifications($db);
$blocks = new App\Models\Gate_pass_blocked_visitors_model();
$rows = static fn(string $t): array => $db->table($t)->orderBy('id')->get()->getResultArray();
for ($u=1;$u<=7;$u++) {
    $db->table('users')->insert(['id'=>$u,'email'=>"gp-qa-{$u}@example.invalid",'language'=>'english','first_name'=>'QA',
        'status'=>$u===5?'inactive':'active','deleted'=>$u===6?1:0,'disable_login'=>$u===7?1:0]);
}
foreach ([[2,1,1],[2,1,1],[3,2,1],[4,1,2],[5,1,1],[6,1,1],[7,1,1]] as [$u,$c,$d]) {
    // Assignment unique indexes are preserved, so a duplicate reviewer cannot be created.
    $db->query('INSERT IGNORE INTO pod_gate_pass_department_users(user_id,company_id,department_id) VALUES(?,?,?)',[$u,$c,$d]);
}
$id=(int)$requests->ci_save(array_merge($requestData(15),['department_id'=>1]));
$check($id>0,'Create request');
$check(count($rows('gate_pass_notification_outbox'))===0,'Saving a draft never sends a department email.');
$data=$visitorData($id);$data['id_number']='QA-123';$data['phone']='96891234567';
foreach (['id_type','id_number'] as $field) {
    $bad=$data;$bad[$field]='';
    $check(!$visitors->ci_save($bad),'Missing '.$field.' refused by model');
}
$v=(int)$visitors->ci_save($data);$check($v>0,'Visitor with ID saves');
$attachment=$visitors->get_one($v)->id_attachment_path;
$check((bool)$visitors->ci_save(['full_name'=>'QA Edited'],$v),'Existing visitor edit accepted');
$check($visitors->get_one($v)->id_attachment_path===$attachment,'Edit retains existing ID attachment');
$before=$rows('gate_pass_requests');
$db->transBegin();
$check((bool)$requests->ci_save(['status'=>'submitted'],$id),'Submission inside outer transaction succeeds');
$check(count($rows('gate_pass_notification_outbox'))===2,'Requester and matching department reviewer queued');
$db->transRollback();
$check($before===$rows('gate_pass_requests') && count($rows('gate_pass_notification_outbox'))===0,'Outer rollback removes status and notifications');
$check((bool)$requests->ci_save(['status'=>'submitted'],$id),'Actual submission');
$queued=$rows('gate_pass_notification_outbox');
$check(array_column($queued,'destination')===['gp-qa-1@example.invalid','gp-qa-2@example.invalid'],'Both groups; wrong company/department, inactive/deleted/disabled excluded');
$check(!App\Libraries\Gate_pass_email::isDepartmentReview($queued[0]['message']) && App\Libraries\Gate_pass_email::isDepartmentReview($queued[1]['message']), 'Requester keeps confirmation; reviewer receives the professional template.');
$check(str_contains($queued[1]['message'], 'QA') && str_contains($queued[1]['message'], $requests->get_one($id)->reference), 'The queued email snapshots the requester and saved reference.');
$check((bool)$requests->ci_save(['status'=>'submitted'],$id),'Same status replay accepted');
$check(count($rows('gate_pass_notification_outbox'))===2,'Status replay never duplicates email');
$sent=[];$mailBodies=[];$mail=static function($to,$subject,$body) use (&$sent,&$mailBodies): bool {$sent[]=$to;$mailBodies[$to]=$body;return true;};
$notices->process(20,0,$mail,static fn()=>throw new RuntimeException('Unexpected SMS'));
$notices->process(20,0,$mail);
$check(count($sent)===2 && array_column($rows('gate_pass_notification_outbox'),'status')===['sent','sent'],'Exactly one send per recipient despite repeated worker');
$check(str_contains($mailBodies['gp-qa-2@example.invalid'], '<!DOCTYPE html>') && !str_contains($mailBodies['gp-qa-2@example.invalid'], '&lt;!DOCTYPE'), 'Delivery sends rendered HTML, not visible HTML source.');
$approval=['gate_pass_request_id'=>$id,'stage'=>'department','decision'=>'approved','decided_by'=>2,'decided_at'=>gmdate('Y-m-d H:i:s')];
$block=(int)$blocks->block_visitor(['id_number'=>'qa 123','id_type'=>'Passport','visitor_name'=>'QA'],2);
$check($block>0 && $eligibility->isBlocked($visitors->get_one($v)),'Normalized ID block covers existing visitor');
$check(count($rows('gate_pass_notification_outbox'))===4,'Block queues visitor SMS and requester email');
$blocks->block_visitor(['id_number'=>'QA123'],2);
$check(count($rows('gate_pass_notification_outbox'))===4,'Already-blocked record does not send duplicate notices');
$state=$rows('gate_pass_requests');$history=$rows('gate_pass_request_approvals');
$check(!$requests->saveDecision(['status'=>'department_approved','stage'=>'commercial'],$approval,$id,'submitted','department'),'Blocked department approval refused');
$check($state===$rows('gate_pass_requests') && $history===$rows('gate_pass_request_approvals'),'Refusal leaves status and approval history byte-identical');
foreach (['submitted','department_approved','commercial_approved','security_approved','rop_approved','issued'] as $status) {
    $db->table('gate_pass_requests')->where('id',$id)->update(['status'=>'returned']);
    $check(!$requests->ci_save(['status'=>$status],$id),'Blocked transition '.$status.' refused');
}
$check(!$visitors->ci_save(['id_number'=>'SOME-OTHER-ID'],$v),'Cannot disguise a blocked visitor by editing ID');
$check(!$visitors->ci_save(array_merge($data,['id_number'=>'qa123'])),'New blocked visitor refused without acknowledgement bypass');
$db->table('gate_pass_requests')->where('id',$id)->update(['status'=>'submitted']);
$notices->process(20,0,$mail,static fn()=>throw new RuntimeException('Disabled SMS must not send'));
$last=$rows('gate_pass_notification_outbox');
$check($last[2]['status']==='disabled' && $last[3]['status']==='sent','SMS settings respected; requester email sent');
$check($blocks->unblock_visitor($block,2,'QA cleared'),'Unblock');
$check(!$eligibility->isBlocked($visitors->get_one($v)),'Unblock synchronizes flags');
$check($requests->saveDecision(['status'=>'department_approved','stage'=>'commercial'],$approval,$id,'submitted','department'),'Clear request advances department to commercial');
$check($requests->get_one($id)->stage==='commercial','Non-waived request still goes to Commercial');
$history=$rows('gate_pass_request_approvals');
$check(!$requests->saveDecision(['status'=>'department_approved'],$approval,$id,'submitted','department'),'Stale decision refused');
$check($history===$rows('gate_pass_request_approvals'),'Stale replay creates no history');
// Simulate an approval-row storage failure and prove the request transition rolls back.
$state=$rows('gate_pass_requests');$badApproval=$approval;$badApproval['nonexistent_column']=1;
$check(!$requests->saveDecision(['status'=>'commercial_approved','stage'=>'security'],$badApproval,$id,'department_approved','commercial'),'Approval DB failure reported');
$check($state===$rows('gate_pass_requests'),'Approval failure rolls request back');
$db->resetTransStatus(); // Start the next simulated HTTP request after an injected SQL error.
$approval['stage']='commercial';
$check($requests->saveDecision(['status'=>'commercial_approved','stage'=>'security'],$approval,$id,'department_approved','commercial'),'Clear Commercial approval succeeds');
// Previously printed QR uses the current registry even if a legacy visitor flag is stale.
$db->table('gate_pass_requests')->where('id',$id)->update(['stage'=>'issued','status'=>'rop_approved','visit_from'=>gmdate('Y-m-d H:i:s',time()-3600),'visit_to'=>gmdate('Y-m-d H:i:s',time()+86400)]);
$db->table('gate_passes')->insert(['gate_pass_request_id'=>$id,'gate_pass_request_visitor_id'=>$v,'gate_pass_no'=>'QA-PASS','qr_token'=>'QA-TOKEN','status'=>'active']);
$passId=(int)$db->insertID();$pass=$db->table('gate_passes')->where('id',$passId)->get()->getRow();
$check($eligibility->canDownload($pass),'Unblocked active pass can download');
helper('gate_pass');
$scan=new App\Libraries\Gate_pass_scan_recorder($db);
$move=static fn($action)=>$scan->record($passId,$id,[$v],$action,null,2,'QA','127.0.0.1','QA');
$check($move('entry')['success'],'Clear visitor enters');
$blocks->block_visitor(['id_number'=>'QA123'],2);
$db->table('gate_pass_request_visitors')->where('id',$v)->update(['is_blocked'=>0]);
$check(!$eligibility->canDownload($pass),'Registry block refuses QR/PDF despite stale visitor flag');
$beforeScan=$rows('gate_pass_scan_log');
$check(!$move('entry')['success'],'Printed QR blocked entry refused');
$check($beforeScan===$rows('gate_pass_scan_log'),'Refused scan has no movement writes');
$check($move('exit')['success'],'Blocked visitor already inside can exit');
// One blocked person must not disable another assigned visitor pass.
$db->table('gate_pass_request_visitors')->insert(array_merge($data,['id_number'=>'OTHER-ID']));$other=(int)$db->insertID();
$otherPass=clone $pass;$otherPass->gate_pass_request_visitor_id=$other;
$check($eligibility->canDownload($otherPass),'Other visitor assigned pass remains available');
$legacy=clone $pass;$legacy->gate_pass_request_visitor_id=null;
$check(!$eligibility->canDownload($legacy),'Legacy combined pass containing blocked visitor refused');
$otherPass->status='revoked';$check(!$eligibility->canDownload($otherPass),'Revoked pass unavailable');
// Delivery records: failure, changed recipient, uncertain outcome, preview expiry.
$notices->process(20,0,static fn()=>false,static fn()=>throw new RuntimeException('Disabled SMS'));
$check(in_array('failed',array_column($rows('gate_pass_notification_outbox'),'status'),true),'SMTP failure stored for follow-up');
$check($requests->get_one($id)->status==='rop_approved','Mail failure never undoes business action');
$blocks->unblock_visitor($block,2,'QA');
config('Rise')->app_settings_array['sms_notifications_enabled']='1';
config('Rise')->app_settings_array['sms_gate_pass_enabled']='1';
config('Rise')->app_settings_array['sms_live_notifications']='1';
$blocks->block_visitor(['id_number'=>'QA123'],2);
$captured=[];
$notices->process(20,0,$mail,static function($to,$text)use(&$captured){$captured[]=$to;return ['status'=>'accepted'];});
$check($captured===['+96891234567'],'Live-mode SMS uses saved visitor number, captured transport only');
$blocks->unblock_visitor($block,2,'QA');$blocks->block_visitor(['id_number'=>'QA123'],2);
$notices->process(20,0,$mail,static fn()=>['status'=>'unknown']);
$check(in_array('unknown',array_column($rows('gate_pass_notification_outbox'),'status'),true),'Uncertain provider result retained, not retried');
$blocks->unblock_visitor($block,2,'QA');
config('Rise')->app_settings_array['sms_live_notifications']='0';
$blocks->block_visitor(['id_number'=>'QA123'],2);
config('Rise')->app_settings_array['sms_live_notifications']='1';
$notices->process(20,0,$mail,static fn()=>throw new RuntimeException('Old preview must not send'));
$check(in_array('dry_run',array_column($rows('gate_pass_notification_outbox'),'status'),true),'Enabling live later never sends old previews');
config('Rise')->app_settings_array['sms_notifications_enabled']='0';
foreach ([['none',0,0,null],['requested',0,1,'pending'],['approved',1,1,'approved'],['rejected',0,1,'rejected'],['approved',1,0,null]] as [$expected,$waived,$requested,$decision]) {
    $r=(object)['fee_is_waived'=>$waived,'fee_waiver_requested'=>$requested,'fee_waiver_commercial_status'=>$decision];
    $check(App\Libraries\Gate_pass_eligibility::waiverStatus($r)===$expected,'Waiver classification '.$expected);
}
// Render actual forms, including the old attachment edit case.
$newHtml=view('gate_pass_portal/requests/visitor_modal_form',['gate_pass_request_id'=>$id,'model_info'=>null]);
$editHtml=view('gate_pass_portal/requests/visitor_modal_form',['gate_pass_request_id'=>$id,'model_info'=>$visitors->get_one($v)]);
$check(str_contains($newHtml,'name="id_type"') && str_contains($newHtml,'name="id_number"'),'Visitor identity controls render');
$check(!str_contains($newHtml,'blocked_visitor_acknowledged'),'No blocked-visitor override control');
$check(str_contains($editHtml,'visitor_attachment_download/'.$v),'Existing attachment stays accessible on edit');

foreach (['english','arabic'] as $language) {
    $labels = include APPPATH . 'Language/' . $language . '/custom_lang.php';
    foreach (['gate_pass_blocked_cannot_process','gate_pass_waiver_none','gate_pass_review_fee','login_otp_delivery','gate_pass_registration_auth_help'] as $key) {
        $check(isset($labels[$key]) && $labels[$key] !== '', $language . ' returns translated label ' . $key);
    }
}
// A blocked request cannot initialize a bank transaction, including a stale flag.
$db->query('DROP TABLE pod_eservice_payments');
foreach (['eservice_payments','eservice_payment_events'] as $t) {$db->query("CREATE TABLE pod_{$t} LIKE bedotscpanel_poderp.pod_{$t}");}
$db->resetDataCache();
$db->table('gate_pass_requests')->where('id',$id)->update(['status'=>'department_approved','stage'=>'commercial','fee_breakdown'=>null,'fee_amount'=>'25.000']);
$payConfig=new Config\EservicesPayments();$payConfig->provider='bank_muscat';$payConfig->currency='OMR';$payConfig->smartpayEnvironment='uat';
$payConfig->smartpayMerchantId='162';$payConfig->smartpayAccessCode='TEST-ONLY';$payConfig->smartpayWorkingKey=str_repeat('A',32);
$payConfig->smartpayPublicBaseUrl='http://127.0.0.1:18084/index.php';
$manager=new App\Libraries\Payments\Eservice_payment_manager($db,$payConfig);
$result=$manager->start('gate_pass_fee',$id,null,1,'25.000','QA','http://127.0.0.1:18084/','http://127.0.0.1:18084/',['currency'=>'OMR']);
$check(!$result['success'],'Blocked checkout refused');
$check(count($rows('eservice_payments'))===0 && count($rows('eservice_payment_events'))===0,'Blocked checkout writes no payment or events');

$blocks->unblock_visitor($block,2,'QA checkout');
$result=$manager->start('gate_pass_fee',$id,null,1,'25.000','QA','http://127.0.0.1:18084/','http://127.0.0.1:18084/',['currency'=>'OMR']);
$check($result['success'],'Clear request checkout created with dummy credentials, never sent');
$payment=$db->table('eservice_payments')->where('public_id',$result['payment_id'])->get()->getRow();
$blocks->block_visitor(['id_number'=>'QA123'],2);
$beforePayment=$rows('eservice_payments');
$check(!$manager->prepareSmartpayHandoff($payment->public_id)['success'],'Block applied after checkout stops bank handoff');
$check($beforePayment===$rows('eservice_payments'),'Refused bank handoff writes nothing');
// Simulate a bank-confirmed payment arriving after a block. Financial truth must survive.
$db->table('eservice_payments')->where('id',$payment->id)->update(['status'=>'paid','verified_at'=>gmdate('Y-m-d H:i:s'),'paid_at'=>gmdate('Y-m-d H:i:s'),'provider_payment_id'=>'TEST-ONLY-REFERENCE']);
$settle=new ReflectionMethod($manager,'settleVerifiedSmartpayPayment');
$beforeRequest=$rows('gate_pass_requests');$beforeApprovals=$rows('gate_pass_request_approvals');
$result=$settle->invoke($manager,(int)$payment->id);
$p=$db->table('eservice_payments')->where('id',$payment->id)->get()->getRow();
$check(!$result['success'] && $p->status==='paid' && $p->settlement_status==='review_required','Late payment remains paid and flagged for Accounting');
$check($beforeRequest===$rows('gate_pass_requests') && $beforeApprovals===$rows('gate_pass_request_approvals'),'Late payment cannot approve a blocked request');
$blocks->unblock_visitor($block,2,'QA reconciled');
$check($settle->invoke($manager,(int)$payment->id)['success'],'After unblock, Accounting can apply the same saved payment');
$beforeApprovals=$rows('gate_pass_request_approvals');
$check($settle->invoke($manager,(int)$payment->id)['success'] && $beforeApprovals===$rows('gate_pass_request_approvals'),'Accounting recheck is idempotent');
// Email destination and stale jobs are checked at dispatch, not blindly sent.
$notices->submitted($requests->get_one($id));
$db->table('users')->where('id',1)->update(['email'=>'changed@example.invalid']);
$notices->process(20,0,$mail,static fn()=>throw new RuntimeException('SMS must not send'));
$check(in_array('recipient_changed',array_column($rows('gate_pass_notification_outbox'),'status'),true),'Changed email is not delivered to an old address');
$notices->submitted($requests->get_one($id));
$db->table('gate_pass_notification_outbox')->where('status','queued')->update(['created_at'=>gmdate('Y-m-d H:i:s',time()-90000)]);
$notices->process(20,0,static fn()=>throw new RuntimeException('Expired email must not send'));
$check(in_array('expired',array_column($rows('gate_pass_notification_outbox'),'status'),true),'Stale notifications expire');
$notices->submitted($requests->get_one($id));
$reviewNotice = $db->table('gate_pass_notification_outbox')->where('status','queued')->where('recipient_user_id',2)->get()->getRow();
$db->table('gate_pass_department_users')->where('user_id',2)->update(['status'=>'inactive']);
$revokedSends = [];
$notices->process(20,0,static function ($to) use (&$revokedSends) { $revokedSends[]=$to; return true; });
$check($reviewNotice && $db->table('gate_pass_notification_outbox')->where('id',$reviewNotice->id)->get()->getRow()->status==='recipient_changed'
    && !in_array('gp-qa-2@example.invalid',$revokedSends,true), 'Removed department reviewers cannot receive queued request details.');
$beforeOutbox=$rows('gate_pass_notification_outbox');
$db->query(file_get_contents(FCPATH . 'documentation/GATE_PASS_DISCUSSION_FIX.sql'));
$check($beforeOutbox===$rows('gate_pass_notification_outbox'),'SQL rerun preserves all delivery history');
echo "GatePassDiscussionDatabaseTest: {$checks} checks passed; no real emails/SMS; isolated database removed on exit\n";

