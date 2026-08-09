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
    "app/Views/tender_procurement_manager_inbox/details.php",
    "app/Views/tender_procurement_inbox/form.php",
    "app/Views/vendor_portal/tenders/details.php",
    "app/Views/vendor_portal/tenders/view_modal.php",
    "app/Views/tender_reports/details.php",
];

foreach ($files as $file) {
    $content = $read($file);
    $assertContains("Tender Details", $content, "$file should use the Tender Details heading");
    $assertContains("PR No (Optional)", $content, "$file should identify PR No as optional");
    $assertContains("Estimated Material/Service Required On", $content, "$file should use the approved material/service date label");
    $assertNotContains("Reference Number", $content, "$file should not show the removed Reference Number field");
    $assertNotContains("Request Date", $content, "$file should not show the removed Request Date field");
    $assertNotContains("RFQ / RFP Details", $content, "$file should not show the old RFQ/RFP Details heading");
    $assertNotContains("RFQ Details", $content, "$file should not show the old RFQ Details heading");
    $assertNotContains("RFQ / RFP Header", $content, "$file should not show the old RFQ/RFP Header heading");
}

$procurementForm = $read("app/Views/tender_procurement_inbox/form.php");
$assertNotContains('name="rfq_no"', $procurementForm, "procurement form should not post the removed reference number");
$assertNotContains('name="rfq_date"', $procurementForm, "procurement form should not post the removed request date");

echo "OK" . PHP_EOL;
