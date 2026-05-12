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

$sql = $read("app/Database/SQL/tender_clarification_scope_attachments_upgrade_pod.sql");
$model = $read("app/Models/Tender_communications_model.php");
$vendorController = $read("app/Controllers/Vendor_portal.php");
$clarificationController = $read("app/Controllers/Tender_clarifications.php");
$vendorDetails = $read("app/Views/vendor_portal/tenders/details.php");
$vendorModal = $read("app/Views/vendor_portal/tenders/view_modal.php");
$adminVendorView = $read("app/Views/tender_clarifications/vendor.php");
$technicalDetails = $read("app/Views/tender_technical_inbox/details.php");
$commercialDetails = $read("app/Views/tender_commercial_inbox/details.php");

$assertContains("clarification_scope", $sql, "SQL should add clarification scope to tender communications");
$assertContains("pod_tender_communication_attachments", $sql, "SQL should create tender communication attachments table");
$assertContains("ensure_clarification_scope_attachment_schema", $model, "model should ensure clarification scope and attachment schema");
$assertContains("save_attachments", $model, "model should save communication attachments");
$assertContains("get_attachments_map", $model, "model should load attachments grouped by communication");
$assertContains("clarification_scope", $vendorController, "vendor clarification save should store selected clarification scope");
$assertContains("_save_clarification_files", $vendorController, "vendor clarification save should persist uploaded files");
$assertContains("download_clarification_attachment", $vendorController, "vendor portal should allow secure clarification attachment downloads");
$assertContains("clarification_scope", $clarificationController, "procurement clarification replies should retain/display scope");
$assertContains("_save_clarification_files", $clarificationController, "procurement reply save should persist uploaded files");
$assertContains("download_attachment", $clarificationController, "staff clarification view should allow attachment downloads");
$assertContains("form_open_multipart", $vendorDetails, "vendor detail clarification form should support file uploads");
$assertContains("clarification_scope", $vendorDetails, "vendor detail clarification form should include scope selector");
$assertContains("name=\"clarification_files[]\"", $vendorDetails, "vendor detail clarification form should allow multiple files");
$assertContains("clarification_scope", $vendorModal, "vendor modal clarification form should include scope selector");
$assertContains("name=\"clarification_files[]\"", $adminVendorView, "procurement reply form should allow multiple files");
$assertContains("clarification-attachments", $adminVendorView, "procurement chat should render attachment links");
$assertContains("technical-general-clarification-form", $technicalDetails, "technical users should have an internal procurement clarification entry point from tender details");
$assertContains("commercial-general-clarification-form", $commercialDetails, "commercial users should have an internal procurement clarification entry point from tender details");

echo "OK" . PHP_EOL;
