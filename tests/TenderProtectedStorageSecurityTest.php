<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $path) use ($root, $fail): string {
    $absolute = $root . '/' . $path;
    if (!is_file($absolute)) {
        $fail($path . ' must exist');
    }
    return (string) file_get_contents($absolute);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . ' Missing: ' . $needle);
    }
};
$notContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . ' Unexpected: ' . $needle);
    }
};

$controllers = [
    'Vendor_portal.php',
    'Tender_commercial_inbox.php',
    'Tender_technical_inbox.php',
    'Tender_procurement_inbox.php',
    'Tender_procurement_manager_inbox.php',
    'Tender_reports.php',
];
foreach ($controllers as $name) {
    $source = $read('app/Controllers/' . $name);
    $notContains('files/tender_files', $source, $name . ' has no public legacy-file fallback');
    $contains('resolveStoredFile', $source, $name . ' resolves documents through protected storage');
}

$rootHtaccess = $read('.htaccess');
$legacyHtaccess = $read('files/tender_files/.htaccess');
$contains('RewriteRule ^files/(?:temp|tender_files)', $rootHtaccess, 'legacy public tender paths are blocked at the root');
$contains('Require all denied', $legacyHtaccess, 'legacy tender directory independently denies access');

$migration = $read('app/Database/Migrations/2026_08_03_140000_migrate_legacy_tender_documents.php');
$contains("path LIKE 'files/tender_files/%'", $migration, 'migration discovers legacy document rows');
$contains('realpath(ROOTPATH', $migration, 'migration canonicalizes legacy sources');
$contains('str_starts_with($this->canonicalPath($source), $legacyPrefix)', $migration, 'migration enforces root containment');
$contains('FILEINFO_MIME_TYPE', $migration, 'migration checks file magic');
$contains('pdfHasEof', $migration, 'migration validates complete PDF payloads');
$contains('random_bytes(24)', $migration, 'migration assigns unpredictable stored names');
$contains('hash_equals($sourceHash, $targetHash)', $migration, 'migration verifies copied content');
$contains('"path" => "tender_documents/', $migration, 'database rows move to protected relative paths');
$contains('transBegin()', $migration, 'metadata updates are transactional');
$contains('transRollback()', $migration, 'failed migrations roll back');
$contains('@unlink($source)', $migration, 'legacy public sources are removed only after commit');

echo 'Tender protected-storage contracts passed.' . PHP_EOL;
