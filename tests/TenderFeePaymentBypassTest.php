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

$model = $read("app/Models/Tenders_model.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$vendorModal = $read("app/Views/vendor_portal/tenders/view_modal.php");
$upgradeSql = $read("app/Database/SQL/tender_fee_payment_bypass_upgrade_pod.sql");

$assertContains("ensure_tender_fee_payments_table", $model, "tenders model should ensure the temporary tender fee payment table");
$assertContains('CREATE TABLE IF NOT EXISTS `$fee_payments`', $model, "runtime schema should create tender fee payments table");
$assertContains("CREATE TABLE IF NOT EXISTS `pod_tender_fee_payments`", $upgradeSql, "SQL upgrade should create tender fee payments table");
$assertContains("fee_payment_status", $model, "vendor tender query should expose fee payment status");
$assertContains("fee_paid_at", $model, "vendor tender query should expose fee paid timestamp");

$assertContains("pay_tender_fee", $vendorPortal, "vendor portal should expose temporary tender fee pay action");
$assertContains("BYPASS", $vendorPortal, "temporary payment should use a bypass reference");
$assertContains("_is_tender_fee_paid", $vendorPortal, "bid submission should check tender fee payment");
$assertContains("Tender fee payment is required before submitting a bid.", $vendorPortal, "bid save should block unpaid tender fees");
$assertContains("Tender fee payment is required before requesting procurement approval.", $vendorPortal, "approval request should block unpaid tender fees");

$assertContains("Pay Tender Fee", $vendorDetails, "vendor details should show the temporary pay button");
$assertContains("vendor-tender-fee-payment-form", $vendorDetails, "vendor details should submit the temporary payment form");
$assertContains("Fee Payment Status", $vendorModal, "vendor quick view should show payment status");

echo "OK" . PHP_EOL;
