<?php

namespace App\Models;

class Tender_documents_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_documents";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $d = $this->db->prefixTable("tender_documents");

        $where = "WHERE $d.deleted=0";

        if ($tender_id = get_array_value($options, "tender_id")) {
            $where .= " AND $d.tender_id=" . (int)$tender_id;
        }

        $sql = "SELECT $d.*
                FROM $d
                $where
                ORDER BY $d.id DESC";

        return $this->db->query($sql);
    }
}