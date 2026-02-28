<?php

namespace App\Models;

class Ptw_requirement_responses_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_requirement_responses";
        parent::__construct($this->table);
    }

    public function get_by_application($ptw_application_id)
    {
        $table = $this->db->prefixTable("ptw_requirement_responses");
        $sql = "SELECT * FROM $table WHERE deleted=0 AND ptw_application_id=" . (int) $ptw_application_id . " ORDER BY id ASC";
        return $this->db->query($sql);
    }

    public function get_one_by_app_and_def($ptw_application_id, $definition_id)
    {
        $table = $this->db->prefixTable("ptw_requirement_responses");
        $sql = "SELECT * FROM $table 
                WHERE deleted=0 
                  AND ptw_application_id=" . (int) $ptw_application_id . "
                  AND ptw_requirement_definition_id=" . (int) $definition_id . "
                LIMIT 1";
        return $this->db->query($sql)->getRow();
    }
}