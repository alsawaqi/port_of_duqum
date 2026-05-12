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

$modelPath = $root . "/app/Models/Tender_bid_item_prices_model.php";
$assertTrue(is_file($modelPath), "tender bid item prices model should exist");

$model = file_get_contents($modelPath);
$vendorController = $read("app/Controllers/Vendor_portal.php");
$commercialController = $read("app/Controllers/Tender_commercial_inbox.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$commercialModal = $read("app/Views/tender_commercial_inbox/bid_modal_form.php");
$sql = $read("app/Database/SQL/tender_bid_item_prices_upgrade_pod.sql");

$assertContains("tender_bid_item_prices", $model, "model should use the tender bid item prices table");
$assertContains("get_price_map", $model, "model should expose saved prices for vendor edit forms");
$assertContains("sync_bid_item_prices", $model, "model should sync submitted RFQ item prices");
$assertContains("get_bid_item_prices", $model, "model should expose detailed item pricing for commercial review");

$assertContains("CREATE TABLE IF NOT EXISTS `pod_tender_bid_item_prices`", $sql, "SQL upgrade should create the item prices table");

$assertContains("Tender_bid_item_prices_model", $vendorController, "vendor portal should load the item pricing model");
$assertContains("sync_bid_item_prices", $vendorController, "vendor bid save should persist RFQ item prices");
$assertContains('"bid_item_price_map"', $vendorController, "vendor tender page should receive saved item prices");
$assertContains('"bid_item_price_rows"', $vendorController, "vendor tender page should receive item price rows");

$assertContains('name="rfq_item_unit_price[', $vendorDetails, "vendor tender details should let vendors enter unit price per RFQ item");
$assertContains("bid-item-pricing-total", $vendorDetails, "vendor tender details should show calculated total");
$assertContains("readonly", $vendorDetails, "vendor total amount should be calculated from RFQ item prices");

$assertContains("Tender_bid_item_prices_model", $commercialController, "commercial inbox should load item pricing model");
$assertContains('"bid_item_prices"', $commercialController, "commercial bid modal should receive item price breakdown");
$assertContains("Item Price Breakdown", $commercialModal, "commercial users should see item price details");
$assertContains("vendor_unit_price", $commercialModal, "commercial item breakdown should include vendor unit prices");
$assertContains("line_total", $commercialModal, "commercial item breakdown should include line totals");

echo "OK" . PHP_EOL;
