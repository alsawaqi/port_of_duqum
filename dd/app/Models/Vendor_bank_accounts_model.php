<?php

namespace App\Models;

class Vendor_bank_accounts_model extends Crud_model
{
    protected $table = "vendor_bank_accounts";

    function __construct()
    {
        parent::__construct($this->table);
    }

    function get_details($options = [])
    {
        $bank_table = $this->db->prefixTable("vendor_bank_accounts");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $bank_table.id=$id";
        }

        $vendor_id = $this->_get_clean_value($options, "vendor_id");
        if ($vendor_id) {
            $where .= " AND $bank_table.vendor_id=$vendor_id";
        }

        $sql = "SELECT 
                    $bank_table.*
                FROM $bank_table
                WHERE $bank_table.deleted=0 $where
                ORDER BY $bank_table.created_at DESC";

        return $this->db->query($sql);
    }
}
