<?php

namespace App\Models;

class Vendor_update_requests_model extends Crud_model
{
    protected $table = "vendor_update_requests"; // ✅ NO prefix here

    function __construct()
    {
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        // ✅ $this->table is ALREADY prefixed by Crud_model
        $requests_table = $this->table;

        // ✅ use prefixTable with NON-prefixed names
        $vendors_table  = $this->db->prefixTable("vendors");
        $users_table    = $this->db->prefixTable("users");

        $fields = $this->db->getFieldNames($requests_table);
        $has_deleted = is_array($fields) && in_array("deleted", $fields, true);
        $where = $has_deleted ? "WHERE $requests_table.deleted = 0" : "WHERE 1=1";

        if (!empty($options["status"])) {
            $where .= " AND $requests_table.status = " . $this->db->escape($options["status"]);
        }

        if (!empty($options["vendor_id"])) {
            $where .= " AND $requests_table.vendor_id = " . (int)$options["vendor_id"];
        }

        $sql = "
            SELECT 
                $requests_table.*,
                $vendors_table.vendor_name,
                CONCAT($users_table.first_name, ' ', $users_table.last_name) AS requested_by_name
            FROM $requests_table
            LEFT JOIN $vendors_table ON $vendors_table.id = $requests_table.vendor_id
            LEFT JOIN $users_table ON $users_table.id = $requests_table.requested_by
            $where
            ORDER BY $requests_table.id DESC
        ";

        return $this->db->query($sql);
    }

    public function get_grouped_by_vendor_details($options = [])
    {
        // ✅ $this->table is already prefixed
        $requests_table = $this->table;
        $vendors_table  = $this->db->prefixTable("vendors");

        $statuses = $options["statuses"] ?? ["pending", "review"];

        $allowed  = ["pending", "review", "approved", "rejected"];
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (!count($statuses)) {
            $statuses = ["pending", "review"];
        }

        $placeholders = implode(",", array_fill(0, count($statuses), "?"));

        $fields = $this->db->getFieldNames($requests_table);
        $has_deleted = is_array($fields) && in_array("deleted", $fields, true);
        $deleted_filter = $has_deleted ? "AND $requests_table.deleted=0" : "";

        $sql = "SELECT
                $requests_table.vendor_id,
                $vendors_table.vendor_name,
                SUM(CASE WHEN $requests_table.status='pending' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN $requests_table.status='review' THEN 1 ELSE 0 END) AS review_count,
                COUNT(*) AS total_count,
                MAX($requests_table.created_at) AS last_request_at
            FROM $requests_table
            LEFT JOIN $vendors_table ON $vendors_table.id = $requests_table.vendor_id
            WHERE $requests_table.status IN ($placeholders)
              $deleted_filter
            GROUP BY $requests_table.vendor_id
            ORDER BY last_request_at DESC";

        return $this->db->query($sql, $statuses);
    }
}
