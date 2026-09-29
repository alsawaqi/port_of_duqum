<?php

namespace App\Models;

class Ptw_reviews_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_reviews";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $reviews = $this->db->prefixTable("ptw_reviews");
        $users = $this->db->prefixTable("users");

        $where = " WHERE $reviews.deleted=0 ";

        if (!empty($options["ptw_application_id"])) {
            $where .= " AND $reviews.ptw_application_id=" . (int)$options["ptw_application_id"];
        }

        if (!empty($options["stage"])) {
            $stage = $this->db->escapeString($options["stage"]);
            $where .= " AND $reviews.stage='$stage'";
        }

        $sql = "SELECT $reviews.*,
                       u.first_name,
                       u.last_name,
                       u.email
                FROM $reviews
                LEFT JOIN $users u ON u.id = $reviews.reviewer_id
                $where
                ORDER BY $reviews.id ASC";

        return $this->db->query($sql);
    }

    public function get_open_review($application_id, $stage)
    {
        $table = $this->db->prefixTable("ptw_reviews");
        $stage = $this->db->escapeString($stage);

        $sql = "SELECT *
                FROM $table
                WHERE deleted=0
                  AND ptw_application_id=" . (int)$application_id . "
                  AND stage='$stage'
                  AND decision IS NULL
                ORDER BY id DESC";

        return $this->db->query($sql);
    }

    public function get_open_or_latest_stage_row($application_id, $stage)
    {
        $table = $this->db->prefixTable("ptw_reviews");
        $stage = $this->db->escapeString($stage);

        // Prefer an open (undecided) row; fall back to the most recent row
        $row = $this->db->query(
            "SELECT * FROM $table
             WHERE deleted=0
               AND ptw_application_id=?
               AND stage='$stage'
             ORDER BY (decision IS NULL) DESC, id DESC
             LIMIT 1",
            [(int)$application_id]
        )->getRow();

        return $row ?: null;
    }

    public function get_next_revision_no($application_id, $stage): int
    {
        $table = $this->db->prefixTable("ptw_reviews");
        $stage = $this->db->escapeString($stage);

        $row = $this->db->query(
            "SELECT MAX(revision_no) AS max_no
             FROM $table
             WHERE deleted=0
               AND ptw_application_id=?
               AND stage=?",
            [(int)$application_id, $stage]
        )->getRow();

        $max = (int)($row->max_no ?? 0);
        return $max + 1;
    }
}