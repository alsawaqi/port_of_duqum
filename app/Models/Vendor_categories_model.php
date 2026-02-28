<?php

namespace App\Models;

class Vendor_categories_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_categories";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $vendor_categories_table = $this->db->prefixTable("vendor_categories");

        $where = "";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $vendor_categories_table.id=$id";
        }

        $sql = "SELECT $vendor_categories_table.*
                FROM $vendor_categories_table
                WHERE $vendor_categories_table.deleted=0 $where
                ORDER BY $vendor_categories_table.id DESC";

        return $this->db->query($sql);
    }
}
