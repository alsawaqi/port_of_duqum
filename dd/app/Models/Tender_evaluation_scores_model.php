<?php

namespace App\Models;

class Tender_evaluation_scores_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_evaluation_scores";
        parent::__construct($this->table);
    }

    public function get_grouped_by_evaluation_ids(array $evaluation_ids): array
    {
        $evaluation_ids = array_values(array_filter(array_map("intval", $evaluation_ids)));
        if (empty($evaluation_ids)) {
            return [];
        }

        $tes = $this->db->prefixTable("tender_evaluation_scores");
        $placeholders = implode(",", array_fill(0, count($evaluation_ids), "?"));

        $sql = "SELECT *
                FROM $tes
                WHERE deleted = 0
                  AND tender_evaluation_id IN ($placeholders)
                ORDER BY id ASC";

        $rows = $this->db->query($sql, $evaluation_ids)->getResult();
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row->tender_evaluation_id][(int) $row->tender_criterion_id] = $row;
        }

        return $grouped;
    }

    public function soft_delete_by_evaluation_id(int $evaluation_id): bool
    {
        $tes = $this->db->prefixTable("tender_evaluation_scores");
        $now = date("Y-m-d H:i:s");

        $this->db->query(
            "UPDATE $tes
             SET deleted = 1,
                 updated_at = ?
             WHERE tender_evaluation_id = ?
               AND deleted = 0",
            [$now, $evaluation_id]
        );

        return true;
    }
}