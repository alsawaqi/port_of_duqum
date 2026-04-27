<?php

namespace App\Models;

class Tender_extensions_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_extensions";
        parent::__construct($this->table);
    }

    public function get_details(array $options = [])
    {
        $tbl = $this->db->prefixTable("tender_extensions");
        $t = $this->db->prefixTable("tenders");
        $u = $this->db->prefixTable("users");

        $where = "WHERE $tbl.deleted=0";

        if ($id = (int) get_array_value($options, "id")) {
            $where .= " AND $tbl.id=" . $id;
        }

        if ($tender_id = (int) get_array_value($options, "tender_id")) {
            $where .= " AND $tbl.tender_id=" . $tender_id;
        }

        $sql = "SELECT
                    $tbl.*,
                    $t.reference AS tender_reference,
                    $t.title AS tender_title,
                    TRIM(CONCAT(COALESCE(requester.first_name,''), ' ', COALESCE(requester.last_name,''))) AS requested_by_name,
                    TRIM(CONCAT(COALESCE(approver.first_name,''), ' ', COALESCE(approver.last_name,''))) AS approved_by_name
                FROM $tbl
                LEFT JOIN $t ON $t.id = $tbl.tender_id
                LEFT JOIN $u requester ON requester.id = $tbl.created_by
                LEFT JOIN $u approver ON approver.id = $tbl.approved_by
                $where
                ORDER BY $tbl.id DESC";

        return $this->db->query($sql);
    }
}
