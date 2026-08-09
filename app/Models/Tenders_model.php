<?php

namespace App\Models;

use App\Libraries\Runtime_schema_guard;
use CodeIgniter\I18n\Time;

class Tenders_model extends Crud_model
{
    protected $table = null;
    private static bool $procurement_manager_change_schema_checked = false;
    private static bool $specific_vendor_target_schema_checked = false;
    private static bool $vendor_participation_approval_schema_checked = false;
    private static bool $tender_fee_schema_checked = false;
    private static bool $tender_evaluation_weight_schema_checked = false;
    private static bool $tender_fee_payments_schema_checked = false;

    public function __construct()
    {
        $this->table = "tenders";
        parent::__construct($this->table);
        $this->ensure_workflow_history_table();
        $this->ensure_procurement_manager_change_columns();
        $this->ensure_specific_vendor_target_schema();
        $this->ensure_vendor_participation_approval_schema();
        $this->ensure_tender_fee_column();
        $this->ensure_tender_evaluation_weight_columns();
        $this->ensure_tender_fee_payments_table();
    }

    public function ensure_procurement_manager_change_columns(): void
    {
        if (self::$procurement_manager_change_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tenders" => ["procurement_manager_action", "procurement_manager_payload"],
        ], "tender procurement-manager change handling");

        self::$procurement_manager_change_schema_checked = true;
    }

    public function ensure_specific_vendor_target_schema(): void
    {
        if (self::$specific_vendor_target_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_target_specialties" => ["vendor_grade_id"],
            "tender_target_vendors" => ["id", "tender_id", "vendor_id", "created_by", "created_at", "deleted"],
        ], "specific-vendor tender targeting");

        self::$specific_vendor_target_schema_checked = true;
    }

    public function ensure_vendor_participation_approval_schema(): void
    {
        if (self::$vendor_participation_approval_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireColumnDefinitionContains(
            $this->db,
            "tender_invited_vendors",
            "invite_status",
            ["pending_approval", "approved", "rejected"],
            "vendor tender participation approvals"
        );

        self::$vendor_participation_approval_schema_checked = true;
    }

    public function ensure_tender_fee_column(): void
    {
        if (self::$tender_fee_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tenders" => ["tender_fee"],
        ], "tender fees");

        self::$tender_fee_schema_checked = true;
    }

    public function ensure_tender_evaluation_weight_columns(): void
    {
        if (self::$tender_evaluation_weight_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tenders" => ["evaluation_method", "technical_weight", "commercial_weight"],
        ], "weighted tender evaluation");
        Runtime_schema_guard::requireColumnDefinitionContains(
            $this->db,
            "tenders",
            "evaluation_method",
            ["separate", "combined"],
            "weighted tender evaluation"
        );

        self::$tender_evaluation_weight_schema_checked = true;
    }

    public function ensure_tender_fee_payments_table(): void
    {
        if (self::$tender_fee_payments_schema_checked) {
            return;
        }

        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_fee_payments" => [
                "id", "tender_id", "vendor_id", "amount", "currency", "status",
                "payment_reference", "paid_at", "created_by", "created_at", "updated_at", "deleted",
            ],
        ], "tender fee payments");

        self::$tender_fee_payments_schema_checked = true;
    }

    private function get_tender_business_now(): string
    {
        return Time::now('Asia/Muscat')->toDateTimeString();
    }

    private function get_stage_days(string $stage): int
    {
        switch ($stage) {
            case 'technical_3key':
                return 3;
            case 'technical':
                return 3;
            case 'commercial':
                return 3;
            default:
                return 0;
        }
    }

    private function ensure_workflow_history_table(): void
    {
        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_workflow_history" => [
                "id", "tender_id", "action_type", "from_status", "to_status", "from_stage",
                "to_stage", "open_until", "reason", "details", "created_by", "created_at", "deleted",
            ],
        ], "tender workflow history");
    }

    private function record_auto_workflow_history(string $source_sql, array $source_params, ?string $to_status, ?string $to_stage, string $details, string $now): void
    {
        $history = $this->db->prefixTable("tender_workflow_history");

        $params = array_merge([
            "auto_progress",
            $to_status,
            $to_stage,
            $details,
            $now,
        ], $source_params);

        $this->db->query(
            "INSERT INTO $history
                (tender_id, action_type, from_status, to_status, from_stage, to_stage, details, created_by, created_at, deleted)
             SELECT
                source.id,
                ?,
                source.status,
                COALESCE(?, source.status),
                source.workflow_stage,
                COALESCE(?, source.workflow_stage),
                ?,
                NULL,
                ?,
                0
             FROM ($source_sql) source",
            $params
        );
    }

    public function auto_progress_workflow(bool $scheduledInvocation = false): int
    {
        // Fail closed if application code accidentally invokes this mutation
        // while rendering or handling a user request. The authenticated cron
        // controller is the sole production caller that opts in explicitly.
        if (!$scheduledInvocation) {
            return 0;
        }

        $driver = strtolower((string) ($this->db->DBDriver ?? ''));
        $usesMysqlLock = in_array($driver, ['mysqli', 'mysql'], true);
        $lockName = 'podc_tender_auto_' . substr(hash('sha256', (string) $this->db->database), 0, 32);
        if ($usesMysqlLock) {
            $lock = $this->db->query('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->getRow();
            if ((int) ($lock->acquired ?? 0) !== 1) {
                return 0;
            }
        }

        $transactionActive = false;
        try {
            $this->db->transBegin();
            $transactionActive = true;

        $t = $this->db->prefixTable("tenders");
        $tb = $this->db->prefixTable("tender_bids");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();
        $affected = 0;

        $technicalDays = $this->get_stage_days('technical');
        $commercialDays = $this->get_stage_days('commercial');

        // 1) Close released tenders once bid closing time has passed.
        $closeExpiredSql = "SELECT id, status, workflow_stage
                            FROM $t
                            WHERE deleted = 0
                              AND status = 'published'
                              AND (release_at IS NULL OR release_at <= ?)
                              AND closing_at IS NOT NULL
                              AND closing_at <= ?";
        $this->record_auto_workflow_history(
            $closeExpiredSql,
            [$now, $now],
            "closed",
            null,
            "Submission deadline passed; tender closed by the system.",
            $now
        );
        $this->db->query(
            "UPDATE $t
             SET status = 'closed',
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'published'
               AND (release_at IS NULL OR release_at <= ?)
               AND closing_at IS NOT NULL
               AND closing_at <= ?",
            [$now, $now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());
        // 2) Move closed tenders from bidding -> technical 3-key opening.
        $technical3KeySql = "SELECT id, status, workflow_stage
                             FROM $t
                             WHERE deleted = 0
                               AND status = 'closed'
                               AND workflow_stage = 'bidding'
                               AND closing_at IS NOT NULL
                               AND closing_at <= ?";
        $this->record_auto_workflow_history(
            $technical3KeySql,
            [$now],
            null,
            "technical_3key",
            "Bid submission window ended; technical 3-key opening started by the system.",
            $now
        );
        $this->db->query(
            "UPDATE $t
             SET workflow_stage = 'technical_3key',
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'closed'
               AND workflow_stage = 'bidding'
               AND closing_at IS NOT NULL
               AND closing_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());
        // 3) Procurement starts technical review explicitly after the single 3-key opening is signed
        // or a manually signed opening form is uploaded.

        // 4) Any bid still left as submitted after the technical deadline is auto-rejected.
        $this->db->query(
            "UPDATE $tb
             INNER JOIN $t ON $t.id = $tb.tender_id
             SET $tb.status = 'rejected',
                 $tb.updated_at = ?
             WHERE $tb.deleted = 0
               AND $tb.status = 'submitted'
               AND $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'technical'
               AND $t.technical_end_at IS NOT NULL
               AND $t.technical_end_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 5) Once technical review is complete or deadline is reached, move directly to commercial evaluation.
        $commercialSql = "SELECT id, status, workflow_stage
                          FROM $t
                          WHERE deleted = 0
                            AND status = 'closed'
                            AND workflow_stage = 'technical'
                            AND (
                                 (technical_end_at IS NOT NULL AND technical_end_at <= ?)
                                 OR NOT EXISTS (
                                     SELECT 1
                                     FROM $tb pending_bids
                                     WHERE pending_bids.deleted = 0
                                       AND pending_bids.tender_id = $t.id
                                       AND pending_bids.status = 'submitted'
                                 )
                            )";
        $this->record_auto_workflow_history(
            $commercialSql,
            [$now],
            null,
            "commercial",
            "Technical review completed or deadline reached; commercial evaluation started by the system.",
            $now
        );
        $this->db->query(
            "UPDATE $t
             SET workflow_stage = 'commercial',
                 technical_locked_at = IFNULL(technical_locked_at, IFNULL(technical_end_at, ?)),
                 commercial_unlocked_at = IFNULL(commercial_unlocked_at, IFNULL(technical_end_at, ?)),
                 commercial_start_at = IFNULL(commercial_start_at, IFNULL(technical_end_at, ?)),
                 commercial_end_at = IFNULL(
                    commercial_end_at,
                    IFNULL(commercial_eval_deadline, DATE_ADD(IFNULL(commercial_start_at, IFNULL(technical_end_at, ?)), INTERVAL {$commercialDays} DAY))
                 ),
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'closed'
               AND workflow_stage = 'technical'
               AND (
                    (technical_end_at IS NOT NULL AND technical_end_at <= ?)
                    OR NOT EXISTS (
                        SELECT 1
                        FROM $tb pending_bids
                        WHERE pending_bids.deleted = 0
                          AND pending_bids.tender_id = $t.id
                          AND pending_bids.status = 'submitted'
                    )
               )",
            [$now, $now, $now, $now, $now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 6) Once commercial window ends, move the tender to award decision stage.
        $awardDecisionSql = "SELECT id, status, workflow_stage
                             FROM $t
                             WHERE deleted = 0
                               AND status = 'closed'
                               AND workflow_stage = 'commercial'
                               AND commercial_end_at IS NOT NULL
                               AND commercial_end_at <= ?";
        $this->record_auto_workflow_history(
            $awardDecisionSql,
            [$now],
            null,
            "award_decision",
            "Commercial evaluation window ended; tender moved to award decision by the system.",
            $now
        );
        $this->db->query(
            "UPDATE $t
             SET workflow_stage = 'award_decision',
                 award_ready_at = IFNULL(award_ready_at, commercial_end_at),
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'closed'
               AND workflow_stage = 'commercial'
               AND commercial_end_at IS NOT NULL
               AND commercial_end_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new \RuntimeException('Tender workflow progression transaction failed.');
            }
            $transactionActive = false;

            return $affected;
        } catch (\Throwable $exception) {
            if ($transactionActive) {
                $this->db->transRollback();
            }
            log_message('error', 'Tender workflow auto-progression failed: {exception}', [
                'exception' => $exception,
            ]);
            return 0;
        } finally {
            if ($usesMysqlLock) {
                $this->db->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }

    public function start_technical_review_after_opening(int $tender_id, int $actor_id, ?string $technical_end_at = null): bool
    {
        $t = $this->db->prefixTable("tenders");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();
        $technicalDays = $this->get_stage_days("technical");

        $technical_end_at = $technical_end_at ?: null;

        $sourceSql = "SELECT $t.id, $t.status, $t.workflow_stage
                      FROM $t
                      INNER JOIN (
                          SELECT tender_id, MAX(id) AS max_id
                          FROM $tbo
                          WHERE deleted=0
                            AND stage='technical'
                            AND status IN ('signed','manual_accepted')
                          GROUP BY tender_id
                      ) latest_opening ON latest_opening.tender_id = $t.id
                      INNER JOIN $tbo opening ON opening.id = latest_opening.max_id
                      WHERE $t.deleted=0
                        AND $t.id=?
                        AND $t.status='closed'
                        AND $t.workflow_stage='technical_3key'";

        $this->record_auto_workflow_history(
            $sourceSql,
            [$tender_id],
            null,
            "technical",
            "Procurement reviewed the opened technical and commercial proposals, then released the tender to technical evaluation.",
            $now
        );

        $this->db->query(
            "UPDATE $t
             INNER JOIN (
                 SELECT tender_id, MAX(id) AS max_id
                 FROM $tbo
                 WHERE deleted=0
                   AND stage='technical'
                   AND status IN ('signed','manual_accepted')
                 GROUP BY tender_id
             ) latest_opening ON latest_opening.tender_id = $t.id
             INNER JOIN $tbo opening ON opening.id = latest_opening.max_id
             SET $t.workflow_stage='technical',
                 $t.technical_start_at=IFNULL($t.technical_start_at, ?),
                 $t.technical_end_at=IFNULL($t.technical_end_at, COALESCE(?, $t.technical_eval_deadline, DATE_ADD(?, INTERVAL {$technicalDays} DAY))),
                 $t.commercial_unlocked_at=IFNULL($t.commercial_unlocked_at, COALESCE(opening.signed_at, opening.unlocked_at, ?)),
                 $t.updated_at=?
             WHERE $t.deleted=0
               AND $t.id=?
               AND $t.status='closed'
               AND $t.workflow_stage='technical_3key'",
            [$now, $technical_end_at, $now, $now, $now, $tender_id]
        );

        return (int) $this->db->affectedRows() > 0;
    }

    public function get_by_request_id(int $tender_request_id)
    {
        $t = $this->db->prefixTable("tenders");

        $sql = "SELECT * FROM $t
                WHERE deleted=0 AND tender_request_id=?
                ORDER BY id DESC
                LIMIT 1";
        return $this->db->query($sql, [$tender_request_id])->getRow();
    }

    public function get_vendor_visible_tenders(int $vendor_id)
    {
        $now = $this->get_tender_business_now();
        $t = $this->db->prefixTable("tenders");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $tfp = $this->db->prefixTable("tender_fee_payments");
        $tts = $this->db->prefixTable("tender_target_specialties");
        $ttv = $this->db->prefixTable("tender_target_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $vc = $this->db->prefixTable("vendor_categories");
        $vsc = $this->db->prefixTable("vendor_sub_categories");
        $vs = $this->db->prefixTable("vendor_specialties");
        $vg = $this->db->prefixTable("vendor_groups");
        $vgr = $this->db->prefixTable("vendor_grades");

        $sql = "SELECT
                    $t.*,
                    $tiv.invite_status,
                    $tiv.invited_at,
                    participated_bid.id AS participated_bid_id,
                    participated_bid.status AS participated_bid_status,
                    participated_bid.submitted_at AS participated_bid_submitted_at,
                    fee_payment.id AS fee_payment_id,
                    fee_payment.status AS fee_payment_status,
                    fee_payment.paid_at AS fee_paid_at,
                    fee_payment.amount AS fee_paid_amount,
                    fee_payment.payment_reference AS fee_payment_reference,
                    target_vendor.id AS specific_target_id,
                    target.vendor_group_id,
                    target.vendor_grade_id,
                    $vc.name AS vendor_category_name,
                    $vsc.name AS vendor_sub_category_name,
                    $vg.name AS vendor_group_name,
                    $vg.code AS vendor_group_code,
                    $vgr.name AS vendor_grade_name,
                    $vgr.code AS vendor_grade_code,
                    CASE
                        WHEN participated_bid.id IS NOT NULL THEN 1
                        WHEN target_vendor.id IS NOT NULL THEN 1
                        WHEN $tiv.invite_status IN ('sent', 'delivered', 'opened', 'approved') THEN 1
                        WHEN target.vendor_group_id IS NOT NULL AND target.vendor_group_id = vendor_profile.vendor_group_id THEN 1
                        WHEN target.vendor_grade_id IS NOT NULL AND target.vendor_grade_id = vendor_profile.vendor_grade_id THEN 1
                        WHEN target.vendor_category_id IS NOT NULL
                             AND EXISTS (
                                SELECT 1
                                FROM $vs approval_check
                                WHERE approval_check.deleted = 0
                                  AND approval_check.status IN ('approved', 'pending')
                                  AND approval_check.vendor_id = vendor_profile.id
                                  AND approval_check.vendor_category_id = target.vendor_category_id
                                  AND (
                                        target.vendor_sub_category_id IS NULL
                                        OR approval_check.vendor_sub_category_id = target.vendor_sub_category_id
                                  )
                             ) THEN 1
                        ELSE 0
                    END AS procurement_approved_for_submission,
                    CASE
                        WHEN participated_bid.id IS NOT NULL THEN 'participated'
                        WHEN target_vendor.id IS NOT NULL THEN 'specific_vendor'
                        WHEN $tiv.id IS NOT NULL THEN 'invited'
                        WHEN target.vendor_group_id IS NOT NULL AND target.vendor_group_id = vendor_profile.vendor_group_id THEN 'vendor_group'
                        WHEN target.vendor_grade_id IS NOT NULL AND target.vendor_grade_id = vendor_profile.vendor_grade_id THEN 'vendor_grade'
                        WHEN target.vendor_category_id IS NOT NULL
                             AND EXISTS (
                                SELECT 1
                                FROM $vs source_check
                                WHERE source_check.deleted = 0
                                  AND source_check.status IN ('approved', 'pending')
                                  AND source_check.vendor_id = vendor_profile.id
                                  AND source_check.vendor_category_id = target.vendor_category_id
                                  AND (
                                        target.vendor_sub_category_id IS NULL
                                        OR source_check.vendor_sub_category_id = target.vendor_sub_category_id
                                  )
                             ) THEN 'specialty'
                        WHEN target.id IS NULL
                             AND NOT EXISTS (
                                SELECT 1
                                FROM $ttv any_target_vendor
                                WHERE any_target_vendor.tender_id = $t.id
                                  AND any_target_vendor.deleted = 0
                             )
                             AND $t.tender_type = 'open' THEN 'open'
                        ELSE 'eligible'
                    END AS eligibility_source
                FROM $t
                INNER JOIN $vendors vendor_profile
                    ON vendor_profile.id = ?
                   AND vendor_profile.deleted = 0
                LEFT JOIN $tiv
                    ON $tiv.tender_id = $t.id
                   AND $tiv.vendor_id = ?
                   AND $tiv.deleted = 0
                LEFT JOIN $ttv target_vendor
                    ON target_vendor.tender_id = $t.id
                   AND target_vendor.vendor_id = ?
                   AND target_vendor.deleted = 0
                LEFT JOIN (
                    SELECT tender_id, vendor_id, MAX(id) AS max_id
                    FROM $tb
                    WHERE deleted = 0
                      AND status <> 'draft'
                    GROUP BY tender_id, vendor_id
                ) participated_bid_latest
                    ON participated_bid_latest.tender_id = $t.id
                   AND participated_bid_latest.vendor_id = vendor_profile.id
                LEFT JOIN $tb participated_bid
                    ON participated_bid.id = participated_bid_latest.max_id
                LEFT JOIN (
                    SELECT tender_id, vendor_id, MAX(id) AS max_id
                    FROM $tfp
                    WHERE deleted = 0
                      AND status = 'paid'
                    GROUP BY tender_id, vendor_id
                ) fee_payment_latest
                    ON fee_payment_latest.tender_id = $t.id
                   AND fee_payment_latest.vendor_id = vendor_profile.id
                LEFT JOIN $tfp fee_payment
                    ON fee_payment.id = fee_payment_latest.max_id
                LEFT JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tts
                    WHERE deleted = 0
                    GROUP BY tender_id
                ) tts_max ON tts_max.tender_id = $t.id
                LEFT JOIN $tts target ON target.id = tts_max.max_id
                LEFT JOIN $vc ON $vc.id = target.vendor_category_id AND $vc.deleted = 0
                LEFT JOIN $vsc ON $vsc.id = target.vendor_sub_category_id AND $vsc.deleted = 0
                LEFT JOIN $vg ON $vg.id = target.vendor_group_id AND $vg.deleted = 0
                LEFT JOIN $vgr ON $vgr.id = target.vendor_grade_id AND $vgr.deleted = 0
                WHERE $t.deleted = 0
                  AND (
                        (
                            $t.status = 'published'
                            AND $t.workflow_stage = 'bidding'
                            AND (COALESCE($t.release_at, $t.published_at) IS NULL OR COALESCE($t.release_at, $t.published_at) <= ?)
                            AND ($t.closing_at IS NULL OR $t.closing_at > ?)
                            AND (
                                target_vendor.id IS NOT NULL
                                OR $tiv.id IS NOT NULL
                                OR
                                (
                                    (
                                        target.id IS NULL
                                        AND NOT EXISTS (
                                            SELECT 1
                                            FROM $ttv any_target_vendor
                                            WHERE any_target_vendor.tender_id = $t.id
                                              AND any_target_vendor.deleted = 0
                                        )
                                        AND $t.tender_type = 'open'
                                    )
                                    OR (
                                        target.id IS NOT NULL
                                        AND (
                                            (
                                            target.vendor_group_id IS NOT NULL
                                            AND target.vendor_group_id = vendor_profile.vendor_group_id
                                            )
                                            OR (
                                            target.vendor_grade_id IS NOT NULL
                                            AND target.vendor_grade_id = vendor_profile.vendor_grade_id
                                            )
                                            OR EXISTS (
                                                SELECT 1
                                                FROM $vs
                                                WHERE $vs.deleted = 0
                                                  AND $vs.status IN ('approved', 'pending')
                                                  AND $vs.vendor_id = vendor_profile.id
                                                  AND target.vendor_category_id IS NOT NULL
                                                  AND (
                                                        $vs.vendor_category_id = target.vendor_category_id
                                                        AND (
                                                            target.vendor_sub_category_id IS NULL
                                                            OR $vs.vendor_sub_category_id = target.vendor_sub_category_id
                                                        )
                                                    )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                        OR (
                            participated_bid.id IS NOT NULL
                            AND $t.status IN ('published', 'closed', 'awarded')
                        )
                  )
                ORDER BY COALESCE($t.created_at, $t.published_at, $t.closing_at) DESC, $t.id DESC";

        return $this->db->query($sql, [$vendor_id, $vendor_id, $vendor_id, $now, $now]);
    }
    public function get_vendor_visible_tender(int $tender_id, int $vendor_id)
    {
        $now = $this->get_tender_business_now();
        $t = $this->db->prefixTable("tenders");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $tfp = $this->db->prefixTable("tender_fee_payments");
        $tts = $this->db->prefixTable("tender_target_specialties");
        $ttv = $this->db->prefixTable("tender_target_vendors");
        $vendors = $this->db->prefixTable("vendors");
        $vc = $this->db->prefixTable("vendor_categories");
        $vsc = $this->db->prefixTable("vendor_sub_categories");
        $vs = $this->db->prefixTable("vendor_specialties");
        $vg = $this->db->prefixTable("vendor_groups");
        $vgr = $this->db->prefixTable("vendor_grades");

        $sql = "SELECT
                    $t.*,
                    $tiv.invite_status,
                    $tiv.invited_at,
                    participated_bid.id AS participated_bid_id,
                    participated_bid.status AS participated_bid_status,
                    participated_bid.submitted_at AS participated_bid_submitted_at,
                    fee_payment.id AS fee_payment_id,
                    fee_payment.status AS fee_payment_status,
                    fee_payment.paid_at AS fee_paid_at,
                    fee_payment.amount AS fee_paid_amount,
                    fee_payment.payment_reference AS fee_payment_reference,
                    target_vendor.id AS specific_target_id,
                    target.vendor_group_id,
                    target.vendor_grade_id,
                    $vc.name AS vendor_category_name,
                    $vsc.name AS vendor_sub_category_name,
                    $vg.name AS vendor_group_name,
                    $vg.code AS vendor_group_code,
                    $vgr.name AS vendor_grade_name,
                    $vgr.code AS vendor_grade_code,
                    CASE
                        WHEN participated_bid.id IS NOT NULL THEN 1
                        WHEN target_vendor.id IS NOT NULL THEN 1
                        WHEN $tiv.invite_status IN ('sent', 'delivered', 'opened', 'approved') THEN 1
                        WHEN target.vendor_group_id IS NOT NULL AND target.vendor_group_id = vendor_profile.vendor_group_id THEN 1
                        WHEN target.vendor_grade_id IS NOT NULL AND target.vendor_grade_id = vendor_profile.vendor_grade_id THEN 1
                        WHEN target.vendor_category_id IS NOT NULL
                             AND EXISTS (
                                SELECT 1
                                FROM $vs approval_check
                                WHERE approval_check.deleted = 0
                                  AND approval_check.status IN ('approved', 'pending')
                                  AND approval_check.vendor_id = vendor_profile.id
                                  AND approval_check.vendor_category_id = target.vendor_category_id
                                  AND (
                                        target.vendor_sub_category_id IS NULL
                                        OR approval_check.vendor_sub_category_id = target.vendor_sub_category_id
                                  )
                             ) THEN 1
                        ELSE 0
                    END AS procurement_approved_for_submission,
                    CASE
                        WHEN participated_bid.id IS NOT NULL THEN 'participated'
                        WHEN target_vendor.id IS NOT NULL THEN 'specific_vendor'
                        WHEN $tiv.id IS NOT NULL THEN 'invited'
                        WHEN target.vendor_group_id IS NOT NULL AND target.vendor_group_id = vendor_profile.vendor_group_id THEN 'vendor_group'
                        WHEN target.vendor_grade_id IS NOT NULL AND target.vendor_grade_id = vendor_profile.vendor_grade_id THEN 'vendor_grade'
                        WHEN target.vendor_category_id IS NOT NULL
                             AND EXISTS (
                                SELECT 1
                                FROM $vs source_check
                                WHERE source_check.deleted = 0
                                  AND source_check.status IN ('approved', 'pending')
                                  AND source_check.vendor_id = vendor_profile.id
                                  AND source_check.vendor_category_id = target.vendor_category_id
                                  AND (
                                        target.vendor_sub_category_id IS NULL
                                        OR source_check.vendor_sub_category_id = target.vendor_sub_category_id
                                  )
                             ) THEN 'specialty'
                        WHEN target.id IS NULL
                             AND NOT EXISTS (
                                SELECT 1
                                FROM $ttv any_target_vendor
                                WHERE any_target_vendor.tender_id = $t.id
                                  AND any_target_vendor.deleted = 0
                             )
                             AND $t.tender_type = 'open' THEN 'open'
                        ELSE 'eligible'
                    END AS eligibility_source
                FROM $t
                INNER JOIN $vendors vendor_profile
                    ON vendor_profile.id = ?
                   AND vendor_profile.deleted = 0
                LEFT JOIN $tiv
                    ON $tiv.tender_id = $t.id
                   AND $tiv.vendor_id = ?
                   AND $tiv.deleted = 0
                LEFT JOIN $ttv target_vendor
                    ON target_vendor.tender_id = $t.id
                   AND target_vendor.vendor_id = ?
                   AND target_vendor.deleted = 0
                LEFT JOIN (
                    SELECT tender_id, vendor_id, MAX(id) AS max_id
                    FROM $tb
                    WHERE deleted = 0
                      AND status <> 'draft'
                    GROUP BY tender_id, vendor_id
                ) participated_bid_latest
                    ON participated_bid_latest.tender_id = $t.id
                   AND participated_bid_latest.vendor_id = vendor_profile.id
                LEFT JOIN $tb participated_bid
                    ON participated_bid.id = participated_bid_latest.max_id
                LEFT JOIN (
                    SELECT tender_id, vendor_id, MAX(id) AS max_id
                    FROM $tfp
                    WHERE deleted = 0
                      AND status = 'paid'
                    GROUP BY tender_id, vendor_id
                ) fee_payment_latest
                    ON fee_payment_latest.tender_id = $t.id
                   AND fee_payment_latest.vendor_id = vendor_profile.id
                LEFT JOIN $tfp fee_payment
                    ON fee_payment.id = fee_payment_latest.max_id
                LEFT JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tts
                    WHERE deleted = 0
                    GROUP BY tender_id
                ) tts_max ON tts_max.tender_id = $t.id
                LEFT JOIN $tts target ON target.id = tts_max.max_id
                LEFT JOIN $vc ON $vc.id = target.vendor_category_id AND $vc.deleted = 0
                LEFT JOIN $vsc ON $vsc.id = target.vendor_sub_category_id AND $vsc.deleted = 0
                LEFT JOIN $vg ON $vg.id = target.vendor_group_id AND $vg.deleted = 0
                LEFT JOIN $vgr ON $vgr.id = target.vendor_grade_id AND $vgr.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.id = ?
                  AND (
                        (
                            $t.status = 'published'
                            AND $t.workflow_stage = 'bidding'
                            AND (COALESCE($t.release_at, $t.published_at) IS NULL OR COALESCE($t.release_at, $t.published_at) <= ?)
                            AND ($t.closing_at IS NULL OR $t.closing_at > ?)
                            AND (
                                target_vendor.id IS NOT NULL
                                OR $tiv.id IS NOT NULL
                                OR
                                (
                                    (
                                        target.id IS NULL
                                        AND NOT EXISTS (
                                            SELECT 1
                                            FROM $ttv any_target_vendor
                                            WHERE any_target_vendor.tender_id = $t.id
                                              AND any_target_vendor.deleted = 0
                                        )
                                        AND $t.tender_type = 'open'
                                    )
                                    OR (
                                        target.id IS NOT NULL
                                        AND (
                                            (
                                            target.vendor_group_id IS NOT NULL
                                            AND target.vendor_group_id = vendor_profile.vendor_group_id
                                            )
                                            OR (
                                            target.vendor_grade_id IS NOT NULL
                                            AND target.vendor_grade_id = vendor_profile.vendor_grade_id
                                            )
                                            OR EXISTS (
                                                SELECT 1
                                                FROM $vs
                                                WHERE $vs.deleted = 0
                                                  AND $vs.status IN ('approved', 'pending')
                                                  AND $vs.vendor_id = vendor_profile.id
                                                  AND target.vendor_category_id IS NOT NULL
                                                  AND (
                                                        $vs.vendor_category_id = target.vendor_category_id
                                                        AND (
                                                            target.vendor_sub_category_id IS NULL
                                                            OR $vs.vendor_sub_category_id = target.vendor_sub_category_id
                                                        )
                                                    )
                                            )
                                        )
                                    )
                                )
                            )
                        )
                        OR (
                            participated_bid.id IS NOT NULL
                            AND $t.status IN ('published', 'closed', 'awarded')
                        )
                  )
                LIMIT 1";

        return $this->db->query($sql, [$vendor_id, $vendor_id, $vendor_id, $tender_id, $now, $now])->getRow();
    }



    public function is_vendor_submission_open($tender): bool
    {
        if (!$tender) {
            return false;
        }

        if ((int) ($tender->deleted ?? 0) === 1) {
            return false;
        }

        if (($tender->status ?? "") !== "published") {
            return false;
        }

        if (($tender->workflow_stage ?? "bidding") !== "bidding") {
            return false;
        }

        if (!empty($tender->closing_at)) {
            $closingAt = Time::parse((string) $tender->closing_at, "Asia/Muscat");
            $now = Time::now("Asia/Muscat");

            // At the exact closing moment, vendor submission must stop.
            if ($closingAt->getTimestamp() <= $now->getTimestamp()) {
                return false;
            }
        }

        return true;
    }
}
