<?php

namespace App\Models;

class Tender_bid_requirements_model extends Crud_model
{
    protected $table = null;

    public const DEFAULT_CODES = [
        "technical",
        "commercial_priced",
        "commercial_unpriced",
        "bank_guarantee",
    ];

    public function __construct()
    {
        $this->table = "tender_bid_requirements";
        parent::__construct($this->table);
    }

    public function get_details(array $options = [])
    {
        $tbl = $this->db->prefixTable("tender_bid_requirements");
        $where = "WHERE $tbl.deleted=0";

        if ($tender_id = (int) get_array_value($options, "tender_id")) {
            $where .= " AND $tbl.tender_id=" . $tender_id;
        }

        $sql = "SELECT *
                FROM $tbl
                $where
                ORDER BY $tbl.sort_order ASC, $tbl.id ASC";

        return $this->db->query($sql);
    }

    public function get_required_codes(int $tender_id): array
    {
        $rows = $this->get_details(["tender_id" => $tender_id])->getResult();
        if (!$rows) {
            return self::DEFAULT_CODES;
        }

        $codes = [];
        foreach ($rows as $row) {
            if ((int) ($row->is_required ?? 0) === 1 && !empty($row->code)) {
                $codes[] = (string) $row->code;
            }
        }

        return array_values(array_unique($codes ?: self::DEFAULT_CODES));
    }

    public function sync_requirements(int $tender_id, array $required_codes): void
    {
        $tbl = $this->db->prefixTable("tender_bid_requirements");
        $labels = $this->get_default_labels();
        $normalized = [];

        foreach ($required_codes as $code) {
            $code = trim((string) $code);
            if ($code !== "" && isset($labels[$code])) {
                $normalized[] = $code;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if (!$normalized) {
            $normalized = self::DEFAULT_CODES;
        }

        $this->db->query("UPDATE $tbl SET deleted=1 WHERE tender_id=?", [$tender_id]);

        $now = date("Y-m-d H:i:s");
        $sort_order = 1;
        foreach ($labels as $code => $label) {
            $this->db->query(
                "INSERT INTO $tbl (tender_id, code, label, is_required, sort_order, created_at, updated_at, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 0)",
                [
                    $tender_id,
                    $code,
                    $label,
                    in_array($code, $normalized, true) ? 1 : 0,
                    $sort_order++,
                    $now,
                    $now,
                ]
            );
        }
    }

    public function get_default_labels(): array
    {
        return [
            "technical" => "Technical Proposal",
            "commercial_priced" => "Commercial Proposal (With Price)",
            "commercial_unpriced" => "Commercial Proposal (Without Price)",
            "bank_guarantee" => "Bank Guarantee Documents",
        ];
    }
}
