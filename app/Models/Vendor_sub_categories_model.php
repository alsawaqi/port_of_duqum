<?php

namespace App\Models;

class Vendor_sub_categories_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        // 👇 IMPORTANT: NO "pod_" here
        $this->table = "vendor_sub_categories";
        parent::__construct($this->table);
    }

    public function get_details($options = array())
    {
        // These will become pod_vendor_sub_categories and pod_vendor_categories
        $sub_table = $this->db->prefixTable("vendor_sub_categories");
        $cat_table = $this->db->prefixTable("vendor_categories");

        $where = "";

        $id = get_array_value($options, "id");
        if ($id) {
            $where .= " AND $sub_table.id=$id";
        }

        $vendor_category_id = get_array_value($options, "vendor_category_id");
        if ($vendor_category_id) {
            $where .= " AND $sub_table.vendor_category_id=$vendor_category_id";
        }

        $is_active = get_array_value($options, "is_active");
        if ($is_active !== null && $is_active !== "") {
            $where .= " AND $sub_table.is_active=" . (int)$is_active;
        }

        $sql = "SELECT 
                    $sub_table.*,
                    $cat_table.name AS category_name
                FROM $sub_table
                LEFT JOIN $cat_table ON $cat_table.id = $sub_table.vendor_category_id
                WHERE $sub_table.deleted = 0 $where
                ORDER BY $cat_table.name ASC, $sub_table.name ASC";

        return $this->db->query($sql);
    }
}
