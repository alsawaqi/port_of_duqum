<?php

namespace App\Models;

class Gate_pass_request_audit_log_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_request_audit_log";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $t = $this->db->prefixTable("gate_pass_request_audit_log");
        $users = $this->db->prefixTable("users");

        $where = "WHERE $t.deleted=0";

        $request_id = get_array_value($options, "gate_pass_request_id");
        if ($request_id) {
            $where .= " AND $t.gate_pass_request_id=" . (int)$request_id;
        }

        $sql = "SELECT $t.*,
                       TRIM(CONCAT(COALESCE($users.first_name,''),' ',COALESCE($users.last_name,''))) AS actor_name
                FROM $t
                LEFT JOIN $users ON $users.id = $t.actor_user_id
                $where
                ORDER BY $t.id DESC";

        return $this->db->query($sql);
    }

    /**
     * Admin feed: audit rows with request reference and company for readability.
     *
     * @param array{limit?:int} $options
     */
    public function get_admin_feed(array $options = [])
    {
        $t = $this->db->prefixTable("gate_pass_request_audit_log");
        $r = $this->db->prefixTable("gate_pass_requests");
        $c = $this->db->prefixTable("companies");
        $u = $this->db->prefixTable("users");

        $limit = (int)($options["limit"] ?? 1500);
        if ($limit < 1) {
            $limit = 500;
        }
        if ($limit > 3000) {
            $limit = 3000;
        }

        // company name lives on `companies`, not on gate_pass_requests (same as Gate_pass_requests_model).
        $sql = "SELECT $t.*,
                       TRIM(CONCAT(COALESCE($u.first_name,''),' ',COALESCE($u.last_name,''))) AS actor_name,
                       $r.reference AS request_reference,
                       $c.name AS request_company
                FROM $t
                LEFT JOIN $u ON $u.id = $t.actor_user_id
                LEFT JOIN $r ON $r.id = $t.gate_pass_request_id AND $r.deleted = 0
                LEFT JOIN $c ON $c.id = $r.company_id
                WHERE $t.deleted = 0
                ORDER BY $t.id DESC
                LIMIT " . $limit;

        return $this->db->query($sql);
    }
}
