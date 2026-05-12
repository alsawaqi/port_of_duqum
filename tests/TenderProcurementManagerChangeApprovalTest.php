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

$procurementInbox = $read("app/Controllers/Tender_procurement_inbox.php");
$managerInbox = $read("app/Controllers/Tender_procurement_manager_inbox.php");
$tendersModel = $read("app/Models/Tenders_model.php");
$form = $read("app/Views/tender_procurement_inbox/form.php");
$managerIndex = $read("app/Views/tender_procurement_manager_inbox/index.php");
$sql = $read("app/Database/SQL/tender_procurement_manager_upgrade_pod.sql");

$assertContains("procurement_manager_action", $sql, "SQL should add a manager action column for update/cancel approvals");
$assertContains("procurement_manager_payload", $sql, "SQL should store pending manager approval payloads");
$assertContains("ensure_procurement_manager_change_columns", $tendersModel, "tenders model should ensure update/cancel approval columns");

$assertContains("_requires_manager_approval_for_tender_change", $procurementInbox, "procurement save should detect manager-approved and live tender changes");
$assertContains("_capture_manager_change_payload", $procurementInbox, "procurement save should capture active tender edits instead of applying them immediately");
$assertContains("_build_manager_approval_payload(\"update\"", $procurementInbox, "active tender edits should submit an update approval action");
$assertContains("_build_manager_approval_payload(\"cancel\"", $procurementInbox, "active tender cancellation should submit a cancel approval action");
$assertContains("Existing tender remains unchanged until approval", $procurementInbox, "procurement should tell users active edits are pending approval");
$assertContains("Tender cancellation submitted for procurement manager approval", $procurementInbox, "cancel action should be pending manager approval");

$assertContains("procurement_manager_action", $managerInbox, "manager inbox should show pending update/cancel action type");
$assertContains("_apply_approved_manager_update", $managerInbox, "manager approval should apply pending update payloads");
$assertContains("case \"cancel\"", $managerInbox, "manager approval should apply pending cancellations");
$assertContains("case \"update\"", $managerInbox, "manager approval should apply pending updates");
$assertContains("_pending_action_summary", $managerInbox, "manager inbox should show a readable pending change summary");

$assertContains("Submit Change for Procurement Manager Approval", $form, "active tender form should communicate change approval workflow");
$assertContains("Approval Action", $managerIndex, "manager inbox table should include action type");
$assertContains("Pending Change", $managerIndex, "manager inbox table should include pending update details");

echo "OK" . PHP_EOL;
