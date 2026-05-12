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

$reportsController = $read("app/Controllers/Tender_reports.php");
$reportsView = $read("app/Views/tender_reports/details.php");

$assertContains("technical_proposals_reviewed", $reportsController, "procurement must confirm technical proposal review before releasing evaluation");
$assertContains("commercial_proposals_reviewed", $reportsController, "procurement must confirm commercial proposal review before releasing evaluation");
$assertContains("procurement_proposal_review", $reportsController, "procurement proposal review should be recorded in workflow history");
$assertContains("_record_procurement_proposal_review", $reportsController, "controller should record the proposal review gate");
$assertContains("_is_bid_opening_completed", $reportsController, "procurement document access should open after completed bid opening");

$assertContains("Procurement Proposal Review", $reportsView, "report should show the procurement review gate");
$assertContains("Confirm technical proposals reviewed", $reportsView, "review form should include technical confirmation");
$assertContains("Confirm commercial proposals reviewed", $reportsView, "review form should include commercial confirmation");
$assertContains("Confirm Review & Send to Technical Evaluation", $reportsView, "release button should describe the review gate");
$assertContains("proposal-review-documents", $reportsView, "review gate should expose proposal document checklist");

echo "OK" . PHP_EOL;
