<?php

namespace App\Models;

class Gate_pass_scan_log_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_scan_log";
        parent::__construct($this->table);
    }

    /**
     * Get log entries for a gate pass request (for display/reports).
     *
     * @param array $options ['gate_pass_request_id' => int, 'limit' => int, 'order' => 'asc'|'desc']
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details($options = [])
    {
        $t = $this->db->prefixTable("gate_pass_scan_log");
        $u = $this->db->prefixTable("users");
        $gps = $this->db->prefixTable("gate_pass_security_users");

        $where = " WHERE 1=1";
        if (!empty($options["gate_pass_request_id"])) {
            $where .= " AND $t.gate_pass_request_id = " . (int)$options["gate_pass_request_id"];
        }
        if (!empty($options["security_user_id"])) {
            $where .= " AND $t.security_user_id = " . (int)$options["security_user_id"];
        }

        $order = " ORDER BY $t.recorded_at DESC";
        if (!empty($options["order"]) && strtolower($options["order"]) === "asc") {
            $order = " ORDER BY $t.recorded_at ASC";
        }

        $limit = "";
        if (!empty($options["limit"])) {
            $limit = " LIMIT " . (int)$options["limit"];
        }

        $sql = "SELECT $t.*,
                    $u.first_name AS performed_by_first_name,
                    $u.last_name AS performed_by_last_name,
                    $gps.id AS security_user_row_id
                FROM $t
                LEFT JOIN $u ON $u.id = $t.performed_by AND $u.deleted = 0
                LEFT JOIN $gps ON $gps.id = $t.security_user_id AND $gps.deleted = 0
                $where
                $order
                $limit";

        return $this->db->query($sql);
    }
}
