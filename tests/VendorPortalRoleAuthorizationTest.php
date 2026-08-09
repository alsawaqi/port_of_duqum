<?php

require_once __DIR__ . '/../app/Libraries/Vendor_portal_authorizer.php';

use App\Libraries\Vendor_portal_authorizer;

$fail = static function (string $message): void {
    fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
    exit(1);
};
$assert = static function (bool $condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};
$membership = static function (string $code, int $owner = 0, string $status = 'active'): object {
    return (object) [
        'vendor_role_code' => $code,
        'is_owner' => $owner,
        'membership_status' => $status,
    ];
};

$auth = new Vendor_portal_authorizer();
$assert($auth->can($membership('VIEWER'), Vendor_portal_authorizer::PROFILE_VIEW), 'viewer can view its selected CR');
$assert(!$auth->can($membership('VIEWER'), Vendor_portal_authorizer::PROFILE_EDIT), 'viewer cannot edit CR data');
$assert(!$auth->can($membership('CONTACT'), Vendor_portal_authorizer::TENDER_PARTICIPATE), 'legacy contact is fail-safe read-only');
$assert($auth->can($membership('BIDDER'), Vendor_portal_authorizer::TENDER_PARTICIPATE), 'bidder can participate');
$assert(!$auth->can($membership('BIDDER'), Vendor_portal_authorizer::PROFILE_EDIT), 'bidder cannot edit CR data');
$assert($auth->can($membership('EDITOR'), Vendor_portal_authorizer::PROFILE_EDIT), 'editor can edit CR data');
$assert(!$auth->can($membership('EDITOR'), Vendor_portal_authorizer::CONTACTS_MANAGE), 'editor cannot provision contacts');
$assert($auth->can($membership('VIEWER', 1), Vendor_portal_authorizer::CONTACTS_MANAGE), 'owner flag grants owner role');
$assert(!$auth->can($membership('OWNER', 1, 'suspended'), Vendor_portal_authorizer::PROFILE_VIEW), 'suspended membership has no access');

$root = dirname(__DIR__);
$roleMigration = (string) file_get_contents(
    $root . '/app/Database/Migrations/2026_08_03_080000_vendor_portal_role_enforcement.php'
);
$roleSql = (string) file_get_contents(
    $root . '/app/Database/SQL/vendor_portal_role_enforcement_upgrade_pod.sql'
);
foreach (['OWNER', 'EDITOR', 'BIDDER', 'VIEWER', 'CONTACT'] as $roleCode) {
    $assert(str_contains($roleMigration, "'{$roleCode}'"), "migration defines {$roleCode}");
    $assert(str_contains($roleSql, "'{$roleCode}'"), "manual SQL defines {$roleCode}");
}
$assert(
    str_contains($roleSql, "SET `name` = 'Owner'") && str_contains($roleSql, "SET `name` = 'Viewer'"),
    'manual SQL normalizes existing role metadata as well as inserting missing roles'
);

echo 'Vendor portal role authorization passed.' . PHP_EOL;
