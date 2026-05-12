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

$sql = $read("app/Database/SQL/tender_technical_evaluation_findings_upgrade_pod.sql");
$attachmentsModel = $read("app/Models/Tender_evaluation_attachments_model.php");
$technicalController = $read("app/Controllers/Tender_technical_inbox.php");
$technicalModal = $read("app/Views/tender_technical_inbox/bid_modal_form.php");
$reportsController = $read("app/Controllers/Tender_reports.php");
$evaluationTable = $read("app/Views/tender_reports/evaluation_table.php");
$communicationsModel = $read("app/Models/Tender_communications_model.php");
$clarificationController = $read("app/Controllers/Tender_clarifications.php");
$procurementChat = $read("app/Views/tender_clarifications/vendor.php");
$vendorController = $read("app/Controllers/Vendor_portal.php");

$assertContains("pod_tender_evaluation_attachments", $sql, "SQL should create technical evaluation findings attachment table");
$assertContains("technical_clarification_request", $sql, "SQL should document the internal technical clarification request type");

$assertContains("ensure_evaluation_attachment_schema", $attachmentsModel, "findings attachment model should ensure its table");
$assertContains("save_attachments", $attachmentsModel, "findings attachment model should persist uploaded files");
$assertContains("get_grouped_by_evaluation_ids", $attachmentsModel, "findings attachment model should group files by evaluation");
$assertContains("get_attachment", $attachmentsModel, "findings attachment model should expose secure single-file lookup");

$assertContains("Tender_evaluation_attachments_model", $technicalController, "technical inbox should load the findings attachment model");
$assertContains("_save_technical_finding_files", $technicalController, "technical inbox should save finding uploads with evaluations");
$assertContains("getFileMultiple(\"technical_finding_files\")", $technicalController, "technical inbox should accept multiple finding files");
$assertContains("request_clarification", $technicalController, "technical inbox should let evaluators request procurement clarification");
$assertContains("technical_clarification_request", $technicalController, "technical clarification requests should be logged as internal procurement handoffs");
$assertContains("download_finding_document", $technicalController, "technical inbox should allow secure findings downloads");

$assertContains("form_open_multipart", $technicalModal, "technical evaluation form should support file uploads");
$assertContains("name=\"technical_finding_files[]\"", $technicalModal, "technical evaluation form should allow multiple finding documents");
$assertContains("Technical Findings Documents", $technicalModal, "technical evaluation modal should show findings upload section");
$assertContains("technical-clarification-request-form", $technicalModal, "technical modal should include a procurement clarification request form");

$assertContains("Tender_evaluation_attachments_model", $reportsController, "procurement report should load findings attachments");
$assertContains("technical_evaluation_attachments", $reportsController, "procurement report should pass technical findings to the view");
$assertContains("download_evaluation_attachment", $reportsController, "procurement report should allow secure findings downloads");
$assertContains("Technical Findings", $evaluationTable, "evaluation summary should show technical findings documents");

$assertContains("get_clarification_root_types", $communicationsModel, "communication model should include internal technical request roots");
$assertContains("technical_clarification_request", $communicationsModel, "communication model should include technical clarification request roots in conversations");
$assertContains("\"technical\" => \"Forward internally to technical team\"", $clarificationController, "procurement should be able to forward vendor replies internally to technical");
$assertContains("technical_clarification_request", $clarificationController, "procurement clarification screens should show technical requests");
$assertContains("Forward internally to technical team", $procurementChat, "procurement chat should expose the technical forwarding option");
$assertContains("_is_vendor_clarification_response_allowed", $vendorController, "vendor portal should allow requested technical clarification replies after normal clarification window");

echo "OK" . PHP_EOL;
