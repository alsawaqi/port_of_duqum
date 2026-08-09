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

$model = $read("app/Models/Tenders_model.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$vendorModal = $read("app/Views/vendor_portal/tenders/view_modal.php");
$upgradeSql = $read("app/Database/SQL/tender_fee_payment_bypass_upgrade_pod.sql");
$schemaMigration = $read("app/Database/Migrations/2026_08_03_150000_runtime_schema_ownership_hardening.php");

$assertContains("ensure_tender_fee_payments_table", $model, "tenders model should verify the tender fee payment schema");
$assertContains('Runtime_schema_guard::requireTablesAndColumns', $model, "runtime schema checks should be read-only");
$assertNotContains('CREATE TABLE IF NOT EXISTS `$fee_payments`', $model, "runtime requests must not create tender fee payment tables");
$assertContains('tender_fee_payments', $schemaMigration, "deployment migration should own the tender fee payments table");
$assertContains("CREATE TABLE IF NOT EXISTS `pod_tender_fee_payments`", $upgradeSql, "SQL upgrade should create tender fee payments table");
$assertContains("fee_payment_status", $model, "vendor tender query should expose fee payment status");
$assertContains("fee_paid_at", $model, "vendor tender query should expose fee paid timestamp");

$assertContains("pay_tender_fee", $vendorPortal, "vendor portal should expose the tender fee checkout action");
$assertContains("Eservice_payment_manager::TENDER_FEE", $vendorPortal, "tender fee must use the verified payment manager");
$assertNotContains("BYPASS", $vendorPortal, "vendor portal must not self-record a payment bypass");
$assertNotContains("status='paid'", $vendorPortal, "vendor portal must not directly settle a payment");
$assertContains("_is_tender_fee_paid", $vendorPortal, "bid submission should check tender fee payment");
$assertContains("Tender fee payment is required before submitting a bid.", $vendorPortal, "bid save should block unpaid tender fees");
$assertContains("Tender fee payment is required before requesting procurement approval.", $vendorPortal, "approval request should block unpaid tender fees");

$assertContains("Pay Tender Fee", $vendorDetails, "vendor details should show the secure checkout button");
$assertContains("vendor-tender-fee-payment-form", $vendorDetails, "vendor details should start a secure checkout");
$assertContains("result.checkout_url", $vendorDetails, "the browser should redirect to the hosted payment provider");
$assertNotContains("Temporary payment bypass", $vendorDetails, "the UI must not advertise or use a bypass");
$assertContains("Fee Payment Status", $vendorModal, "vendor quick view should show payment status");

echo "OK" . PHP_EOL;
