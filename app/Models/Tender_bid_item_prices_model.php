<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Tender_bid_item_prices_model extends Crud_model
{
    protected $table = null;
    private static bool $schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_bid_item_prices";
        parent::__construct($this->table);
        $this->ensure_schema();
    }

    public function ensure_schema(): void
    {
        if (self::$schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_bid_item_prices" => [
                "id", "tender_bid_id", "tender_id", "vendor_id", "tender_rfq_item_id",
                "qty", "unit_price", "line_total", "created_at", "updated_at", "deleted",
            ],
        ], "tender bid item pricing");

        self::$schema_checked = true;
    }

    public function prepare_submitted_item_prices(array $rfq_items, $submitted_prices): array
    {
        if (!$rfq_items) {
            return [
                "success" => true,
                "has_items" => false,
                "rows" => [],
                "total_amount" => null,
            ];
        }

        $submitted_prices = is_array($submitted_prices) ? $submitted_prices : [];
        $rows = [];
        $total = 0.0;

        foreach ($rfq_items as $item) {
            $item_id = (int) ($item->id ?? 0);
            $raw_price = trim((string) ($submitted_prices[$item_id] ?? ""));

            if (!$item_id || $raw_price === "") {
                return [
                    "success" => false,
                    "message" => "Please enter a unit price for every tender item.",
                ];
            }

            if (!is_numeric($raw_price) || (float) $raw_price < 0) {
                return [
                    "success" => false,
                    "message" => "Tender item prices must be valid positive numbers.",
                ];
            }

            $unit_price = round((float) $raw_price, 3);
            $qty = ($item->qty !== null && $item->qty !== "" && is_numeric($item->qty)) ? round((float) $item->qty, 3) : null;
            $line_total = $qty !== null ? round($qty * $unit_price, 3) : null;

            if ($line_total !== null) {
                $total += $line_total;
            }

            $rows[] = [
                "tender_rfq_item_id" => $item_id,
                "qty" => $qty !== null ? number_format($qty, 3, ".", "") : null,
                "unit_price" => number_format($unit_price, 3, ".", ""),
                "line_total" => $line_total !== null ? number_format($line_total, 3, ".", "") : null,
            ];
        }

        return [
            "success" => true,
            "has_items" => true,
            "rows" => $rows,
            "total_amount" => number_format($total, 3, ".", ""),
        ];
    }

    public function sync_bid_item_prices(int $bid_id, int $tender_id, int $vendor_id, array $rows): void
    {
        $table = $this->db->prefixTable("tender_bid_item_prices");
        $now = date("Y-m-d H:i:s");

        $this->db->query(
            "UPDATE $table
             SET deleted = 1, updated_at = ?
             WHERE tender_bid_id = ?",
            [$now, $bid_id]
        );

        foreach ($rows as $row) {
            $this->ci_save(clean_data([
                "tender_bid_id" => $bid_id,
                "tender_id" => $tender_id,
                "vendor_id" => $vendor_id,
                "tender_rfq_item_id" => (int) ($row["tender_rfq_item_id"] ?? 0),
                "qty" => $row["qty"] ?? null,
                "unit_price" => $row["unit_price"] ?? "0.000",
                "line_total" => $row["line_total"] ?? null,
                "created_at" => $now,
                "updated_at" => $now,
                "deleted" => 0,
            ]));
        }
    }

    public function get_price_map(int $bid_id): array
    {
        $table = $this->db->prefixTable("tender_bid_item_prices");
        $rows = $this->db->query(
            "SELECT *
             FROM $table
             WHERE tender_bid_id = ?
               AND deleted = 0
             ORDER BY id DESC",
            [$bid_id]
        )->getResult();

        $map = [];
        foreach ($rows as $row) {
            $item_id = (int) ($row->tender_rfq_item_id ?? 0);
            if ($item_id && !isset($map[$item_id])) {
                $map[$item_id] = $row;
            }
        }

        return $map;
    }

    public function get_bid_item_prices(int $bid_id): array
    {
        $bids = $this->db->prefixTable("tender_bids");
        $rfq_items = $this->db->prefixTable("tender_rfq_items");
        $prices = $this->db->prefixTable("tender_bid_item_prices");

        $bid = $this->db->query(
            "SELECT tender_id
             FROM $bids
             WHERE id = ?
               AND deleted = 0
             LIMIT 1",
            [$bid_id]
        )->getRow();

        if (!$bid) {
            return [];
        }

        return $this->db->query(
            "SELECT
                $rfq_items.id AS tender_rfq_item_id,
                $rfq_items.sr_no,
                $rfq_items.description,
                $rfq_items.uom,
                $rfq_items.qty,
                $rfq_items.brand AS part_no,
                $prices.unit_price AS vendor_unit_price,
                $prices.line_total
             FROM $rfq_items
             LEFT JOIN $prices
                ON $prices.tender_rfq_item_id = $rfq_items.id
               AND $prices.tender_bid_id = ?
               AND $prices.deleted = 0
             WHERE $rfq_items.tender_id = ?
               AND $rfq_items.deleted = 0
             ORDER BY $rfq_items.sort_order ASC, $rfq_items.id ASC",
            [$bid_id, (int) $bid->tender_id]
        )->getResult();
    }

}
