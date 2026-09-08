<?php
declare(strict_types=1);

// Explicit local UAT fixtures only. This script never calls the bank or sends mail.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('FCPATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require FCPATH . 'app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
final class LocalUatFixtureBootstrap extends CodeIgniter\Boot {
    public static function load(Config\Paths $paths): void {
        static::definePathConstants($paths); static::loadConstants(); static::loadDotEnv($paths);
        static::defineEnvironment(); static::loadCommonFunctions(); static::loadAutoloader();
    }
}
LocalUatFixtureBootstrap::load($paths);
if (!defined('CI_DEBUG')) { define('CI_DEBUG', true); }
$settings = (new Config\Database())->default;
if (!in_array(strtolower((string)$settings['hostname']), ['localhost', '127.0.0.1', '::1'], true)
    || ($settings['DBDriver'] ?? '') !== 'MySQLi' || !empty($settings['DSN'])
    || (int)$settings['port'] !== 3306 || $settings['database'] !== 'bedotscpanel_poderp'
    || $settings['DBPrefix'] !== 'pod_' || ENVIRONMENT === 'production') {
    throw new RuntimeException('Only the local XAMPP test database is supported.');
}
$db = Config\Database::connect($settings, false);
$mode = $argv[1] ?? '--inspect';
$tables = ['vendor_groups','vendor_group_fees','vendors','vendor_contacts','vendor_documents','vendor_bank_accounts','tender_target_vendors',
    'vendor_specialties','companies','departments','gate_pass_purposes','gate_pass_requests',
    'gate_pass_request_approvals','gate_pass_users','gate_pass_request_visitors','tenders','tender_requests',
    'tender_target_specialties','tender_invited_vendors','vendor_categories','vendor_sub_categories','vendor_grades'];
if ($mode === '--inspect') {
    $output = [];
    foreach ($tables as $table) {
        if (!$db->tableExists($table)) { continue; }
        $output[$table] = array_map(static fn(array $c):string => $c['Field'] . ' ' . $c['Type'] . ' default=' . ($c['Default'] ?? 'NULL') . ' nullable=' . $c['Null'], $db->query('SHOW COLUMNS FROM ' . $db->prefixTable($table))->getResultArray());
    }
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--masters') {
    $output = [];
    foreach (['vendor_groups','vendor_group_fees','companies','departments','gate_pass_purposes',
        'vendor_categories','vendor_sub_categories','vendor_grades','vendor_document_types'] as $table) {
        $output[$table] = $db->table($table)->limit(20)->get()->getResultArray();
    }
    echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--create-group') {
    $db->transBegin();
    $group = $db->table('vendor_groups')->where('code', 'POD-UAT-SMOKE-20260905')->get()->getRow();
    if (!$group) {
        $db->table('vendor_groups')->insert(['name'=>'POD-UAT-SMOKE Payment Test', 'code'=>'POD-UAT-SMOKE-20260905',
            'requires_riyada'=>0, 'default_validity_days'=>30, 'is_active'=>1, 'created_at'=>gmdate('Y-m-d H:i:s'), 'deleted'=>0]);
        $group = $db->table('vendor_groups')->where('id', $db->insertID())->get()->getRow();
    }
    $fees = [];
    foreach (['registration','renewal'] as $type) {
        $fee = $db->table('vendor_group_fees')->where(['vendor_group_id'=>$group->id,'fee_type'=>$type,'deleted'=>0])->get()->getRow();
        if (!$fee) {
            $db->table('vendor_group_fees')->insert(['vendor_group_id'=>$group->id,'fee_type'=>$type,'currency'=>'OMR',
                'amount'=>'0.100','active_from'=>gmdate('Y-m-d'),'is_active'=>1,'created_at'=>gmdate('Y-m-d H:i:s'),'deleted'=>0]);
            $fee = $db->table('vendor_group_fees')->where('id', $db->insertID())->get()->getRow();
        }
        $fees[$type] = ['id'=>(int)$fee->id,'amount'=>$fee->amount,'currency'=>$fee->currency];
    }
    if (!$db->transStatus()) { $db->transRollback(); throw new RuntimeException('Fixture transaction failed.'); }
    $db->transCommit();
    echo json_encode(['group_id'=>(int)$group->id,'group_name'=>$group->name,'validity_days'=>$group->default_validity_days,'fees'=>$fees], JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--create-flows') {
    $now = gmdate('Y-m-d H:i:s');
    $reference = 'POD-UAT-SMOKE-20260905';
    $admin = $db->table('users')->select('id')->where(['email'=>'admin@pod.com','is_admin'=>1,'deleted'=>0])->get()->getRow();
    $group = $db->table('vendor_groups')->where(['code'=>$reference,'deleted'=>0])->get()->getRow();
    $department = $db->table('departments')->where(['id'=>3,'company_id'=>11,'is_active'=>1,'deleted'=>0])->get()->getRow();
    if (!$admin || !$group || !$department) { throw new RuntimeException('Expected active local fixture references missing.'); }
    $db->transBegin();
    $docType = $db->table('vendor_document_types')->where(['code'=>'POD-UAT-SMOKE-CR','vendor_group_id'=>$group->id,'deleted'=>0])->get()->getRow();
    if (!$docType) {
        $db->table('vendor_document_types')->insert(['name'=>'POD-UAT-SMOKE CR Test Document','code'=>'POD-UAT-SMOKE-CR',
            'is_required'=>1,'vendor_group_id'=>$group->id,'is_active'=>1,'created_at'=>$now,'deleted'=>0]);
        $docType = $db->table('vendor_document_types')->where('id',$db->insertID())->get()->getRow();
    }
    $gp = $db->table('gate_pass_requests')->where(['reference'=>$reference.'-GP','deleted'=>0])->get()->getRow();
    if (!$gp) {
        $db->table('gate_pass_requests')->insert(['reference'=>$reference.'-GP','requester_id'=>$admin->id,'company_id'=>11,
            'department_id'=>3,'stage'=>'commercial','stage_updated_at'=>$now,'gate_pass_purpose_id'=>3,
            'visit_type'=>'visitor','request_type'=>'person','vehicle_type'=>'none','visit_from'=>gmdate('Y-m-d H:i:s',time()+86400),
            'visit_to'=>gmdate('Y-m-d H:i:s',time()+172800),'purpose_notes'=>'POD-UAT-SMOKE: local Bank Muscat UAT fee and accounting test only.',
            'currency'=>'OMR','fee_amount'=>'0.100','fee_is_waived'=>0,'status'=>'department_approved','submitted_at'=>$now,
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $gpId = (int)$db->insertID();
        $db->table('gate_pass_request_approvals')->insert(['gate_pass_request_id'=>$gpId,'stage'=>'department',
            'decision'=>'approved','comment'=>'POD-UAT-SMOKE: prepared local fixture for payment-stage smoke test.',
            'decided_by'=>$admin->id,'decided_at'=>$now,'created_at'=>$now,'deleted'=>0]);
        $db->table('gate_pass_request_visitors')->insert(['gate_pass_request_id'=>$gpId,'role'=>'visitor',
            'full_name'=>'POD-UAT-SMOKE Test Visitor','id_type'=>'Passport','id_number'=>'POD-UAT-SMOKE-GP-20260905',
            'nationality'=>'Oman','visitor_company'=>'POD-UAT-SMOKE Payment Test','is_primary'=>1,'created_at'=>$now,'deleted'=>0]);
        $gp = $db->table('gate_pass_requests')->where('id',$gpId)->get()->getRow();
    }
    $tender = $db->table('tenders')->where(['reference'=>$reference.'-TN','deleted'=>0])->get()->getRow();
    if (!$tender) {
        $db->table('tender_requests')->insert(['reference'=>$reference.'-TR','company_id'=>11,'department_id'=>3,
            'requester_id'=>$admin->id,'request_date'=>gmdate('Y-m-d'),'budget_omr'=>'200.000','tender_fee'=>'0.100',
            'subject'=>'POD-UAT-SMOKE Tender Payment Test','brief_description'=>'Local UAT fixture only. No real procurement.',
            'announcement'=>'local','tender_type'=>'open','status'=>'committee_approved',
            'department_manager_user_id'=>$admin->id,'department_manager_signed_at'=>$now,'finance_verified_by'=>$admin->id,
            'finance_verified_at'=>$now,'committee_approved_by'=>$admin->id,'committee_approved_at'=>$now,
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $requestId = (int)$db->insertID();
        $db->table('tenders')->insert(['tender_request_id'=>$requestId,'reference'=>$reference.'-TN',
            'title'=>'POD-UAT-SMOKE Tender Payment Test','company_id'=>11,'department_id'=>3,
            'brief_description'=>'POD-UAT-SMOKE: local hosted bank checkout and accounting smoke test. No real procurement.',
            'tender_fee'=>'0.100','tender_type'=>'open','status'=>'published','workflow_stage'=>'bidding',
            'procurement_manager_status'=>'approved','procurement_manager_reviewed_by'=>$admin->id,'procurement_manager_reviewed_at'=>$now,
            'release_at'=>$now,'published_at'=>$now,'document_purchase_deadline'=>gmdate('Y-m-d H:i:s',time()+86400*5),
            'closing_at'=>gmdate('Y-m-d H:i:s',time()+86400*7),'created_by'=>$admin->id,'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $tender = $db->table('tenders')->where('id',$db->insertID())->get()->getRow();
    }
    if (!$db->transStatus()) { $db->transRollback(); throw new RuntimeException('Fixture transaction failed.'); }
    $db->transCommit();
    echo json_encode(['vendor_document_type_id'=>(int)$docType->id,
        'gate_pass'=>['id'=>(int)$gp->id,'requester_id'=>(int)$gp->requester_id,'reference'=>$gp->reference,'fee'=>$gp->fee_amount,'status'=>$gp->status],
        'tender'=>['id'=>(int)$tender->id,'request_id'=>(int)$tender->tender_request_id,'reference'=>$tender->reference,'fee'=>$tender->tender_fee,
            'status'=>$tender->status,'closing_at'=>$tender->closing_at]],JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--complete-profile') {
    $vendor = $db->table('vendors')->where(['cr_number'=>'UAT20260905','vendor_group_id'=>5,'deleted'=>0])->get()->getRow();
    if (!$vendor || $vendor->vendor_name !== 'POD UAT Smoke Vendor 20260905'
        || $vendor->email !== 'vendor-smoke-20260905@example.invalid') {
        throw new RuntimeException('The exact guest-created local smoke vendor is required.');
    }
    $owner = $db->table('vendor_users')->where(['vendor_id'=>$vendor->id,'is_owner'=>1,'deleted'=>0])->get()->getRow();
    if (!$owner) { throw new RuntimeException('The guest-created owner membership is missing.'); }
    $now = gmdate('Y-m-d H:i:s');
    $records = [
        'bank'=>['table'=>'vendor_bank_accounts','identity'=>['bank_name'=>'POD-UAT-SMOKE Synthetic Bank'],
            'data'=>['vendor_id'=>$vendor->id,'bank_name'=>'POD-UAT-SMOKE Synthetic Bank','bank_branch'=>'Local test fixture',
                'bank_account_no'=>'POD-UAT-SMOKE-NOT-A-REAL-ACCOUNT','status'=>'pending']],
        'specialties'=>['table'=>'vendor_specialties','identity'=>['specialty_name'=>'POD-UAT-SMOKE Software Testing'],
            'data'=>['vendor_id'=>$vendor->id,'vendor_category_id'=>2,'vendor_sub_category_id'=>1,'specialty_type'=>'service',
                'specialty_name'=>'POD-UAT-SMOKE Software Testing','specialty_description'=>'Synthetic local UAT fixture only.',
                'status'=>'pending']],
    ];
    $db->transBegin(); $result = [];
    foreach ($records as $module=>$fixture) {
        $row = $db->table($fixture['table'])->where(['vendor_id'=>$vendor->id,'deleted'=>0])->where($fixture['identity'])->get()->getRow();
        if (!$row) {
            $data = $fixture['data'] + ['created_at'=>$now,'updated_at'=>$now,'deleted'=>0];
            $db->table($fixture['table'])->insert($data);
            $recordId = (int)$db->insertID();
            $changes = ['module'=>$module,'table'=>$fixture['table'],'action'=>'create','record_id'=>$recordId,'before'=>[],'after'=>$fixture['data']];
            $db->table('vendor_update_requests')->insert(['vendor_id'=>$vendor->id,'requested_by'=>$owner->user_id,
                'changes'=>json_encode($changes,JSON_UNESCAPED_SLASHES),'status'=>'pending','deleted'=>0,'created_at'=>$now,'updated_at'=>$now]);
            $result[$module] = ['record_id'=>$recordId,'approval_request_id'=>(int)$db->insertID(),'status'=>'pending'];
        } else { $result[$module] = ['record_id'=>(int)$row->id,'status'=>$row->status,'already_exists'=>true]; }
    }
    $target = $db->table('tender_target_vendors')->where(['tender_id'=>12,'vendor_id'=>$vendor->id,'deleted'=>0])->get()->getRow();
    if (!$target) {
        $db->table('tender_target_vendors')->insert(['tender_id'=>12,'vendor_id'=>$vendor->id,'created_by'=>1,'created_at'=>$now,'deleted'=>0]);
    }
    if (!$db->transStatus()) { $db->transRollback(); throw new RuntimeException('Fixture transaction failed.'); }
    $db->transCommit();
    $contact = $db->table('vendor_contacts')->select('id,status')->where(['vendor_id'=>$vendor->id,'user_id'=>$owner->user_id,'deleted'=>0])->get()->getRow();
    echo json_encode(['vendor_id'=>(int)$vendor->id,'user_id'=>(int)$owner->user_id,'owner_membership_status'=>$owner->status,
        'contact'=>$contact,'new_profile_records'=>$result,'targeted_tender_id'=>12],JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--create-renewal-fixture') {
    // Historical approval is a test prerequisite only; never fabricate a paid bank transaction.
    $source = $db->table('vendors')->where(['id'=>27,'cr_number'=>'UAT20260905','vendor_group_id'=>5,'deleted'=>0])->get()->getRow();
    $owner = $db->table('users')->select('id,email')->where(['id'=>96,'email'=>'vendor-smoke-20260905@example.invalid','status'=>'active','deleted'=>0])->get()->getRow();
    $document = $db->table('vendor_documents')->where(['vendor_id'=>27,'vendor_document_type_id'=>5,'deleted'=>0])->get()->getRow();
    if (!$source || !$owner || !$document) { throw new RuntimeException('Expected guest test vendor, owner and uploaded synthetic document required.'); }
    $vendor = $db->table('vendors')->where(['cr_number'=>'UATREN20260905','vendor_group_id'=>5,'deleted'=>0])->get()->getRow();
    $db->transBegin();
    if (!$vendor) {
        $now = gmdate('Y-m-d H:i:s');
        $db->table('vendors')->insert(['vendor_group_id'=>5,'vendor_name'=>'POD-UAT-SMOKE Renewal Vendor 20260905',
            'email'=>'vendor-renewal-smoke-20260905@example.invalid','cr_number'=>'UATREN20260905','phone'=>'+96899990001',
            'phone_country_code'=>'+968','contact_person'=>'POD UAT Smoke Test Owner','currency'=>'OMR',
            'registration_valid_from'=>gmdate('Y-m-d',time()-86400*30),'registration_valid_to'=>gmdate('Y-m-d',time()-86400),
            'status'=>'expired','notes'=>'POD-UAT-SMOKE: synthetic previously approved registration, expired yesterday, prepared solely to test renewal checkout. No historical bank payment is asserted.',
            'created_by'=>1,'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $vendorId = (int)$db->insertID();
        $db->table('vendor_users')->insert(['vendor_id'=>$vendorId,'user_id'=>96,'vendor_role_id'=>1,'is_owner'=>1,
            'status'=>'active','invited_by'=>1,'deleted'=>0]);
        $db->table('vendor_contacts')->insert(['vendor_id'=>$vendorId,'user_id'=>96,'contacts_name'=>'POD UAT Smoke Test Owner',
            'phone'=>'+96899990001','email'=>$owner->email,'role'=>'Owner','is_primary'=>1,'is_active'=>1,'status'=>'approved',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_bank_accounts')->insert(['vendor_id'=>$vendorId,'bank_name'=>'POD-UAT-SMOKE Synthetic Bank',
            'bank_branch'=>'Local test fixture','bank_account_no'=>'POD-UAT-SMOKE-NOT-A-REAL-ACCOUNT','status'=>'approved',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_specialties')->insert(['vendor_id'=>$vendorId,'vendor_category_id'=>2,'vendor_sub_category_id'=>1,
            'specialty_type'=>'service','specialty_name'=>'POD-UAT-SMOKE Software Testing',
            'specialty_description'=>'Synthetic previously approved local renewal test profile.','status'=>'approved',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_documents')->insert(['vendor_id'=>$vendorId,'vendor_document_type_id'=>5,'disk'=>$document->disk,
            'path'=>$document->path,'original_name'=>'POD-UAT-SMOKE-test-document.pdf','mime_type'=>$document->mime_type,
            'size_bytes'=>$document->size_bytes,'uploaded_by'=>96,'status'=>'approved','created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $vendor = $db->table('vendors')->where('id',$vendorId)->get()->getRow();
    }
    if (!$db->transStatus()) { $db->transRollback(); throw new RuntimeException('Fixture transaction failed.'); }
    $db->transCommit();
    echo json_encode(['vendor_id'=>(int)$vendor->id,'vendor_name'=>$vendor->vendor_name,'cr_number'=>$vendor->cr_number,
        'status'=>$vendor->status,'registration_valid_from'=>$vendor->registration_valid_from,
        'registration_valid_to'=>$vendor->registration_valid_to,'owner_user_id'=>96,'expected_renewal_fee'=>'OMR 0.100',
        'payment_ledger'=>'No payment row created or marked paid.'],JSON_PRETTY_PRINT) . PHP_EOL;
} elseif (in_array($mode, ['--create-new-registration-fixture','--create-new-registration-fixture-c','--create-new-registration-fixture-d','--create-new-registration-fixture-e','--create-new-registration-fixture-f'], true)) {
    $suffix = ['--create-new-registration-fixture-c'=>'C','--create-new-registration-fixture-d'=>'D','--create-new-registration-fixture-e'=>'E','--create-new-registration-fixture-f'=>'F'][$mode] ?? 'B';
    $owner = $db->table('users')->select('id,email')->where(['id'=>96,'email'=>'vendor-smoke-20260905@example.invalid','status'=>'active','deleted'=>0])->get()->getRow();
    $document = $db->table('vendor_documents')->where(['vendor_id'=>27,'vendor_document_type_id'=>5,'deleted'=>0])->get()->getRow();
    if (!$owner || !$document) { throw new RuntimeException('The exact local smoke owner and synthetic uploaded document are required.'); }
    $vendor = $db->table('vendors')->where(['cr_number'=>'UATNEW20260905'.$suffix,'vendor_group_id'=>5,'deleted'=>0])->get()->getRow();
    $db->transBegin();
    if (!$vendor) {
        $now = gmdate('Y-m-d H:i:s');
        $db->table('vendors')->insert(['vendor_group_id'=>5,'vendor_name'=>'POD-UAT-SMOKE New Vendor '.$suffix.' 20260905',
            'email'=>'vendor-smoke-'.strtolower($suffix).'-20260905@example.invalid','cr_number'=>'UATNEW20260905'.$suffix,'phone'=>'+96899990002',
            'phone_country_code'=>'+968','contact_person'=>'POD UAT Smoke Test Owner','currency'=>'OMR','status'=>'new',
            'notes'=>'POD-UAT-SMOKE: new vendor fixture '.$suffix.' for registered-origin checkout test. Initial registration is neither paid nor approved. Earlier merchant-authentication-failed attempts remain untouched.',
            'created_by'=>1,'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $vendorId = (int)$db->insertID();
        $db->table('vendor_users')->insert(['vendor_id'=>$vendorId,'user_id'=>96,'vendor_role_id'=>1,'is_owner'=>1,
            'status'=>'active','invited_by'=>1,'deleted'=>0]);
        $db->table('vendor_contacts')->insert(['vendor_id'=>$vendorId,'user_id'=>96,'contacts_name'=>'POD UAT Smoke Test Owner',
            'phone'=>'+96899990002','email'=>$owner->email,'role'=>'Owner','is_primary'=>1,'is_active'=>1,'status'=>'approved',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_bank_accounts')->insert(['vendor_id'=>$vendorId,'bank_name'=>'POD-UAT-SMOKE Synthetic Bank',
            'bank_branch'=>'Local test fixture','bank_account_no'=>'POD-UAT-SMOKE-NOT-A-REAL-ACCOUNT','status'=>'pending',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_specialties')->insert(['vendor_id'=>$vendorId,'vendor_category_id'=>2,'vendor_sub_category_id'=>1,
            'specialty_type'=>'service','specialty_name'=>'POD-UAT-SMOKE Software Testing',
            'specialty_description'=>'Synthetic local UAT registration test profile.','status'=>'pending',
            'created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('vendor_documents')->insert(['vendor_id'=>$vendorId,'vendor_document_type_id'=>5,'disk'=>$document->disk,
            'path'=>$document->path,'original_name'=>'POD-UAT-SMOKE-test-document.pdf','mime_type'=>$document->mime_type,
            'size_bytes'=>$document->size_bytes,'uploaded_by'=>96,'status'=>'pending','created_at'=>$now,'updated_at'=>$now,'deleted'=>0]);
        $db->table('tender_target_vendors')->insert(['tender_id'=>12,'vendor_id'=>$vendorId,'created_by'=>1,'created_at'=>$now,'deleted'=>0]);
        $vendor = $db->table('vendors')->where('id',$vendorId)->get()->getRow();
    }
    if (!$db->transStatus()) { $db->transRollback(); throw new RuntimeException('Fixture transaction failed.'); }
    $db->transCommit();
    echo json_encode(['vendor_id'=>(int)$vendor->id,'vendor_name'=>$vendor->vendor_name,'cr_number'=>$vendor->cr_number,
        'status'=>$vendor->status,'registration_valid_from'=>$vendor->registration_valid_from,
        'registration_valid_to'=>$vendor->registration_valid_to,'owner_user_id'=>96,'expected_registration_fee'=>'OMR 0.100',
        'targeted_tender_id'=>12,'payment_ledger'=>'No payment row created or marked paid. Previous registration vendors and payment attempts unchanged.'],JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--status') {
    $group = $db->table('vendor_groups')->where(['code'=>'POD-UAT-SMOKE-20260905','deleted'=>0])->get()->getRow();
    $vendors = $group ? $db->table('vendors')->select('id,vendor_name,cr_number,status,registration_valid_from,registration_valid_to')
        ->where(['vendor_group_id'=>$group->id,'deleted'=>0])->get()->getResultArray() : [];
    $memberships = [];
    foreach ($vendors as $vendor) {
        $memberships[$vendor['id']] = $db->table('vendor_users')->select('id,vendor_id,user_id,status,is_owner,vendor_role_id')
            ->where(['vendor_id'=>$vendor['id'],'deleted'=>0])->get()->getResultArray();
    }
    $gp = $db->table('gate_pass_requests')->select('id,reference,requester_id,status,stage,fee_amount,payment_transaction_id,visit_from,visit_to')
        ->where(['reference'=>'POD-UAT-SMOKE-20260905-GP','deleted'=>0])->get()->getRowArray();
    $tender = $db->table('tenders')->select('id,reference,status,tender_fee,closing_at')
        ->where(['reference'=>'POD-UAT-SMOKE-20260905-TN','deleted'=>0])->get()->getRowArray();
    $payments = []; $feeRequests = [];
    foreach ($vendors as $vendor) {
        $payments = array_merge($payments, $db->table('eservice_payments')->select('id,subject_type,subject_id,vendor_id,amount,currency,status,settlement_status,verified_at,initiated_at,expires_at,handed_off_at')
            ->where('vendor_id',$vendor['id'])->get()->getResultArray());
        $feeRequests = array_merge($feeRequests, $db->table('vendor_fee_requests')->select('id,vendor_id,fee_type,amount,currency,prior_valid_until,status,review_status')
            ->where('vendor_id',$vendor['id'])->get()->getResultArray());
    }
    if ($gp) { $payments = array_merge($payments, $db->table('eservice_payments')->select('id,subject_type,subject_id,vendor_id,amount,currency,status,settlement_status,verified_at,initiated_at,expires_at,handed_off_at')
        ->where(['subject_type'=>'gate_pass_fee','subject_id'=>$gp['id']])->get()->getResultArray()); }
    echo json_encode(['utc_now'=>gmdate('Y-m-d H:i:s'),'vendors'=>$vendors,'memberships'=>$memberships,'gate_pass'=>$gp,'tender'=>$tender,'payments'=>$payments,'fee_requests'=>$feeRequests],JSON_PRETTY_PRINT) . PHP_EOL;
} elseif ($mode === '--create-document') {
    $text = "BT /F1 18 Tf 60 740 Td (POD-UAT-SMOKE TEST DOCUMENT) Tj 0 -35 Td /F1 11 Tf (Local payment smoke test fixture only. Not an official registration.) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length " . strlen($text) . ">>\nstream\n" . $text . "\nendstream"
    ];
    $pdf = "%PDF-1.4\n"; $offsets = [0];
    foreach ($objects as $index=>$object) { $offsets[] = strlen($pdf); $pdf .= ($index+1) . " 0 obj\n" . $object . "\nendobj\n"; }
    $xref = strlen($pdf); $pdf .= "xref\n0 6\n0000000000 65535 f \n";
    for ($i=1;$i<=5;$i++) { $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n"; }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    $path = __DIR__ . '/POD-UAT-SMOKE-test-document.pdf';
    if (file_put_contents($path, $pdf) === false) { throw new RuntimeException('Could not write test document.'); }
    echo json_encode(['path'=>$path,'bytes'=>strlen($pdf),'purpose'=>'Fake document for local UAT upload validation only.'],JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    throw new RuntimeException('Unknown fixture operation.');
}
$db->close();
