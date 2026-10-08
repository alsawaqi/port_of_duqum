<?php

if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class GatePassTemplateTestBoot extends CodeIgniter\Boot
{
    public static function init(): void
    {
        static::definePathConstants(new Config\Paths());
        static::loadConstants();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
GatePassTemplateTestBoot::init();
helper(['general', 'date_time', 'url', 'language']);
config('Rise')->app_settings_array = ['language' => 'english', 'timezone' => 'Asia/Muscat', 'date_format' => 'Y-m-d'];
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) { throw new ErrorException($message, 0, $level, $file, $line); }
});
$checks = 0;
$check = static function ($ok, $message) use (&$checks): void {
    $checks++;
    if (!$ok) { throw new RuntimeException($message); }
};
$request = (object) ['id' => 9001, 'reference' => 'GP-SAMPLE-9001', 'stage' => 'issued', 'status' => 'rop_approved',
    'visit_from' => '2026-10-01 00:00:00', 'visit_to' => '2026-10-07 23:59:59', 'created_at' => '2026-09-30 12:00:00',
    'request_type' => 'both', 'company_name' => 'Port of Duqm', 'purpose_name' => 'Delivery',
    'requester_email' => 'gatepass@example.invalid', 'requester_phone' => '96890000000',
    'fee_amount' => '15.000', 'currency' => 'OMR'];
$pass = (object) ['id' => 9001, 'gate_pass_request_id' => 9001, 'gate_pass_request_visitor_id' => null,
    'gate_pass_no' => 'GP-SAMPLE-9001', 'qr_token' => 'SAMPLE-NOT-A-VALID-GATE-PASS', 'status' => 'active'];
$visitors = [];
foreach (['Sample Visitor', 'Companion One', 'Companion Two', 'Companion Three', 'Companion Four'] as $i => $name) {
    $visitors[] = (object) ['id' => $i + 1, 'full_name' => $name, 'id_number' => 'DEMO-000' . ($i + 1),
        'phone' => '96890000000', 'visitor_company' => 'Example Logistics', 'is_primary' => $i === 0 ? 1 : 0];
}
$vehicles = [(object) ['plate_no' => 'S 12345'], (object) ['is_international_plate' => 1,
    'international_plate_no' => 'DXB 98765', 'plate_country' => 'UAE']];
$payment = (object) ['id' => 9001, 'subject_id' => 9001, 'subject_type' => 'gate_pass_fee', 'vendor_id' => null,
    'provider' => 'bank_muscat', 'status' => 'paid', 'verified_at' => '2026-09-30 12:01:00',
    'settlement_status' => 'applied', 'currency' => 'OMR', 'amount' => '15.000',
    'provider_checkout_id' => 'GPTEST9001', 'provider_payment_id' => 'DEMO-TRACK-9001',
    'bank_reference' => 'DEMO-BANK-9001', 'response_json' => '{"never":"PRINT-RAW-BANK-RESPONSE"}'];
$details = App\Libraries\Gate_pass_pdf::details($request, $pass, $visitors, $vehicles, $payment);
$check($details['name'] === 'Sample Visitor' && count($details['companions']) === 4, 'Primary visitor and four companions');
$check($details['to'] === '2026-10-07', 'Inclusive calendar end date must not shift in Oman time');
$check($details['email'] === $request->requester_email && $details['phone'] === $visitors[0]->phone, 'Saved requester email and visitor mobile');
$check($details['plates'] === 'S 12345, DXB 98765 (UAE)', 'All request vehicles, including international plates');
$check($details['payment'] === ['OMR 15.000', '9001', 'GPTEST9001', 'DEMO-BANK-9001'], 'Verified ledger references and three-decimal amount');
$check(!str_contains(json_encode($details), 'PRINT-RAW-BANK-RESPONSE'), 'No raw bank response in printed data');
$check($details['escort'] === '-', 'Do not invent an escort');

foreach ([['status', 'failed'], ['status', 'pending'], ['status', 'cancelled'], ['verified_at', null],
    ['settlement_status', 'review_required'], ['settlement_status', 'pending'], ['subject_type', 'vendor_registration'],
    ['subject_id', 9002], ['currency', 'USD'], ['amount', '14.000'], ['amount', 'invalid'], ['vendor_id', 44],
    ['deleted', 1], ['provider', 'stripe']] as [$field, $value]) {
    $bad = clone $payment; $bad->$field = $value;
    $check(App\Libraries\Gate_pass_pdf::details($request, $pass, $visitors, $vehicles, $bad)['payment'] === ['Not recorded', '-', '-', '-'],
        'Do not print a successful receipt for invalid ledger field ' . $field);
}
$fallback = clone $payment; $fallback->bank_reference = null;
$check(App\Libraries\Gate_pass_pdf::details($request, $pass, $visitors, $vehicles, $fallback)['payment'][3] === 'DEMO-TRACK-9001', 'Tracking reference fallback');
$individual = clone $pass; $individual->gate_pass_request_visitor_id = 3;
$detail = App\Libraries\Gate_pass_pdf::details($request, $individual, $visitors, $vehicles);
$check($detail['name'] === 'Companion Two' && $detail['companions'] === [] && $detail['individual'], 'Individual pass excludes other visitors');
$missing = clone $individual; $missing->gate_pass_request_visitor_id = 99;
try {
    App\Libraries\Gate_pass_pdf::details($request, $missing, $visitors, $vehicles);
    $check(false, 'Missing assigned visitor must be refused');
} catch (DomainException $e) { $check(true, 'Missing assigned visitor refused'); }
$deleted = clone $visitors[2]; $deleted->deleted = 1;
try {
    App\Libraries\Gate_pass_pdf::details($request, $individual, [$deleted], $vehicles);
    $check(false, 'Deleted assigned visitor must be refused');
} catch (DomainException $e) { $check(true, 'Deleted assigned visitor refused'); }

$render = static function ($name, $r, $p, $people, $cars, $pay = null, $pages = 2) use ($check): void {
    $pdf = (new App\Libraries\Gate_pass_pdf())->build($r, $p, $people, $cars, $pay);
    $count = $pdf->getNumPages();
    $check($pages === null ? $count >= 3 : $count === $pages, "$name page count: $count");
    $binary = $pdf->Output($name . '.pdf', 'S');
    $check(str_starts_with($binary, '%PDF-') && strlen($binary) > 2000, "$name renders a PDF");
    if ($out = getenv('POD_PDF_TEST_OUTPUT')) {
        if (!is_dir($out)) { mkdir($out, 0700, true); }
        file_put_contents($out . '/' . $name . '.pdf', $binary);
    }
};
$render('gate-pass-sample', $request, $pass, $visitors, $vehicles, $payment);
$render('gate-pass-individual', $request, $individual, $visitors, $vehicles, $payment);
$waived = clone $request; $waived->fee_is_waived = 1; $waived->request_type = 'person';
$detail = App\Libraries\Gate_pass_pdf::details($waived, $individual, $visitors, $vehicles, $payment);
$check($detail['payment'] === ['Waived', '-', '-', '-'] && $detail['plates'] === '', 'Waived person-only pass never prints stale payment or vehicle data');
$render('gate-pass-waived', $waived, $individual, $visitors, $vehicles, $payment);
$free = clone $request; $free->fee_amount = 0;
$check(App\Libraries\Gate_pass_pdf::details($free, $pass, [], [])['payment'][0] === 'No charge', 'Zero-fee pass');
$render('gate-pass-legacy', $request, $pass, [], []);

$arabic = clone $visitors[0]; $arabic->full_name = 'أحمد محمد البلوشي'; $arabic->visitor_company = 'شركة الخدمات اللوجستية';
$arabicRequest = clone $request; $arabicRequest->company_name = 'شركة ميناء الدقم'; $arabicRequest->purpose_name = 'توصيل البضائع';
service('language')->setLocale('arabic');
$render('gate-pass-arabic', $arabicRequest, $pass, [$arabic], $vehicles, $payment);
service('language')->setLocale('english');

$long = clone $request;
$long->company_name = str_repeat('Long company name ', 15) . 'END-COMPANY';
$long->purpose_name = str_repeat('Delivery and maintenance ', 30) . 'END-PURPOSE';
$manyVisitors = $visitors;
for ($i = 6; $i <= 42; $i++) {
    $manyVisitors[] = (object) ['id' => $i, 'full_name' => 'Extra Companion ' . $i, 'id_number' => 'EXTRA-' . $i, 'visitor_company' => 'Example Logistics'];
}
$manyVehicles = $vehicles;
for ($i = 1; $i <= 30; $i++) { $manyVehicles[] = (object) ['plate_no' => 'S ' . (10000 + $i)]; }
$render('gate-pass-overflow', $long, $pass, $manyVisitors, $manyVehicles, $payment, null);
$injected = clone $visitors[0]; $injected->full_name = '<img src="https://example.invalid/private"> & Test';
$render('gate-pass-literal-text', $request, $pass, [$injected], [], $payment);

foreach (['draft', 'submitted', 'security_approved'] as $status) {
    $draft = clone $request; $draft->status = $status;
    try {
        (new App\Libraries\Gate_pass_pdf())->build($draft, $pass, $visitors, $vehicles);
        $check(false, 'Unissued request must not print ROP approval');
    } catch (DomainException $e) { $check(true, 'Unissued request refused'); }
}
foreach ([['status', 'cancelled'], ['gate_pass_request_id', 777], ['deleted', 1]] as [$field, $value]) {
    $bad = clone $pass; $bad->$field = $value;
    try {
        (new App\Libraries\Gate_pass_pdf())->build($request, $bad, $visitors, $vehicles);
        $check(false, 'Invalid pass must not print ROP approval');
    } catch (DomainException $e) { $check(true, 'Invalid pass refused'); }
}
echo "GatePassPdfTemplateTest: $checks checks passed.\n";
