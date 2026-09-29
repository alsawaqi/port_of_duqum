<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

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

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_rfq_items" => [
                "id", "tender_id", "sr_no", "description", "uom", "qty", "unit_price",
                "brand", "sort_order", "created_at", "updated_at", "deleted",
            ],
        ], "tender RFQ items");

        self::$schema_checked = true;
    }

    public function get_by_tender(int $tender_id): array
    {
        $table = $this->db->prefixTable("tender_rfq_items");

        $rows = $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted = 0
               AND tender_id = ?
             ORDER BY sort_order ASC, id ASC",
            [$tender_id]
        )->getResult();

        // `brand` remains the legacy storage column so existing databases do
        // not require a destructive rename. Tender code uses Part No wording.
        foreach ($rows as $row) {
            $row->part_no = $row->brand ?? null;
        }

        return $rows;
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
            $part_no = trim((string) ($item["part_no"] ?? ($item["brand"] ?? "")));

            if ($description === "" && $sr_no === "" && $uom === "" && $qty === null && $unit_price === null && $part_no === "") {
                continue;
            }

            $this->ci_save(clean_data([
                "tender_id" => $tender_id,
                "sr_no" => $sr_no ?: (string) $sort,
                "description" => $description ?: null,
                "uom" => $uom ?: null,
                "qty" => $qty,
                "unit_price" => $unit_price,
                "brand" => $part_no ?: null,
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
