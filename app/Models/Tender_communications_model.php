<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Tender_communications_model extends Crud_model
{
    protected $table = null;
    private static bool $clarification_scope_attachment_schema_checked = false;

    public function __construct()
    {
        $this->table = "tender_communications";
        parent::__construct($this->table);
        $this->ensure_clarification_scope_attachment_schema();
    }

    public function ensure_clarification_scope_attachment_schema(): void
    {
        if (self::$clarification_scope_attachment_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_communications" => ["clarification_scope", "tender_bid_id", "internal_audience", "type"],
            "tender_communication_attachments" => [
                "id", "communication_id", "tender_id", "vendor_id", "disk", "path",
                "original_name", "mime_type", "size_bytes", "uploaded_by", "created_at", "deleted",
            ],
        ], "tender clarifications and attachments");
        Runtime_schema_guard::requireColumnProperties(
            $this->db,
            "tender_communications",
            "type",
            ["types" => ["varchar"]],
            "tender clarification routing"
        );

        self::$clarification_scope_attachment_schema_checked = true;
    }

    public static function clarification_scope_options(): array
    {
        return [
            "general" => "General",
            "tender" => "Tender / Procurement",
            "technical" => "Technical Team",
            "commercial" => "Commercial Team",
            "vendor" => "Vendor Specific",
        ];
    }

    public static function normalize_clarification_scope(?string $scope): string
    {
        $scope = strtolower(trim((string) $scope));
        return array_key_exists($scope, self::clarification_scope_options()) ? $scope : "general";
    }

    public static function clarification_scope_label(?string $scope): string
    {
        $scope = self::normalize_clarification_scope($scope);
        return self::clarification_scope_options()[$scope] ?? "General";
    }

    public static function get_clarification_root_types(): array
    {
        return ["clarification", "technical_clarification_request", "commercial_clarification_request"];
    }

    public static function normalize_internal_audience(?string $audience): string
    {
        $audience = strtolower(trim((string) $audience));
        return in_array($audience, ["technical", "commercial"], true) ? $audience : "technical";
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
            } else {
                $where .= " AND ($tbl.vendor_id IS NULL OR $tbl.vendor_id=0)";
            }
        }

        if (array_key_exists("tender_bid_id", $options)) {
            $tender_bid_id = (int) get_array_value($options, "tender_bid_id");
            if ($tender_bid_id > 0) {
                $where .= " AND $tbl.tender_bid_id=" . $tender_bid_id;
            } else {
                $where .= " AND ($tbl.tender_bid_id IS NULL OR $tbl.tender_bid_id=0)";
            }
        }

        if ($internal_audience = trim((string) get_array_value($options, "internal_audience"))) {
            $where .= " AND $tbl.internal_audience=" . $this->db->escape(self::normalize_internal_audience($internal_audience));
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

    public function get_clarification_conversation(int $tender_id, int $vendor_id, bool $only_vendor_visible = false, array $scope_filter = []): array
    {
        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");
        $v = $this->db->prefixTable("vendors");
        $u = $this->db->prefixTable("users");

        $visibility_where = $only_vendor_visible ? " AND $tbl.is_vendor_visible=1" : "";
        $scope_filter = array_values(array_intersect(array_map([self::class, "normalize_clarification_scope"], $scope_filter), array_keys(self::clarification_scope_options())));
        $scope_where = "";
        $scope_params = [];
        if ($scope_filter) {
            $scope_where = " AND $tbl.clarification_scope IN (" . implode(",", array_fill(0, count($scope_filter), "?")) . ")";
            $scope_params = $scope_filter;
        }
        $global_updates_where = $only_vendor_visible
            ? " OR (
                    $tbl.type IN ('circular', 'addendum', 'site_visit_notice')
                    AND $tbl.sent_to_all=1
                    AND ($tbl.vendor_id IS NULL OR $tbl.vendor_id=0)
                  )"
            : "";
        $vendor_direct_reply_where = $only_vendor_visible ? " OR ($tbl.parent_id IS NOT NULL AND $tbl.vendor_id=?)" : "";

        $root_types = self::get_clarification_root_types();
        $root_type_placeholders = implode(",", array_fill(0, count($root_types), "?"));
        $root_subquery = "SELECT root.id
                          FROM $tbl root
                          WHERE root.deleted=0
                            AND root.tender_id=?
                            AND root.type IN ($root_type_placeholders)
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
                  $scope_where
                  AND (
                        (
                            $tbl.type IN ($root_type_placeholders)
                            AND ($tbl.parent_id IS NULL OR $tbl.parent_id=0)
                            AND $tbl.vendor_id=?
                        )
                        OR $tbl.parent_id IN ($root_subquery)
                        $global_updates_where
                        $vendor_direct_reply_where
                  )
                ORDER BY COALESCE($tbl.published_at, $tbl.created_at) ASC, $tbl.id ASC";

        $params = array_merge([$tender_id], $scope_params, $root_types, [$vendor_id, $tender_id], $root_types, [$vendor_id]);
        if ($only_vendor_visible) {
            $params[] = $vendor_id;
        }

        return $this->db->query($sql, $params)->getResult();
    }

    public function get_latest_vendor_root_clarification(int $tender_id, int $vendor_id)
    {
        $tbl = $this->db->prefixTable("tender_communications");
        $root_types = self::get_clarification_root_types();
        $placeholders = implode(",", array_fill(0, count($root_types), "?"));

        return $this->db->query(
            "SELECT *
             FROM $tbl
             WHERE deleted=0
               AND tender_id=?
               AND vendor_id=?
               AND type IN ($placeholders)
               AND (parent_id IS NULL OR parent_id=0)
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT 1",
            array_merge([$tender_id, $vendor_id], $root_types)
        )->getRow();
    }

    public function has_vendor_visible_evaluator_clarification_request(int $tender_id, int $vendor_id): bool
    {
        $tbl = $this->db->prefixTable("tender_communications");

        $row = $this->db->query(
            "SELECT id
             FROM $tbl
             WHERE deleted=0
               AND tender_id=?
               AND vendor_id=?
               AND clarification_scope IN ('technical', 'commercial')
               AND is_vendor_visible=1
               AND type IN ('response', 'technical_clarification_response', 'commercial_clarification_response', 'technical_clarification_request', 'commercial_clarification_request')
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT 1",
            [$tender_id, $vendor_id]
        )->getRow();

        return (bool) $row;
    }

    public function has_vendor_visible_technical_clarification_request(int $tender_id, int $vendor_id): bool
    {
        return $this->has_vendor_visible_evaluator_clarification_request($tender_id, $vendor_id);
    }

    public function get_latest_vendor_visible_evaluator_request(int $tender_id, int $vendor_id)
    {
        $tbl = $this->db->prefixTable("tender_communications");

        return $this->db->query(
            "SELECT root.*
             FROM $tbl root
             WHERE root.deleted=0
               AND root.tender_id=?
               AND root.vendor_id=?
               AND root.type IN ('technical_clarification_request', 'commercial_clarification_request')
               AND EXISTS (
                    SELECT 1
                    FROM $tbl child
                    WHERE child.deleted=0
                      AND child.parent_id=root.id
                      AND child.is_vendor_visible=1
               )
             ORDER BY COALESCE(root.published_at, root.created_at) DESC, root.id DESC
             LIMIT 1",
            [$tender_id, $vendor_id]
        )->getRow();
    }

    public function get_internal_conversation(int $tender_id, string $audience, ?int $tender_bid_id = null): array
    {
        $audience = self::normalize_internal_audience($audience);
        $root_type = $audience . "_clarification_request";
        $response_type = $audience . "_clarification_response";

        $tbl = $this->db->prefixTable("tender_communications");
        $t = $this->db->prefixTable("tenders");
        $v = $this->db->prefixTable("vendors");
        $u = $this->db->prefixTable("users");

        $bid_sql = $tender_bid_id ? "$tbl.tender_bid_id=?" : "($tbl.tender_bid_id IS NULL OR $tbl.tender_bid_id=0)";
        $root_bid_sql = $tender_bid_id ? "root.tender_bid_id=?" : "(root.tender_bid_id IS NULL OR root.tender_bid_id=0)";

        $params = [$tender_id, $audience, $root_type];
        if ($tender_bid_id) {
            $params[] = $tender_bid_id;
        }
        $params = array_merge($params, [$tender_id, $audience, $root_type]);
        if ($tender_bid_id) {
            $params[] = $tender_bid_id;
        }
        $params[] = $response_type;

        return $this->db->query(
            "SELECT
                $tbl.*,
                $t.reference AS tender_reference,
                $t.title AS tender_title,
                $v.vendor_name,
                TRIM(CONCAT(COALESCE($u.first_name,''), ' ', COALESCE($u.last_name,''))) AS created_by_name
             FROM $tbl
             LEFT JOIN $t ON $t.id=$tbl.tender_id
             LEFT JOIN $v ON $v.id=$tbl.vendor_id
             LEFT JOIN $u ON $u.id=$tbl.created_by
             WHERE $tbl.deleted=0
               AND $tbl.tender_id=?
               AND $tbl.internal_audience=?
               AND $tbl.is_vendor_visible=0
               AND (
                    (
                        $tbl.type=?
                        AND ($tbl.parent_id IS NULL OR $tbl.parent_id=0)
                        AND $bid_sql
                    )
                    OR (
                        $tbl.parent_id IN (
                            SELECT root.id
                            FROM $tbl root
                            WHERE root.deleted=0
                              AND root.tender_id=?
                              AND root.internal_audience=?
                              AND root.type=?
                              AND $root_bid_sql
                        )
                        AND $tbl.type=?
                    )
               )
             ORDER BY COALESCE($tbl.published_at, $tbl.created_at) ASC, $tbl.id ASC",
            $params
        )->getResult();
    }

    public function save_attachments(int $communication_id, int $tender_id, ?int $vendor_id, array $attachments, int $uploaded_by): void
    {
        if (!$communication_id || !$tender_id || empty($attachments)) {
            return;
        }

        $table = $this->db->prefixTable("tender_communication_attachments");
        $now = date("Y-m-d H:i:s");

        foreach ($attachments as $attachment) {
            $path = trim((string) ($attachment["path"] ?? ""));
            if ($path === "") {
                continue;
            }

            $this->db->query(
                "INSERT INTO $table
                    (communication_id, tender_id, vendor_id, disk, path, original_name, mime_type, size_bytes, uploaded_by, created_at, deleted)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
                [
                    $communication_id,
                    $tender_id,
                    $vendor_id ?: null,
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

    public function get_attachments_map(array $communication_ids): array
    {
        $communication_ids = array_values(array_unique(array_filter(array_map("intval", $communication_ids))));
        if (!$communication_ids) {
            return [];
        }

        $table = $this->db->prefixTable("tender_communication_attachments");
        $placeholders = implode(",", array_fill(0, count($communication_ids), "?"));
        $rows = $this->db->query(
            "SELECT *
             FROM $table
             WHERE deleted=0
               AND communication_id IN ($placeholders)
             ORDER BY id ASC",
            $communication_ids
        )->getResult();

        $map = [];
        foreach ($rows as $row) {
            $communication_id = (int) ($row->communication_id ?? 0);
            if (!isset($map[$communication_id])) {
                $map[$communication_id] = [];
            }
            $map[$communication_id][] = $row;
        }

        return $map;
    }

    public function get_attachment(int $attachment_id)
    {
        $attachments = $this->db->prefixTable("tender_communication_attachments");
        $communications = $this->db->prefixTable("tender_communications");

        return $this->db->query(
            "SELECT
                $attachments.*,
                $communications.type,
                $communications.clarification_scope,
                $communications.tender_id,
                $communications.tender_bid_id,
                $communications.vendor_id,
                $communications.internal_audience,
                $communications.is_vendor_visible,
                $communications.sent_to_all,
                $communications.parent_id
             FROM $attachments
             JOIN $communications ON $communications.id=$attachments.communication_id AND $communications.deleted=0
             WHERE $attachments.deleted=0
               AND $attachments.id=?
             LIMIT 1",
            [$attachment_id]
        )->getRow();
    }
}
