<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Establishes the data foundation for one user identity to access multiple
 * vendor registrations (CRs), including approved vendor contacts.
 *
 * This migration is deliberately defensive because older POD databases were
 * shipped with vendor_users.user_id as BIGINT UNSIGNED while users.id is INT.
 * Constraints and narrowing type changes are applied only after compatibility,
 * range, and orphan checks pass. Existing business rows are never deleted.
 */
class Vendor_multi_cr_contact_identities extends Migration
{
    private const CONTACT_USER_INDEX = "idx_vendor_contacts_user_id";
    private const LEGACY_CONTACT_VENDOR_USER_INDEX = "uq_vendor_contacts_vendor_user";
    private const CONTACT_USER_IDENTITY_COLUMN = "live_user_identity";
    private const CONTACT_USER_IDENTITY_INDEX = "uq_vendor_contacts_live_user";
    private const CONTACT_EMAIL_IDENTITY_COLUMN = "live_email_identity";
    private const CONTACT_EMAIL_IDENTITY_INDEX = "uq_vendor_contacts_live_email";
    private const CONTACT_USER_FOREIGN_KEY = "fk_vendor_contacts_user_id";
    private const VENDOR_USER_FOREIGN_KEY = "fk_vendor_users_user_id";
    private const VENDOR_EMAIL_INDEX = "idx_vendors_email";
    private const CR_IDENTITY_COLUMN = "cr_number_identity";
    private const CR_IDENTITY_INDEX = "uq_vendors_cr_number_identity";

    public function up()
    {
        $this->ensureContactRole();
        $this->ensureContactUserColumn();
        $this->backfillLegacyOwnerContacts();
        $this->ensureUniqueLiveContactUsers();
        $this->ensureUniqueLiveContactEmails();
        $this->relaxVendorEmailUniqueness();
        $this->ensureUniqueLiveCrNumbers();
        $this->alignVendorUserIdentityColumns();
        $this->ensureUserForeignKeys();
    }

    public function down()
    {
        $contacts = $this->db->prefixTable("vendor_contacts");
        $vendorUsers = $this->db->prefixTable("vendor_users");
        $vendors = $this->db->prefixTable("vendors");

        // Do not restore the old vendors.email UNIQUE index: valid multi-CR
        // registrations may now share that address.
        $this->dropForeignKeyIfExists($contacts, self::CONTACT_USER_FOREIGN_KEY);
        $this->dropForeignKeyIfExists($vendorUsers, self::VENDOR_USER_FOREIGN_KEY);

        if ($this->tableExists($contacts)) {
            $this->dropIndexIfExists($contacts, self::CONTACT_USER_IDENTITY_INDEX);
            if ($this->columnExists($contacts, self::CONTACT_USER_IDENTITY_COLUMN)) {
                $this->safeQuery(
                    "ALTER TABLE " . $this->quoteIdentifier($contacts)
                    . " DROP COLUMN " . $this->quoteIdentifier(self::CONTACT_USER_IDENTITY_COLUMN),
                    "Unable to remove the generated live contact user identity during rollback."
                );
            }

            $this->dropIndexIfExists($contacts, self::CONTACT_EMAIL_IDENTITY_INDEX);
            if ($this->columnExists($contacts, self::CONTACT_EMAIL_IDENTITY_COLUMN)) {
                $this->safeQuery(
                    "ALTER TABLE " . $this->quoteIdentifier($contacts)
                    . " DROP COLUMN " . $this->quoteIdentifier(self::CONTACT_EMAIL_IDENTITY_COLUMN),
                    "Unable to remove the generated live contact email identity during rollback."
                );
            }
        }

        if ($this->tableExists($vendors)) {
            $this->dropIndexIfExists($vendors, self::CR_IDENTITY_INDEX);
            if ($this->columnExists($vendors, self::CR_IDENTITY_COLUMN)) {
                $this->safeQuery(
                    "ALTER TABLE " . $this->quoteIdentifier($vendors)
                    . " DROP COLUMN " . $this->quoteIdentifier(self::CR_IDENTITY_COLUMN),
                    "Unable to remove the generated CR identity column during rollback."
                );
            }
        }

        // Removing a populated contact link would destroy identity data. Only
        // remove the new column on rollback when it has never been used.
        if ($this->tableExists($contacts) && $this->columnExists($contacts, "user_id")) {
            $linked = $this->db->query(
                "SELECT COUNT(*) AS total FROM " . $this->quoteIdentifier($contacts)
                . " WHERE `user_id` IS NOT NULL"
            )->getRow();

            if ((int) ($linked->total ?? 0) === 0) {
                $this->dropIndexIfExists($contacts, self::CONTACT_USER_INDEX);
                $this->safeQuery(
                    "ALTER TABLE " . $this->quoteIdentifier($contacts) . " DROP COLUMN `user_id`",
                    "Unable to remove the unused vendor contact user column during rollback."
                );
            }
        }

        // CONTACT role rows and aligned vendor_users integer types are retained:
        // either may already be referenced by production data.
    }

    private function ensureContactRole(): void
    {
        $roles = $this->db->prefixTable("vendor_roles");
        if (!$this->tableExists($roles)) {
            return;
        }

        $role = $this->db->query(
            "SELECT `id` FROM " . $this->quoteIdentifier($roles)
            . " WHERE UPPER(TRIM(`code`)) = 'CONTACT' ORDER BY `id` ASC LIMIT 1"
        )->getRow();

        $now = date("Y-m-d H:i:s");
        if ($role && !empty($role->id)) {
            $this->db->table($roles)->where("id", (int) $role->id)->update([
                "name" => "Contact",
                "code" => "CONTACT",
                "description" => "Approved vendor contact with full access to the linked vendor portal.",
                "is_active" => 1,
                "deleted" => 0,
                "updated_at" => $now,
            ]);
            return;
        }

        $this->db->table($roles)->insert([
            "name" => "Contact",
            "code" => "CONTACT",
            "description" => "Approved vendor contact with full access to the linked vendor portal.",
            "is_active" => 1,
            "created_at" => $now,
            "updated_at" => $now,
            "deleted" => 0,
        ]);
    }

    private function ensureContactUserColumn(): void
    {
        $contacts = $this->db->prefixTable("vendor_contacts");
        $users = $this->db->prefixTable("users");
        if (!$this->tableExists($contacts) || !$this->tableExists($users)) {
            return;
        }

        $userId = $this->columnInfo($users, "id");
        $integerDefinition = $this->integerDefinition($userId);
        if (!$integerDefinition) {
            log_message("warning", "Vendor contact identity migration skipped user_id: users.id is not a supported integer type.");
            return;
        }

        if (!$this->columnExists($contacts, "user_id")) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD COLUMN `user_id` {$integerDefinition} NULL DEFAULT NULL AFTER `vendor_id`",
                "Unable to add vendor_contacts.user_id."
            );
        }

        $contactUserId = $this->columnInfo($contacts, "user_id");
        if ($contactUserId
            && !$this->integerTypesCompatible($contactUserId, $userId)
            && !$this->columnHasForeignKey($contacts, "user_id")
            && $this->valuesFitIntegerType($contacts, "user_id", $userId)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " MODIFY COLUMN `user_id` {$integerDefinition} NULL DEFAULT NULL",
                "Unable to align vendor_contacts.user_id with users.id."
            );
        }

        if ($this->columnExists($contacts, "user_id") && !$this->indexExists($contacts, self::CONTACT_USER_INDEX)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD INDEX `" . self::CONTACT_USER_INDEX . "` (`user_id`)",
                "Unable to add the vendor contact user index."
            );
        }

    }

    private function relaxVendorEmailUniqueness(): void
    {
        $vendors = $this->db->prefixTable("vendors");
        if (!$this->tableExists($vendors) || !$this->columnExists($vendors, "email")) {
            return;
        }

        $indexes = $this->db->query(
            "SELECT `INDEX_NAME`
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
             GROUP BY `INDEX_NAME`
             HAVING COUNT(*) = 1 AND MAX(`COLUMN_NAME`) = 'email'",
            [$this->schemaName(), $vendors]
        )->getResult();

        foreach ($indexes as $index) {
            $name = (string) ($index->INDEX_NAME ?? "");
            if ($this->isSafeIdentifier($name)) {
                $this->safeQuery(
                    "ALTER TABLE " . $this->quoteIdentifier($vendors)
                    . " DROP INDEX " . $this->quoteIdentifier($name),
                    "Unable to relax the vendors.email unique index {$name}."
                );
            }
        }

        if (!$this->hasLeadingIndex($vendors, "email")) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($vendors)
                . " ADD INDEX `" . self::VENDOR_EMAIL_INDEX . "` (`email`)",
                "Unable to add the non-unique vendors.email lookup index."
            );
        }
    }

    /**
     * Makes legacy registration owners visible in the contact list without
     * inferring access for ordinary contacts. The vendor_users row is the proof
     * of identity and authority; a matching contact receives only user_id.
     */
    private function backfillLegacyOwnerContacts(): void
    {
        $vendorUsers = $this->db->prefixTable("vendor_users");
        $contacts = $this->db->prefixTable("vendor_contacts");
        $users = $this->db->prefixTable("users");
        $vendors = $this->db->prefixTable("vendors");

        if (!$this->tableExists($vendorUsers)
            || !$this->tableExists($contacts)
            || !$this->tableExists($users)
            || !$this->tableExists($vendors)
            || !$this->columnExists($contacts, "user_id")) {
            return;
        }

        $owners = $this->db->query(
            "SELECT memberships.id AS membership_id, memberships.vendor_id, memberships.user_id,
                    users.first_name, users.last_name, users.email AS user_email,
                    users.phone AS user_phone, users.job_title,
                    vendors.vendor_name, vendors.phone AS vendor_phone,
                    vendors.contact_designation
             FROM " . $this->quoteIdentifier($vendorUsers) . " memberships
             INNER JOIN " . $this->quoteIdentifier($users) . " users
                ON users.id = memberships.user_id
               AND users.deleted = 0
               AND users.status = 'active'
               AND users.user_type = 'staff'
             INNER JOIN " . $this->quoteIdentifier($vendors) . " vendors
                ON vendors.id = memberships.vendor_id
               AND vendors.deleted = 0
             WHERE memberships.deleted = 0
               AND memberships.is_owner = 1
               AND memberships.status = 'active'
               AND NULLIF(TRIM(users.email), '') IS NOT NULL
             ORDER BY memberships.vendor_id ASC, memberships.id ASC"
        )->getResult();

        // If two proven owner identities under the same vendor share a legacy
        // email, neither may claim an unlinked contact based on email alone.
        $identityCounts = [];
        foreach ($owners as $owner) {
            $identityKey = (int) $owner->vendor_id . "|" . strtolower(trim((string) $owner->user_email));
            $identityCounts[$identityKey] = ($identityCounts[$identityKey] ?? 0) + 1;
        }

        foreach ($owners as $owner) {
            $vendorId = (int) $owner->vendor_id;
            $userId = (int) $owner->user_id;
            $email = strtolower(trim((string) $owner->user_email));
            $identityKey = $vendorId . "|" . $email;

            if ($this->legacyContactLinkExists($contacts, $vendorId, $userId)) {
                continue;
            }

            if (($identityCounts[$identityKey] ?? 0) === 1) {
                $match = $this->db->query(
                    "SELECT `id`
                     FROM " . $this->quoteIdentifier($contacts) . "
                     WHERE `vendor_id` = ?
                       AND `deleted` = 0
                       AND `user_id` IS NULL
                       AND LOWER(TRIM(`email`)) = ?
                     ORDER BY `id` ASC
                     LIMIT 1",
                    [$vendorId, $email]
                )->getRow();

                if ($match && !empty($match->id)) {
                    // Preserve the entire legacy contact record, including its
                    // approval/activity state. Only establish identity.
                    $this->db->table($contacts)
                        ->where("id", (int) $match->id)
                        ->where("user_id", null)
                        ->update(["user_id" => $userId]);
                }
            }

            if ($this->legacyContactLinkExists($contacts, $vendorId, $userId)) {
                continue;
            }

            $primary = $this->db->query(
                "SELECT `id` FROM " . $this->quoteIdentifier($contacts)
                . " WHERE `vendor_id` = ? AND `deleted` = 0 AND `is_primary` = 1 LIMIT 1",
                [$vendorId]
            )->getRow();

            $fullName = trim((string) $owner->first_name . " " . (string) $owner->last_name);
            if ($fullName === "") {
                $fullName = trim((string) $owner->vendor_name);
            }
            if ($fullName === "") {
                $fullName = $email;
            }

            $phone = trim((string) ($owner->user_phone ?: $owner->vendor_phone));
            $designation = trim((string) ($owner->job_title ?: $owner->contact_designation));
            $now = date("Y-m-d H:i:s");

            try {
                $this->db->table($contacts)->insert([
                    "vendor_id" => $vendorId,
                    "user_id" => $userId,
                    "contacts_name" => mb_substr($fullName, 0, 255),
                    "phone" => $phone !== "" ? mb_substr($phone, 0, 255) : null,
                    "designation" => $designation !== "" ? mb_substr($designation, 0, 255) : "Owner",
                    "email" => mb_substr($email, 0, 255),
                    "mobile" => $phone !== "" ? mb_substr($phone, 0, 255) : null,
                    "role" => "Owner",
                    "is_primary" => $primary ? 0 : 1,
                    "is_active" => 1,
                    "status" => "approved",
                    "created_at" => $now,
                    "updated_at" => $now,
                    "deleted" => 0,
                ]);
            } catch (\Throwable $exception) {
                // A concurrent/idempotent insert may win the unique pair. Only
                // warn when the required link still does not exist.
                if (!$this->legacyContactLinkExists($contacts, $vendorId, $userId)) {
                    log_message(
                        "warning",
                        "Unable to backfill vendor owner contact for membership {$owner->membership_id}: "
                        . $exception->getMessage()
                    );
                }
            }
        }
    }

    private function legacyContactLinkExists(string $contacts, int $vendorId, int $userId): bool
    {
        // Deleted rows count as an existing historical link so a rollback or
        // later migration never resurrects a deliberately removed contact.
        $row = $this->db->query(
            "SELECT `id` FROM " . $this->quoteIdentifier($contacts)
            . " WHERE `vendor_id` = ? AND `user_id` = ? ORDER BY `id` ASC LIMIT 1",
            [$vendorId, $userId]
        )->getRow();

        return (bool) $row;
    }

    /**
     * Enforces one nonblank live contact email per CR while allowing the same
     * normalized email under other vendors. Legacy duplicates are reported and
     * left untouched so an administrator can resolve them explicitly.
     */
    private function ensureUniqueLiveContactEmails(): void
    {
        $contacts = $this->db->prefixTable("vendor_contacts");
        if (!$this->tableExists($contacts) || !$this->columnExists($contacts, "email")) {
            return;
        }

        if (!$this->columnExists($contacts, self::CONTACT_EMAIL_IDENTITY_COLUMN)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD COLUMN `" . self::CONTACT_EMAIL_IDENTITY_COLUMN . "` VARCHAR(255)"
                . " GENERATED ALWAYS AS (CASE WHEN `deleted` = 0"
                . " THEN NULLIF(LOWER(TRIM(`email`)), '') ELSE NULL END) STORED",
                "Unable to add the normalized live vendor contact email identity."
            );
        }

        $duplicates = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM (
                 SELECT `vendor_id`, LOWER(TRIM(`email`)) AS normalized_email
                 FROM " . $this->quoteIdentifier($contacts) . "
                 WHERE `deleted` = 0 AND NULLIF(TRIM(`email`), '') IS NOT NULL
                 GROUP BY `vendor_id`, LOWER(TRIM(`email`))
                 HAVING COUNT(*) > 1
             ) duplicate_contact_emails"
        )->getRow();

        if ((int) ($duplicates->total ?? 0) > 0) {
            log_message(
                "warning",
                "Unique live vendor contact email enforcement skipped because duplicate emails exist within one or more CRs."
            );
            return;
        }

        $column = $this->columnInfo($contacts, self::CONTACT_EMAIL_IDENTITY_COLUMN);
        $isGenerated = stripos((string) ($column->EXTRA ?? ""), "GENERATED") !== false;
        if ($column && $isGenerated && !$this->indexExists($contacts, self::CONTACT_EMAIL_IDENTITY_INDEX)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD UNIQUE INDEX `" . self::CONTACT_EMAIL_IDENTITY_INDEX . "`"
                . " (`vendor_id`, `" . self::CONTACT_EMAIL_IDENTITY_COLUMN . "`)",
                "Unable to enforce unique live contact email addresses within each vendor CR."
            );
        }
    }

    /**
     * Enforces one live contact profile per user and CR without erasing the
     * user_id from soft-deleted historical contacts. A replacement contact can
     * therefore be added after deletion.
     */
    private function ensureUniqueLiveContactUsers(): void
    {
        $contacts = $this->db->prefixTable("vendor_contacts");
        if (!$this->tableExists($contacts)
            || !$this->columnExists($contacts, "vendor_id")
            || !$this->columnExists($contacts, "user_id")
            || !$this->columnExists($contacts, "deleted")) {
            return;
        }

        $expectedIndexColumns = [
            "vendor_id",
            "user_id",
            self::CONTACT_USER_IDENTITY_COLUMN,
        ];
        $existingIndexColumns = $this->indexColumns($contacts, self::CONTACT_USER_IDENTITY_INDEX);
        if ($this->indexExists($contacts, self::CONTACT_USER_IDENTITY_INDEX)
            && ($existingIndexColumns !== $expectedIndexColumns
                || !$this->indexIsUnique($contacts, self::CONTACT_USER_IDENTITY_INDEX))) {
            $this->dropIndexIfExists($contacts, self::CONTACT_USER_IDENTITY_INDEX);
        }

        $column = $this->columnInfo($contacts, self::CONTACT_USER_IDENTITY_COLUMN);
        $expression = strtolower((string) ($column->GENERATION_EXPRESSION ?? ""));
        $isCompatibleGuard = $column
            && strtolower((string) ($column->DATA_TYPE ?? "")) === "tinyint"
            && stripos((string) ($column->EXTRA ?? ""), "STORED") !== false
            && stripos((string) ($column->EXTRA ?? ""), "GENERATED") !== false
            && strpos($expression, "deleted") !== false
            && strpos($expression, "user_id") === false;

        if ($column && !$isCompatibleGuard) {
            $this->dropIndexIfExists($contacts, self::CONTACT_USER_IDENTITY_INDEX);
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " DROP COLUMN `" . self::CONTACT_USER_IDENTITY_COLUMN . "`",
                "Unable to replace the incompatible live vendor contact user identity."
            );
        }

        if (!$this->columnExists($contacts, self::CONTACT_USER_IDENTITY_COLUMN)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD COLUMN `" . self::CONTACT_USER_IDENTITY_COLUMN . "` TINYINT"
                . " GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 THEN 1 ELSE NULL END) STORED",
                "Unable to add the generated live vendor contact user guard."
            );
        }

        $column = $this->columnInfo($contacts, self::CONTACT_USER_IDENTITY_COLUMN);
        $expression = strtolower((string) ($column->GENERATION_EXPRESSION ?? ""));
        $isCompatibleGuard = $column
            && strtolower((string) ($column->DATA_TYPE ?? "")) === "tinyint"
            && stripos((string) ($column->EXTRA ?? ""), "STORED") !== false
            && stripos((string) ($column->EXTRA ?? ""), "GENERATED") !== false
            && strpos($expression, "deleted") !== false
            && strpos($expression, "user_id") === false;
        if (!$isCompatibleGuard) {
            log_message("warning", "Live vendor contact user uniqueness skipped: live user guard is incompatible.");
            return;
        }

        // The original index included soft-deleted rows and prevented a user
        // from being re-added. The separate user_id index keeps the FK covered.
        $this->dropIndexIfExists($contacts, self::LEGACY_CONTACT_VENDOR_USER_INDEX);

        $duplicates = $this->db->query(
            "SELECT COUNT(*) AS total FROM (
                SELECT `vendor_id`, `user_id`
                FROM " . $this->quoteIdentifier($contacts) . "
                WHERE `deleted` = 0 AND `user_id` IS NOT NULL
                GROUP BY `vendor_id`, `user_id`
                HAVING COUNT(*) > 1
            ) duplicate_live_contact_users"
        )->getRow();

        if ((int) ($duplicates->total ?? 0) > 0) {
            log_message(
                "warning",
                "Unique live vendor contact user enforcement skipped because duplicate live user links exist within a CR."
            );
            return;
        }

        if (!$this->indexExists($contacts, self::CONTACT_USER_IDENTITY_INDEX)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD UNIQUE INDEX `" . self::CONTACT_USER_IDENTITY_INDEX . "`"
                . " (`vendor_id`, `user_id`, `" . self::CONTACT_USER_IDENTITY_COLUMN . "`)",
                "Unable to enforce one live contact profile per user and vendor CR."
            );
        }
    }

    private function ensureUniqueLiveCrNumbers(): void
    {
        $vendors = $this->db->prefixTable("vendors");
        if (!$this->tableExists($vendors) || !$this->columnExists($vendors, "cr_number")) {
            return;
        }

        $duplicates = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM (
                 SELECT UPPER(TRIM(`cr_number`)) AS normalized_cr
                 FROM " . $this->quoteIdentifier($vendors) . "
                 WHERE `deleted` = 0 AND NULLIF(TRIM(`cr_number`), '') IS NOT NULL
                 GROUP BY UPPER(TRIM(`cr_number`))
                 HAVING COUNT(*) > 1
             ) duplicate_crs"
        )->getRow();

        if ((int) ($duplicates->total ?? 0) > 0) {
            log_message("warning", "Unique vendor CR enforcement skipped because duplicate nonblank live CR numbers already exist.");
            return;
        }

        if (!$this->columnExists($vendors, self::CR_IDENTITY_COLUMN)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($vendors)
                . " ADD COLUMN `" . self::CR_IDENTITY_COLUMN . "` VARCHAR(100)"
                . " GENERATED ALWAYS AS (CASE WHEN `deleted` = 0"
                . " THEN NULLIF(UPPER(TRIM(`cr_number`)), '') ELSE NULL END) STORED",
                "Unable to add the normalized vendor CR identity column."
            );
        }

        $column = $this->columnInfo($vendors, self::CR_IDENTITY_COLUMN);
        $isGenerated = stripos((string) ($column->EXTRA ?? ""), "GENERATED") !== false;
        if ($column && $isGenerated && !$this->indexExists($vendors, self::CR_IDENTITY_INDEX)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($vendors)
                . " ADD UNIQUE INDEX `" . self::CR_IDENTITY_INDEX . "` (`" . self::CR_IDENTITY_COLUMN . "`)",
                "Unable to add unique live vendor CR enforcement."
            );
        }
    }

    private function alignVendorUserIdentityColumns(): void
    {
        $users = $this->db->prefixTable("users");
        $vendorUsers = $this->db->prefixTable("vendor_users");
        if (!$this->tableExists($users) || !$this->tableExists($vendorUsers)) {
            return;
        }

        $target = $this->columnInfo($users, "id");
        $definition = $this->integerDefinition($target);
        if (!$definition) {
            return;
        }

        foreach (["user_id" => false, "invited_by" => true] as $column => $nullable) {
            $source = $this->columnInfo($vendorUsers, $column);
            if (!$source || $this->integerTypesCompatible($source, $target)) {
                continue;
            }

            // An existing constraint must be managed by its owning migration.
            if ($this->columnHasForeignKey($vendorUsers, $column)) {
                log_message("warning", "Skipped aligning {$vendorUsers}.{$column}: it already has a foreign key.");
                continue;
            }

            if (!$this->valuesFitIntegerType($vendorUsers, $column, $target)) {
                log_message("warning", "Skipped aligning {$vendorUsers}.{$column}: existing values do not fit users.id.");
                continue;
            }

            $nullSql = $nullable ? "NULL DEFAULT NULL" : "NOT NULL";
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($vendorUsers)
                . " MODIFY COLUMN " . $this->quoteIdentifier($column) . " {$definition} {$nullSql}",
                "Unable to align {$vendorUsers}.{$column} with users.id."
            );
        }
    }

    private function ensureUserForeignKeys(): void
    {
        $users = $this->db->prefixTable("users");
        $contacts = $this->db->prefixTable("vendor_contacts");
        $vendorUsers = $this->db->prefixTable("vendor_users");
        if (!$this->tableExists($users)) {
            return;
        }

        $this->ensureUserForeignKey(
            $contacts,
            "user_id",
            $users,
            self::CONTACT_USER_FOREIGN_KEY,
            "SET NULL"
        );
        $this->ensureUserForeignKey(
            $vendorUsers,
            "user_id",
            $users,
            self::VENDOR_USER_FOREIGN_KEY,
            "CASCADE"
        );
    }

    private function ensureUserForeignKey(
        string $table,
        string $column,
        string $users,
        string $constraint,
        string $onDelete
    ): void {
        if (!$this->tableExists($table)
            || !$this->columnExists($table, $column)
            || $this->foreignKeyExists($table, $constraint)
            || $this->columnHasForeignKey($table, $column)) {
            return;
        }

        $source = $this->columnInfo($table, $column);
        $target = $this->columnInfo($users, "id");
        if (!$this->integerTypesCompatible($source, $target)
            || !$this->tablesSupportForeignKeys($table, $users)) {
            log_message("warning", "Skipped {$constraint}: user key types or storage engines are incompatible.");
            return;
        }

        $orphans = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM " . $this->quoteIdentifier($table) . " source_rows
             LEFT JOIN " . $this->quoteIdentifier($users) . " users ON users.`id` = source_rows."
                . $this->quoteIdentifier($column) . "
             WHERE source_rows." . $this->quoteIdentifier($column) . " IS NOT NULL
               AND users.`id` IS NULL"
        )->getRow();
        if ((int) ($orphans->total ?? 0) > 0) {
            log_message("warning", "Skipped {$constraint}: orphan user references already exist.");
            return;
        }

        $this->safeQuery(
            "ALTER TABLE " . $this->quoteIdentifier($table)
            . " ADD CONSTRAINT " . $this->quoteIdentifier($constraint)
            . " FOREIGN KEY (" . $this->quoteIdentifier($column) . ")"
            . " REFERENCES " . $this->quoteIdentifier($users) . " (`id`)"
            . " ON DELETE {$onDelete}",
            "Unable to add {$constraint}."
        );
    }

    private function tableExists(string $table): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [$this->schemaName(), $table]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->columnInfo($table, $column) !== null;
    }

    private function columnInfo(string $table, string $column): ?object
    {
        $row = $this->db->query(
            "SELECT `DATA_TYPE`, `COLUMN_TYPE`, `IS_NULLABLE`, `EXTRA`, `GENERATION_EXPRESSION`
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1",
            [$this->schemaName(), $table, $column]
        )->getRow();

        return $row ?: null;
    }

    private function integerDefinition(?object $column): string
    {
        if (!$column) {
            return "";
        }

        $type = strtolower((string) ($column->DATA_TYPE ?? ""));
        if (!in_array($type, ["tinyint", "smallint", "mediumint", "int", "bigint"], true)) {
            return "";
        }

        $unsigned = stripos((string) ($column->COLUMN_TYPE ?? ""), "unsigned") !== false;
        return strtoupper($type) . ($unsigned ? " UNSIGNED" : "");
    }

    private function integerTypesCompatible(?object $left, ?object $right): bool
    {
        return $left && $right && $this->integerDefinition($left) === $this->integerDefinition($right);
    }

    private function valuesFitIntegerType(string $table, string $column, object $target): bool
    {
        $type = strtolower((string) ($target->DATA_TYPE ?? ""));
        $unsigned = stripos((string) ($target->COLUMN_TYPE ?? ""), "unsigned") !== false;
        $ranges = [
            "tinyint" => $unsigned ? ["0", "255"] : ["-128", "127"],
            "smallint" => $unsigned ? ["0", "65535"] : ["-32768", "32767"],
            "mediumint" => $unsigned ? ["0", "16777215"] : ["-8388608", "8388607"],
            "int" => $unsigned ? ["0", "4294967295"] : ["-2147483648", "2147483647"],
            "bigint" => $unsigned
                ? ["0", "18446744073709551615"]
                : ["-9223372036854775808", "9223372036854775807"],
        ];
        if (!isset($ranges[$type])) {
            return false;
        }

        [$minimum, $maximum] = $ranges[$type];
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM " . $this->quoteIdentifier($table)
            . " WHERE " . $this->quoteIdentifier($column) . " IS NOT NULL"
            . " AND (" . $this->quoteIdentifier($column) . " < {$minimum}"
            . " OR " . $this->quoteIdentifier($column) . " > {$maximum})"
        )->getRow();

        return (int) ($row->total ?? 0) === 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$this->schemaName(), $table, $index]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    /**
     * @return string[]
     */
    private function indexColumns(string $table, string $index): array
    {
        $rows = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX ASC",
            [$this->schemaName(), $table, $index]
        )->getResult();

        $columns = [];
        foreach ($rows as $row) {
            $column = (string) ($row->COLUMN_NAME ?? "");
            if ($column !== "") {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function indexIsUnique(string $table, string $index): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? AND NON_UNIQUE = 0",
            [$this->schemaName(), $table, $index]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    private function hasLeadingIndex(string $table, string $column): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1",
            [$this->schemaName(), $table, $column]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    private function columnHasForeignKey(string $table, string $column): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$this->schemaName(), $table, $column]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
            [$this->schemaName(), $table, $constraint]
        )->getRow();

        return (int) ($row->total ?? 0) > 0;
    }

    private function tablesSupportForeignKeys(string $left, string $right): bool
    {
        $rows = $this->db->query(
            "SELECT `ENGINE` FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN (?, ?)",
            [$this->schemaName(), $left, $right]
        )->getResult();

        return count($rows) === 2
            && strtoupper((string) ($rows[0]->ENGINE ?? "")) === "INNODB"
            && strtoupper((string) ($rows[1]->ENGINE ?? "")) === "INNODB";
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->tableExists($table) && $this->indexExists($table, $index)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($table)
                . " DROP INDEX " . $this->quoteIdentifier($index),
                "Unable to drop index {$index}."
            );
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if ($this->tableExists($table) && $this->foreignKeyExists($table, $constraint)) {
            $this->safeQuery(
                "ALTER TABLE " . $this->quoteIdentifier($table)
                . " DROP FOREIGN KEY " . $this->quoteIdentifier($constraint),
                "Unable to drop foreign key {$constraint}."
            );
        }
    }

    private function schemaName(): string
    {
        return (string) $this->db->database;
    }

    private function isSafeIdentifier(string $identifier): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_$]+$/', $identifier);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!$this->isSafeIdentifier($identifier)) {
            throw new \InvalidArgumentException("Unsafe SQL identifier: {$identifier}");
        }

        return "`{$identifier}`";
    }

    private function safeQuery(string $sql, string $warning): bool
    {
        try {
            return (bool) $this->db->query($sql);
        } catch (\Throwable $exception) {
            log_message("warning", $warning . " " . $exception->getMessage());
            return false;
        }
    }
}
