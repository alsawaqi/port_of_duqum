<?php
// Render the real tender views without a database or network transport.
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development'); define('CI_DEBUG', true);
require 'app/Config/Paths.php'; require 'system/Boot.php';
class TenderDateDisplayBoot extends CodeIgniter\Boot {
    static function init(): void {
        static::definePathConstants(new Config\Paths()); static::loadConstants();
        static::loadCommonFunctions(); static::loadAutoloader();
    }
}
TenderDateDisplayBoot::init();
helper(['general', 'date_time', 'url', 'language', 'form']);
config('Rise')->app_settings_array = [
    'language' => 'english', 'timezone' => 'Asia/Muscat',
    'date_format' => 'Y-m-d', 'time_format' => '24_hours',
];
config('App')->baseURL = 'https://example.invalid/';
date_default_timezone_set('UTC');
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) throw new ErrorException($message, 0, $level, $file, $line);
});
$n = 0;
$check = static function ($ok, $message) use (&$n): void {
    $n++;
    if (!$ok) throw new RuntimeException($message);
};
$tender = new class extends stdClass {
    public function __get($name) { return null; }
};
// Each date is distinct so the assertions identify the rendered field.
foreach ([
    'id'=>1, 'reference'=>'QA-DATES', 'title'=>'QA Dates', 'status'=>'awarded',
    'workflow_stage'=>'award_decision', 'release_at'=>'2026-09-20 09:00:00',
    'published_at'=>'2026-09-20 09:00:00', 'closing_at'=>'2026-09-29 23:30:00',
    'document_purchase_deadline'=>'2026-09-23 09:00:00',
    'site_visit_at'=>'2026-09-24 09:00:00', 'clarification_deadline'=>'2026-09-25 09:00:00',
    'bid_opening_at'=>'2026-09-30 09:00:00', 'created_at'=>'2026-09-19 09:00:00',
    'loa_issued_at'=>'2026-10-01 09:00:00', 'vendor_category_name'=>'QA',
] as $field=>$value) { $tender->$field = $value; }
$reflection = new ReflectionClass(App\Controllers\Tender_reports::class);
$controller = $reflection->newInstanceWithoutConstructor();
$timeline = $reflection->getMethod('_get_stage_timeline')->invoke($controller, $tender);
$check($timeline[0]['convert_to_local'] === true, 'UTC creation audit retains conversion');
$check($timeline[1]['convert_to_local'] === false, 'Oman publication clock is not converted twice');
foreach (['tender_reports/details', 'vendor_portal/tenders/details', 'vendor_portal/tenders/view_modal'] as $viewName) {
    $html = view($viewName, ['tender'=>$tender, 'timeline'=>$timeline, 'vendor_id'=>1], ['saveData'=>false]);
    $check(str_contains($html, '2026-09-20 09:00'), "$viewName preserves entered release time");
    $check(str_contains($html, '2026-09-29 23:30'), "$viewName preserves deadline date and time");
    $check(!str_contains($html, '2026-09-30 03:30'), "$viewName does not move deadline to the next day");
    if ($viewName === 'tender_reports/details') {
        $check(str_contains($html, '2026-09-19 13:00'), 'UTC creation audit still converts to Oman time');
        $check(str_contains($html, '2026-10-01 09:00'), 'Award time is already in Oman time');
    }
}
$evaluation = (object) [
    'submitted_after_deadline'=>1, 'late_review_status'=>'accepted',
    'deadline_at'=>'2026-09-29 23:30:00', 'submitted_at'=>'2026-09-30 00:00:00',
];
$html = view('tender_reports/evaluation_table', ['rows'=>[$evaluation]], ['saveData'=>false]);
$check(str_contains($html, 'Deadline: 2026-09-29 23:30'), 'Evaluation deadline uses the business clock');
$html = view('tender_reports/bid_opening_form', ['tender'=>$tender], ['saveData'=>false]);
$check(str_contains($html, '2026-09-29 23:30'), 'Bid opening form preserves the submission deadline');
$check(str_contains($html, '2026-09-30 09:00'), 'Bid opening form preserves scheduled opening time');
$html = view('tender_procurement_manager_inbox/details', [
    'tender'=>$tender, 'schedule'=>[['label'=>'Release', 'value'=>$tender->release_at]],
], ['saveData'=>false]);
$check(str_contains($html, '2026-09-20 09:00'), 'Manager review shows the entered schedule');
echo "TenderDateDisplayTest: $n checks passed; real views preserve Oman deadlines and UTC creation audit.\n";
