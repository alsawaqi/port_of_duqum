<?php

namespace App\Models;

class Gate_pass_reasons_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_reasons"; // => pod_gate_pass_reasons
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $table = $this->db->prefixTable("gate_pass_reasons");
        $where = "WHERE $table.deleted=0";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $table.id=" . (int)$id;
        }

        $only_active = get_array_value($options, "only_active");
        if ($only_active) {
            $where .= " AND $table.is_active=1";
        }

        $sql = "SELECT $table.*
                FROM $table
                $where
                ORDER BY $table.sort_order ASC, $table.title ASC";

        return $this->db->query($sql);
    }
}

