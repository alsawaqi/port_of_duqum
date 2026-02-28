<?php

namespace App\Models;

class Ptw_hsse_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_hsse_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $ptw = $this->db->prefixTable("ptw_hsse_users");
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

    public function is_hsse_user($user_id): bool
    {
        $table = $this->db->prefixTable("ptw_hsse_users");
        $row = $this->db->query(
            "SELECT id FROM $table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [(int)$user_id]
        )->getRow();

        return $row !== null;
    }

    public function get_user_assignments($user_id)
    {
        $ptw = $this->db->prefixTable("ptw_hsse_users");
        $companies = $this->db->prefixTable("companies");

        $sql = "SELECT $ptw.id, $ptw.user_id, $ptw.company_id, $companies.name AS company_name
                FROM $ptw
                LEFT JOIN $companies ON $companies.id = $ptw.company_id
                WHERE $ptw.deleted=0
                  AND $ptw.status='active'
                  AND $ptw.user_id=" . (int)$user_id;

        return $this->db->query($sql);
    }
}