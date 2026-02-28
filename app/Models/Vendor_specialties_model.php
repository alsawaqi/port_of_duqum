<?php

namespace App\Models;

class Vendor_specialties_model extends Crud_model
{

    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_specialties"; // keep it WITHOUT "pod_"
        parent::__construct($this->table);
    }

    public function get_details($options = array())
    {
        $table = $this->table;
        $prefix = $this->db->DBPrefix ?? "";

        if ($prefix && strpos($table, $prefix) !== 0) {
            $table = $this->db->prefixTable($table);
        }

        // categories
        $vendor_categories_table = "vendor_categories";
        if ($prefix && strpos($vendor_categories_table, $prefix) !== 0) {
            $vendor_categories_table = $this->db->prefixTable($vendor_categories_table);
        }

        // NEW: sub-categories
        $vendor_sub_categories_table = "vendor_sub_categories";
        if ($prefix && strpos($vendor_sub_categories_table, $prefix) !== 0) {
            $vendor_sub_categories_table = $this->db->prefixTable($vendor_sub_categories_table);
        }

        $where = "";
        $id = $options["id"] ?? null;
        $vendor_id = $options["vendor_id"] ?? null;

        if ($id) {
            $where .= " AND $table.id=" . (int) $id;
        }

        if ($vendor_id) {
            $where .= " AND $table.vendor_id=" . (int) $vendor_id;
        }

        $sql = "SELECT
                $table.*,
                $vendor_categories_table.name AS vendor_category_name,
                $vendor_sub_categories_table.name AS vendor_sub_category_name
            FROM $table
            LEFT JOIN $vendor_categories_table
                   ON $vendor_categories_table.id = $table.vendor_category_id
                  AND $vendor_categories_table.deleted=0
            LEFT JOIN $vendor_sub_categories_table
                   ON $vendor_sub_categories_table.id = $table.vendor_sub_category_id
                  AND $vendor_sub_categories_table.deleted=0
            WHERE $table.deleted=0 $where
            ORDER BY $table.id DESC";

        return $this->db->query($sql);
    }
}
