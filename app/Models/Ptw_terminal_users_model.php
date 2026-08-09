<?php

namespace App\Models;

class Ptw_terminal_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_terminal_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $ptw = $this->db->prefixTable("ptw_terminal_users");
        $users = $this->db->prefixTable("users");
        $companies = $this->db->prefixTable("companies");

        $where = " WHERE $ptw.deleted=0 ";

        if (!empty($options["id"])) {
            $where .= " AND $ptw.id=" . (int)$options["id"];
        }
        if (!empty($options["user_id"])) {
            $where .= " AND $ptw.user_id=" . (int)$options["user_id"];
        }
        if (isset($options["company_id"]) && $options["company_id"] !== "") {
            $where .= " AND $ptw.company_id=" . (int)$options["company_id"];
        }
        if (isset($options["status"]) && $options["status"] !== "") {
            $status = $this->db->escapeString($options["status"]);
            $where .= " AND $ptw.status='$status'";
        }

        $sql = "SELECT $ptw.*,
                       $users.first_name,
                       $users.last_name,
                       $users.email,
                       $users.phone,
                       $users.status AS user_status,
                       $companies.name AS company_name
                FROM $ptw
                LEFT JOIN $users ON $users.id = $ptw.user_id AND $users.deleted=0
                LEFT JOIN $companies ON $companies.id = $ptw.company_id AND $companies.deleted=0
                $where
                ORDER BY $ptw.id DESC";

        return $this->db->query($sql);
    }

    public function is_terminal_user($user_id): bool
    {
        return !empty($this->get_active_company_ids((int)$user_id));
    }

    public function get_user_assignments($user_id)
    {
        $ptw = $this->db->prefixTable("ptw_terminal_users");
        $companies = $this->db->prefixTable("companies");

        $sql = "SELECT $ptw.id, $ptw.user_id, $ptw.company_id, $companies.name AS company_name
                FROM $ptw
                INNER JOIN $companies ON $companies.id = $ptw.company_id AND $companies.deleted=0
                WHERE $ptw.deleted=0
                  AND $ptw.status='active'
                  AND $ptw.company_id IS NOT NULL
                  AND $ptw.company_id > 0
                  AND $ptw.user_id=?";

        return $this->db->query($sql, [(int)$user_id]);
    }

    public function get_active_company_ids($user_id): array
    {
        $ids = [];
        foreach ($this->get_user_assignments((int)$user_id)->getResult() as $assignment) {
            $company_id = (int)($assignment->company_id ?? 0);
            if ($company_id > 0) {
                $ids[$company_id] = $company_id;
            }
        }
        return array_values($ids);
    }

    public function has_active_company_assignment($user_id, $company_id): bool
    {
        $user_id = (int)$user_id;
        $company_id = (int)$company_id;
        if ($user_id < 1 || $company_id < 1) {
            return false;
        }

        $ptw = $this->db->prefixTable("ptw_terminal_users");
        $companies = $this->db->prefixTable("companies");
        $row = $this->db->query(
            "SELECT $ptw.id
             FROM $ptw
             INNER JOIN $companies ON $companies.id = $ptw.company_id AND $companies.deleted=0
             WHERE $ptw.user_id=?
               AND $ptw.company_id=?
               AND $ptw.deleted=0
               AND $ptw.status='active'
             LIMIT 1",
            [$user_id, $company_id]
        )->getRow();

        return $row !== null;
    }
}
