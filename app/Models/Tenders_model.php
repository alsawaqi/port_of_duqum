<?php

namespace App\Models;

class Tenders_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tenders";
        parent::__construct($this->table);
    }

    public function get_by_request_id(int $tender_request_id)
    {
        $t = $this->db->prefixTable("tenders");

        $sql = "SELECT * FROM $t
                WHERE deleted=0 AND tender_request_id=?
                ORDER BY id DESC
                LIMIT 1";
        return $this->db->query($sql, [$tender_request_id])->getRow();
    }
}