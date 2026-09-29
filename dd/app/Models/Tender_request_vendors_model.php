<?php

namespace App\Models;

class Tender_request_vendors_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_request_vendors"; // will map to pod_tender_request_vendors via prefix
        parent::__construct($this->table);
    }

    public function get_selected_vendors($tender_request_id)
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $vendors = $this->db->prefixTable("vendors");

        $sql = "SELECT $vendors.id, $vendors.vendor_name
                FROM $trv
                JOIN $vendors ON $vendors.id = $trv.vendor_id
                WHERE $trv.deleted=0
                  AND $vendors.deleted=0
                  AND $trv.tender_request_id=?
                ORDER BY $vendors.vendor_name ASC";

        return $this->db->query($sql, [(int)$tender_request_id])->getResult();
    }

    public function sync_request_vendors($tender_request_id, $vendor_ids, $user_id)
    {
        $trv = $this->db->prefixTable("tender_request_vendors");
        $tender_request_id = (int)$tender_request_id;

        // mark all as deleted first
        $this->db->query("UPDATE $trv SET deleted=1 WHERE tender_request_id=?", [$tender_request_id]);

        if (!is_array($vendor_ids) || !count($vendor_ids)) {
            return true;
        }

        $now = date("Y-m-d H:i:s");

        foreach ($vendor_ids as $vendor_id) {
            $vendor_id = (int)$vendor_id;
            if (!$vendor_id) continue;

            // if exists before, restore the latest row, else insert new
            $row = $this->db->query(
                "SELECT id FROM $trv WHERE tender_request_id=? AND vendor_id=? ORDER BY id DESC LIMIT 1",
                [$tender_request_id, $vendor_id]
            )->getRow();

            if ($row && $row->id) {
                $this->db->query(
                    "UPDATE $trv SET deleted=0, created_by=?, created_at=? WHERE id=?",
                    [(int)$user_id, $now, (int)$row->id]
                );
            } else {
                $this->db->query(
                    "INSERT INTO $trv (tender_request_id, vendor_id, created_by, created_at, deleted)
                     VALUES (?, ?, ?, ?, 0)",
                    [$tender_request_id, $vendor_id, (int)$user_id, $now]
                );
            }
        }

        return true;
    }
}