<?php

namespace App\Models;

class Vendor_groups_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'vendor_groups';
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $table = $this->db->prefixTable('vendor_groups');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $table.id=$id";
        }

        $sql = "SELECT
                    $table.id,
                    $table.name,
                    $table.code,
                    $table.requires_riyada,
                    $table.default_validity_days,
                    $table.is_active
                FROM $table
                WHERE $table.deleted=0 $where
                ORDER BY $table.name ASC";

        return $this->db->query($sql);
    }
}
