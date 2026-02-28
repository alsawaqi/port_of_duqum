<?php

namespace App\Models;

class Ptw_attachments_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "ptw_attachments";
        parent::__construct($this->table);
    }

    public function get_by_application($ptw_application_id)
    {
        $att = $this->db->prefixTable("ptw_attachments");
        $defs = $this->db->prefixTable("ptw_requirement_definitions");
        $sql = "SELECT $att.*, $defs.label AS requirement_label
                FROM $att
                LEFT JOIN $defs ON $defs.id = $att.ptw_requirement_id
                WHERE $att.deleted=0 AND $att.ptw_application_id=" . (int) $ptw_application_id . "
                ORDER BY $att.id ASC";
        return $this->db->query($sql);
    }
}