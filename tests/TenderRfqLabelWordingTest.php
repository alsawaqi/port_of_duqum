<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$assertNotContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$files = [
    "app/Views/tender_procurement_inbox/form.php",
    "app/Views/vendor_portal/tenders/details.php",
    "app/Views/vendor_portal/tenders/view_modal.php",
    "app/Views/tender_reports/details.php",
];

foreach ($files as $file) {
    $content = $read($file);
    $assertContains("Reference Number", $content, "$file should show Reference Number instead of RFQ No");
    $assertContains("Request Date", $content, "$file should show Request Date instead of RFQ Date");
    $assertNotContains("RFQ No", $content, "$file should not show the old RFQ No label");
    $assertNotContains("RFQ Date", $content, "$file should not show the old RFQ Date label");
}

echo "OK" . PHP_EOL;
