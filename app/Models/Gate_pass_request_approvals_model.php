<?php

namespace App\Models;

class Gate_pass_request_approvals_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "gate_pass_request_approvals";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $approvals = $this->db->prefixTable("gate_pass_request_approvals");
        $users = $this->db->prefixTable("users");
        $reasons = $this->db->prefixTable("gate_pass_reasons");

        $where = "WHERE $approvals.deleted=0";

        $request_id = get_array_value($options, "gate_pass_request_id");
        if ($request_id) {
            $request_id = (int) $request_id;
            $where .= " AND $approvals.gate_pass_request_id=$request_id";
        }

        $sql = "SELECT $approvals.*,
                       $users.first_name,
                       $users.last_name,
                       $reasons.title AS reason_title
                FROM $approvals
                LEFT JOIN $users ON $users.id=$approvals.decided_by
                LEFT JOIN $reasons ON $reasons.id=$approvals.reason_id
                $where
                ORDER BY $approvals.id ASC";

        return $this->db->query($sql);
    }
}
