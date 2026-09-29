<?php

namespace App\Models;

class Vendor_grades_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_grades";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $table = $this->db->prefixTable("vendor_grades");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $table.id=" . (int)$id;
        }

        $active_only = $this->_get_clean_value($options, "active_only");
        if ($active_only) {
            $where .= " AND $table.is_active=1";
        }

        $sql = "SELECT
                    $table.id,
                    $table.name,
                    $table.code,
                    $table.description,
                    $table.sort,
                    $table.is_active
                FROM $table
                WHERE $table.deleted=0 $where
                ORDER BY $table.sort ASC, $table.code ASC, $table.name ASC";

        return $this->db->query($sql);
    }
}
