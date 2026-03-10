<?php

namespace App\Models;

class Tender_bid_documents_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_bid_documents";
        parent::__construct($this->table);
    }

    public function get_bid_documents(int $tender_bid_id)
    {
        $tbd = $this->db->prefixTable("tender_bid_documents");

        $sql = "SELECT *
                FROM $tbd
                WHERE deleted = 0
                  AND tender_bid_id = ?
                ORDER BY id DESC";

        return $this->db->query($sql, [$tender_bid_id])->getResult();
    }

    public function get_bid_document_by_section(int $tender_bid_id, string $section)
    {
        $tbd = $this->db->prefixTable("tender_bid_documents");

        $sql = "SELECT *
                FROM $tbd
                WHERE deleted = 0
                  AND tender_bid_id = ?
                  AND section = ?
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql, [$tender_bid_id, $section])->getRow();
    }
}