<?php

namespace App\Models;

class Tender_rfq_details_model extends Crud_model
{
    protected $table = null;
    private static bool $schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_rfq_details";
        parent::__construct($this->table);
        $this->ensure_schema();
    }

    public function ensure_schema(): void
    {
        if (self::$schema_checked) {
            return;
        }

        $table = $this->db->prefixTable("tender_rfq_details");
        $tenders = $this->db->prefixTable("tenders");

        if (!$this->_table_exists($table)) {
            $this->db->query(
                "CREATE TABLE `$table` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tender_id` BIGINT UNSIGNED NOT NULL,
                    `rfq_no` VARCHAR(100) DEFAULT NULL,
                    `rfq_date` DATE DEFAULT NULL,
                    `pr_no` VARCHAR(100) DEFAULT NULL,
                    `delivery_location` VARCHAR(255) DEFAULT NULL,
                    `incoterm` VARCHAR(100) DEFAULT NULL,
                    `material_required_on` DATE DEFAULT NULL,
                    `terms_reference` VARCHAR(255) DEFAULT NULL,
                    `notes` TEXT DEFAULT NULL,
                    `enclosures` TEXT DEFAULT NULL,
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `tender_rfq_details_tender_unique` (`tender_id`),
                    KEY `tender_rfq_details_tender_id_idx` (`tender_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            $this->db->query(
                "ALTER TABLE `$table`
                 ADD CONSTRAINT `{$table}_tender_fk`
                 FOREIGN KEY (`tender_id`) REFERENCES `$tenders` (`id`)
                 ON DELETE CASCADE"
            );
        }

        self::$schema_checked = true;
    }

    private function _table_exists(string $table): bool
    {
        $row = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table))->getRow();
        return (bool) $row;
    }

    public function get_by_tender(int $tender_id)
    {
        $table = $this->db->prefixTable("tender_rfq_details");

        return $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted = 0
               AND tender_id = ?
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    public function sync_detail(int $tender_id, array $data): void
    {
        $existing = $this->get_by_tender($tender_id);
        $now = date("Y-m-d H:i:s");

        $payload = [
            "tender_id" => $tender_id,
            "rfq_no" => $data["rfq_no"] ?? null,
            "rfq_date" => $data["rfq_date"] ?? null,
            "pr_no" => $data["pr_no"] ?? null,
            "delivery_location" => $data["delivery_location"] ?? null,
            "incoterm" => $data["incoterm"] ?? null,
            "material_required_on" => $data["material_required_on"] ?? null,
            "terms_reference" => $data["terms_reference"] ?? null,
            "notes" => $data["notes"] ?? null,
            "enclosures" => $data["enclosures"] ?? null,
            "updated_at" => $now,
            "deleted" => 0,
        ];

        if (empty($existing->id)) {
            $payload["created_at"] = $now;
        }

        $this->ci_save(clean_data($payload), (int) ($existing->id ?? 0));
    }
}
