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

$assertNotContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$sql = $read("app/Database/SQL/tender_technical_evaluation_findings_upgrade_pod.sql");
$communicationsModel = $read("app/Models/Tender_communications_model.php");
$commercialController = $read("app/Controllers/Tender_commercial_inbox.php");
$technicalController = $read("app/Controllers/Tender_technical_inbox.php");
$commercialModal = $read("app/Views/tender_commercial_inbox/bid_modal_form.php");
$commercialDetails = $read("app/Views/tender_commercial_inbox/details.php");
$technicalModal = $read("app/Views/tender_technical_inbox/bid_modal_form.php");
$technicalDetails = $read("app/Views/tender_technical_inbox/details.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$reportsView = $read("app/Views/tender_reports/details.php");
$clarificationController = $read("app/Controllers/Tender_clarifications.php");
$procurementChat = $read("app/Views/tender_clarifications/vendor.php");
$vendorController = $read("app/Controllers/Vendor_portal.php");

$assertContains("tender_bid_id", $sql, "SQL should add tender bid linkage for evaluator clarification requests");
$assertContains("internal_audience", $sql, "SQL should add internal audience for evaluator-procurement messages");
$assertContains("MODIFY COLUMN `type` VARCHAR(50)", $sql, "SQL should allow internal clarification request and response type values");

$assertContains("commercial_clarification_request", $communicationsModel, "communication roots should include commercial evaluator requests");
$assertContains("_ensure_type_column_accepts_internal_values", $communicationsModel, "communication model should repair old enum-limited internal type values");
$assertContains("get_internal_conversation", $communicationsModel, "model should load evaluator-procurement internal conversation only");
$assertContains("has_vendor_visible_evaluator_clarification_request", $communicationsModel, "vendor late replies should be allowed for any evaluator request");
$assertContains("get_latest_vendor_visible_evaluator_request", $communicationsModel, "vendor replies should attach to the evaluator request thread after procurement asks");

$assertContains("Tender_evaluation_attachments_model", $commercialController, "commercial inbox should load findings attachment model");
$assertContains("_save_commercial_finding_files", $commercialController, "commercial inbox should save findings files");
$assertContains("getFileMultiple(\"commercial_finding_files\")", $commercialController, "commercial inbox should accept multiple findings files");
$assertContains("commercial_clarification_request", $commercialController, "commercial inbox should log commercial clarification requests");
$assertContains("get_internal_conversation", $commercialController, "commercial modal should load only internal procurement conversation");
$assertContains("download_finding_document", $commercialController, "commercial inbox should allow findings downloads");
$assertContains("download_clarification_attachment", $commercialController, "commercial inbox should allow internal clarification attachment downloads");

$assertContains("get_internal_conversation", $technicalController, "technical modal should load only internal procurement conversation");
$assertContains("download_clarification_attachment", $technicalController, "technical inbox should allow internal clarification attachment downloads");

$assertContains("form_open_multipart", $commercialModal, "commercial evaluation form should support file uploads");
$assertContains("name=\"commercial_finding_files[]\"", $commercialModal, "commercial evaluation form should upload multiple findings documents");
$assertContains("Commercial Findings Documents", $commercialModal, "commercial modal should show findings document section");
$assertContains("commercial-clarification-request-form", $commercialModal, "commercial modal should include an internal procurement clarification form");
$assertContains("internal-clarification-thread", $commercialModal, "commercial modal should show the internal conversation thread");
$assertContains("internal-clarification-thread", $technicalModal, "technical modal should show the internal conversation thread");

$assertNotContains("tender_clarifications/tender", $commercialDetails, "commercial users should not link into procurement/vendor clarification chat");
$assertNotContains("tender_clarifications/tender", $technicalDetails, "technical users should not link into procurement/vendor clarification chat");
$assertContains("commercial-general-clarification-form", $commercialDetails, "commercial details should allow broad procurement messages");
$assertContains("technical-general-clarification-form", $technicalDetails, "technical details should allow broad procurement messages");

$assertContains("commercial_evaluation_attachments", $reportsController, "procurement reports should pass commercial findings attachments");
$assertContains("can_reply_clarifications", $reportsController, "procurement reports should expose clarification reply permission");
$assertContains("Commercial Findings", $reportsView, "procurement report should label commercial findings");
$assertContains("internal-clarification-reply-form", $reportsView, "procurement report should allow inline replies to evaluator requests");
$assertContains("tender_clarifications/save_reply", $reportsView, "procurement report replies should use the existing clarification reply endpoint");
$assertContains("commercial_clarification_request", $reportsView, "procurement report should detect commercial evaluator requests");
$assertContains("technical_clarification_request", $reportsView, "procurement report should detect technical evaluator requests");

$assertContains("\"commercial\" => \"Forward internally to commercial team\"", $clarificationController, "procurement should forward vendor answers internally to commercial");
$assertContains("commercial_clarification_request", $clarificationController, "procurement chat should include commercial evaluator requests");
$assertContains("Forward internally to commercial team", $procurementChat, "procurement chat view should expose commercial forwarding");

$assertContains("has_vendor_visible_evaluator_clarification_request", $vendorController, "vendor portal should allow requested evaluator clarification replies after normal window");
$assertContains("get_latest_vendor_visible_evaluator_request", $vendorController, "vendor replies after evaluator requests should attach to the right thread");

echo "OK" . PHP_EOL;
