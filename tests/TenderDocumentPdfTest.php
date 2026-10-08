<?php

// Local rendering only: no database, SMTP or remote assets.
if (PHP_SAPI !== 'cli') { exit; }
chdir(dirname(__DIR__));
define('FCPATH', getcwd() . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
define('CI_DEBUG', true);
require 'app/Config/Paths.php';
require 'system/Boot.php';
class TenderDocumentTestBoot extends CodeIgniter\Boot
{
    public static function init(): void
    {
        static::definePathConstants(new Config\Paths());
        static::loadConstants();
        static::loadCommonFunctions();
        static::loadAutoloader();
    }
}
TenderDocumentTestBoot::init();
helper(['general', 'date_time', 'url', 'language']);
config('Rise')->app_settings_array = ['language' => 'english', 'timezone' => 'Asia/Muscat', 'date_format' => 'Y-m-d'];
set_error_handler(static function ($level, $message, $file, $line) {
    if (error_reporting() & $level) { throw new ErrorException($message, 0, $level, $file, $line); }
});
$checks = 0;
$assert = static function ($ok, $message) use (&$checks) {
    if (!$ok) { throw new RuntimeException($message); } $checks++;
};
$tender = (object) ['id' => 1, 'reference' => 'PODC-SAMPLE-2026-001', 'title' => 'Supply of Safety Equipment',
    'budget_omr' => '25000.000', 'currency' => 'OMR', 'closing_at' => '2026-09-29 23:30:00', 'bid_opening_at' => '2026-09-30 09:00:00'];
$session = (object) ['id' => 22, 'status' => 'signed', 'unlocked_at' => '2026-09-30 09:00:00', 'signed_at' => '2026-10-01 10:00:00'];
$bidders = [];
foreach (['Example Supplies LLC', 'Sample Industrial Services LLC', 'Example Trading LLC'] as $i => $name) {
    $bidders[] = (object) ['bid_id' => $i + 1, 'bid_status' => 'submitted', 'vendor_name' => $name,
        'total_amount' => 18500 + $i * 500, 'currency' => 'OMR'];
}
$signatures = [];
foreach (['chairman', 'secretary', 'itc_member'] as $i => $role) {
    $signatures[] = (object) ['tender_bid_opening_id' => 22, 'is_valid' => 1, 'role' => $role,
        'member_name' => 'Sample Committee Member ' . ($i + 1), 'signed_at' => null];
}
$pdf = App\Libraries\Tender_document_pdf::opening($tender, $session, $bidders, $signatures);
$assert($pdf->getNumPages() === 1, 'Normal bid opening record fits one page.');
$opening = $pdf->Output('', 'S');
$assert(str_starts_with($opening, '%PDF-'), 'Opening output is a PDF.');
foreach (['codes_generated', 'expired', 'cancelled', ''] as $status) {
    $bad = clone $session; $bad->status = $status;
    try { App\Libraries\Tender_document_pdf::opening($tender, $bad, $bidders, []); $assert(false, 'Sealed record exported.'); }
    catch (DomainException $e) { $assert(true, 'Sealed record refused.'); }
}
$vendor = (object) ['id' => 42, 'vendor_name' => 'Example Supplies LLC', 'address' => "P.O. Box 123, Duqm\nSultanate of Oman",
    'phone' => '96890000000', 'email' => 'sample@example.invalid', 'submitted_at' => '2026-09-29 23:30:00'];
$message = App\Libraries\Tender_document_pdf::regretMessage($tender, $vendor, '2026-10-04 10:00:00');
$assert(str_contains($message, '29 September 2026'), 'Submission date is not shifted to another day.');
$assert(str_contains($message, 'REGRET-PODC-SAMPLE-2026-001-V42'), 'Stable bidder-specific reference.');
$assert(str_contains($message, 'Buthaina Al Zadjali'), 'Supplied template signatory preserved.');
$assert(!str_contains($message, '{{'), 'No unfilled template placeholders.');
$letter = (object) ['subject' => 'Regret Letter - ' . $tender->reference, 'message' => $message];
$pdf = App\Libraries\Tender_document_pdf::regret($letter);
$assert($pdf->getNumPages() === 1, 'Normal regret letter fits one page.');
$regret = $pdf->Output('', 'S');
$assert(str_starts_with($regret, '%PDF-'), 'Regret output is a PDF.');
$many = [];
for ($i = 1; $i <= 65; $i++) {
    $row = clone $bidders[0]; $row->bid_id = $i; $row->vendor_name = 'Supplier ' . $i . ' - ' . str_repeat('Sample ', 5); $many[] = $row;
}
$draft = clone $bidders[0]; $draft->bid_status = 'draft'; $draft->vendor_name = 'DRAFT-NEVER-PRINT'; $many[] = $draft;
$pdf = App\Libraries\Tender_document_pdf::opening($tender, $session, $many, $signatures);
$assert($pdf->getNumPages() >= 4, 'Long roster flows onto continuation letterheads.');
$overflow = $pdf->Output('', 'S');
$assert(App\Libraries\Tender_document_pdf::filename('Regret', "../../bad\r\nfile") === 'Regret--bad-file.pdf', 'Safe download filename.');
$decodeSignature = new ReflectionMethod(App\Libraries\Tender_document_pdf::class, 'signaturePng');
$signaturePath = tempnam(sys_get_temp_dir(), 'pod-signature-test-');
try {
    file_put_contents($signaturePath, 'not a PNG');
    $assert($decodeSignature->invoke(null, $signaturePath) === null, 'Malformed signatures fall back to the recorded signer.');
    if (function_exists('imagecreatetruecolor')) {
        $image = imagecreatetruecolor(40, 12);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 3, 3, 35, 8, imagecolorallocate($image, 0, 0, 0));
        imagepng($image, $signaturePath); imagedestroy($image);
        $decoded = $decodeSignature->invoke(null, $signaturePath);
        $assert(is_string($decoded) && str_starts_with($decoded, "\x89PNG"), 'Valid signatures are decoded before embedding.');
        $corrupt = file_get_contents($signaturePath); $offset = strpos($corrupt, 'IDAT') + 4;
        $corrupt[$offset] = chr(ord($corrupt[$offset]) ^ 255);
        file_put_contents($signaturePath, $corrupt);
        $assert(is_array(getimagesize($signaturePath)) && $decodeSignature->invoke(null, $signaturePath) === null,
            'PNG with readable dimensions but a corrupt body cannot abort PDF output.');
    }
} finally {
    unlink($signaturePath);
}
$out = getenv('POD_TENDER_PDF_TEST_OUTPUT');
if ($out && is_dir($out)) {
    file_put_contents($out . '/bid-opening-sample.pdf', $opening);
    file_put_contents($out . '/regret-letter-sample.pdf', $regret);
    file_put_contents($out . '/bid-opening-overflow.pdf', $overflow);
}
echo "$checks tender document PDF checks passed.\n";
