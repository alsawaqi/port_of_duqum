<?php

namespace App\Models;

class Tender_invited_vendors_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_invited_vendors";
        parent::__construct($this->table);
    }

    public function get_invited_vendors($tender_id)
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $v = $this->db->prefixTable("vendors");

        $sql = "SELECT $tiv.*, $v.vendor_name, $v.email
                FROM $tiv
                LEFT JOIN $v ON $v.id=$tiv.vendor_id
                WHERE $tiv.deleted=0 AND $tiv.tender_id=?
                ORDER BY $tiv.id DESC";

        return $this->db->query($sql, [(int)$tender_id])->getResult();
    }
}