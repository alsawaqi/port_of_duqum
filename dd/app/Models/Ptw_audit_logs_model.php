<?php

namespace App\Models;

class Ptw_audit_logs_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_audit_logs";
        parent::__construct($this->table);
    }

    public function get_by_application($ptw_application_id)
    {
        $logs = $this->db->prefixTable("ptw_audit_logs");
        $users = $this->db->prefixTable("users");
        $sql = "SELECT 
                    $logs.*,
                    CONCAT(COALESCE($users.first_name,''),' ',COALESCE($users.last_name,'')) AS user_name
                FROM $logs
                LEFT JOIN $users ON $users.id = $logs.user_id
                WHERE $logs.deleted=0 AND $logs.ptw_application_id=" . (int)$ptw_application_id . "
                ORDER BY $logs.id DESC";
        return $this->db->query($sql);
    }
}