<?php

namespace App\Models;

use CodeIgniter\I18n\Time;

class Tender_bid_openings_model extends Crud_model
{
    protected $table = null;
    private static bool $single_opening_signature_schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_bid_openings";
        parent::__construct($this->table);
        $this->ensure_single_opening_signature_schema();
    }

    public function ensure_single_opening_signature_schema(): void
    {
        if (self::$single_opening_signature_schema_checked) {
            return;
        }

        $openings = $this->db->prefixTable("tender_bid_openings");
        $entries = $this->db->prefixTable("tender_bid_opening_entries");

        $status_info = $this->_column_info($openings, "status");
        if ($status_info && stripos((string) ($status_info->Type ?? ""), "varchar") === false) {
            $this->db->query("ALTER TABLE `$openings` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'codes_generated'");
        }

        $opening_columns = [
            "signed_at" => "ALTER TABLE `$openings` ADD COLUMN `signed_at` DATETIME DEFAULT NULL AFTER `unlocked_at`",
            "manual_form_path" => "ALTER TABLE `$openings` ADD COLUMN `manual_form_path` VARCHAR(255) DEFAULT NULL AFTER `signed_at`",
            "manual_form_original_name" => "ALTER TABLE `$openings` ADD COLUMN `manual_form_original_name` VARCHAR(255) DEFAULT NULL AFTER `manual_form_path`",
            "manual_form_uploaded_by" => "ALTER TABLE `$openings` ADD COLUMN `manual_form_uploaded_by` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `manual_form_original_name`",
            "manual_form_uploaded_at" => "ALTER TABLE `$openings` ADD COLUMN `manual_form_uploaded_at` DATETIME DEFAULT NULL AFTER `manual_form_uploaded_by`",
        ];

        foreach ($opening_columns as $column => $sql) {
            if (!$this->_column_exists($openings, $column)) {
                $this->db->query($sql);
            }
        }

        $entry_columns = [
            "signature_statement" => "ALTER TABLE `$entries` ADD COLUMN `signature_statement` TEXT DEFAULT NULL AFTER `confirmed_at`",
            "signature_name" => "ALTER TABLE `$entries` ADD COLUMN `signature_name` VARCHAR(255) DEFAULT NULL AFTER `signature_statement`",
            "signed_at" => "ALTER TABLE `$entries` ADD COLUMN `signed_at` DATETIME DEFAULT NULL AFTER `signature_name`",
            "signature_ip_address" => "ALTER TABLE `$entries` ADD COLUMN `signature_ip_address` VARCHAR(45) DEFAULT NULL AFTER `signed_at`",
            "signature_user_agent" => "ALTER TABLE `$entries` ADD COLUMN `signature_user_agent` TEXT DEFAULT NULL AFTER `signature_ip_address`",
        ];

        foreach ($entry_columns as $column => $sql) {
            if (!$this->_column_exists($entries, $column)) {
                $this->db->query($sql);
            }
        }

        self::$single_opening_signature_schema_checked = true;
    }

    private function _column_exists(string $table, string $column): bool
    {
        return (bool) $this->_column_info($table, $column);
    }

    private function _column_info(string $table, string $column)
    {
        return $this->db->query(
            "SHOW COLUMNS FROM `$table` LIKE " . $this->db->escape($column)
        )->getRow();
    }

    private function get_tender_business_now(): string
    {
        return Time::now('Asia/Muscat')->toDateTimeString();
    }

    public function expire_old_sessions(): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='expired', updated_at=?
             WHERE deleted=0
               AND status='codes_generated'
               AND expires_at IS NOT NULL
               AND expires_at < ?",
            [$now, $now]
        );
    }

    public function get_active_session(int $tender_id, string $stage = "technical")
    {
        $this->expire_old_sessions();
        $stage = $this->_normalize_stage($stage);

        $tbo = $this->db->prefixTable("tender_bid_openings");

        $sql = "SELECT *
                FROM $tbo
                WHERE deleted=0
                  AND tender_id=?
                  AND stage=?
                  AND status IN ('codes_generated','unlocked','signed','manual_accepted')
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql, [$tender_id, $stage])->getRow();
    }

    public function get_confirmation_map(int $opening_id): array
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $sql = "SELECT role, COUNT(*) AS total
                FROM $tbl
                WHERE deleted=0
                  AND tender_bid_opening_id=?
                  AND is_valid=1
                GROUP BY role";

        $rows = $this->db->query($sql, [$opening_id])->getResult();
        $map = [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        foreach ($rows as $row) {
            $map[$row->role] = (int) $row->total;
        }

        return $map;
    }

    public function get_signature_map(int $opening_id): array
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $rows = $this->db->query(
            "SELECT role, COUNT(*) AS total
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND is_valid=1
               AND signed_at IS NOT NULL
             GROUP BY role",
            [$opening_id]
        )->getResult();

        $map = [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        foreach ($rows as $row) {
            $map[$row->role] = (int) $row->total;
        }

        return $map;
    }

    public function all_required_signatures_completed(int $opening_id): bool
    {
        $map = $this->get_signature_map($opening_id);
        return $map["chairman"] >= 1 && $map["secretary"] >= 1 && $map["itc_member"] >= 1;
    }

    public function user_already_confirmed(int $opening_id, int $user_id): bool
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $row = $this->db->query(
            "SELECT id
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND user_id=?
               AND is_valid=1
             LIMIT 1",
            [$opening_id, $user_id]
        )->getRow();

        return !!$row;
    }

    public function create_new_session(int $tender_id, int $actor_id, string $stage = "technical"): int
    {
        $stage = $this->_normalize_stage($stage);

        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();
        $expires = Time::parse($now, 'Asia/Muscat')->addMinutes(5)->toDateTimeString();

        $this->db->query(
            "UPDATE $tbo
             SET status='expired', updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status='codes_generated'",
            [$now, $tender_id, $stage]
        );

        $data = [
            "tender_id"       => $tender_id,
            "stage"           => $stage,
            "status"          => "codes_generated",
            "chairman_code"   => (string) random_int(100000, 999999),
            "secretary_code"  => (string) random_int(100000, 999999),
            "member_code"     => (string) random_int(100000, 999999),
            "generated_by"    => $actor_id,
            "generated_at"    => $now,
            "expires_at"      => $expires,
            "created_at"      => $now,
            "updated_at"      => $now,
            "deleted"         => 0
        ];

        return (int) $this->ci_save($data);
    }

    public function save_confirmation(
        int $opening_id,
        int $user_id,
        string $role,
        string $chairman_code,
        string $secretary_code,
        string $member_code,
        bool $is_valid
    ): void {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "INSERT INTO $tbl
            (tender_bid_opening_id, user_id, role, input_chairman_code, input_secretary_code, input_member_code, is_valid, confirmed_at, ip_address, user_agent, created_at, updated_at, deleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $opening_id,
                $user_id,
                $role,
                $chairman_code,
                $secretary_code,
                $member_code,
                $is_valid ? 1 : 0,
                $now,
                get_real_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $now,
                $now
            ]
        );
    }

    public function unlock_session(int $opening_id): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='unlocked',
                 unlocked_at=?,
                 updated_at=?
             WHERE id=?",
            [$now, $now, $opening_id]
        );
    }

    public function save_signature(int $opening_id, int $user_id, string $role, string $statement, string $signature_name): bool
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");
        $now = $this->get_tender_business_now();

        $entry = $this->db->query(
            "SELECT id
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND user_id=?
               AND role=?
               AND is_valid=1
             ORDER BY id DESC
             LIMIT 1",
            [$opening_id, $user_id, $role]
        )->getRow();

        if (!$entry) {
            return false;
        }

        $this->db->query(
            "UPDATE $tbl
             SET signature_statement=?,
                 signature_name=?,
                 signed_at=IFNULL(signed_at, ?),
                 signature_ip_address=?,
                 signature_user_agent=?,
                 updated_at=?
             WHERE id=?",
            [
                $statement,
                $signature_name,
                $now,
                get_real_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $now,
                (int) $entry->id,
            ]
        );

        if ($this->all_required_signatures_completed($opening_id)) {
            $this->mark_all_signatures_completed($opening_id);
        }

        return true;
    }

    public function mark_all_signatures_completed(int $opening_id): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='signed',
                 signed_at=IFNULL(signed_at, ?),
                 updated_at=?
             WHERE id=?
               AND deleted=0
               AND status IN ('unlocked','signed')",
            [$now, $now, $opening_id]
        );
    }

    public function mark_manual_form_accepted(int $tender_id, int $actor_id, string $path, string $original_name): int
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $session = $this->get_active_session($tender_id, "technical");
        if ($session) {
            $this->db->query(
                "UPDATE $tbo
                 SET status='manual_accepted',
                     signed_at=IFNULL(signed_at, ?),
                     manual_form_path=?,
                     manual_form_original_name=?,
                     manual_form_uploaded_by=?,
                     manual_form_uploaded_at=?,
                     unlocked_at=IFNULL(unlocked_at, ?),
                     updated_at=?
                 WHERE id=?",
                [$now, $path, $original_name, $actor_id, $now, $now, $now, (int) $session->id]
            );

            return (int) $session->id;
        }

        $this->db->query(
            "INSERT INTO $tbo
                (tender_id, stage, status, chairman_code, secretary_code, member_code, generated_by, generated_at, expires_at, unlocked_at, signed_at, manual_form_path, manual_form_original_name, manual_form_uploaded_by, manual_form_uploaded_at, created_at, updated_at, deleted)
             VALUES
                (?, 'technical', 'manual_accepted', 'MANUAL', 'MANUAL', 'MANUAL', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $actor_id,
                $now,
                $now,
                $now,
                $now,
                $path,
                $original_name,
                $actor_id,
                $now,
                $now,
                $now,
            ]
        );

        return (int) $this->db->insertID();
    }

    public function get_completed_session_for_technical_start(int $tender_id)
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");

        return $this->db->query(
            "SELECT *
             FROM $tbo
             WHERE deleted=0
               AND tender_id=?
               AND stage='technical'
               AND status IN ('signed','manual_accepted')
             ORDER BY id DESC
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    public function get_signature_rows(int $opening_id): array
    {
        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $entries.*,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS member_name,
                $users.email AS member_email
             FROM $entries
             LEFT JOIN $users ON $users.id = $entries.user_id
             WHERE $entries.deleted=0
               AND $entries.tender_bid_opening_id=?
               AND $entries.is_valid=1
             ORDER BY FIELD($entries.role, 'chairman', 'secretary', 'itc_member'), $entries.confirmed_at ASC, $entries.id ASC",
            [$opening_id]
        )->getResult();
    }

    public function get_bid_summary_for_opening(int $tender_id): array
    {
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");

        return $this->db->query(
            "SELECT
                $tb.id AS bid_id,
                $tb.vendor_id,
                $tb.status AS bid_status,
                $tb.submitted_at,
                $tb.total_amount,
                $tb.currency,
                $v.vendor_name,
                docs.technical_doc_id,
                docs.technical_doc_name,
                docs.commercial_unpriced_doc_id,
                docs.commercial_unpriced_doc_name,
                COALESCE(docs.commercial_priced_doc_id, docs.commercial_legacy_doc_id) AS commercial_priced_doc_id,
                COALESCE(docs.commercial_priced_doc_name, docs.commercial_legacy_doc_name) AS commercial_priced_doc_name,
                docs.bank_guarantee_doc_id,
                docs.bank_guarantee_doc_name
             FROM $tb
             INNER JOIN $v
                ON $v.id = $tb.vendor_id
               AND $v.deleted = 0
             LEFT JOIN (
                SELECT
                    tender_bid_id,
                    MAX(CASE WHEN section = 'technical' THEN id ELSE NULL END) AS technical_doc_id,
                    MAX(CASE WHEN section = 'technical' THEN original_name ELSE NULL END) AS technical_doc_name,
                    MAX(CASE WHEN section = 'commercial_unpriced' THEN id ELSE NULL END) AS commercial_unpriced_doc_id,
                    MAX(CASE WHEN section = 'commercial_unpriced' THEN original_name ELSE NULL END) AS commercial_unpriced_doc_name,
                    MAX(CASE WHEN section = 'commercial_priced' THEN id ELSE NULL END) AS commercial_priced_doc_id,
                    MAX(CASE WHEN section = 'commercial_priced' THEN original_name ELSE NULL END) AS commercial_priced_doc_name,
                    MAX(CASE WHEN section = 'commercial' THEN id ELSE NULL END) AS commercial_legacy_doc_id,
                    MAX(CASE WHEN section = 'commercial' THEN original_name ELSE NULL END) AS commercial_legacy_doc_name,
                    MAX(CASE WHEN section = 'bank_guarantee' THEN id ELSE NULL END) AS bank_guarantee_doc_id,
                    MAX(CASE WHEN section = 'bank_guarantee' THEN original_name ELSE NULL END) AS bank_guarantee_doc_name
                FROM $tbd
                WHERE deleted=0
                GROUP BY tender_bid_id
             ) docs ON docs.tender_bid_id = $tb.id
             WHERE $tb.deleted=0
               AND $tb.tender_id=?
               AND $tb.status <> 'draft'
             ORDER BY $v.vendor_name ASC, $tb.submitted_at ASC",
            [$tender_id]
        )->getResult();
    }

    private function _normalize_stage(string $stage): string
    {
        $stage = strtolower(trim($stage));
        return in_array($stage, ["technical", "commercial"], true) ? $stage : "technical";
    }
}
