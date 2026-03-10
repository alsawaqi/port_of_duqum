<?php

namespace App\Models;

use CodeIgniter\I18n\Time;

class Tender_bid_openings_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_bid_openings";
        parent::__construct($this->table);
    }

    private function get_tender_business_now(): string
    {
        return Time::now('Asia/Muscat')->toDateTimeString();
    }

    public function expire_old_sessions(): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='expired', updated_at=?
             WHERE deleted=0
               AND status='codes_generated'
               AND expires_at IS NOT NULL
               AND expires_at < ?",
            [$now, $now]
        );
    }

    public function get_active_session(int $tender_id, string $stage = "commercial")
    {
        $this->expire_old_sessions();

        $tbo = $this->db->prefixTable("tender_bid_openings");

        $sql = "SELECT *
                FROM $tbo
                WHERE deleted=0
                  AND tender_id=?
                  AND stage=?
                  AND status IN ('codes_generated','unlocked')
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql, [$tender_id, $stage])->getRow();
    }

    public function get_confirmation_map(int $opening_id): array
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $sql = "SELECT role, COUNT(*) AS total
                FROM $tbl
                WHERE deleted=0
                  AND tender_bid_opening_id=?
                  AND is_valid=1
                GROUP BY role";

        $rows = $this->db->query($sql, [$opening_id])->getResult();
        $map = [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        foreach ($rows as $row) {
            $map[$row->role] = (int) $row->total;
        }

        return $map;
    }

    public function user_already_confirmed(int $opening_id, int $user_id): bool
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $row = $this->db->query(
            "SELECT id
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND user_id=?
               AND is_valid=1
             LIMIT 1",
            [$opening_id, $user_id]
        )->getRow();

        return !!$row;
    }

    public function create_new_session(int $tender_id, int $actor_id): int
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();
        $expires = Time::parse($now, 'Asia/Muscat')->addMinutes(5)->toDateTimeString();

        $this->db->query(
            "UPDATE $tbo
             SET status='expired', updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage='commercial'
               AND status='codes_generated'",
            [$now, $tender_id]
        );

        $data = [
            "tender_id"       => $tender_id,
            "stage"           => "commercial",
            "status"          => "codes_generated",
            "chairman_code"   => (string) random_int(100000, 999999),
            "secretary_code"  => (string) random_int(100000, 999999),
            "member_code"     => (string) random_int(100000, 999999),
            "generated_by"    => $actor_id,
            "generated_at"    => $now,
            "expires_at"      => $expires,
            "created_at"      => $now,
            "updated_at"      => $now,
            "deleted"         => 0
        ];

        return (int) $this->ci_save($data);
    }

    public function save_confirmation(
        int $opening_id,
        int $user_id,
        string $role,
        string $chairman_code,
        string $secretary_code,
        string $member_code,
        bool $is_valid
    ): void {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "INSERT INTO $tbl
            (tender_bid_opening_id, user_id, role, input_chairman_code, input_secretary_code, input_member_code, is_valid, confirmed_at, ip_address, user_agent, created_at, updated_at, deleted)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $opening_id,
                $user_id,
                $role,
                $chairman_code,
                $secretary_code,
                $member_code,
                $is_valid ? 1 : 0,
                $now,
                get_real_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $now,
                $now
            ]
        );
    }

    public function unlock_session(int $opening_id): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='unlocked',
                 unlocked_at=?,
                 updated_at=?
             WHERE id=?",
            [$now, $now, $opening_id]
        );
    }
}