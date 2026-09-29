<?php

namespace App\Models;

class Vendor_document_types_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = 'vendor_document_types';
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $types_table  = $this->db->prefixTable('vendor_document_types');
        $groups_table = $this->db->prefixTable('vendor_groups');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $types_table.id=$id";
        }

        $sql = "SELECT
                    $types_table.id,
                    $types_table.name,
                    $types_table.code,
                    $types_table.is_required,
                    $types_table.vendor_group_id,
                    $types_table.is_active,
                    $groups_table.name AS vendor_group_name

                FROM $types_table
                LEFT JOIN $groups_table ON $groups_table.id = $types_table.vendor_group_id
                WHERE $types_table.deleted=0 $where
                ORDER BY $groups_table.name ASC, $types_table.name ASC";

        return $this->db->query($sql);
    }
}
