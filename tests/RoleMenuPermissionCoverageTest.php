<?php

$root = dirname(__DIR__);

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$roles_controller = file_get_contents($root . "/app/Controllers/Roles.php");
$roles_view = file_get_contents($root . "/app/Views/roles/permissions.php");
$left_menu = file_get_contents($root . "/app/Libraries/Left_menu.php");
$security_controller = file_get_contents($root . "/app/Controllers/Security_Controller.php");

$tender_master_sections = [
    "department_users",
    "department_manager_users",
    "finance_users",
    "procurement_manager_users",
    "committee_users",
    "procurement_users",
    "technical_users",
    "commercial_users",
];

$tender_workflow_sections = [
    "requests",
    "manager_inbox",
    "finance_inbox",
    "procurement",
    "procurement_manager_inbox",
    "committee",
    "technical_eval",
    "commercial_eval",
    "reports",
    "portal",
];

foreach (array_merge($tender_master_sections, $tender_workflow_sections) as $section) {
    foreach (["view", "create", "update", "delete"] as $action) {
        $key = "can_{$action}_tender_{$section}";
        $assertContains("can_{$action}_tender_", $roles_view, "roles form should build tender {$action} fields");
        $assertContains("can_{$action}_tender_", $roles_controller, "roles controller should load/save tender {$action} fields");
    }

    $assertContains($section, $roles_view, "roles form should include tender section {$section}");
    $assertContains($section, $roles_controller, "roles controller should include tender section {$section}");
    $assertContains($section, $left_menu, "left menu should gate tender {$section}");
    $assertContains("can_view_tender_", $left_menu, "left menu should gate tender sections by permission");
}

foreach (["blocked_visitors", "activity_logs"] as $section) {
    foreach (["view", "create", "update", "delete"] as $action) {
        $key = "can_{$action}_gate_pass_{$section}";
        $assertContains("can_{$action}_gate_pass_", $roles_view, "roles form should build gate pass {$action} fields");
        $assertContains("can_{$action}_gate_pass_", $roles_controller, "roles controller should load/save gate pass {$action} fields");
    }
    $assertContains($section, $roles_view, "roles form should include gate pass section {$section}");
    $assertContains($section, $roles_controller, "roles controller should include gate pass section {$section}");
    $assertContains($section, $left_menu, "left menu should gate gate pass {$section}");
    $assertContains("can_view_gate_pass_", $left_menu, "left menu should gate gate pass sections by permission");
}

$assertContains("can_view_pod_reports", $roles_view, "roles form should expose POD reports view permission");
$assertContains("can_view_pod_reports", $roles_controller, "roles controller should load/save POD reports view permission");
$assertContains("can_view_pod_reports", $left_menu, "left menu should gate POD reports by permission");
$assertContains("can_view_pod_reports", $security_controller, "security controller should provide POD reports access check");

$controller_expectations = [
    "app/Controllers/Pod_reports.php" => "access_only_pod_reports",
    "app/Controllers/Gate_pass_activity_logs.php" => 'access_only_gate_pass("activity_logs", "view")',
    "app/Controllers/Gate_pass_blocked_visitors.php" => 'access_only_gate_pass("blocked_visitors", "view")',
    "app/Controllers/Tender_department_users.php" => 'access_only_tender("department_users", "view")',
    "app/Controllers/Tender_department_manager_users.php" => 'access_only_tender("department_manager_users", "view")',
    "app/Controllers/Tender_finance_users.php" => 'access_only_tender("finance_users", "view")',
    "app/Controllers/Tender_committee_users.php" => 'access_only_tender("committee_users", "view")',
    "app/Controllers/Tender_procurement_users.php" => 'access_only_tender("procurement_users", "view")',
    "app/Controllers/Tender_technical_users.php" => 'access_only_tender("technical_users", "view")',
    "app/Controllers/Tender_commercial_users.php" => 'access_only_tender("commercial_users", "view")',
];

foreach ($controller_expectations as $file => $needle) {
    $assertContains($needle, file_get_contents($root . "/" . $file), "{$file} should use {$needle}");
}

echo "Role menu permission coverage passed." . PHP_EOL;
