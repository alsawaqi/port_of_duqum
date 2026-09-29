<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Tender_evaluation_attachments_model extends Crud_model
{
    protected $table = null;
    private static bool $evaluation_attachment_schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_evaluation_attachments";
        parent::__construct($this->table);
        $this->ensure_evaluation_attachment_schema();
    }

    public function ensure_evaluation_attachment_schema(): void
    {
        if (self::$evaluation_attachment_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_evaluation_attachments" => [
                "id", "tender_evaluation_id", "tender_id", "tender_bid_id", "disk", "path",
                "original_name", "mime_type", "size_bytes", "uploaded_by", "created_at", "deleted",
            ],
        ], "tender evaluation attachments");

        self::$evaluation_attachment_schema_checked = true;
    }

    public function save_attachments(int $evaluation_id, int $tender_id, int $tender_bid_id, array $attachments, int $uploaded_by): void
    {
        if (!$evaluation_id || !$tender_id || !$tender_bid_id || empty($attachments)) {
            return;
        }

        $table = $this->db->prefixTable("tender_evaluation_attachments");
        $now = date("Y-m-d H:i:s");

        foreach ($attachments as $attachment) {
            $path = trim((string) ($attachment["path"] ?? ""));
            if ($path === "") {
                continue;
            }

            $this->db->query(
                "INSERT INTO $table
                    (tender_evaluation_id, tender_id, tender_bid_id, disk, path, original_name, mime_type, size_bytes, uploaded_by, created_at, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
                [
                    $evaluation_id,
                    $tender_id,
                    $tender_bid_id,
                    $attachment["disk"] ?? "local",
                    $path,
                    $attachment["original_name"] ?? null,
                    $attachment["mime_type"] ?? null,
                    !empty($attachment["size_bytes"]) ? (int) $attachment["size_bytes"] : null,
                    $uploaded_by ?: null,
                    $now,
                ]
            );
        }
    }

    public function get_grouped_by_evaluation_ids(array $evaluation_ids): array
    {
        $evaluation_ids = array_values(array_unique(array_filter(array_map("intval", $evaluation_ids))));
        if (!$evaluation_ids) {
            return [];
        }

        $table = $this->db->prefixTable("tender_evaluation_attachments");
        $placeholders = implode(",", array_fill(0, count($evaluation_ids), "?"));
        $rows = $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted=0
               AND tender_evaluation_id IN ($placeholders)
             ORDER BY id ASC",
            $evaluation_ids
        )->getResult();

        return $this->_group_by_evaluation_id($rows);
    }

    public function get_by_tender_grouped_by_evaluation_id(int $tender_id, ?string $type = null): array
    {
        if (!$tender_id) {
            return [];
        }

        $table = $this->db->prefixTable("tender_evaluation_attachments");
        $evaluations = $this->db->prefixTable("tender_evaluations");
        $params = [$tender_id];
        $type_sql = "";

        if ($type) {
            $type_sql = " AND $evaluations.type=?";
            $params[] = $type;
        }

        $rows = $this->db->query(
            "SELECT $table.*
             FROM $table
             INNER JOIN $evaluations
                ON $evaluations.id=$table.tender_evaluation_id
               AND $evaluations.deleted=0
             WHERE $table.deleted=0
               AND $table.tender_id=?
               $type_sql
             ORDER BY $table.id ASC",
            $params
        )->getResult();

        return $this->_group_by_evaluation_id($rows);
    }

    public function get_attachment(int $attachment_id)
    {
        $table = $this->db->prefixTable("tender_evaluation_attachments");
        $evaluations = $this->db->prefixTable("tender_evaluations");
        $bids = $this->db->prefixTable("tender_bids");
        $vendors = $this->db->prefixTable("vendors");

        return $this->db->query(
            "SELECT
                $table.*,
                $evaluations.type AS evaluation_type,
                $evaluations.evaluator_id,
                $evaluations.status AS evaluation_status,
                $bids.vendor_id,
                $vendors.vendor_name
             FROM $table
             INNER JOIN $evaluations
                ON $evaluations.id=$table.tender_evaluation_id
               AND $evaluations.deleted=0
             INNER JOIN $bids
                ON $bids.id=$table.tender_bid_id
               AND $bids.deleted=0
             LEFT JOIN $vendors
                ON $vendors.id=$bids.vendor_id
             WHERE $table.deleted=0
               AND $table.id=?
             LIMIT 1",
            [$attachment_id]
        )->getRow();
    }

    private function _group_by_evaluation_id(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $evaluation_id = (int) ($row->tender_evaluation_id ?? 0);
            if (!$evaluation_id) {
                continue;
            }

            if (!isset($grouped[$evaluation_id])) {
                $grouped[$evaluation_id] = [];
            }
            $grouped[$evaluation_id][] = $row;
        }

        return $grouped;
    }
}
