<?php

namespace App\Models;

class Tender_rfq_items_model extends Crud_model
{
    protected $table = null;
    private static bool $schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_rfq_items";
        parent::__construct($this->table);
        $this->ensure_schema();
    }

    public function ensure_schema(): void
    {
        if (self::$schema_checked) {
            return;
        }

        $table = $this->db->prefixTable("tender_rfq_items");
        $tenders = $this->db->prefixTable("tenders");

        if (!$this->_table_exists($table)) {
            $this->db->query(
                "CREATE TABLE `$table` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `tender_id` BIGINT UNSIGNED NOT NULL,
                    `sr_no` VARCHAR(30) DEFAULT NULL,
                    `description` TEXT DEFAULT NULL,
                    `uom` VARCHAR(50) DEFAULT NULL,
                    `qty` DECIMAL(18,3) DEFAULT NULL,
                    `unit_price` DECIMAL(18,3) DEFAULT NULL,
                    `brand` VARCHAR(150) DEFAULT NULL,
                    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at` DATETIME DEFAULT NULL,
                    `updated_at` DATETIME DEFAULT NULL,
                    `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `tender_rfq_items_tender_id_idx` (`tender_id`)
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

    public function get_by_tender(int $tender_id): array
    {
        $table = $this->db->prefixTable("tender_rfq_items");

        return $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted = 0
               AND tender_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$tender_id]
        )->getResult();
    }

    public function sync_items(int $tender_id, array $items): void
    {
        $table = $this->db->prefixTable("tender_rfq_items");
        $now = date("Y-m-d H:i:s");

        $this->db->query("UPDATE $table SET deleted = 1, updated_at = ? WHERE tender_id = ?", [$now, $tender_id]);

        $sort = 1;
        foreach ($items as $item) {
            $description = trim((string) ($item["description"] ?? ""));
            $sr_no = trim((string) ($item["sr_no"] ?? ""));
            $uom = trim((string) ($item["uom"] ?? ""));
            $qty = $this->_decimal_or_null($item["qty"] ?? null);
            $unit_price = $this->_decimal_or_null($item["unit_price"] ?? null);
            $brand = trim((string) ($item["brand"] ?? ""));

            if ($description === "" && $sr_no === "" && $uom === "" && $qty === null && $unit_price === null && $brand === "") {
                continue;
            }

            $this->ci_save(clean_data([
                "tender_id" => $tender_id,
                "sr_no" => $sr_no ?: (string) $sort,
                "description" => $description ?: null,
                "uom" => $uom ?: null,
                "qty" => $qty,
                "unit_price" => $unit_price,
                "brand" => $brand ?: null,
                "sort_order" => $sort,
                "created_at" => $now,
                "updated_at" => $now,
                "deleted" => 0,
            ]));

            $sort++;
        }
    }

    private function _decimal_or_null($value): ?string
    {
        $value = trim((string) $value);
        if ($value === "") {
            return null;
        }

        return is_numeric($value) ? number_format((float) $value, 3, ".", "") : null;
    }
}
