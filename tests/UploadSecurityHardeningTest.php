<?php

require_once __DIR__ . '/../app/Libraries/Upload_security.php';

use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};
$expectReject = static function (callable $callback, string $message) use ($fail): void {
    try {
        $callback();
    } catch (UploadSecurityException $e) {
        return;
    }
    $fail($message);
};

$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pod_upload_security_' . bin2hex(random_bytes(8));
mkdir($dir, 0700, true);
$writable = $dir . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR;
mkdir($writable, 0700, true);
if (!defined('WRITEPATH')) {
    define('WRITEPATH', $writable);
}
$png = $dir . DIRECTORY_SEPARATOR . 'sample.png';
$pdf = $dir . DIRECTORY_SEPARATOR . 'sample.pdf';
$fakePdf = $dir . DIRECTORY_SEPARATOR . 'fake.pdf';
$trailingPng = $dir . DIRECTORY_SEPARATOR . 'trailing.png';

file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
file_put_contents($pdf, "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n");
file_put_contents($fakePdf, "<?php echo 'not a pdf';");
file_put_contents($trailingPng, file_get_contents($png) . "<?php echo 'trailing';");

$security = new Upload_security(static fn() => true, true);
$pngMeta = $security->validatePath($png, 'evidence.png', filesize($png), Upload_security::CONTEXT_SECURITY_DOCUMENT);
$pdfMeta = $security->validatePath($pdf, 'evidence.pdf', filesize($pdf), Upload_security::CONTEXT_SECURITY_DOCUMENT);

$assert($pngMeta['detected_mime'] === 'image/png', 'PNG MIME is detected server-side');
$assert($pdfMeta['detected_mime'] === 'application/pdf', 'PDF MIME is detected server-side');
$assert(
    $security->allowedExtensions(Upload_security::CONTEXT_SECURITY_DOCUMENT) === ['jpg', 'jpeg', 'png', 'pdf'],
    'security-document policy is the checklist allowlist'
);
$expectReject(
    fn() => $security->validatePath($fakePdf, 'fake.pdf', filesize($fakePdf), Upload_security::CONTEXT_SECURITY_DOCUMENT),
    'extension/MIME/magic mismatch must be rejected'
);
$expectReject(
    fn() => $security->validateClientClaim('../evidence.pdf', 100, Upload_security::CONTEXT_SECURITY_DOCUMENT),
    'client path traversal must be rejected'
);
$expectReject(
    fn() => $security->validateClientClaim('payload.php.jpg', 100, Upload_security::CONTEXT_SECURITY_DOCUMENT),
    'embedded executable extensions must be rejected'
);
$expectReject(
    fn() => (new Upload_security(static fn() => false, true))->validatePath($png, 'evidence.png', filesize($png), Upload_security::CONTEXT_IMAGE),
    'malware-scanner rejection must fail closed'
);
$expectReject(
    fn() => (new Upload_security(null, true))->validatePath($png, 'evidence.png', filesize($png), Upload_security::CONTEXT_IMAGE),
    'missing scanner must fail closed when configured'
);
$assert(
    $security->allowedExtensions(Upload_security::CONTEXT_SIGNATURE_IMAGE) === ['png'],
    'drawn signatures are PNG-only'
);
$assert(
    $security->maximumBytes(Upload_security::CONTEXT_SIGNATURE_IMAGE) === 1024 * 1024,
    'drawn signatures are capped at one MiB'
);
$expectReject(
    fn() => $security->validatePath($trailingPng, 'signature.png', filesize($trailingPng), Upload_security::CONTEXT_SIGNATURE_IMAGE),
    'signature PNGs with trailing payloads must be rejected'
);
$expectReject(
    fn() => $security->validateClientClaim('signature.jpg', 100, Upload_security::CONTEXT_SIGNATURE_IMAGE),
    'non-PNG signature claims must be rejected'
);
$rawPdfMeta = $security->validateUntrustedBytes(
    (string) file_get_contents($pdf),
    'mail-attachment.pdf',
    Upload_security::CONTEXT_SECURITY_DOCUMENT
);
$assert($rawPdfMeta['detected_mime'] === 'application/pdf', 'raw attachment bytes receive full MIME and magic validation');
$expectReject(
    fn() => $security->validateUntrustedBytes(
        (string) file_get_contents($fakePdf),
        'mail-attachment.pdf',
        Upload_security::CONTEXT_SECURITY_DOCUMENT
    ),
    'raw attachment bytes cannot bypass extension, MIME, and magic validation'
);

$signatureDirectory = WRITEPATH . 'uploads/tender_opening_signatures/opening_99';
$storedSignature = $security->storeUntrustedBytes(
    (string) file_get_contents($png),
    'signature.png',
    $signatureDirectory,
    Upload_security::CONTEXT_SIGNATURE_IMAGE,
    'signature_'
);
$assert(
    preg_match('/^signature_[a-f0-9]{48}\.png$/', $storedSignature['stored_name']) === 1,
    'generated signature names use a CSPRNG token'
);
$signatureRelative = 'tender_opening_signatures/opening_99/' . $storedSignature['stored_name'];
$assert(
    $security->resolveStoredFile($signatureRelative, 'tender_opening_signatures/opening_99') === realpath($storedSignature['path']),
    'protected file resolver accepts contained signature files'
);
$assert(
    $security->resolveStoredFile('tender_opening_signatures/opening_99/../outside.png', 'tender_opening_signatures/opening_99') === null,
    'protected file resolver rejects traversal'
);

$uploader = file_get_contents(__DIR__ . '/../app/Controllers/Uploader.php');
$helper = file_get_contents(__DIR__ . '/../app/Helpers/app_files_helper.php');
$tender = file_get_contents(__DIR__ . '/../app/Controllers/Tender_procurement_inbox.php');
$vendor = file_get_contents(__DIR__ . '/../app/Controllers/Vendor_portal.php');
$reports = file_get_contents(__DIR__ . '/../app/Controllers/Tender_reports.php');
$committee = file_get_contents(__DIR__ . '/../app/Controllers/Tender_committee_opening_inbox.php');
$committeeDetails = file_get_contents(__DIR__ . '/../app/Views/tender_committee_opening_inbox/details.php');
$openingForm = file_get_contents(__DIR__ . '/../app/Views/tender_reports/bid_opening_form.php');
$assert(str_contains($uploader, 'extends Security_Controller'), 'generic uploader requires authentication');
$assert(str_contains($uploader, "service('throttler')"), 'generic uploader is rate limited');
$assert(str_contains($uploader, 'verify_secure_upload_context_token'), 'claimed upload contexts require a signature');
$assert(str_contains($helper, "WRITEPATH . 'uploads/secure_temp/user_'"), 'temporary uploads are protected and randomized');
$assert(str_contains($helper, 'validateUntrustedBytes('), 'mail/API byte payloads receive centralized content and malware inspection');
$assert(str_contains($tender, "WRITEPATH . 'uploads/tender_documents/tender_'"), 'new tender documents are not public');
$assert(str_contains($vendor, "resolveStoredFile(\$relative, 'tender_documents')"), 'vendor serving enforces protected tender-path containment');
$assert(str_contains($reports, 'storeUploadedFile('), 'tender report uploads use centralized storage');
$assert(str_contains($reports, 'Upload_security::CONTEXT_SECURITY_DOCUMENT'), 'manual opening forms use the strict document policy');
$assert(str_contains($reports, 'resolveStoredFile('), 'tender report downloads enforce protected path containment');
$assert(!str_contains($reports, '$file->move('), 'tender reports contain no direct UploadedFile move');
$assert(str_contains($committee, 'storeUntrustedBytes('), 'committee signature bytes use centralized storage');
$assert(str_contains($committee, 'Upload_security::CONTEXT_SIGNATURE_IMAGE'), 'committee signatures use the PNG-only signature policy');
$assert(!str_contains($committee, 'file_put_contents($upload_dir'), 'committee signatures contain no direct decoded-byte write');
$assert(str_contains($committeeDetails, 'tender_committee_opening_inbox/signature_image/'), 'committee signature rendering uses an authorized controller route');
$assert(str_contains($openingForm, '$signature_image_route'), 'shared opening form renders signatures through an authorized route');

foreach ([$png, $pdf, $fakePdf, $trailingPng, $storedSignature['path']] as $path) {
    @unlink($path);
}
@rmdir($signatureDirectory);
@rmdir(dirname($signatureDirectory));
@rmdir(dirname(dirname($signatureDirectory)));
@rmdir(WRITEPATH . 'uploads');
@rmdir(rtrim(WRITEPATH, DIRECTORY_SEPARATOR));
@rmdir($dir);

echo 'Upload security hardening checks passed.' . PHP_EOL;
