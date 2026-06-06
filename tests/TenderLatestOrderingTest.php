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

$assertAtLeast = function (int $expected, int $actual, string $message) use ($assertTrue): void {
    $assertTrue($actual >= $expected, $message . " Expected at least {$expected}, found {$actual}.");
};

$procurementInbox = $read("app/Controllers/Tender_procurement_inbox.php");
$managerInbox = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$requestsModel = $read("app/Models/Tender_requests_model.php");
$bidsModel = $read("app/Models/Tender_bids_model.php");
$tendersModel = $read("app/Models/Tenders_model.php");
$latestViewPaths = [
    "app/Views/tender_procurement_inbox/index.php",
    "app/Views/tender_procurement_manager_inbox/index.php",
    "app/Views/tender_department_manager_inbox/index.php",
    "app/Views/tender_finance_inbox/index.php",
    "app/Views/tender_committee_inbox/index.php",
    "app/Views/tender_committee_opening_inbox/index.php",
    "app/Views/tender_technical_inbox/index.php",
    "app/Views/tender_commercial_inbox/index.php",
    "app/Views/tender_reports/index.php",
    "app/Views/tender_requests/index.php",
    "app/Views/vendor_portal/tenders/index.php",
];

$assertContains("ORDER BY created_at DESC, tender_id DESC, tender_request_id DESC", $procurementInbox, "procurement tender inbox should list latest tenders first");
$assertContains('ORDER BY COALESCE($t.created_at, $t.procurement_manager_submitted_at) DESC, $t.id DESC', $managerInbox, "procurement manager inbox should list latest tenders first");
$assertContains('ORDER BY COALESCE($req.created_at, $req.request_date) DESC, $req.id DESC', $requestsModel, "department manager and committee request inboxes should list latest requests first");
$assertContains("ORDER BY COALESCE(t.created_at, t.published_at) DESC, t.id DESC", $reportsController, "tender register/report list should list latest tenders first");
$assertContains('COALESCE($t.created_at, $t.published_at, $t.closing_at) DESC', $tendersModel, "vendor tender list should list latest tenders first");
$assertAtLeast(
    3,
    substr_count($bidsModel, 'COALESCE($t.created_at, $t.published_at, $t.closing_at) DESC'),
    "technical, commercial, and committee opening inboxes should list latest tenders first"
);

foreach ($latestViewPaths as $path) {
    $view = $read($path);
    $assertContains("order: []", $view, "$path should preserve backend latest-first ordering instead of sorting by reference");
    $assertContains("stateSave: false", $view, "$path should not restore an old saved DataTable sort");
}

echo "OK" . PHP_EOL;
