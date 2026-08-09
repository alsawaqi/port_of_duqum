<?php

$root = dirname(__DIR__);
$google = (string) file_get_contents($root . '/app/Libraries/Google.php');
$helper = (string) file_get_contents($root . '/app/Helpers/app_files_helper.php');
$uploader = (string) file_get_contents($root . '/app/Controllers/Uploader.php');

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (strpos($source, $needle) === false) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $source, string $message) use ($fail): void {
    if (strpos($source, $needle) !== false) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$notContains("'type' => 'anyone'", $google, 'new Google Drive uploads must not receive public permissions');
$notContains('_make_file_as_public', $google, 'public permission helper must be removed');
$notContains('https://drive.google.com/uc?id=', $helper, 'raw links must not bypass the application');
$notContains('https://drive.google.com/thumbnail?id=', $helper, 'thumbnail links must not bypass the application');
$contains('PODC_FILE_STREAM_HMAC_KEY', $helper, 'stream capability uses a deployment secret');
$contains('hash_hmac', $helper, 'stream capability must authenticate its complete context');
$contains('hash_equals', $helper, 'stream capability comparison must be timing safe');
$contains("service('session')->get('user_id')", $helper, 'stream capability is bound to the current account');
$contains('verify_google_drive_stream_signature', $uploader, 'stream endpoint verifies the capability');
$contains("'Cache-Control', 'private, no-store'", $uploader, 'private Drive files must not be cached');
$contains("'X-Content-Type-Options', 'nosniff'", $uploader, 'streamed Drive content must not be MIME sniffed');

echo 'Google Drive private-file security checks passed.' . PHP_EOL;
