<?php

namespace App\Models;

class Tender_team_members_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_team_members";
        parent::__construct($this->table);
    }

    public function get_members(int $tender_id, ?string $role = null)
    {
        $ttm = $this->db->prefixTable("tender_team_members");
        $users = $this->db->prefixTable("users");

        $where = "WHERE $ttm.deleted=0
                  AND $ttm.is_active=1
                  AND $users.deleted=0
                  AND $ttm.tender_id=" . (int) $tender_id;

        if ($role) {
            $where .= " AND $ttm.team_role=" . $this->db->escape($role);
        }

        $sql = "SELECT
                    $users.id,
                    TRIM(CONCAT(COALESCE($users.first_name,''), ' ', COALESCE($users.last_name,''))) AS full_name,
                    $users.email,
                    $ttm.team_role
                FROM $ttm
                LEFT JOIN $users ON $users.id = $ttm.user_id
                $where
                ORDER BY $users.first_name ASC, $users.last_name ASC";

        return $this->db->query($sql)->getResult();
    }

    public function sync_members(int $tender_id, string $role, array $user_ids): bool
    {
        $table = $this->db->prefixTable("tender_team_members");
        $tender_id = (int) $tender_id;

        $normalized = [];
        foreach ($user_ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $normalized[] = $id;
            }
        }
        $normalized = array_values(array_unique($normalized));

        $this->db->query(
            "UPDATE $table SET deleted=1, is_active=0 WHERE tender_id=? AND team_role=?",
            [$tender_id, $role]
        );

        if (!count($normalized)) {
            return true;
        }

        $now = date("Y-m-d H:i:s");

        foreach ($normalized as $user_id) {
            $existing = $this->db->query(
                "SELECT id
                 FROM $table
                 WHERE tender_id=? AND user_id=? AND team_role=?
                 ORDER BY id DESC
                 LIMIT 1",
                [$tender_id, $user_id, $role]
            )->getRow();

            if ($existing && $existing->id) {
                $this->db->query(
                    "UPDATE $table
                     SET deleted=0, is_active=1, updated_at=?
                     WHERE id=?",
                    [$now, (int) $existing->id]
                );
            } else {
                $this->db->query(
                    "INSERT INTO $table
                     (tender_id, user_id, team_role, is_active, created_at, updated_at, deleted)
                     VALUES (?, ?, ?, 1, ?, ?, 0)",
                    [$tender_id, $user_id, $role, $now, $now]
                );
            }
        }

        return true;
    }
}