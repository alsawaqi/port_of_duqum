<?php

namespace App\Models;

class Tender_evaluations_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_evaluations";
        parent::__construct($this->table);
    }

    public function get_evaluator_stage_evaluations(int $tender_id, int $evaluator_id, string $type = "technical"): array
    {
        $te = $this->db->prefixTable("tender_evaluations");

        $sql = "SELECT *
                FROM $te
                WHERE deleted = 0
                  AND tender_id = ?
                  AND evaluator_id = ?
                  AND type = ?
                ORDER BY id ASC";

        return $this->db->query($sql, [$tender_id, $evaluator_id, $type])->getResult();
    }

    public function get_one_for_bid_and_evaluator(int $tender_id, int $tender_bid_id, int $evaluator_id, string $type = "technical")
    {
        $te = $this->db->prefixTable("tender_evaluations");

        $sql = "SELECT *
                FROM $te
                WHERE deleted = 0
                  AND tender_id = ?
                  AND tender_bid_id = ?
                  AND evaluator_id = ?
                  AND type = ?
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql, [$tender_id, $tender_bid_id, $evaluator_id, $type])->getRow();
    }


    public function get_latest_stage_evaluation_for_bid(int $tender_bid_id, string $type = "technical")
{
    $te = $this->db->prefixTable("tender_evaluations");
    $u = $this->db->prefixTable("users");

    $sql = "SELECT
                $te.*,
                TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS evaluator_name,
                $u.email AS evaluator_email
            FROM $te
            LEFT JOIN $u ON $u.id = $te.evaluator_id
            WHERE $te.deleted = 0
              AND $te.tender_bid_id = ?
              AND $te.type = ?
            ORDER BY $te.id DESC
            LIMIT 1";

    return $this->db->query($sql, [$tender_bid_id, $type])->getRow();
}

public function get_latest_stage_evaluations_for_bid_ids(array $bid_ids, string $type = "technical"): array
{
    $bid_ids = array_values(array_filter(array_map("intval", $bid_ids)));
    if (empty($bid_ids)) {
        return [];
    }

    $te = $this->db->prefixTable("tender_evaluations");
    $u = $this->db->prefixTable("users");
    $placeholders = implode(",", array_fill(0, count($bid_ids), "?"));

    $sql = "SELECT
                latest.*,
                TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS evaluator_name,
                $u.email AS evaluator_email
            FROM $te latest
            INNER JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = ?
                  AND tender_bid_id IN ($placeholders)
                GROUP BY tender_bid_id
            ) picked ON picked.max_id = latest.id
            LEFT JOIN $u ON $u.id = latest.evaluator_id
            ORDER BY latest.id ASC";

    $rows = $this->db->query($sql, array_merge([$type], $bid_ids))->getResult();
    $result = [];

    foreach ($rows as $row) {
        $result[(int) $row->tender_bid_id] = $row;
    }

    return $result;
}
}