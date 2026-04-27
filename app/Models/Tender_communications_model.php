<?php

namespace App\Models;

class Tender_communications_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_communications";
        parent::__construct($this->table);
    }

    public function get_details(array $options = [])
    {
        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");
        $v = $this->db->prefixTable("vendors");
        $u = $this->db->prefixTable("users");

        $where = "WHERE $tbl.deleted=0";

        if ($id = (int) get_array_value($options, "id")) {
            $where .= " AND $tbl.id=" . $id;
        }

        if ($tender_id = (int) get_array_value($options, "tender_id")) {
            $where .= " AND $tbl.tender_id=" . $tender_id;
        }

        if (array_key_exists("parent_id", $options)) {
            $parent_id = (int) get_array_value($options, "parent_id");
            if ($parent_id > 0) {
                $where .= " AND $tbl.parent_id=" . $parent_id;
            } else {
                $where .= " AND ($tbl.parent_id IS NULL OR $tbl.parent_id=0)";
            }
        }

        if (array_key_exists("vendor_id", $options)) {
            $vendor_id = (int) get_array_value($options, "vendor_id");
            if ($vendor_id > 0) {
                $where .= " AND ($tbl.vendor_id IS NULL OR $tbl.vendor_id=" . $vendor_id . ")";
            }
        }

        if ($only_vendor_visible = (int) get_array_value($options, "only_vendor_visible")) {
            $where .= " AND $tbl.is_vendor_visible=1";
        }

        if ($type = trim((string) get_array_value($options, "type"))) {
            $where .= " AND $tbl.type=" . $this->db->escape($type);
        }

        $sql = "SELECT
                    $tbl.*,
                    $t.reference AS tender_reference,
                    $t.title AS tender_title,
                    $v.vendor_name,
                    TRIM(CONCAT(COALESCE($u.first_name,''), ' ', COALESCE($u.last_name,''))) AS created_by_name
                FROM $tbl
                LEFT JOIN $t ON $t.id = $tbl.tender_id
                LEFT JOIN $v ON $v.id = $tbl.vendor_id
                LEFT JOIN $u ON $u.id = $tbl.created_by
                $where
                ORDER BY COALESCE($tbl.published_at, $tbl.created_at) DESC, $tbl.id DESC";

        return $this->db->query($sql);
    }

    public function get_clarification_conversation(int $tender_id, int $vendor_id, bool $only_vendor_visible = false): array
    {
        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");
        $v = $this->db->prefixTable("vendors");
        $u = $this->db->prefixTable("users");

        $visibility_where = $only_vendor_visible ? " AND $tbl.is_vendor_visible=1" : "";
        $global_updates_where = $only_vendor_visible
            ? " OR (
                    $tbl.type IN ('circular', 'addendum', 'site_visit_notice')
                    AND $tbl.sent_to_all=1
                    AND ($tbl.vendor_id IS NULL OR $tbl.vendor_id=0)
                  )"
            : "";

        $root_subquery = "SELECT root.id
                          FROM $tbl root
                          WHERE root.deleted=0
                            AND root.tender_id=?
                            AND root.type='clarification'
                            AND (root.parent_id IS NULL OR root.parent_id=0)
                            AND root.vendor_id=?";

        $sql = "SELECT
                    $tbl.*,
                    $t.reference AS tender_reference,
                    $t.title AS tender_title,
                    $v.vendor_name,
                    TRIM(CONCAT(COALESCE($u.first_name,''), ' ', COALESCE($u.last_name,''))) AS created_by_name
                FROM $tbl
                LEFT JOIN $t ON $t.id = $tbl.tender_id
                LEFT JOIN $v ON $v.id = $tbl.vendor_id
                LEFT JOIN $u ON $u.id = $tbl.created_by
                WHERE $tbl.deleted=0
                  AND $tbl.tender_id=?
                  $visibility_where
                  AND (
                        (
                            $tbl.type='clarification'
                            AND ($tbl.parent_id IS NULL OR $tbl.parent_id=0)
                            AND $tbl.vendor_id=?
                        )
                        OR $tbl.parent_id IN ($root_subquery)
                        $global_updates_where
                  )
                ORDER BY COALESCE($tbl.published_at, $tbl.created_at) ASC, $tbl.id ASC";

        return $this->db->query($sql, [$tender_id, $vendor_id, $tender_id, $vendor_id])->getResult();
    }

    public function get_latest_vendor_root_clarification(int $tender_id, int $vendor_id)
    {
        $tbl = $this->db->prefixTable("tender_communications");

        return $this->db->query(
            "SELECT *
             FROM $tbl
             WHERE deleted=0
               AND tender_id=?
               AND vendor_id=?
               AND type='clarification'
               AND (parent_id IS NULL OR parent_id=0)
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT 1",
            [$tender_id, $vendor_id]
        )->getRow();
    }
}
