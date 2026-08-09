<?php

namespace App\Models;

use App\Libraries\TenderOpeningCodeVault;
use CodeIgniter\I18n\Time;

class Tender_bid_openings_model extends Crud_model
{
    protected $table = null;
    private static bool $single_opening_signature_schema_checked = false;
    private static bool $expired_session_cleanup_ran = false;
    private const CONFIRMATION_FAILURE_WINDOW_MINUTES = 15;
    private const CONFIRMATION_USER_FAILURE_LIMIT = 5;
    private const CONFIRMATION_IP_FAILURE_LIMIT = 20;
    private const REQUIRED_ROLES = ["chairman", "secretary", "itc_member"];
    private TenderOpeningCodeVault $codeVault;

    public function __construct()
    {
        $this->table = "tender_bid_openings";
        parent::__construct($this->table);
        $this->codeVault = new TenderOpeningCodeVault();
        $this->ensure_single_opening_signature_schema();
    }

    public function ensure_single_opening_signature_schema(): void
    {
        if (self::$single_opening_signature_schema_checked) {
            return;
        }

        $requirements = [
            "tender_bid_openings" => [
                "id", "tender_id", "stage", "status",
                "chairman_code", "secretary_code", "member_code",
                "chairman_code_hash", "secretary_code_hash", "member_code_hash",
                "chairman_code_ciphertext", "secretary_code_ciphertext", "member_code_ciphertext",
                "generated_by", "generated_at", "expires_at", "unlocked_at", "signed_at",
                "manual_form_path", "manual_form_original_name",
                "manual_form_uploaded_by", "manual_form_uploaded_at",
                "created_at", "updated_at", "deleted",
            ],
            "tender_bid_opening_entries" => [
                "id", "tender_bid_opening_id", "user_id", "role",
                "input_chairman_code", "input_secretary_code", "input_member_code",
                "is_valid", "confirmed_at", "ip_address", "user_agent",
                "signature_statement", "signature_name", "signature_image_path",
                "signed_at", "signature_ip_address", "signature_user_agent",
                "created_at", "updated_at", "deleted",
            ],
        ];

        foreach ($requirements as $table => $required_fields) {
            if (!$this->db->tableExists($table)) {
                throw new \RuntimeException("Tender opening database schema is not installed.");
            }

            $missing = array_diff($required_fields, $this->db->getFieldNames($table));
            if ($missing) {
                throw new \RuntimeException(
                    "Tender opening database migration is required; missing fields: "
                    . implode(", ", $missing)
                );
            }
        }

        $openings = $this->db->prefixTable("tender_bid_openings");
        $status_info = $this->db->query(
            "SHOW COLUMNS FROM `$openings` LIKE 'status'"
        )->getRow();
        if (!$status_info || stripos((string) ($status_info->Type ?? ""), "varchar") === false) {
            throw new \RuntimeException("Tender opening status migration is required.");
        }

        self::$single_opening_signature_schema_checked = true;
    }

    private function get_tender_business_now(): string
    {
        return Time::now('Asia/Muscat')->toDateTimeString();
    }

    public function expire_old_sessions(): void
    {
        if (self::$expired_session_cleanup_ran) {
            return;
        }

        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 chairman_code=NULL,
                 secretary_code=NULL,
                 member_code=NULL,
                 chairman_code_hash=NULL,
                 secretary_code_hash=NULL,
                 member_code_hash=NULL,
                 chairman_code_ciphertext=NULL,
                 secretary_code_ciphertext=NULL,
                 member_code_ciphertext=NULL,
                 updated_at=?
             WHERE deleted=0
               AND status='codes_generated'
               AND (expires_at IS NULL OR expires_at <= ?)",
            [$now, $now]
        );
        self::$expired_session_cleanup_ran = true;
    }

    public function get_active_session(int $tender_id, string $stage = "technical")
    {
        $this->expire_old_sessions();
        $stage = $this->_normalize_stage($stage);

        $tbo = $this->db->prefixTable("tender_bid_openings");

        $sql = "SELECT
                    id,
                    tender_id,
                    stage,
                    status,
                    generated_by,
                    generated_at,
                    expires_at,
                    unlocked_at,
                    signed_at,
                    manual_form_path,
                    manual_form_original_name,
                    manual_form_uploaded_by,
                    manual_form_uploaded_at,
                    created_at,
                    updated_at,
                    deleted
                FROM $tbo
                WHERE deleted=0
                  AND tender_id=?
                  AND stage=?
                  AND status IN ('codes_generated','unlocked','signed','manual_accepted')
                ORDER BY id DESC
                LIMIT 1";

        return $this->stripOpeningSecrets(
            $this->db->query($sql, [$tender_id, $stage])->getRow()
        );
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

    public function get_signature_map(int $opening_id): array
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");

        $rows = $this->db->query(
            "SELECT role, COUNT(*) AS total
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND is_valid=1
               AND signed_at IS NOT NULL
             GROUP BY role",
            [$opening_id]
        )->getResult();

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

    public function all_required_signatures_completed(int $opening_id): bool
    {
        $map = $this->get_signature_map($opening_id);
        return $map["chairman"] >= 1 && $map["secretary"] >= 1 && $map["itc_member"] >= 1;
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

    public function create_new_session(int $tender_id, int $actor_id, string $stage = "technical"): int
    {
        $stage = $this->_normalize_stage($stage);
        $this->codeVault->assertReady();
        if ($actor_id < 1) {
            throw new \InvalidArgumentException("Invalid tender opening generator.");
        }

        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tenders = $this->db->prefixTable("tenders");
        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $team_members = $this->db->prefixTable("tender_team_members");
        $now = $this->get_tender_business_now();
        $expires = Time::parse($now, 'Asia/Muscat')->addMinutes(5)->toDateTimeString();
        $codes = [];
        $transaction_started = false;

        try {
            foreach (self::REQUIRED_ROLES as $role) {
                do {
                    $codes[$role] = $this->codeVault->generateCode();
                } while (count(array_unique($codes, SORT_STRING)) !== count($codes));
            }

            $this->db->transBegin();
            $transaction_started = true;
            $tender = $this->db->query(
                "SELECT id
                 FROM $tenders
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id]
            )->getRow();
            if (!$tender) {
                throw new \RuntimeException("Tender opening session has no active tender.");
            }

            $assigned_generator = $this->db->query(
                "SELECT id
                 FROM $team_members
                 WHERE tender_id=?
                   AND user_id=?
                   AND deleted=0
                   AND is_active=1
                   AND team_role IN ('chairman','secretary','itc_member')
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id, $actor_id]
            )->getRow();
            if (!$assigned_generator) {
                throw new \RuntimeException("Tender opening generator is not actively assigned.");
            }

            $this->db->query(
                "UPDATE $tbo
                 SET status='expired',
                     chairman_code=NULL,
                     secretary_code=NULL,
                     member_code=NULL,
                     chairman_code_hash=NULL,
                     secretary_code_hash=NULL,
                     member_code_hash=NULL,
                     chairman_code_ciphertext=NULL,
                     secretary_code_ciphertext=NULL,
                     member_code_ciphertext=NULL,
                     updated_at=?
                 WHERE tender_id=?
                   AND stage=?
                   AND deleted=0
                   AND status='codes_generated'
                   AND (expires_at IS NULL OR expires_at<=?)",
                [$now, $tender_id, $stage, $now]
            );

            $terminal_session = $this->db->query(
                "SELECT id
                 FROM $tbo
                 WHERE tender_id=?
                   AND stage=?
                   AND deleted=0
                   AND status IN ('unlocked','signed','manual_accepted')
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id, $stage]
            )->getRow();
            if ($terminal_session) {
                throw new \DomainException(
                    "Tender opening codes cannot be regenerated after the opening is unlocked."
                );
            }

            $attempt = $this->db->query(
                "SELECT $entries.id
                 FROM $entries
                 INNER JOIN $tbo
                    ON $tbo.id=$entries.tender_bid_opening_id
                   AND $tbo.tender_id=?
                   AND $tbo.stage=?
                   AND $tbo.deleted=0
                   AND $tbo.status='codes_generated'
                 WHERE $entries.deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id, $stage]
            )->getRow();
            if ($attempt) {
                throw new \DomainException(
                    "Tender opening codes cannot be regenerated after a confirmation attempt."
                );
            }

            $this->db->query(
                "UPDATE $tbo
                 SET status='expired',
                     chairman_code=NULL,
                     secretary_code=NULL,
                     member_code=NULL,
                     chairman_code_hash=NULL,
                     secretary_code_hash=NULL,
                     member_code_hash=NULL,
                     chairman_code_ciphertext=NULL,
                     secretary_code_ciphertext=NULL,
                     member_code_ciphertext=NULL,
                     updated_at=?
                 WHERE deleted=0
                   AND tender_id=?
                   AND stage=?
                   AND status='codes_generated'",
                [$now, $tender_id, $stage]
            );

            $this->db->table($tbo)->insert([
                "tender_id" => $tender_id,
                "stage" => $stage,
                "status" => "codes_generated",
                "generated_by" => $actor_id,
                "generated_at" => $now,
                "expires_at" => $expires,
                "created_at" => $now,
                "updated_at" => $now,
                "deleted" => 0,
            ]);
            $opening_id = (int) $this->db->insertID();
            if (!$opening_id) {
                throw new \RuntimeException("Tender opening session could not be created.");
            }

            $secret_fields = [];
            foreach ($codes as $role => $code) {
                $sealed = $this->codeVault->seal(
                    $code,
                    $this->secretContext($opening_id, $tender_id, $stage, $role)
                );
                $columns = $this->roleSecretColumns($role);
                $secret_fields[$columns["hash"]] = $sealed["hash"];
                $secret_fields[$columns["ciphertext"]] = $sealed["ciphertext"];
            }

            $this->db->table($tbo)
                ->where("id", $opening_id)
                ->where("status", "codes_generated")
                ->where("deleted", 0)
                ->update($secret_fields);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Tender opening secrets could not be stored.");
            }
            $this->db->transCommit();
            $transaction_started = false;
            return $opening_id;
        } catch (\Throwable $e) {
            if ($transaction_started) {
                $this->db->transRollback();
            }
            throw $e;
        } finally {
            $this->wipeCodes($codes);
        }
    }

    public function getRoleCodeForDisplay(int $opening_id, int $user_id, string $role): string
    {
        $this->codeVault->assertReady();
        $this->expire_old_sessions();
        $role = $this->normalizeRole($role);
        if ($user_id < 1) {
            throw new \InvalidArgumentException("Invalid tender opening committee user.");
        }
        $columns = $this->roleSecretColumns($role);
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $team_members = $this->db->prefixTable("tender_team_members");
        $now = $this->get_tender_business_now();

        $session = $this->db->query(
            "SELECT
                    $tbo.id,
                    $tbo.tender_id,
                    $tbo.stage,
                    $tbo.status,
                    $tbo.expires_at,
                    $tbo.{$columns["ciphertext"]} AS role_ciphertext
             FROM $tbo
             INNER JOIN $team_members
                ON $team_members.tender_id=$tbo.tender_id
               AND $team_members.user_id=?
               AND $team_members.team_role=?
               AND $team_members.deleted=0
               AND $team_members.is_active=1
             WHERE $tbo.id=?
               AND $tbo.deleted=0
               AND $tbo.status='codes_generated'
               AND $tbo.expires_at IS NOT NULL
               AND $tbo.expires_at>?
             LIMIT 1",
            [$user_id, $role, $opening_id, $now]
        )->getRow();

        if (!$session || empty($session->role_ciphertext)) {
            throw new \RuntimeException("Tender opening code is unavailable.");
        }

        return $this->codeVault->reveal(
            (string) $session->role_ciphertext,
            $this->secretContext(
                (int) $session->id,
                (int) $session->tender_id,
                (string) $session->stage,
                $role
            )
        );
    }

    /**
     * Atomically verifies one role's code, records an audit attempt without
     * plaintext input, and unlocks only after three distinct roles/users.
     *
     * @return array{status:string,unlocked:bool,retry_after_seconds?:int}
     */
    public function confirmRoleCode(
        int $opening_id,
        int $user_id,
        string $role,
        #[\SensitiveParameter] string $submitted_code
    ): array {
        $this->codeVault->assertReady();
        $role = $this->normalizeRole($role);
        if ($user_id < 1) {
            throw new \InvalidArgumentException("Invalid tender opening committee user.");
        }
        $columns = $this->roleSecretColumns($role);
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tenders = $this->db->prefixTable("tenders");
        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $team_members = $this->db->prefixTable("tender_team_members");
        $now = $this->get_tender_business_now();
        $cutoff = Time::parse($now, 'Asia/Muscat')
            ->subMinutes(self::CONFIRMATION_FAILURE_WINDOW_MINUTES)
            ->toDateTimeString();
        $ip_address = substr(trim((string) service("request")->getIPAddress()), 0, 45) ?: "0.0.0.0";
        $user_agent = substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 500) ?: null;
        $submitted_code = trim($submitted_code);
        if (strlen($submitted_code) > 32) {
            $submitted_code = "";
        }

        $this->db->transBegin();
        try {
            $opening_context = $this->db->query(
                "SELECT tender_id
                 FROM $tbo
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1",
                [$opening_id]
            )->getRow();
            if (!$opening_context) {
                $this->db->transCommit();
                return ["status" => "unavailable", "unlocked" => false];
            }

            $tender = $this->db->query(
                "SELECT id
                 FROM $tenders
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [(int) $opening_context->tender_id]
            )->getRow();
            if (!$tender) {
                $this->db->transCommit();
                return ["status" => "unavailable", "unlocked" => false];
            }

            $session = $this->db->query(
                "SELECT id, tender_id, stage, status, expires_at,
                        {$columns["hash"]} AS role_hash
                 FROM $tbo
                 WHERE id=?
                   AND tender_id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [$opening_id, (int) $opening_context->tender_id]
            )->getRow();

            if (!$session || (string) $session->status !== "codes_generated") {
                $this->db->transCommit();
                return ["status" => "unavailable", "unlocked" => false];
            }

            if (empty($session->expires_at) || (string) $session->expires_at <= $now) {
                $this->expireSessionSecrets($opening_id, $now);
                $this->db->transCommit();
                return ["status" => "expired", "unlocked" => false];
            }

            $assigned = $this->db->query(
                "SELECT id
                 FROM $team_members
                 WHERE tender_id=?
                   AND user_id=?
                   AND team_role=?
                   AND deleted=0
                   AND is_active=1
                 LIMIT 1
                 FOR UPDATE",
                [(int) $session->tender_id, $user_id, $role]
            )->getRow();
            if (!$assigned) {
                $this->db->transCommit();
                return ["status" => "unavailable", "unlocked" => false];
            }

            $confirmed = $this->db->query(
                "SELECT id
                 FROM $entries
                 WHERE tender_bid_opening_id=?
                   AND deleted=0
                   AND is_valid=1
                   AND (user_id=? OR role=?)
                 LIMIT 1",
                [$opening_id, $user_id, $role]
            )->getRow();
            if ($confirmed) {
                $this->db->transCommit();
                return ["status" => "already_confirmed", "unlocked" => false];
            }

            $user_failures = $this->db->query(
                "SELECT COUNT(*) AS total
                 FROM $entries AS failed_entries
                 INNER JOIN $tbo AS failed_openings
                    ON failed_openings.id=failed_entries.tender_bid_opening_id
                   AND failed_openings.tender_id=?
                   AND failed_openings.stage=?
                   AND failed_openings.deleted=0
                 WHERE failed_entries.deleted=0
                   AND failed_entries.is_valid=0
                   AND failed_entries.user_id=?
                   AND failed_entries.confirmed_at>=?",
                [
                    (int) $session->tender_id,
                    (string) $session->stage,
                    $user_id,
                    $cutoff,
                ]
            )->getRow();
            $ip_failures = $this->db->query(
                "SELECT COUNT(*) AS total
                 FROM $entries AS failed_entries
                 INNER JOIN $tbo AS failed_openings
                    ON failed_openings.id=failed_entries.tender_bid_opening_id
                   AND failed_openings.tender_id=?
                   AND failed_openings.stage=?
                   AND failed_openings.deleted=0
                 WHERE failed_entries.deleted=0
                   AND failed_entries.is_valid=0
                   AND failed_entries.ip_address=?
                   AND failed_entries.confirmed_at>=?",
                [
                    (int) $session->tender_id,
                    (string) $session->stage,
                    $ip_address,
                    $cutoff,
                ]
            )->getRow();
            if (
                (int) ($user_failures->total ?? 0) >= self::CONFIRMATION_USER_FAILURE_LIMIT
                || (int) ($ip_failures->total ?? 0) >= self::CONFIRMATION_IP_FAILURE_LIMIT
            ) {
                $this->db->transCommit();
                return [
                    "status" => "rate_limited",
                    "unlocked" => false,
                    "retry_after_seconds" => self::CONFIRMATION_FAILURE_WINDOW_MINUTES * 60,
                ];
            }

            $valid = $this->codeVault->verify(
                $submitted_code,
                (string) ($session->role_hash ?? ""),
                $this->secretContext(
                    (int) $session->id,
                    (int) $session->tender_id,
                    (string) $session->stage,
                    $role
                )
            );

            // The legacy input_* columns are deliberately omitted. Submitted
            // plaintext is never persisted, including for failed attempts.
            $this->db->table($entries)->insert([
                "tender_bid_opening_id" => $opening_id,
                "user_id" => $user_id,
                "role" => $role,
                "is_valid" => $valid ? 1 : 0,
                "confirmed_at" => $now,
                "ip_address" => $ip_address,
                "user_agent" => $user_agent,
                "created_at" => $now,
                "updated_at" => $now,
                "deleted" => 0,
            ]);

            $unlocked = $valid ? $this->unlockSessionIfComplete($opening_id, $now) : false;
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Tender opening confirmation could not be recorded.");
            }
            $this->db->transCommit();

            return [
                "status" => $valid ? "confirmed" : "invalid",
                "unlocked" => $unlocked,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        } finally {
            if (function_exists("sodium_memzero") && $submitted_code !== "") {
                sodium_memzero($submitted_code);
            } else {
                $submitted_code = "";
            }
        }
    }

    public function unlock_session(int $opening_id): bool
    {
        $now = $this->get_tender_business_now();
        $this->db->transBegin();
        try {
            $tbo = $this->db->prefixTable("tender_bid_openings");
            $tenders = $this->db->prefixTable("tenders");
            $context = $this->db->query(
                "SELECT tender_id
                 FROM $tbo
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1",
                [$opening_id]
            )->getRow();
            if (!$context) {
                $this->db->transCommit();
                return false;
            }
            $this->db->query(
                "SELECT id
                 FROM $tenders
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [(int) $context->tender_id]
            )->getRow();
            $this->db->query(
                "SELECT id
                 FROM $tbo
                 WHERE id=?
                   AND tender_id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [$opening_id, (int) $context->tender_id]
            )->getRow();
            $unlocked = $this->unlockSessionIfComplete($opening_id, $now);
            $this->db->transCommit();
            return $unlocked;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function save_signature(int $opening_id, int $user_id, string $role, string $statement, string $signature_name, ?string $signature_image_path = null): bool
    {
        $tbl = $this->db->prefixTable("tender_bid_opening_entries");
        $now = $this->get_tender_business_now();

        $entry = $this->db->query(
            "SELECT id
             FROM $tbl
             WHERE deleted=0
               AND tender_bid_opening_id=?
               AND user_id=?
               AND role=?
               AND is_valid=1
             ORDER BY id DESC
             LIMIT 1",
            [$opening_id, $user_id, $role]
        )->getRow();

        if (!$entry) {
            return false;
        }

        $this->db->query(
            "UPDATE $tbl
             SET signature_statement=?,
                 signature_name=?,
                 signature_image_path=COALESCE(?, signature_image_path),
                 signed_at=IFNULL(signed_at, ?),
                 signature_ip_address=?,
                 signature_user_agent=?,
                 updated_at=?
             WHERE id=?",
            [
                $statement,
                $signature_name,
                $signature_image_path,
                $now,
                get_real_ip(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $now,
                (int) $entry->id,
            ]
        );

        if ($this->all_required_signatures_completed($opening_id)) {
            $this->mark_all_signatures_completed($opening_id);
        }

        return true;
    }

    public function mark_all_signatures_completed(int $opening_id): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();

        $this->db->query(
            "UPDATE $tbo
             SET status='signed',
                 signed_at=IFNULL(signed_at, ?),
                 updated_at=?
             WHERE id=?
               AND deleted=0
               AND status IN ('unlocked','signed')",
            [$now, $now, $opening_id]
        );
    }

    public function mark_manual_form_accepted(int $tender_id, int $actor_id, string $path, string $original_name): int
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tenders = $this->db->prefixTable("tenders");
        $now = $this->get_tender_business_now();

        $this->db->transBegin();
        try {
            $tender = $this->db->query(
                "SELECT id
                 FROM $tenders
                 WHERE id=?
                   AND deleted=0
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id]
            )->getRow();
            if (!$tender) {
                throw new \RuntimeException("Manual tender opening has no active tender.");
            }

            $session = $this->db->query(
                "SELECT id
                 FROM $tbo
                 WHERE tender_id=?
                   AND stage='technical'
                   AND deleted=0
                   AND status IN ('codes_generated','unlocked','signed','manual_accepted')
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE",
                [$tender_id]
            )->getRow();

            if ($session) {
                $opening_id = (int) $session->id;
                $this->db->query(
                    "UPDATE $tbo
                     SET status='manual_accepted',
                         chairman_code=NULL,
                         secretary_code=NULL,
                         member_code=NULL,
                         chairman_code_hash=NULL,
                         secretary_code_hash=NULL,
                         member_code_hash=NULL,
                         chairman_code_ciphertext=NULL,
                         secretary_code_ciphertext=NULL,
                         member_code_ciphertext=NULL,
                         signed_at=IFNULL(signed_at, ?),
                         manual_form_path=?,
                         manual_form_original_name=?,
                         manual_form_uploaded_by=?,
                         manual_form_uploaded_at=?,
                         unlocked_at=IFNULL(unlocked_at, ?),
                         updated_at=?
                     WHERE id=?
                       AND tender_id=?
                       AND deleted=0",
                    [
                        $now,
                        $path,
                        $original_name,
                        $actor_id,
                        $now,
                        $now,
                        $now,
                        $opening_id,
                        $tender_id,
                    ]
                );
            } else {
                $this->db->query(
                    "INSERT INTO $tbo
                        (tender_id, stage, status, generated_by, generated_at, expires_at, unlocked_at, signed_at, manual_form_path, manual_form_original_name, manual_form_uploaded_by, manual_form_uploaded_at, created_at, updated_at, deleted)
                     VALUES
                        (?, 'technical', 'manual_accepted', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
                    [
                        $tender_id,
                        $actor_id,
                        $now,
                        $now,
                        $now,
                        $now,
                        $path,
                        $original_name,
                        $actor_id,
                        $now,
                        $now,
                        $now,
                    ]
                );
                $opening_id = (int) $this->db->insertID();
            }

            if (!$opening_id || $this->db->transStatus() === false) {
                throw new \RuntimeException("Manual tender opening could not be recorded.");
            }
            $this->db->transCommit();
            return $opening_id;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function get_completed_session_for_technical_start(int $tender_id)
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");

        return $this->stripOpeningSecrets(
            $this->db->query(
                "SELECT
                    id,
                    tender_id,
                    stage,
                    status,
                    generated_by,
                    generated_at,
                    expires_at,
                    unlocked_at,
                    signed_at,
                    manual_form_path,
                    manual_form_original_name,
                    manual_form_uploaded_by,
                    manual_form_uploaded_at,
                    created_at,
                    updated_at,
                    deleted
                 FROM $tbo
                 WHERE deleted=0
                   AND tender_id=?
                   AND stage='technical'
                   AND status IN ('signed','manual_accepted')
                 ORDER BY id DESC
                 LIMIT 1",
                [$tender_id]
            )->getRow()
        );
    }

    public function get_signature_rows(int $opening_id): array
    {
        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $entries.id,
                $entries.tender_bid_opening_id,
                $entries.user_id,
                $entries.role,
                $entries.is_valid,
                $entries.confirmed_at,
                $entries.ip_address,
                $entries.user_agent,
                $entries.signature_statement,
                $entries.signature_name,
                $entries.signature_image_path,
                $entries.signed_at,
                $entries.signature_ip_address,
                $entries.signature_user_agent,
                $entries.created_at,
                $entries.updated_at,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS member_name,
                $users.email AS member_email
             FROM $entries
             LEFT JOIN $users ON $users.id = $entries.user_id
             WHERE $entries.deleted=0
               AND $entries.tender_bid_opening_id=?
               AND $entries.is_valid=1
             ORDER BY FIELD($entries.role, 'chairman', 'secretary', 'itc_member'), $entries.confirmed_at ASC, $entries.id ASC",
            [$opening_id]
        )->getResult();
    }

    public function get_bid_summary_for_opening(int $tender_id): array
    {
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");

        return $this->db->query(
            "SELECT
                $tb.id AS bid_id,
                $tb.vendor_id,
                $tb.status AS bid_status,
                $tb.submitted_at,
                $tb.total_amount,
                $tb.currency,
                $v.vendor_name,
                docs.technical_doc_id,
                docs.technical_doc_name,
                docs.commercial_unpriced_doc_id,
                docs.commercial_unpriced_doc_name,
                COALESCE(docs.commercial_priced_doc_id, docs.commercial_legacy_doc_id) AS commercial_priced_doc_id,
                COALESCE(docs.commercial_priced_doc_name, docs.commercial_legacy_doc_name) AS commercial_priced_doc_name,
                docs.bank_guarantee_doc_id,
                docs.bank_guarantee_doc_name
             FROM $tb
             INNER JOIN $v
                ON $v.id = $tb.vendor_id
               AND $v.deleted = 0
             LEFT JOIN (
                SELECT
                    tender_bid_id,
                    MAX(CASE WHEN section = 'technical' THEN id ELSE NULL END) AS technical_doc_id,
                    MAX(CASE WHEN section = 'technical' THEN original_name ELSE NULL END) AS technical_doc_name,
                    MAX(CASE WHEN section = 'commercial_unpriced' THEN id ELSE NULL END) AS commercial_unpriced_doc_id,
                    MAX(CASE WHEN section = 'commercial_unpriced' THEN original_name ELSE NULL END) AS commercial_unpriced_doc_name,
                    MAX(CASE WHEN section = 'commercial_priced' THEN id ELSE NULL END) AS commercial_priced_doc_id,
                    MAX(CASE WHEN section = 'commercial_priced' THEN original_name ELSE NULL END) AS commercial_priced_doc_name,
                    MAX(CASE WHEN section = 'commercial' THEN id ELSE NULL END) AS commercial_legacy_doc_id,
                    MAX(CASE WHEN section = 'commercial' THEN original_name ELSE NULL END) AS commercial_legacy_doc_name,
                    MAX(CASE WHEN section = 'bank_guarantee' THEN id ELSE NULL END) AS bank_guarantee_doc_id,
                    MAX(CASE WHEN section = 'bank_guarantee' THEN original_name ELSE NULL END) AS bank_guarantee_doc_name
                FROM $tbd
                WHERE deleted=0
                GROUP BY tender_bid_id
             ) docs ON docs.tender_bid_id = $tb.id
             WHERE $tb.deleted=0
               AND $tb.tender_id=?
               AND $tb.status <> 'draft'
             ORDER BY $v.vendor_name ASC, $tb.submitted_at ASC",
            [$tender_id]
        )->getResult();
    }

    private function stripOpeningSecrets($session)
    {
        if (!$session) {
            return null;
        }

        foreach ([
            "chairman_code",
            "secretary_code",
            "member_code",
            "chairman_code_hash",
            "secretary_code_hash",
            "member_code_hash",
            "chairman_code_ciphertext",
            "secretary_code_ciphertext",
            "member_code_ciphertext",
        ] as $field) {
            unset($session->{$field});
        }

        return $session;
    }

    private function normalizeRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (!in_array($role, self::REQUIRED_ROLES, true)) {
            throw new \InvalidArgumentException("Invalid tender opening committee role.");
        }

        return $role;
    }

    /** @return array{hash:string,ciphertext:string} */
    private function roleSecretColumns(string $role): array
    {
        $role = $this->normalizeRole($role);
        $prefix = [
            "chairman" => "chairman",
            "secretary" => "secretary",
            "itc_member" => "member",
        ][$role];

        return [
            "hash" => $prefix . "_code_hash",
            "ciphertext" => $prefix . "_code_ciphertext",
        ];
    }

    private function secretContext(
        int $opening_id,
        int $tender_id,
        string $stage,
        string $role
    ): string {
        if ($opening_id < 1 || $tender_id < 1) {
            throw new \InvalidArgumentException("Invalid tender opening secret context.");
        }
        $stage = strtolower(trim($stage));
        if (!in_array($stage, ["technical", "commercial"], true)) {
            throw new \InvalidArgumentException("Invalid tender opening secret stage.");
        }

        return sprintf(
            "opening:%d:tender:%d:stage:%s:role:%s",
            $opening_id,
            $tender_id,
            $stage,
            $this->normalizeRole($role)
        );
    }

    private function wipeCodes(array &$codes): void
    {
        foreach ($codes as &$code) {
            if (is_string($code) && $code !== "" && function_exists("sodium_memzero")) {
                sodium_memzero($code);
            } else {
                $code = "";
            }
        }
        unset($code);
        $codes = [];
    }

    private function expireSessionSecrets(int $opening_id, string $now): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 chairman_code=NULL,
                 secretary_code=NULL,
                 member_code=NULL,
                 chairman_code_hash=NULL,
                 secretary_code_hash=NULL,
                 member_code_hash=NULL,
                 chairman_code_ciphertext=NULL,
                 secretary_code_ciphertext=NULL,
                 member_code_ciphertext=NULL,
                 updated_at=?
             WHERE id=?
               AND deleted=0
               AND status='codes_generated'",
            [$now, $opening_id]
        );
    }

    private function unlockSessionIfComplete(int $opening_id, string $now): bool
    {
        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $rows = $this->db->query(
            "SELECT role, user_id
             FROM $entries
             WHERE tender_bid_opening_id=?
               AND deleted=0
               AND is_valid=1
               AND role IN ('chairman','secretary','itc_member')",
            [$opening_id]
        )->getResult();

        $role_users = [
            "chairman" => [],
            "secretary" => [],
            "itc_member" => [],
        ];
        foreach ($rows as $row) {
            $role = (string) $row->role;
            $role_users[$role][(int) $row->user_id] = true;
        }

        $distinct_users = [];
        foreach (self::REQUIRED_ROLES as $role) {
            if (count($role_users[$role]) !== 1) {
                return false;
            }
            $distinct_users[array_key_first($role_users[$role])] = true;
        }
        if (count($distinct_users) !== count(self::REQUIRED_ROLES)) {
            return false;
        }

        $this->db->query(
            "UPDATE $tbo
             SET status='unlocked',
                 unlocked_at=?,
                 chairman_code=NULL,
                 secretary_code=NULL,
                 member_code=NULL,
                 chairman_code_hash=NULL,
                 secretary_code_hash=NULL,
                 member_code_hash=NULL,
                 chairman_code_ciphertext=NULL,
                 secretary_code_ciphertext=NULL,
                 member_code_ciphertext=NULL,
                 updated_at=?
             WHERE id=?
               AND deleted=0
               AND status='codes_generated'
               AND expires_at IS NOT NULL
               AND expires_at>?",
            [$now, $now, $opening_id, $now]
        );

        return $this->db->affectedRows() === 1;
    }

    private function _normalize_stage(string $stage): string
    {
        $stage = strtolower(trim($stage));
        return in_array($stage, ["technical", "commercial"], true) ? $stage : "technical";
    }
}
