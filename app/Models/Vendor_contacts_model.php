<?php

namespace App\Models;

class Vendor_contacts_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_contacts";
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        $vendor_contacts_table = $this->db->prefixTable("vendor_contacts");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $vendor_contacts_table.id=$id";
        }

        $vendor_id = $this->_get_clean_value($options, "vendor_id");
        if ($vendor_id) {
            $where .= " AND $vendor_contacts_table.vendor_id=$vendor_id";
        }

        $sql = "SELECT 
                    $vendor_contacts_table.*
                FROM $vendor_contacts_table
                WHERE $vendor_contacts_table.deleted=0 $where
                ORDER BY $vendor_contacts_table.is_primary DESC, $vendor_contacts_table.contacts_name ASC";

        return $this->db->query($sql);
    }
}
