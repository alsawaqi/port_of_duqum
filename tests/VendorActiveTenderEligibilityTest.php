<?php

$root = dirname(__DIR__);

require_once $root . "/app/Helpers/general_helper.php";

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

$assertFalse = function ($condition, string $message) use ($assertTrue): void {
    $assertTrue(!$condition, $message);
};

$assertContains = function (string $needle, string $haystack, string $message) use ($assertTrue): void {
    $assertTrue(strpos($haystack, $needle) !== false, $message);
};

$tendersModel = $read("app/Models/Tenders_model.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$tendersIndex = $read("app/Views/vendor_portal/tenders/index.php");

$assertTrue(vendor_can_access_tender_portal("new"), "newly registered vendors can open eligible active tenders");
$assertTrue(vendor_can_access_tender_portal("submitted"), "submitted vendors can still apply to active tenders while registration is under review");
$assertTrue(vendor_can_access_tender_portal("approved"), "approved vendors can apply to active tenders");
$assertFalse(vendor_can_access_tender_portal("rejected"), "rejected vendors cannot apply to tenders");
$assertFalse(vendor_can_access_tender_portal("suspended"), "blocked vendors cannot apply to tenders");

$assertContains("vendor_tender_allowed_statuses", $tendersModel . $vendorPortal . $read("app/Helpers/general_helper.php"), "tender access should use an explicit allowed vendor status list");
$assertContains("tender_target_vendors", $tendersModel, "eligibility should honor specific vendor targets");
$assertContains("target.vendor_group_id = vendor_profile.vendor_group_id", $tendersModel, "eligibility should match active tenders by vendor group");
$assertContains("target.vendor_grade_id = vendor_profile.vendor_grade_id", $tendersModel, "eligibility should match active tenders by vendor grade");
$assertContains("\$t.status = 'published'", $tendersModel, "vendor tender list should only expose active published tenders for application");
$assertContains("\$t.workflow_stage = 'bidding'", $tendersModel, "vendor tender list should only expose bidding-stage tenders for application");
$assertContains("\$t.closing_at > ?", $tendersModel, "expired tenders should not be available for vendor application");
$assertContains("specific_target_id", $tendersModel, "query should expose whether access came from a specific target");
$assertContains("eligibility_source", $tendersModel, "query should explain why the tender is visible");

$assertContains("Eligibility", $tendersIndex, "vendor tender table should show why the tender is available");
$assertContains("Only active tenders matching your vendor profile are shown.", $tendersIndex, "vendor tender tab should explain active eligibility");

echo "OK" . PHP_EOL;
