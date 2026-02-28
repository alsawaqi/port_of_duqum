<?php

namespace App\Models;

/**
 * Model for gate_pass_users table (portal/visitor users).
 * For department users use Gate_pass_department_users_model instead.
 */
class Gate_pass_users_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_users";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $gp_table = $this->db->prefixTable("gate_pass_users");
        $users_table = $this->db->prefixTable("users");

        $where = "WHERE $gp_table.deleted=0";
        $id = $options["id"] ?? null;
        if ($id) {
            $where .= " AND $gp_table.id=" . (int)$id;
        }

        $sql = "SELECT $gp_table.*,
                    $users_table.first_name,
                    $users_table.last_name,
                    $users_table.email,
                    $users_table.phone,
                    $users_table.alternative_phone,
                    $users_table.status AS user_status
                FROM $gp_table
                LEFT JOIN $users_table ON $users_table.id = $gp_table.user_id AND $users_table.deleted=0
                $where
                ORDER BY $gp_table.id DESC";

        return $this->db->query($sql);
    }
}
