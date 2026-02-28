<?php

namespace App\Models;

class Gate_pass_commercial_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_commercial_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $gpc = $this->db->prefixTable("gate_pass_commercial_users");
        $users = $this->db->prefixTable("users");
        $companies = $this->db->prefixTable("companies");

        $where = " WHERE $gpc.deleted=0";

        if (!empty($options["id"])) {
            $where .= " AND $gpc.id=" . (int)$options["id"];
        }
        if (!empty($options["user_id"])) {
            $where .= " AND $gpc.user_id=" . (int)$options["user_id"];
        }
        if (isset($options["company_id"]) && $options["company_id"] !== "") {
            $where .= " AND $gpc.company_id=" . (int)$options["company_id"];
        }

        $sql = "SELECT $gpc.*,
                    $users.first_name,
                    $users.last_name,
                    $users.email,
                    $users.phone,
                    $users.status AS user_status,
                    $companies.name AS company_name
                FROM $gpc
                LEFT JOIN $users ON $users.id = $gpc.user_id AND $users.deleted=0
                LEFT JOIN $companies ON $companies.id = $gpc.company_id AND $companies.deleted=0
                $where
                ORDER BY $gpc.id DESC";

        return $this->db->query($sql);
    }

    /**
     * Whether the user is a commercial user (can see commercial inbox).
     */
    public function is_commercial_user($user_id): bool
    {
        $table = $this->db->prefixTable("gate_pass_commercial_users");
        $row = $this->db->query(
            "SELECT id FROM $table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [(int)$user_id]
        )->getRow();
        return $row !== null;
    }

    /**
     * Get user assignments (company_id) for commercial users.
     */
    public function get_user_assignments($user_id)
    {
        $table = $this->db->prefixTable("gate_pass_commercial_users");
        return $this->db->query(
            "SELECT id, user_id, company_id FROM $table WHERE deleted=0 AND status='active' AND user_id=" . (int)$user_id
        );
    }
}
