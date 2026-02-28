<?php

namespace App\Models;

class Gate_pass_purposes_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "gate_pass_purposes"; // => pod_gate_pass_purposes
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $table = $this->db->prefixTable("gate_pass_purposes");
        $where = "WHERE $table.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $table.id=$id";
        }

        $sql = "SELECT $table.*
                FROM $table
                $where
                ORDER BY $table.name ASC";

        return $this->db->query($sql);
    }
}
