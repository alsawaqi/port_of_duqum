<?php

$migration = file_get_contents(
    __DIR__ . "/../app/Database/Migrations/2026_07_22_120000_vendor_multi_cr_contact_identities.php"
);
$upgradeSql = file_get_contents(
    __DIR__ . "/../app/Database/SQL/vendor_multi_cr_contact_identities_upgrade_pod.sql"
);
$model = file_get_contents(__DIR__ . "/../app/Models/Vendor_users_model.php");

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Missing: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$assertNotContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Unexpected: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$assertContains('ADD COLUMN `user_id`', $migration, "contacts gain a user identity link");
$assertContains('fk_vendor_contacts_user_id', $migration, "contact links use a guarded user foreign key");
$assertContains('fk_vendor_users_user_id', $migration, "membership user types can receive a real foreign key");
$assertContains('ensureUniqueLiveContactUsers', $migration, "one live contact profile is enforced per user and CR");
$assertContains('live_user_identity', $migration, "soft-deleted contacts retain user history outside live uniqueness");
$assertContains('CASE WHEN `deleted` = 0 THEN 1 ELSE NULL END', $migration, "live user guard ignores soft-deleted rows");
$assertContains('(`vendor_id`, `user_id`, `" . self::CONTACT_USER_IDENTITY_COLUMN', $migration, "live user uniqueness is scoped by CR, user, and live guard");
$assertContains('uq_vendor_contacts_live_user', $migration, "live contact user uniqueness has a stable index");
$assertContains('LEGACY_CONTACT_VENDOR_USER_INDEX', $migration, "the unconditional legacy user index is removed safely");
$assertContains('UPPER(TRIM(`cr_number`))', $migration, "CR identity is normalized");
$assertContains('GENERATED ALWAYS AS', $migration, "live CR uniqueness uses a generated normalized identity");
$assertContains('CONTACT', $migration, "the contact vendor role is seeded");
$assertContains('backfillLegacyOwnerContacts', $migration, "legacy registration owners enter the contact list");
$assertContains("memberships.is_owner = 1", $migration, "only proven owner memberships are backfilled");
$assertContains("memberships.status = 'active'", $migration, "only active memberships are backfilled");
$assertContains("users.user_type = 'staff'", $migration, "only live staff identities are backfilled");
$assertContains('ORDER BY `id` ASC', $migration, "legacy email matches choose the earliest contact deterministically");
$assertContains('->update(["user_id" => $userId])', $migration, "matching contact data is preserved except for its identity link");
$assertContains('"role" => "Owner"', $migration, "missing owner contacts are labelled as owners");
$assertContains('"status" => "approved"', $migration, "new owner contacts are approved");
$assertContains('ensureUniqueLiveContactEmails', $migration, "live contact email uniqueness is installed");
$assertContains('LOWER(TRIM(`email`))', $migration, "contact email identity is normalized");
$assertContains('duplicate_contact_emails', $migration, "legacy same-CR duplicates are detected without cleanup");
$assertContains('uq_vendor_contacts_live_email', $migration, "contact email uniqueness has a stable index");
$assertContains('(`vendor_id`, `" . self::CONTACT_EMAIL_IDENTITY_COLUMN', $migration, "contact emails are unique only inside one CR");

$assertContains('DROP INDEX', $upgradeSql, "the company email unique index is relaxed");
$assertContains('uq_vendors_cr_number_identity', $upgradeSql, "nonblank live CR uniqueness is enforceable");
$assertContains('ON DELETE SET NULL', $upgradeSql, "hard-deleting a user preserves the contact profile");
$assertContains('candidate_contacts.`user_id` IS NULL', $upgradeSql, "SQL backfill only claims unlinked contacts");
$assertContains('MIN(materialized_matches.`contact_id`)', $upgradeSql, "SQL backfill chooses one earliest matching contact");
$assertContains("memberships.`is_owner` = 1", $upgradeSql, "SQL backfill cannot grant ordinary contacts access");
$assertContains("memberships.`status` = 'active'", $upgradeSql, "SQL backfill requires an active membership");
$assertContains("users.`user_type` = 'staff'", $upgradeSql, "SQL backfill requires a staff identity");
$assertContains("'Owner'", $upgradeSql, "SQL backfill inserts explicit Owner contacts");
$assertContains("'approved'", $upgradeSql, "SQL backfill approves only newly created owner contacts");
$assertNotContains('INSERT INTO `pod_vendor_users`', $upgradeSql, "legacy contacts never receive inferred memberships");
$assertContains('`live_email_identity` VARCHAR(255) GENERATED ALWAYS AS', $upgradeSql, "SQL adds a nullable normalized live email identity");
$assertContains('GROUP BY `vendor_id`, LOWER(TRIM(`email`))', $upgradeSql, "SQL detects duplicates within each CR");
$assertContains('WARNING: skipped uq_vendor_contacts_live_email', $upgradeSql, "SQL reports rather than deletes legacy duplicates");
$assertContains('`uq_vendor_contacts_live_email` (`vendor_id`, `live_email_identity`)', $upgradeSql, "SQL allows the same email under different CRs");
$assertContains('`live_user_identity`', $upgradeSql, "SQL adds a nullable generated live user identity");
$assertContains('CASE WHEN `deleted` = 0 THEN 1 ELSE NULL END', $upgradeSql, "SQL excludes deleted contacts from live user uniqueness");
$assertContains('DROP INDEX `uq_vendor_contacts_vendor_user`', $upgradeSql, "SQL removes the unconditional historical index");
$assertContains('`uq_vendor_contacts_live_user` (`vendor_id`, `user_id`, `live_user_identity`)', $upgradeSql, "SQL enforces one live user profile per CR");
$assertContains('WARNING: skipped uq_vendor_contacts_live_user', $upgradeSql, "SQL preserves and reports duplicate live user links");
$assertNotContains('ADD UNIQUE INDEX `uq_vendor_contacts_vendor_user` (`vendor_id`, `user_id`)', $upgradeSql, "SQL never restores unconditional history-blocking uniqueness");

$assertContains('SESSION_VENDOR_ID = "active_vendor_id"', $model, "selected CR has one session key");
$assertContains('function get_accessible_memberships', $model, "all accessible CRs can be listed");
$assertContains('function get_accessible_membership', $model, "a selected CR can be validated");
$assertContains('function has_vendor_memberships', $model, "blocked vendor-only identities remain distinguishable");
$assertContains('function set_active_vendor_context', $model, "validated CR context can be stored");
$assertContains('function upsert_membership', $model, "contact and owner memberships share one upsert path");
$assertContains('function ensure_contact_role', $model, "CONTACT role lookup is centralized");

echo "Vendor identity foundation contracts passed." . PHP_EOL;
