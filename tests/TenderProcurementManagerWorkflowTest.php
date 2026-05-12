<?php

$root = dirname(__DIR__);

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$assertFileExists = static function (string $path, string $message): void {
    if (!is_file($path)) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing file: " . $path . PHP_EOL);
        exit(1);
    }
};

$required_files = [
    "/app/Controllers/Tender_procurement_manager_users.php",
    "/app/Controllers/Tender_procurement_manager_inbox.php",
    "/app/Models/Tender_procurement_manager_users_model.php",
    "/app/Views/tender_procurement_manager_users/index.php",
    "/app/Views/tender_procurement_manager_users/modal_form.php",
    "/app/Views/tender_procurement_manager_inbox/index.php",
    "/app/Database/SQL/tender_procurement_manager_upgrade_pod.sql",
];

foreach ($required_files as $file) {
    $assertFileExists($root . $file, "{$file} should exist for the procurement manager step");
}

$procurement_inbox = file_get_contents($root . "/app/Controllers/Tender_procurement_inbox.php");
$manager_inbox = file_get_contents($root . "/app/Controllers/Tender_procurement_manager_inbox.php");
$reports = file_get_contents($root . "/app/Controllers/Tender_reports.php");
$form = file_get_contents($root . "/app/Views/tender_procurement_inbox/form.php");
$sql = file_get_contents($root . "/app/Database/SQL/tender_procurement_manager_upgrade_pod.sql");

$assertContains("submit_for_approval", $procurement_inbox, "procurement save should submit drafts for manager approval");
$assertContains("procurement_manager_status", $procurement_inbox, "procurement flow should persist manager approval status");
$assertContains("Procurement manager approval is required before publishing.", $procurement_inbox, "publishing should be blocked before approval");
$assertContains("_build_manager_approval_payload", $procurement_inbox, "procurement flow should have a manager submission payload");

$assertContains("access_only_tender(\"procurement_manager_inbox\", \"view\")", $manager_inbox, "manager inbox should use tender permissions");
$assertContains("approve", $manager_inbox, "manager inbox should expose approval action");
$assertContains("procurement_manager_status\" => \"approved\"", $manager_inbox, "manager approval should set approved status");
$assertContains("can_tender(\"procurement_manager_inbox\", \"view\")", $reports, "manager should be able to open tender details for review");

$assertContains("Submit for Procurement Manager Approval", $form, "procurement form should show manager submission control");
$assertContains("Publish After Manager Approval", $form, "approved tenders should show publish control");

$assertContains("CREATE TABLE IF NOT EXISTS `pod_tender_procurement_manager_users`", $sql, "SQL should create procurement manager pivot table");
$assertContains("procurement_manager_status", $sql, "SQL should add manager approval status column");

echo "Tender procurement manager workflow coverage passed." . PHP_EOL;
