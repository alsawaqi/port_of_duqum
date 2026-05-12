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
$procurementController = $read("app/Controllers/Tender_procurement_inbox.php");
$managerController = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$procurementForm = $read("app/Views/tender_procurement_inbox/form.php");
$procurementModal = $read("app/Views/tender_procurement_inbox/modal_form.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$vendorModal = $read("app/Views/vendor_portal/tenders/view_modal.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$reportDetails = $read("app/Views/tender_reports/details.php");
$upgradeSql = $read("app/Database/SQL/tender_fees_upgrade_pod.sql");
$baseSql = $read("app/Database/SQL/tender.sql");

$assertContains("ensure_tender_fee_column", $model, "tenders model should ensure tender fee schema");
$assertContains("ADD COLUMN `tender_fee`", $model . $upgradeSql . $baseSql, "SQL/runtime schema should add tender_fee to tenders");
$assertContains("getPost(\"tender_fee\")", $procurementController, "procurement save should read tender fee from the form");
$assertContains('"tender_fee" => $tender_fee', $procurementController, "procurement save should persist tender fee");
$assertContains("Tender fee cannot be negative", $procurementController, "procurement save should validate negative tender fees");
$assertContains('"tender_fee" => $source->tender_fee', $procurementController, "retender should carry the source tender fee into the draft");
$assertContains("\"tender_fee\" => \"Tender Fees\"", $managerController, "manager approval diff should label tender fees");

$assertContains("\"name\" => \"tender_fee\"", $procurementForm, "full procurement form should include tender fee input");
$assertContains("data-preview=\"tender_fee\"", $procurementForm, "full procurement preview should show tender fee");
$assertContains("\"name\" => \"tender_fee\"", $procurementModal, "procurement modal form should include tender fee input");

$assertContains("Tender Fees", $vendorDetails, "vendor tender details should show tender fee");
$assertContains("Tender Fees", $vendorModal, "vendor quick view should show tender fee");
$assertContains("COALESCE(t.tender_fee, req.tender_fee) AS tender_fee", $reportsController, "tender report should prefer specified tender fee and fall back to request fee");
$assertContains("Tender Fees", $reportDetails, "tender report should display tender fee");

echo "OK" . PHP_EOL;
