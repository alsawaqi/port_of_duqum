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

$procurementInbox = $read("app/Controllers/Tender_procurement_inbox.php");
$managerInbox = $read("app/Controllers/Tender_procurement_manager_inbox.php");

$assertContains("_cascade_tender_milestones", $procurementInbox, "procurement save should cascade downstream tender milestones before validation");
$assertContains("_cascade_downstream_milestones", $procurementInbox, "schedule cascade should shift downstream phases by the same delta");
$assertContains('$new_milestones = $this->_cascade_tender_milestones($old_milestones, $new_milestones);', $procurementInbox, "cascaded dates should be used before manager approval payload is captured");
$assertContains('$release_at = $new_milestones["release_at"];', $procurementInbox, "cascaded release date should be written back to save fields");
$assertContains('$commercial_eval_deadline = $new_milestones["commercial_eval_deadline"];', $procurementInbox, "cascaded commercial deadline should be written back to save fields");
$assertContains("procurement schedule update", $procurementInbox, "schedule changes should remain logged as procurement schedule updates");

$assertContains("case \"stage_override\"", $managerInbox, "manager approval should apply pending stage override requests");
$assertContains("_apply_approved_stage_override", $managerInbox, "manager inbox should apply manager-approved workflow stage changes");
$assertContains("_apply_pending_testing_stage_override", $managerInbox, "manager inbox should apply approved temporary testing stage changes");
$assertContains("'stage_override'", $managerInbox, "manager inbox list should include stage override approval actions");

echo "OK" . PHP_EOL;
