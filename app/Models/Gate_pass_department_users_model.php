<?php

namespace App\Models;

class Gate_pass_department_users_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        // maps to pod_gate_pass_department_users via DB prefix
        $this->table = "gate_pass_department_users";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $gpd_users_table = $this->db->prefixTable("gate_pass_department_users");
        $users_table = $this->db->prefixTable("users");
        $departments_table = $this->db->prefixTable("departments");
        $companies_table = $this->db->prefixTable("companies");

        $where = "";

        $id = $options["id"] ?? null;
        if ($id) {
            $where .= " AND $gpd_users_table.id=" . $this->db->escapeString($id);
        }

        $user_id = $options["user_id"] ?? null;
        if ($user_id) {
            $where .= " AND $gpd_users_table.user_id=" . $this->db->escapeString($user_id);
        }

        $company_id = $options["company_id"] ?? null;
        if ($company_id) {
            $where .= " AND $gpd_users_table.company_id=" . $this->db->escapeString($company_id);
        }

        $department_id = $options["department_id"] ?? null;
        if ($department_id) {
            $where .= " AND $gpd_users_table.department_id=" . $this->db->escapeString($department_id);
        }

        $sql = "SELECT
                    $gpd_users_table.id,
                    $gpd_users_table.user_id,
                    $gpd_users_table.company_id,
                    $gpd_users_table.department_id,
                    $gpd_users_table.status,
                    $gpd_users_table.created_at,
                    $gpd_users_table.updated_at,
                    COALESCE($users_table.first_name, '') AS first_name,
                    COALESCE($users_table.last_name, '') AS last_name,
                    COALESCE($users_table.email, '') AS email,
                    COALESCE($users_table.phone, '') AS phone,
                    $users_table.status AS user_status,
                    COALESCE($departments_table.name, '') AS department_name,
                    COALESCE($companies_table.name, '') AS company_name
                FROM $gpd_users_table
                LEFT JOIN $users_table ON $users_table.id = $gpd_users_table.user_id AND $users_table.deleted=0
                LEFT JOIN $departments_table ON $departments_table.id = $gpd_users_table.department_id AND $departments_table.deleted=0
                LEFT JOIN $companies_table ON $companies_table.id = $gpd_users_table.company_id AND $companies_table.deleted=0
                WHERE $gpd_users_table.deleted=0 
                    AND $gpd_users_table.id IS NOT NULL
                    AND $gpd_users_table.id > 0
                    $where
                ORDER BY $gpd_users_table.id DESC";

        return $this->db->query($sql);
    }

    /**
     * Get department IDs for a user (for department-request access).
     */
    public function get_department_ids_by_user($user_id): array
    {
        $table = $this->db->prefixTable("gate_pass_department_users");

        $rows = $this->db->query(
            "SELECT department_id FROM $table WHERE deleted=0 AND status='active' AND user_id=?",
            [(int)$user_id]
        )->getResult();

        $ids = [];
        foreach ($rows as $r) {
            $ids[] = (int)$r->department_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * Whether the user is a department user (can see department inbox).
     */
    public function is_department_user($user_id): bool
    {
        $table = $this->db->prefixTable("gate_pass_department_users");
        $row = $this->db->query(
            "SELECT id FROM $table WHERE user_id=? AND deleted=0 AND status='active' LIMIT 1",
            [(int)$user_id]
        )->getRow();
        return $row !== null;
    }

    /**
     * Get user assignments (company_id, department_id) for department users (e.g. for approval checks).
     */
    public function get_user_assignments($user_id)
    {
        $table = $this->db->prefixTable("gate_pass_department_users");
        $sql = "SELECT id, user_id, company_id, department_id
                FROM $table
                WHERE deleted=0 AND status='active' AND user_id=" . (int)$user_id;
        return $this->db->query($sql);
    }
}
