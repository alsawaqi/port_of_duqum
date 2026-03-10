<?php

namespace App\Models;

class Tender_criteria_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_criteria";
        parent::__construct($this->table);
    }

    public function get_stage_criteria(int $tender_id, string $type): array
    {
        $tc = $this->db->prefixTable("tender_criteria");

        $sql = "SELECT *
                FROM $tc
                WHERE deleted = 0
                  AND tender_id = ?
                  AND type = ?
                ORDER BY sort_order ASC, id ASC";

        return $this->db->query($sql, [$tender_id, $type])->getResult();
    }

    public function ensure_stage_criteria(int $tender_id, string $type): array
    {
        $criteria = $this->get_stage_criteria($tender_id, $type);
        if (!empty($criteria)) {
            return $criteria;
        }

        $now = date("Y-m-d H:i:s");
        $weight = $this->get_default_stage_weight($tender_id, $type);
        if ($weight <= 0) {
            $weight = 100;
        }

        $name = $type === "commercial"
            ? "Overall Commercial Evaluation"
            : "Overall Technical Evaluation";

        $this->ci_save([
            "tender_id"  => $tender_id,
            "type"       => $type,
            "name"       => $name,
            "weight"     => $weight,
            "sort_order" => 1,
            "created_at" => $now,
            "updated_at" => $now,
            "deleted"    => 0,
        ]);

        return $this->get_stage_criteria($tender_id, $type);
    }

    private function get_default_stage_weight(int $tender_id, string $type): int
    {
        $t = $this->db->prefixTable("tenders");
        $tr = $this->db->prefixTable("tender_requests");

        $sql = "SELECT
                    $tr.technical_weight,
                    $tr.commercial_weight
                FROM $t
                LEFT JOIN $tr
                    ON $tr.id = $t.tender_request_id
                   AND $tr.deleted = 0
                WHERE $t.id = ?
                  AND $t.deleted = 0
                LIMIT 1";

        $row = $this->db->query($sql, [$tender_id])->getRow();
        if (!$row) {
            return 0;
        }

        return (int) ($type === "commercial"
            ? ($row->commercial_weight ?? 0)
            : ($row->technical_weight ?? 0));
    }
}