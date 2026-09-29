<?php

namespace App\Models;

class Gate_pass_departments_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "departments"; // maps to pod_departments via DB prefix
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $departments_table = $this->db->prefixTable("departments");
        $companies_table   = $this->db->prefixTable("companies");

        $where = "WHERE $departments_table.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $departments_table.id=$id";
        }

        $company_id = get_array_value($options, "company_id");
        if ($company_id) {
            $where .= " AND $departments_table.company_id=$company_id";
        }

        $sql = "SELECT 
                    $departments_table.*,
                    $companies_table.name AS company_name,
                    $companies_table.code AS company_code
                FROM $departments_table
                LEFT JOIN $companies_table ON $companies_table.id = $departments_table.company_id
                $where
                ORDER BY $companies_table.name ASC, $departments_table.name ASC";

        return $this->db->query($sql);
    }
}
