<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertTrue = function ($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$assertContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$assertNotContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) === false, $message);
};

$files = [
    "app/Controllers/Tender_reports.php",
    "app/Controllers/Tender_committee_opening_inbox.php",
    "app/Controllers/Tender_procurement_inbox.php",
    "app/Views/tender_reports/details.php",
    "app/Views/tender_reports/index.php",
    "app/Views/tender_reports/bid_opening_form.php",
    "app/Views/tender_committee_opening_inbox/index.php",
    "app/Views/tender_committee_opening_inbox/modal_form.php",
    "app/Views/pod_reports/index.php",
    "app/Views/roles/permissions.php",
    "app/Libraries/Tender_testing_stage.php",
];

$combined = "";
foreach ($files as $file) {
    $combined .= "\n/* $file */\n" . $read($file);
}

$assertContains("\"technical_3key\" => \"Bid Opening\"", $combined, "technical_3key stage should display as Bid Opening");
$assertContains("{id: \"technical_3key\", text: \"Bid Opening\"}", $combined, "tender report filter should display Bid Opening");
$assertContains("<h1>Bid Opening</h1>", $combined, "committee opening inbox should display Bid Opening");
$assertContains("Allow Bid Opening (Committee)", $combined, "role permission label should display Bid Opening");
$assertNotContains("Technical Bid Opening", $combined, "old technical bid opening wording should not remain");
$assertNotContains("3-Key Bid Opening", $combined, "stage display should not show 3-Key Bid Opening");
$assertNotContains("3-Key Technical Opening", $combined, "stage display should not show 3-Key Technical Opening");

echo "OK" . PHP_EOL;
