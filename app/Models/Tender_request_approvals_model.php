<?php

namespace App\Models;

class Tender_request_approvals_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_request_approvals";
        parent::__construct($this->table);
    }

    public function log_stage(int $request_id, string $stage, string $decision, ?string $comment, int $user_id)
    {
        $request = service('request');
        $agent = $request->getUserAgent();

        $data = [
            "tender_request_id" => $request_id,
            "stage"             => $stage,
            "decision"          => $decision,
            "comment"           => $comment,
            "decided_by"        => $user_id,
            "decided_at"        => date("Y-m-d H:i:s"),
            "ip_address"        => $request->getIPAddress(),
            "user_agent"        => substr($agent ? $agent->getAgentString() : "", 0, 500),
            "created_at"        => date("Y-m-d H:i:s"),
        ];

        return $this->ci_save($data);
    }

    public function get_details($options = [])
    {
        $approvals = $this->db->prefixTable("tender_request_approvals");
        $users = $this->db->prefixTable("users");

        $where = "WHERE $approvals.deleted=0";

        $request_id = get_array_value($options, "tender_request_id");
        if ($request_id) {
            $where .= " AND $approvals.tender_request_id=" . (int) $request_id;
        }

        $sql = "SELECT
                    $approvals.*,
                    TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS decided_by_name
                FROM $approvals
                LEFT JOIN $users ON $users.id = $approvals.decided_by
                $where
                ORDER BY $approvals.id ASC";

        return $this->db->query($sql);
    }
}