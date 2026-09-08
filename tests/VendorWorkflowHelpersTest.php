<?php

require_once __DIR__ . "/../app/Helpers/general_helper.php";

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$assertTrue = static function ($actual, string $message): void {
    if ($actual !== true) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$assertFalse = static function ($actual, string $message): void {
    if ($actual !== false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$assertSame(
    ["new", "pending_payment", "submitted", "approved", "rejected", "revise", "suspended", "expired"],
    vendor_status_options(),
    "vendor status options match the database enum"
);

$assertSame("new", vendor_initial_registration_status(), "guest registration starts as new");

$assertTrue(vendor_can_access_profile_portal("new"), "new vendors can complete profile");
$assertTrue(vendor_can_access_profile_portal("revise"), "revise vendors can correct profile");
$assertFalse(vendor_can_access_profile_portal("rejected"), "rejected vendors cannot access normal portal");
$assertFalse(vendor_can_access_profile_portal("suspended"), "suspended vendors cannot access normal portal");
$assertTrue(vendor_can_access_profile_portal("expired"), "expired vendors can maintain profile and renew registration");
$assertFalse(vendor_can_access_tender_portal("expired"), "expired vendors still cannot access tenders before renewal");

$assertTrue(vendor_can_access_tender_portal("approved"), "approved vendors can access tender portal");
$assertTrue(vendor_can_access_tender_portal("submitted"), "submitted vendors can access eligible active tenders");
$assertTrue(vendor_can_access_tender_portal("new"), "new vendors can access eligible active tenders");
$assertFalse(vendor_can_access_tender_portal("suspended"), "suspended vendors cannot access tender portal");

$assertSame("suspended", vendor_blocked_status(), "blocked vendors use the suspended status");
$assertSame(
    ["new", "pending_payment", "submitted", "approved", "revise", "expired"],
    vendor_login_allowed_statuses(),
    "vendor login permits renewal while excluding blocked and rejected vendors"
);
$assertSame("A - Excellent", vendor_grade_label("Excellent", "A"), "vendor grade labels include code and name");
$assertSame("B2", vendor_grade_label("", "B2"), "vendor grade labels fall back to code");
$assertSame("-", vendor_grade_label("", ""), "vendor grade labels fall back to dash when no grade exists");

echo "Vendor workflow helpers passed." . PHP_EOL;
