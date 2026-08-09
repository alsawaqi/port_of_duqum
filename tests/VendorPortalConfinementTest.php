<?php

$security = file_get_contents(__DIR__ . "/../app/Controllers/Security_Controller.php");
$users = file_get_contents(__DIR__ . "/../app/Models/Users_model.php");
$leftMenu = file_get_contents(__DIR__ . "/../app/Libraries/Left_menu.php");
$topbar = file_get_contents(__DIR__ . "/../app/Views/includes/topbar.php");
$vendorUsers = file_get_contents(__DIR__ . "/../app/Models/Vendor_users_model.php");

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Missing: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$assertContains('_confine_vendor_only_identity', $security, "security controller confines vendor-only identities");
$assertContains('is_vendor_only_identity($userId, $this->login_user)', $security, "security uses the centralized identity classification");
$assertContains('function is_vendor_only_identity', $users, "vendor-only classification is centralized");
$assertContains('(int) ($user_info->role_id ?? 0) !== 0', $users, "internal role-bearing staff are not reclassified");
$assertContains('FROM {$vendorUsers} memberships', $users, "vendor-only classification requires a real membership");
$assertContains('This is an identity-history check, not an access check.', $vendorUsers, "deleted CRs cannot turn vendor identities into internal staff");
$assertContains('"gate_pass_users"', $users, "mixed gate-pass identities retain operational access");
$assertContains('"tender_technical_users"', $users, "mixed tender identities retain operational access");
$assertContains('["vendor_portal", "gate_pass_portal", "ptw_portal", "portal_account", "notifications"]', $security, "external identities have an explicit portal-only controller allowlist");
$assertContains('app_redirect("forbidden")', $security, "all other internal controllers are denied");
$assertContains('is_vendor_only_identity', $leftMenu, "vendor menu is reduced to its portal");
$assertContains('"name" => "vendor_portal"', $leftMenu, "vendor menu contains the workspace");
$assertContains('$is_vendor_only_identity', $topbar, "topbar hides internal account controls for vendor-only users");

echo "Vendor portal confinement contracts passed." . PHP_EOL;
