<?php

namespace App\Models;

class Gate_pass_companies_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "companies"; // maps to pod_companies via DB prefix
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $companies_table = $this->db->prefixTable("companies");

        $where = "WHERE $companies_table.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $companies_table.id=$id";
        }

        $sql = "SELECT $companies_table.*
                FROM $companies_table
                $where
                ORDER BY $companies_table.name ASC";

        return $this->db->query($sql);
    }
}
