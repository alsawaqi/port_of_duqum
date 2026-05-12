<?php

$root = dirname(__DIR__);

$controller = file_get_contents($root . "/app/Controllers/Vendor_portal.php");
$portalView = file_get_contents($root . "/app/Views/vendor_portal/view.php");
$index = file_get_contents($root . "/app/Views/vendor_portal/tenders/index.php");
$detailsPath = $root . "/app/Views/vendor_portal/tenders/details.php";
$details = is_file($detailsPath) ? file_get_contents($detailsPath) : "";

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

$assertContains("function tender(", $controller, "vendor portal should expose a full tender detail page");
$assertContains("_get_vendor_tender_view_data", $controller, "tender page and legacy modal should share one data loader");
$assertContains("_mark_tender_invite_opened", $controller, "opening a tender page should mark vendor invite as opened");
$assertContains('get_uri("vendor_portal/tender/"', $controller, "tender table row action should link to the full detail page");
$assertNotContains('get_uri("vendor_portal/tender_view_modal")', $controller, "tender table row action should no longer open the details modal");

$assertContains("vp-tenders", $index, "tender tab should use the polished vendor portal tab shell");
$assertContains("vpt-shell", $index, "tender tab should match the other shell-style tabs");
$assertContains("vpt-table-wrap", $index, "tender tab should wrap the table like the other tabs");

$assertTrue(is_file($detailsPath), "full tender detail view should exist");
$assertContains("vendor-tender-detail-page", $details, "detail page should have its own professional page layout");
$assertContains("Tender Documents", $details, "detail page should show tender documents");
$assertContains("RFQ / RFP Details", $details, "detail page should show RFQ/RFP details");
$assertContains("Bid Submission", $details, "detail page should include inline bid submission");
$assertContains("form_open_multipart(get_uri(\"vendor_portal/save_bid\")", $details, "detail page should submit bids through the existing upload endpoint");
$assertContains("vendor-clarification-form", $details, "detail page should keep clarification workflow available");
$assertContains("#vp-tenders", $details, "detail page back link should return vendors to the tenders tab");
$assertNotContains("modal-footer", $details, "detail page should not be a modal body/footer view");
$assertContains("window.location.hash", $portalView, "vendor portal should honor tab hashes on load");

echo "OK" . PHP_EOL;
