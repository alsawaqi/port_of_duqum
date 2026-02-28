<?php

namespace App\Models;

class Vendor_credentials_model extends Crud_model
{
    protected $table = null;

    function __construct()
    {
        $this->table = "vendor_credentials"; // keep it WITHOUT "pod_"
        parent::__construct($this->table);
    }

    function get_details($options = array())
    {
        // IMPORTANT:
        // Crud_model already stores $this->table as prefixed in many Rise/CI bases.
        // So DO NOT prefixTable() again blindly.

        $table = $this->table;

        // If for some reason it's still unprefixed, prefix it once safely.
        $prefix = $this->db->DBPrefix ?? "";
        if ($prefix && strpos($table, $prefix) !== 0) {
            $table = $this->db->prefixTable($table);
        }

        $where = "WHERE $table.deleted=0";

        $id = get_array_value($options, "id");
        $vendor_id = get_array_value($options, "vendor_id");

        if ($id) {
            $where .= " AND $table.id=" . (int) $id;
        }
        if ($vendor_id) {
            $where .= " AND $table.vendor_id=" . (int) $vendor_id;
        }

        return $this->db->query("
            SELECT $table.*
            FROM $table
            $where
            ORDER BY $table.id DESC
        ");
    }
}
