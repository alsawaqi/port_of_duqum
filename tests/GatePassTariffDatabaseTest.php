<?php
// Real models and SQL against an isolated local database; no messages or gateway calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class GatePassTariffTestBoot extends CodeIgniter\Boot {
    static function init(): void {
        $paths = new Config\Paths();
        static::definePathConstants($paths); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
GatePassTariffTestBoot::init();
helper(['general','plugin','date_time','safe_serialization','url','language','form']);
require_once APPPATH . 'ThirdParty/PHP-Hooks/php-hooks.php';
config('Rise')->app_settings_array = ['language'=>'english','sms_notifications_enabled'=>'0'];
$mysqli = new mysqli('127.0.0.1', 'root', '', '', 3306);
$name = 'codex_gp_tariff_' . bin2hex(random_bytes(6));
$mysqli->query("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
register_shutdown_function(static function () use ($mysqli, $name): void {
    if (preg_match('/^codex_gp_tariff_[a-f0-9]{12}$/D', $name)) { $mysqli->query("DROP DATABASE `{$name}`"); }
});
$mysqli->select_db($name);
foreach (['gate_pass_requests','gate_pass_request_visitors','gate_pass_fee_rules','activity_logs','gate_pass_blocked_visitors','users','gate_pass_department_users'] as $table) {
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
$rulesBefore = $db->query('SELECT * FROM pod_gate_pass_fee_rules ORDER BY id')->getResultArray();
$install();
$check($rulesBefore === $db->query('SELECT * FROM pod_gate_pass_fee_rules ORDER BY id')->getResultArray(), 'SQL rerun is idempotent');
foreach ([1=>2,2=>3,7=>3,8=>5,9=>5,14=>5,15=>25,30=>25,90=>25,91=>40,105=>40,180=>40,181=>55,365=>55] as $days=>$perPerson) {
    $id = (int)$requests->ci_save($requestData($days));
    $check($id>0, 'Create draft: '.($requests->tariff_error ?? ''));
    $check((float)$requests->get_one($id)->fee_amount === 0.0, 'No visitors = zero draft estimate');
    $a = (int)$visitors->ci_save($visitorData($id));
    $b = (int)$visitors->ci_save($visitorData($id, 'driver'));
    $c = (int)$visitors->ci_save($visitorData($id, 'passenger'));
    $check($a>0 && $b>0 && $c>0, 'All visitor roles saved: '.($visitors->tariff_error ?? ''));
    $q = App\Libraries\Gate_pass_tariff::snapshot($requests->get_one($id));
    $check($q['visitor_count']===3 && (float)$q['total']===$perPerson*3.0, "{$days} days, all 3 roles count");
    $check((bool)$visitors->delete($b), 'Remove visitor');
    $check((float)$requests->get_one($id)->fee_amount===$perPerson*2.0, 'Deletion recalculates');
    $check((bool)$visitors->delete($b,true), 'Restore visitor');
    $check((float)$requests->get_one($id)->fee_amount===$perPerson*3.0, 'Restore recalculates');
}
$id = (int)$requests->ci_save($requestData(9));
$check(!$requests->ci_save(['status'=>'submitted'], $id), 'Cannot submit an empty request');
$v = (int)$visitors->ci_save($visitorData($id));
$check((bool)$requests->ci_save(['visit_to'=>'2026-10-15 23:59:59'], $id), 'Extend dates');
$check((float)$requests->get_one($id)->fee_amount===25.0, '15 days includes induction');
$check((bool)$requests->ci_save(['status'=>'submitted'], $id), 'Submit uses final visitor count');
$check(!$visitors->ci_save($visitorData($id)), 'Cannot add people during review');
$check((bool)$requests->ci_save(['status'=>'returned'], $id), 'Return to preparation');
foreach (['pending','processing','verification_required','paid'] as $status) {
    $db->query("INSERT INTO pod_eservice_payments(subject_type,subject_id,status) VALUES('gate_pass_fee',?,?)",[$id,$status]);
    $before=$requests->get_one($id);
    $beforeVisitors=$db->query('SELECT * FROM pod_gate_pass_request_visitors WHERE gate_pass_request_id=?',[$id])->getResultArray();
    $check(!$requests->ci_save(['visit_to'=>'2026-10-16 23:59:59'],$id), "{$status}: dates locked");
    $check(!$visitors->ci_save($visitorData($id)) && !$visitors->delete($v), "{$status}: visitor count locked");
    $check((array)$before===(array)$requests->get_one($id), 'Refusal leaves request unchanged');
    $check($beforeVisitors===$db->query('SELECT * FROM pod_gate_pass_request_visitors WHERE gate_pass_request_id=?',[$id])->getResultArray(), 'Refusal leaves visitors unchanged');
    $db->query('DELETE FROM pod_eservice_payments WHERE subject_id=?',[$id]);
}
$check(!$requests->ci_save(['fee_amount'=>'1.000'],$id), 'Commercial cannot overwrite calculated amount');
$check((bool)$requests->ci_save(['status'=>'department_approved','stage'=>'commercial'], $id), 'Return resubmits to commercial');
$check((bool)$requests->ci_save(['fee_amount'=>'25.000','fee_is_waived'=>1],$id), 'Existing waiver mechanism preserves full quoted amount');
$tariff->assertPayable($requests->get_one($id)); $check(true,'Matching quote passes payable validation');
$db->query("UPDATE pod_gate_pass_request_visitors SET deleted=1 WHERE id=?",[$v]);
try { $tariff->assertPayable($requests->get_one($id)); throw new RuntimeException('Stale quote accepted'); }
catch (DomainException $e) { $check($e->getMessage()==='gate_pass_tariff_changed','Out-of-band visitor changes block checkout'); }
$db->query("UPDATE pod_gate_pass_requests SET fee_breakdown=NULL,fee_amount=9.000 WHERE id=?",[$id]);
$check((bool)$requests->ci_save(['purpose_notes'=>'Legacy record unchanged'],$id), 'Unrelated legacy edit works');
$check((float)$requests->get_one($id)->fee_amount===9.0 && $requests->get_one($id)->fee_breakdown===null,'Legacy fee preserved');
$db->query('UPDATE pod_gate_pass_fee_rules SET is_active=0 WHERE min_days=1');
$check(!$requests->ci_save($requestData(1)), 'Missing tariff refuses creation');
$install();
$db->query("INSERT INTO pod_gate_pass_fee_rules(min_days,max_days,rate_type,currency,amount,is_active) VALUES(1,7,'flat','OMR',99,1)");
$check(!$requests->ci_save($requestData(1)), 'Overlapping rules refuse creation');
$check(!$requests->ci_save($requestData(366)), 'More than one year refused');
$install();
// Exercise the actual checkout manager and encrypted bank form, with dummy keys only.
// createCheckout()/hostedForm() are local operations; never submit the form to the bank.
$db->query('DROP TABLE pod_eservice_payments');
$db->query('CREATE TABLE pod_eservice_payments LIKE bedotscpanel_poderp.pod_eservice_payments');
$db->query('CREATE TABLE pod_eservice_payment_events LIKE bedotscpanel_poderp.pod_eservice_payment_events');
$db->resetDataCache();
$id=(int)$requests->ci_save($requestData(15));
$paidVisitor=(int)$visitors->ci_save($visitorData($id)); $visitors->ci_save($visitorData($id));
$check((bool)$requests->ci_save(['status'=>'department_approved','stage'=>'commercial'],$id),'Checkout fixture approved');
$payConfig=new Config\EservicesPayments();
$payConfig->provider='bank_muscat';$payConfig->currency='OMR';$payConfig->smartpayEnvironment='uat';
$payConfig->smartpayMerchantId='162';$payConfig->smartpayAccessCode='LOCAL-TEST-ONLY';
$payConfig->smartpayWorkingKey=str_repeat('A',32);$payConfig->smartpayPublicBaseUrl='http://127.0.0.1:18084/index.php';
$manager=new App\Libraries\Payments\Eservice_payment_manager($db,$payConfig);
$start=static fn(string $amount,int $payer=1) => $manager->start('gate_pass_fee',$id,null,$payer,$amount,'Tariff test',
    'http://127.0.0.1:18084/index.php/gate_pass_portal','http://127.0.0.1:18084/index.php/gate_pass_portal',['currency'=>'OMR']);
$check(!$start('5.000')['success'],'Tampered underpayment refused');
$check(!$start('50.000',2)['success'],'Wrong payer refused');
$check((int)$db->query('SELECT COUNT(*) AS n FROM pod_eservice_payments')->getRow()->n===0,'Refused checkout writes no payment');
$started=$start('50.000');
$check($started['success']===true,'Checkout successfully initialized with calculated amount');
$payment=$db->query('SELECT * FROM pod_eservice_payments WHERE public_id=?',[$started['payment_id']])->getRow();
$check($payment->amount==='50.000' && (int)$payment->amount_minor===50000,'Bank amount is full per-person total');
$metadata=json_decode($payment->metadata,true);
$check($metadata['gate_pass_tariff']['visitor_count']===2 && $metadata['gate_pass_tariff']['induction_subtotal']==='10.000','Accounting snapshot saved');
$form=(new App\Libraries\Payments\Bank_muscat_gateway($payConfig))->hostedForm($payment);
parse_str(App\Libraries\Payments\Bank_muscat_gateway::decrypt($form['fields']['encRequest'],$payConfig->smartpayWorkingKey),$bankFields);
$check($bankFields['amount']==='50.000' && $bankFields['currency']==='OMR','Encrypted bank request carries exact 50.000 OMR');
$again=$start('50.000');
$check($again['success'] && $again['payment_id']===$started['payment_id'],'Repeat checkout reuses same payment');
$check((int)$db->query('SELECT COUNT(*) AS n FROM pod_eservice_payments')->getRow()->n===1,'No duplicate payment on retry');
$check(!$visitors->delete($paidVisitor),'Cannot delete a visitor from an approved request');
$frozen=$requests->get_one($id)->fee_breakdown;
$check((bool)$requests->ci_save(['status'=>'returned','stage'=>'security'],$id),'Security can return a request');
$check(!$requests->ci_save(['status'=>'commercial_approved','fee_amount'=>'1.000'],$id),'Resubmission cannot change frozen fee');
$check((bool)$requests->ci_save(['status'=>'commercial_approved'],$id),'Returned request can resubmit without charging again');
$check($requests->get_one($id)->fee_breakdown===$frozen,'Post-payment resubmit preserves quote byte-for-byte');
$check((bool)$visitors->ci_save(['phone'=>'96800000001'],$paidVisitor),'Authorized non-price visitor correction remains possible');
$check($requests->get_one($id)->fee_breakdown===$frozen,'Visitor contact correction never reprices a payment');
$requestBefore=(array)$requests->get_one($id);
$paymentBefore=(array)$db->query('SELECT * FROM pod_eservice_payments WHERE id=?',[$payment->id])->getRow();
$install();
$check($requestBefore===(array)$requests->get_one($id),'SQL rerun never reprices existing requests');
$check($paymentBefore===(array)$db->query('SELECT * FROM pod_eservice_payments WHERE id=?',[$payment->id])->getRow(),'SQL rerun never modifies transactions');
$html=view('gate_pass_portal/requests/fee_breakdown',['request'=>(object)[], 'quote'=>$metadata['gate_pass_tariff']]);
$check(str_contains($html,'50.000') && str_contains($html,'10.000') && str_contains($html,'Number of visitors'),'Accounting breakdown renders tariff, induction and visitor count');
$check(!str_contains($html,'"visitor_count"') && !str_contains($html,'"version"'),'Accounting shows readable fields rather than JSON');
echo "GatePassTariffDatabaseTest: {$checks} checks passed; isolated database removed on exit\n";
