<?php

namespace App\Models;

class Tender_request_team_members_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_request_team_members";
        parent::__construct($this->table);
    }

    public function get_members(int $tender_request_id, ?string $role = null)
    {
        $trtm = $this->db->prefixTable("tender_request_team_members");
        $users = $this->db->prefixTable("users");

        $where = "WHERE $trtm.deleted=0
                  AND $users.deleted=0
                  AND $trtm.tender_request_id=" . (int) $tender_request_id;

        if ($role) {
            $where .= " AND $trtm.team_role=" . $this->db->escape($role);
        }

        $sql = "SELECT
                    $users.id,
                    TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
                    $users.email,
                    $trtm.team_role
                FROM $trtm
                LEFT JOIN $users ON $users.id = $trtm.user_id
                $where
                ORDER BY $users.first_name ASC, $users.last_name ASC";

        return $this->db->query($sql)->getResult();
    }

    public function get_grouped_members(int $tender_request_id): array
    {
        $rows = $this->get_members($tender_request_id);
        $grouped = [
            "technical_evaluator" => [],
            "commercial_evaluator" => [],
            "itc_member" => [],
            "chairman" => [],
            "secretary" => [],
        ];

        foreach ($rows as $row) {
            $role = (string) ($row->team_role ?? "");
            if (!isset($grouped[$role])) {
                $grouped[$role] = [];
            }
            $grouped[$role][] = $row;
        }

        return $grouped;
    }

    public function sync_members(int $tender_request_id, string $role, array $user_ids, int $actor_id): bool
    {
        $table = $this->db->prefixTable("tender_request_team_members");
        $tender_request_id = (int) $tender_request_id;
        $actor_id = (int) $actor_id;

        $normalized = [];
        foreach ($user_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[] = $id;
            }
        }
        $normalized = array_values(array_unique($normalized));

        $this->db->query(
            "UPDATE $table SET deleted=1 WHERE tender_request_id=? AND team_role=?",
            [$tender_request_id, $role]
        );

        if (!count($normalized)) {
            return true;
        }

        $now = date("Y-m-d H:i:s");

        foreach ($normalized as $user_id) {
            $existing = $this->db->query(
                "SELECT id
                 FROM $table
                 WHERE tender_request_id=? AND user_id=? AND team_role=?
                 ORDER BY id DESC
                 LIMIT 1",
                [$tender_request_id, $user_id, $role]
            )->getRow();

            if ($existing && $existing->id) {
                $this->db->query(
                    "UPDATE $table
                     SET deleted=0, created_by=?, created_at=?
                     WHERE id=?",
                    [$actor_id, $now, (int) $existing->id]
                );
            } else {
                $this->db->query(
                    "INSERT INTO $table
                     (tender_request_id, user_id, team_role, created_by, created_at, deleted)
                     VALUES (?, ?, ?, ?, ?, 0)",
                    [$tender_request_id, $user_id, $role, $actor_id, $now]
                );
            }
        }

        return true;
    }
}