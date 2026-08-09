<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;

class Gate_pass_blocked_visitors_model extends Crud_model
{
    protected $table = null;
    private static bool $schema_checked = false;

    public function __construct()
    {
        $this->table = "gate_pass_blocked_visitors";
        parent::__construct($this->table);
        $this->ensure_schema();
    }

    public function ensure_schema(): void
    {
        if (self::$schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "gate_pass_blocked_visitors" => [
                "id", "id_number", "normalized_id_number", "id_type", "visitor_name",
                "nationality", "visitor_company", "source_request_id", "source_visitor_id",
                "reason", "status", "blocked_by", "blocked_at", "unblocked_by",
                "unblocked_at", "unblock_reason", "last_action_by", "last_action_at",
                "created_at", "updated_at", "deleted",
            ],
            "gate_pass_blocked_visitor_logs" => [
                "id", "blocked_visitor_id", "action", "reason", "action_by",
                "action_at", "ip_address", "user_agent", "deleted",
            ],
        ], "gate-pass blocked visitor controls");

        self::$schema_checked = true;
    }

    public function normalize_id_number(?string $id_number): string
    {
        $id_number = strtoupper(trim((string) $id_number));
        return preg_replace('/[\s\-]+/', '', $id_number) ?: "";
    }

    public function get_details(array $options = [])
    {
        $blocked = $this->db->prefixTable("gate_pass_blocked_visitors");
        $users = $this->db->prefixTable("users");

        $where = "WHERE bv.deleted=0";
        $params = [];

        if (!empty($options["id"])) {
            $where .= " AND bv.id=?";
            $params[] = (int) $options["id"];
        }

        if (!empty($options["status"])) {
            $where .= " AND bv.status=?";
            $params[] = (string) $options["status"];
        }

        $sql = "SELECT bv.*,
                    TRIM(CONCAT(COALESCE(blocked_by_user.first_name, ''), ' ', COALESCE(blocked_by_user.last_name, ''))) AS blocked_by_name,
                    TRIM(CONCAT(COALESCE(unblocked_by_user.first_name, ''), ' ', COALESCE(unblocked_by_user.last_name, ''))) AS unblocked_by_name,
                    TRIM(CONCAT(COALESCE(last_action_user.first_name, ''), ' ', COALESCE(last_action_user.last_name, ''))) AS last_action_by_name
                FROM $blocked bv
                LEFT JOIN $users blocked_by_user ON blocked_by_user.id = bv.blocked_by
                LEFT JOIN $users unblocked_by_user ON unblocked_by_user.id = bv.unblocked_by
                LEFT JOIN $users last_action_user ON last_action_user.id = bv.last_action_by
                $where
                ORDER BY bv.last_action_at DESC, bv.id DESC";

        return $this->db->query($sql, $params);
    }

    public function find_by_id_number(?string $id_number)
    {
        $normalized = $this->normalize_id_number($id_number);
        if ($normalized === "") {
            return null;
        }

        return $this->find_by_normalized_id($normalized);
    }

    public function find_active_by_id_number(?string $id_number)
    {
        $row = $this->find_by_id_number($id_number);
        if (!$row || strtolower((string) ($row->status ?? "")) !== "blocked") {
            return null;
        }

        return $row;
    }

    public function find_by_normalized_id(string $normalized_id)
    {
        $blocked = $this->db->prefixTable("gate_pass_blocked_visitors");

        return $this->db->query(
            "SELECT *
             FROM $blocked
             WHERE deleted=0
               AND normalized_id_number=?
             LIMIT 1",
            [$normalized_id]
        )->getRow();
    }

    public function block_visitor(array $data, int $actor_user_id, ?string $ip_address = null, ?string $user_agent = null): int
    {
        $id_number = trim((string) ($data["id_number"] ?? ""));
        $normalized = $this->normalize_id_number($id_number);
        if ($normalized === "") {
            return 0;
        }

        $existing = $this->find_by_normalized_id($normalized);
        $now = get_current_utc_time();
        $was_unblocked = $existing && strtolower((string) ($existing->status ?? "")) === "unblocked";
        $action = $was_unblocked ? "reblocked" : "blocked";

        $payload = [
            "id_number" => $id_number,
            "normalized_id_number" => $normalized,
            "id_type" => trim((string) ($data["id_type"] ?? "")) ?: null,
            "visitor_name" => trim((string) ($data["visitor_name"] ?? "")) ?: null,
            "nationality" => trim((string) ($data["nationality"] ?? "")) ?: null,
            "visitor_company" => trim((string) ($data["visitor_company"] ?? "")) ?: null,
            "source_request_id" => !empty($data["source_request_id"]) ? (int) $data["source_request_id"] : null,
            "source_visitor_id" => !empty($data["source_visitor_id"]) ? (int) $data["source_visitor_id"] : null,
            "reason" => trim((string) ($data["reason"] ?? "")) ?: null,
            "status" => "blocked",
            "blocked_by" => $actor_user_id ?: null,
            "blocked_at" => $now,
            "unblocked_by" => null,
            "unblocked_at" => null,
            "unblock_reason" => null,
            "last_action_by" => $actor_user_id ?: null,
            "last_action_at" => $now,
            "updated_at" => $now,
            "deleted" => 0,
        ];

        if (!$existing) {
            $payload["created_at"] = $now;
        }

        $existing_id = (int) ($existing->id ?? 0);
        $saved = $this->ci_save(clean_data($payload), $existing_id);
        $save_id = $existing_id ? ($saved ? $existing_id : 0) : (int) $saved;
        if ($save_id) {
            $this->_log_action($save_id, $action, (string) ($payload["reason"] ?? ""), $actor_user_id, $ip_address, $user_agent);
            $this->sync_request_visitor_flags($id_number);
        }

        return $save_id;
    }

    public function unblock_visitor(int $id, int $actor_user_id, ?string $reason = "", ?string $ip_address = null, ?string $user_agent = null): bool
    {
        $row = $this->get_details(["id" => $id])->getRow();
        if (!$row) {
            return false;
        }

        $now = get_current_utc_time();
        $reason = trim((string) $reason);

        $ok = $this->ci_save(clean_data([
            "status" => "unblocked",
            "unblocked_by" => $actor_user_id ?: null,
            "unblocked_at" => $now,
            "unblock_reason" => $reason ?: null,
            "last_action_by" => $actor_user_id ?: null,
            "last_action_at" => $now,
            "updated_at" => $now,
        ]), $id);

        if ($ok) {
            $this->_log_action($id, "unblocked", $reason, $actor_user_id, $ip_address, $user_agent);
            $this->sync_request_visitor_flags((string) ($row->id_number ?? ""));
        }

        return (bool) $ok;
    }

    public function get_logs(int $blocked_visitor_id)
    {
        $logs = $this->db->prefixTable("gate_pass_blocked_visitor_logs");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT l.*,
                    TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS action_by_name
             FROM $logs l
             LEFT JOIN $users u ON u.id = l.action_by
             WHERE l.deleted=0
               AND l.blocked_visitor_id=?
             ORDER BY l.action_at DESC, l.id DESC",
            [$blocked_visitor_id]
        );
    }

    public function sync_request_visitor_flags(?string $id_number): void
    {
        $normalized = $this->normalize_id_number($id_number);
        if ($normalized === "") {
            return;
        }

        $visitors = $this->db->prefixTable("gate_pass_request_visitors");

        try {
            $fields = $this->db->getFieldNames($visitors);
        } catch (\Throwable $e) {
            return;
        }

        if (!in_array("is_blocked", $fields, true)) {
            return;
        }

        $active = $this->find_active_by_id_number($id_number);
        $sets = [];
        $params = [];

        if ($active) {
            $field_map = [
                "is_blocked" => 1,
                "block_reason" => $active->reason ?? null,
                "blocked_by" => $active->blocked_by ?? null,
                "blocked_at" => $active->blocked_at ?? null,
            ];
        } else {
            $field_map = [
                "is_blocked" => 0,
                "block_reason" => null,
                "blocked_by" => null,
                "blocked_at" => null,
            ];
        }

        foreach ($field_map as $field => $value) {
            if (in_array($field, $fields, true)) {
                $sets[] = "$field=?";
                $params[] = $value;
            }
        }

        if (!$sets) {
            return;
        }

        $params[] = $normalized;
        $normalized_expr = "UPPER(REPLACE(REPLACE(TRIM(COALESCE(id_number, '')), ' ', ''), '-', ''))";
        $this->db->query(
            "UPDATE $visitors
             SET " . implode(", ", $sets) . "
             WHERE deleted=0
               AND $normalized_expr=?",
            $params
        );
    }

    private function _log_action(int $blocked_visitor_id, string $action, string $reason, int $actor_user_id, ?string $ip_address, ?string $user_agent): void
    {
        $logs = $this->db->prefixTable("gate_pass_blocked_visitor_logs");

        $this->db->table($logs)->insert(clean_data([
            "blocked_visitor_id" => $blocked_visitor_id,
            "action" => $action,
            "reason" => trim($reason) ?: null,
            "action_by" => $actor_user_id ?: null,
            "action_at" => get_current_utc_time(),
            "ip_address" => $ip_address,
            "user_agent" => $user_agent ? substr($user_agent, 0, 500) : null,
            "deleted" => 0,
        ]));
    }
}
