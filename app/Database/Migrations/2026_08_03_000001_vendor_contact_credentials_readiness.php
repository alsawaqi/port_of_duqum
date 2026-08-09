<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Records whether a vendor membership has usable credentials and verifies the
 * database-level contact email boundary used by the multi-CR portal.
 *
 * The live contact email identity predates this migration. It is intentionally
 * verified (and created only when absent), never dropped or replaced here. A
 * malformed identity/index or legacy duplicate data stops the migration so a
 * deployment cannot silently continue without the same-CR duplicate guard.
 */
class Vendor_contact_credentials_readiness extends Migration
{
    private const CONTACT_EMAIL_IDENTITY_COLUMN = "live_email_identity";
    private const CONTACT_EMAIL_IDENTITY_INDEX = "uq_vendor_contacts_live_email";
    private const CREDENTIALS_READY_COLUMN = "credentials_ready_at";

    public function up()
    {
        $this->ensureNormalizedLiveContactEmailUniqueness();
        $this->ensureCredentialsReadyTimestamp();
    }

    public function down()
    {
        $vendorUsers = $this->db->prefixTable("vendor_users");
        if ($this->tableExists($vendorUsers)
            && $this->columnInfo($vendorUsers, self::CREDENTIALS_READY_COLUMN)) {
            $this->queryOrFail(
                "ALTER TABLE " . $this->quoteIdentifier($vendorUsers)
                . " DROP COLUMN `" . self::CREDENTIALS_READY_COLUMN . "`",
                "Unable to remove the vendor credential-readiness timestamp."
            );
        }

        // The normalized contact identity and its unique index belong to the
        // earlier multi-CR foundation. A rollback of this migration must never
        // weaken that existing security boundary.
    }

    private function ensureCredentialsReadyTimestamp(): void
    {
        $vendorUsers = $this->db->prefixTable("vendor_users");
        if (!$this->tableExists($vendorUsers)) {
            throw new \RuntimeException("The vendor_users table is required before adding credential readiness.");
        }

        $column = $this->columnInfo($vendorUsers, self::CREDENTIALS_READY_COLUMN);
        if (!$column) {
            $after = $this->columnInfo($vendorUsers, "invited_at") ? " AFTER `invited_at`" : "";
            $this->queryOrFail(
                "ALTER TABLE " . $this->quoteIdentifier($vendorUsers)
                . " ADD COLUMN `" . self::CREDENTIALS_READY_COLUMN . "` DATETIME NULL DEFAULT NULL"
                . $after,
                "Unable to add the vendor credential-readiness timestamp."
            );
            $column = $this->columnInfo($vendorUsers, self::CREDENTIALS_READY_COLUMN);
        }

        if (!$column
            || strtolower((string) ($column->DATA_TYPE ?? "")) !== "datetime"
            || strtoupper((string) ($column->IS_NULLABLE ?? "")) !== "YES") {
            throw new \RuntimeException(
                "vendor_users.credentials_ready_at must be a nullable DATETIME column."
            );
        }
    }

    private function ensureNormalizedLiveContactEmailUniqueness(): void
    {
        $contacts = $this->db->prefixTable("vendor_contacts");
        if (!$this->tableExists($contacts)) {
            throw new \RuntimeException("The vendor_contacts table is required before verifying contact identities.");
        }

        foreach (["vendor_id", "email", "deleted"] as $requiredColumn) {
            if (!$this->columnInfo($contacts, $requiredColumn)) {
                throw new \RuntimeException(
                    "vendor_contacts.{$requiredColumn} is required for normalized same-CR email uniqueness."
                );
            }
        }

        $identity = $this->columnInfo($contacts, self::CONTACT_EMAIL_IDENTITY_COLUMN);
        if (!$identity) {
            $this->queryOrFail(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD COLUMN `" . self::CONTACT_EMAIL_IDENTITY_COLUMN . "` VARCHAR(255)"
                . " GENERATED ALWAYS AS (CASE WHEN `deleted` = 0"
                . " THEN NULLIF(LOWER(TRIM(`email`)), '') ELSE NULL END) STORED",
                "Unable to add the normalized live vendor contact email identity."
            );
            $identity = $this->columnInfo($contacts, self::CONTACT_EMAIL_IDENTITY_COLUMN);
        }

        if (!$this->isCompatibleLiveEmailIdentity($identity)) {
            throw new \RuntimeException(
                "vendor_contacts.live_email_identity is incompatible; refusing to weaken the contact email guard."
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
            throw new \RuntimeException(
                "Duplicate live contact emails exist within one or more CRs; resolve them before migrating."
            );
        }

        $index = $this->indexDefinition($contacts, self::CONTACT_EMAIL_IDENTITY_INDEX);
        if ($index && !$this->isExpectedUniqueEmailIndex($index)) {
            throw new \RuntimeException(
                "uq_vendor_contacts_live_email exists with an incompatible definition; refusing to replace it automatically."
            );
        }

        if (!$index) {
            $this->queryOrFail(
                "ALTER TABLE " . $this->quoteIdentifier($contacts)
                . " ADD UNIQUE INDEX `" . self::CONTACT_EMAIL_IDENTITY_INDEX . "`"
                . " (`vendor_id`, `" . self::CONTACT_EMAIL_IDENTITY_COLUMN . "`)",
                "Unable to enforce normalized live contact email uniqueness within each CR."
            );
            $index = $this->indexDefinition($contacts, self::CONTACT_EMAIL_IDENTITY_INDEX);
        }

        if (!$index || !$this->isExpectedUniqueEmailIndex($index)) {
            throw new \RuntimeException(
                "Normalized live contact email uniqueness was not installed successfully."
            );
        }
    }

    private function isCompatibleLiveEmailIdentity(?object $column): bool
    {
        if (!$column
            || strtolower((string) ($column->DATA_TYPE ?? "")) !== "varchar"
            || (int) ($column->CHARACTER_MAXIMUM_LENGTH ?? 0) !== 255
            || stripos((string) ($column->EXTRA ?? ""), "STORED") === false
            || stripos((string) ($column->EXTRA ?? ""), "GENERATED") === false) {
            return false;
        }

        $expression = strtolower((string) ($column->GENERATION_EXPRESSION ?? ""));
        $expression = str_replace(["`", " ", "\t", "\r", "\n"], "", $expression);

        return strpos($expression, "deleted=0") !== false
            && strpos($expression, "email") !== false
            && strpos($expression, "trim") !== false
            && strpos($expression, "nullif") !== false
            && (strpos($expression, "lower") !== false || strpos($expression, "lcase") !== false);
    }

    /**
     * @return array{columns: string[], unique: bool}|null
     */
    private function indexDefinition(string $table, string $index): ?array
    {
        $rows = $this->db->query(
            "SELECT `COLUMN_NAME`, `NON_UNIQUE`
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY `SEQ_IN_INDEX` ASC",
            [(string) $this->db->database, $table, $index]
        )->getResult();

        if (!$rows) {
            return null;
        }

        return [
            "columns" => array_map(
                static fn($row): string => (string) ($row->COLUMN_NAME ?? ""),
                $rows
            ),
            "unique" => count(array_filter(
                $rows,
                static fn($row): bool => (int) ($row->NON_UNIQUE ?? 1) !== 0
            )) === 0,
        ];
    }

    /**
     * @param array{columns: string[], unique: bool} $index
     */
    private function isExpectedUniqueEmailIndex(array $index): bool
    {
        return $index["unique"] === true
            && $index["columns"] === ["vendor_id", self::CONTACT_EMAIL_IDENTITY_COLUMN];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [(string) $this->db->database, $table]
        )->getRow();

        return (int) ($row->total ?? 0) === 1;
    }

    private function columnInfo(string $table, string $column): ?object
    {
        $row = $this->db->query(
            "SELECT `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `IS_NULLABLE`, `EXTRA`, `GENERATION_EXPRESSION`
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1",
            [(string) $this->db->database, $table, $column]
        )->getRow();

        return $row ?: null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_$]+$/', $identifier)) {
            throw new \InvalidArgumentException("Unsafe SQL identifier: {$identifier}");
        }

        return "`{$identifier}`";
    }

    private function queryOrFail(string $sql, string $message): void
    {
        try {
            $result = $this->db->query($sql);
        } catch (\Throwable $exception) {
            throw new \RuntimeException($message, 0, $exception);
        }

        if (!$result) {
            $error = $this->db->error();
            throw new \RuntimeException($message . " " . ($error["message"] ?? "Database error."));
        }
    }
}
