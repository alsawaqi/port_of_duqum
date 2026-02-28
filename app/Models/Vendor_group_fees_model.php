<?php

namespace App\Models;

class Vendor_group_fees_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = 'vendor_group_fees'; // => pod_vendor_group_fees
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {

        $fees_table   = $this->db->prefixTable('vendor_group_fees');
        $groups_table = $this->db->prefixTable('vendor_groups');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $fees_table.id=$id";
        }

        $vendor_group_id = $this->_get_clean_value($options, "vendor_group_id");
        if ($vendor_group_id) {
            $where .= " AND $fees_table.vendor_group_id=$vendor_group_id";
        }

        $sql = "SELECT
                    $fees_table.id,
                    $fees_table.vendor_group_id,
                    $fees_table.fee_type,
                    $fees_table.currency,
                    $fees_table.amount,
                    $fees_table.active_from,
                    $fees_table.active_to,
                    $fees_table.is_active,

                    $groups_table.name AS vendor_group_name,
                    $groups_table.code AS vendor_group_code

                FROM $fees_table
                LEFT JOIN $groups_table ON $groups_table.id = $fees_table.vendor_group_id
                WHERE $fees_table.deleted=0 $where
                ORDER BY $groups_table.name ASC, $fees_table.fee_type ASC, $fees_table.active_from DESC";

        return $this->db->query($sql);
    }
}
