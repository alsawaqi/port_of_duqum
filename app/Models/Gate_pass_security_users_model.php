<?php

namespace App\Models;

class Gate_pass_security_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_security_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $gps = $this->db->prefixTable("gate_pass_security_users");
        $users = $this->db->prefixTable("users");
        $companies = $this->db->prefixTable("companies");

        $where = " WHERE $gps.deleted=0";

        if (!empty($options["id"])) {
            $where .= " AND $gps.id=" . (int)$options["id"];
        }
        if (!empty($options["user_id"])) {
            $where .= " AND $gps.user_id=" . (int)$options["user_id"];
        }
        if (isset($options["company_id"]) && $options["company_id"] !== "") {
            $where .= " AND $gps.company_id=" . (int)$options["company_id"];
        }

        $sql = "SELECT $gps.*,
                    $users.first_name,
                    $users.last_name,
                    $users.email,
                    $users.phone,
                    $users.status AS user_status,
                    $companies.name AS company_name
                FROM $gps
                LEFT JOIN $users ON $users.id = $gps.user_id AND $users.deleted=0
                LEFT JOIN $companies ON $companies.id = $gps.company_id AND $companies.deleted=0
                $where
                ORDER BY $gps.id DESC";

        return $this->db->query($sql);
    }

    /**
     * Whether the user is a security user (can see security inbox).
     */
    public function is_security_user($user_id): bool
    {
        $table = $this->db->prefixTable("gate_pass_security_users");
        $row = $this->db->query(
            "SELECT id FROM $table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [(int)$user_id]
        )->getRow();
        return $row !== null;
    }

    /**
     * Get user assignments (company_id) for security users.
     */
    public function get_user_assignments($user_id)
    {
        $table = $this->db->prefixTable("gate_pass_security_users");
        return $this->db->query(
            "SELECT id, user_id, company_id FROM $table WHERE deleted=0 AND status='active' AND user_id=" . (int)$user_id
        );
    }
}
