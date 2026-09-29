<?php

namespace App\Models;

class Legal_types_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'legal_types';
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {

        $legal_types_table = $this->db->prefixTable('legal_types');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $legal_types_table.id=$id";
        }

        $sql = "SELECT 
                    $legal_types_table.id,
                    $legal_types_table.name,
                    $legal_types_table.code,
                    $legal_types_table.is_active
                FROM $legal_types_table
                WHERE $legal_types_table.deleted=0 $where
                ORDER BY $legal_types_table.name ASC";

        return $this->db->query($sql);
    }
}
