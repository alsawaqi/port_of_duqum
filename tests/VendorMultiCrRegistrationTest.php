<?php

$guestController = file_get_contents(__DIR__ . "/../app/Controllers/Guest_vendor.php");
$adminController = file_get_contents(__DIR__ . "/../app/Controllers/Vendors.php");
$guestView = file_get_contents(__DIR__ . "/../app/Views/guest_vendor/index.php");
$adminView = file_get_contents(__DIR__ . "/../app/Views/vendors/modal_form.php");

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$assertNotContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Unexpected: " . $needle . PHP_EOL);
        exit(1);
    }
};

$assertContains('->select("id, user_type, status, disable_login, role_id, is_admin, password, deleted")', $guestController, "guest registration must validate the complete existing identity state");
$assertContains('$this->Users_model->verify_user_password((int) $existing_user->id, $password)', $guestController, "guest registration verifies and upgrades an existing account password");
$assertContains('(int)($existing_user->disable_login ?? 0) === 1', $guestController, "public signup cannot reactivate a login-disabled identity");
$assertContains('"user_id"       => (int)$user_id', $guestController, "guest registrant contact must link to its user");
$assertContains('"module"    => "contacts"', $guestController, "guest registrant contact must enter contact approval");
$assertContains('"is_primary"    => 1', $guestController, "guest registrant must be the primary contact");
$assertContains('"status" => "invited"', $guestController, "guest owner access must wait for contact approval");
$assertContains('A public registration must never reactivate an identity', $guestController, "deleted identities require administrator restoration");
$assertNotContains('Failed to restore existing user', $guestController, "public signup cannot revive a deleted account");
$assertNotContains('user_already_registered_as_vendor', $guestController, "an existing vendor login may register another CR");

$assertContains('"cr_number"      => $is_create ? "required" : "permit_empty"', $adminController, "admin creation must capture a CR");
$assertContains('if ($existing_user)', $adminController, "admin creation must reuse an existing login");
$assertContains('This staff account is inactive. Restore it before linking it to a vendor CR.', $adminController, "admin creation cannot silently reactivate a disabled identity");
$assertNotContains('Failed to restore existing user', $adminController, "vendor creation requires explicit account restoration");
$assertContains('$existing_pivot = $db->table("vendor_users")', $adminController, "admin creation must revive or create a CR membership");
$assertContains('name" => "cr_number"', $adminView, "admin vendor form must expose the CR field");
$assertContains('"autocomplete" => "current-password"', $guestView, "guest form must request the current password when an email exists");

echo "Vendor multi-CR registration contracts passed." . PHP_EOL;
