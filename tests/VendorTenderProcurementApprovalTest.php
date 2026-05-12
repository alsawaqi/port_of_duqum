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

$tendersModel = $read("app/Models/Tenders_model.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$tenderReports = $read("app/Controllers/Tender_reports.php");
$tenderReportDetails = $read("app/Views/tender_reports/details.php");
$approvalSql = $read("app/Database/SQL/tender_vendor_participation_approval_upgrade_pod.sql");

$assertContains("procurement_approved_for_submission", $tendersModel, "vendor tender query should expose whether procurement already approved bid submission");
$assertContains("'pending_approval'", $vendorPortal, "vendor portal should create a pending procurement approval request");
$assertContains("request_tender_approval", $vendorPortal, "vendor portal should let eligible vendors request procurement approval");
$assertContains("Procurement approval is required before submitting a bid.", $vendorPortal, "pending vendors should be blocked from bid submission");
$assertContains("_is_tender_procurement_approved", $vendorPortal, "bid submission should have a dedicated procurement approval gate");

$assertContains("Request Procurement Approval", $vendorDetails, "vendor tender page should show the approval request action");
$assertContains("pending procurement approval", strtolower($vendorDetails), "vendor tender page should show pending approval state");
$assertContains("procurement_approved_for_submission", $vendorDetails, "vendor tender page should receive the approval state");

$assertContains("approve_vendor_participation", $tenderReports, "procurement report should approve vendor participation requests");
$assertContains("reject_vendor_participation", $tenderReports, "procurement report should reject vendor participation requests");
$assertContains("pending_approval", $tenderReports, "procurement decisions should target pending approval requests");
$assertContains("Approve participation", $tenderReportDetails, "procurement report should expose approve action");
$assertContains("Reject participation", $tenderReportDetails, "procurement report should expose reject action");
$assertContains("tender-vendor-participation-action", $tenderReportDetails, "procurement report actions should submit approval decisions");
$assertContains("pending_approval", $approvalSql . $tendersModel, "database schema should support pending procurement approval status");
$assertContains("MODIFY COLUMN `invite_status`", $approvalSql . $tendersModel, "SQL/runtime schema should widen tender invite status values");

echo "OK" . PHP_EOL;
