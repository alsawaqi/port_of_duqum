<?php

namespace App\Models;

class Ptw_applications_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_applications";
        parent::__construct($this->table);
        $this->ensure_terminal_approval_required_column();
    }

    private function ensure_terminal_approval_required_column(): void
    {
        try {
            if (!$this->db->fieldExists("terminal_approval_required", $this->table)) {
                $table = $this->table;
                $this->db->query("ALTER TABLE `$table` ADD COLUMN `terminal_approval_required` TINYINT(1) NOT NULL DEFAULT 1 AFTER `completed_at`");
            }
        } catch (\Throwable $e) {
            log_message("error", "Failed to ensure PTW terminal approval column: " . $e->getMessage());
        }
    }

    public function get_details($options = [])
    {
        $apps = $this->db->prefixTable("ptw_applications");
        $users = $this->db->prefixTable("users");

        $where = " WHERE $apps.deleted=0 ";

        if (!empty($options["id"])) {
            $where .= " AND $apps.id=" . (int)$options["id"];
        }

        if (!empty($options["stage"])) {
            $stage = $this->db->escapeString($options["stage"]);
            $where .= " AND $apps.stage='$stage'";
        }

        if (!empty($options["status"])) {
            $status = $this->db->escapeString($options["status"]);
            $where .= " AND $apps.status='$status'";
        }

        if (!empty($options["statuses"]) && is_array($options["statuses"])) {
            $statuses = array_map(function ($s) {
                return "'" . $this->db->escapeString($s) . "'";
            }, $options["statuses"]);
            if ($statuses) {
                $where .= " AND $apps.status IN (" . implode(",", $statuses) . ")";
            }
        }

        if (!empty($options["applicant_user_id"])) {
            $where .= " AND $apps.applicant_user_id=" . (int)$options["applicant_user_id"];
        }

        if (!empty($options["search"])) {
            $search = $this->db->escapeLikeString($options["search"]);
            $where .= " AND (
                $apps.reference LIKE '%$search%' ESCAPE '!'
                OR $apps.company_name LIKE '%$search%' ESCAPE '!'
                OR $apps.applicant_name LIKE '%$search%' ESCAPE '!'
                OR $apps.contact_email LIKE '%$search%' ESCAPE '!'
                OR $apps.exact_location LIKE '%$search%' ESCAPE '!'
            )";
        }

        $sql = "SELECT $apps.*,
                       u.first_name AS applicant_user_first_name,
                       u.last_name AS applicant_user_last_name,
                       u.email AS applicant_user_email
                FROM $apps
                LEFT JOIN $users u ON u.id = $apps.applicant_user_id
                $where
                ORDER BY $apps.id DESC";

        return $this->db->query($sql);
    }
}
