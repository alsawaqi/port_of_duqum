<?php

namespace App\Models;

use CodeIgniter\I18n\Time;

class Tenders_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tenders";
        parent::__construct($this->table);
    }

    private function get_tender_business_now(): string
    {
        return Time::now('Asia/Muscat')->toDateTimeString();
    }

    private function get_stage_days(string $stage): int
    {
        switch ($stage) {
            case 'technical':
                return 3;
            case 'committee_3key':
                return 3;
            case 'commercial':
                return 3;
            default:
                return 0;
        }
    }

    public function auto_close_expired_tenders(): int
    {
        return $this->auto_progress_workflow();
    }

    public function auto_progress_workflow(): int
    {
        $t = $this->db->prefixTable("tenders");
        $tb = $this->db->prefixTable("tender_bids");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = $this->get_tender_business_now();
        $affected = 0;

        $technicalDays = $this->get_stage_days('technical');
        $committeeDays = $this->get_stage_days('committee_3key');
        $commercialDays = $this->get_stage_days('commercial');

        // 1) Close published tenders once bid closing time has passed.
        $this->db->query(
            "UPDATE $t
             SET status = 'closed',
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'published'
               AND closing_at IS NOT NULL
               AND closing_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 2) Move closed tenders from bidding -> technical.
        $this->db->query(
            "UPDATE $t
             SET workflow_stage = 'technical',
                 technical_start_at = IFNULL(technical_start_at, closing_at),
                 technical_end_at = IFNULL(technical_end_at, DATE_ADD(IFNULL(technical_start_at, closing_at), INTERVAL {$technicalDays} DAY)),
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'closed'
               AND workflow_stage = 'bidding'
               AND closing_at IS NOT NULL
               AND closing_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 3) Any bid still left as submitted after the technical deadline is auto-rejected.
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

        // 4) Once technical deadline is reached, lock technical and open the committee 3-key stage.
        $this->db->query(
            "UPDATE $t
             SET workflow_stage = 'committee_3key',
                 technical_locked_at = IFNULL(technical_locked_at, technical_end_at),
                 committee_3key_start_at = IFNULL(committee_3key_start_at, technical_end_at),
                 committee_3key_end_at = IFNULL(committee_3key_end_at, DATE_ADD(IFNULL(committee_3key_start_at, technical_end_at), INTERVAL {$committeeDays} DAY)),
                 updated_at = ?
             WHERE deleted = 0
               AND status = 'closed'
               AND workflow_stage = 'technical'
               AND technical_end_at IS NOT NULL
               AND technical_end_at <= ?",
            [$now, $now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 5) If committee successfully unlocks the commercial opening, start the commercial stage.
        $this->db->query(
            "UPDATE $t
             INNER JOIN (
                 SELECT tender_id, MAX(id) AS max_id
                 FROM $tbo
                 WHERE deleted = 0
                   AND stage = 'commercial'
                   AND status = 'unlocked'
                 GROUP BY tender_id
             ) latest_opening ON latest_opening.tender_id = $t.id
             INNER JOIN $tbo opening ON opening.id = latest_opening.max_id
             SET $t.workflow_stage = 'commercial',
                 $t.commercial_unlocked_at = IFNULL($t.commercial_unlocked_at, opening.unlocked_at),
                 $t.commercial_start_at = IFNULL($t.commercial_start_at, opening.unlocked_at),
                 $t.commercial_end_at = IFNULL($t.commercial_end_at, DATE_ADD(IFNULL($t.commercial_start_at, opening.unlocked_at), INTERVAL {$commercialDays} DAY)),
                 $t.updated_at = ?
             WHERE $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'committee_3key'",
            [$now]
        );
        $affected += max(0, (int) $this->db->affectedRows());

        // 6) Once commercial window ends, move the tender to award decision stage.
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

        return $affected;
    }

    public function get_by_request_id(int $tender_request_id)
    {
        $this->auto_progress_workflow();

        $t = $this->db->prefixTable("tenders");

        $sql = "SELECT * FROM $t
                WHERE deleted=0 AND tender_request_id=?
                ORDER BY id DESC
                LIMIT 1";
        return $this->db->query($sql, [$tender_request_id])->getRow();
    }

    public function get_vendor_visible_tenders(int $vendor_id)
    {
        $this->auto_progress_workflow();

        $t = $this->db->prefixTable("tenders");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tts = $this->db->prefixTable("tender_target_specialties");
        $vc = $this->db->prefixTable("vendor_categories");
        $vsc = $this->db->prefixTable("vendor_sub_categories");

        $sql = "SELECT
                    $t.*,
                    $tiv.invite_status,
                    $tiv.invited_at,
                    $vc.name AS vendor_category_name,
                    $vsc.name AS vendor_sub_category_name
                FROM $t
                INNER JOIN $tiv
                    ON $tiv.tender_id = $t.id
                   AND $tiv.vendor_id = ?
                   AND $tiv.deleted = 0
                LEFT JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tts
                    WHERE deleted = 0
                    GROUP BY tender_id
                ) tts_max ON tts_max.tender_id = $t.id
                LEFT JOIN $tts target ON target.id = tts_max.max_id
                LEFT JOIN $vc ON $vc.id = target.vendor_category_id AND $vc.deleted = 0
                LEFT JOIN $vsc ON $vsc.id = target.vendor_sub_category_id AND $vsc.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status IN ('published', 'closed', 'awarded')
                ORDER BY
                    CASE WHEN $t.status = 'published' THEN 0 ELSE 1 END ASC,
                    CASE WHEN $t.closing_at IS NULL THEN 1 ELSE 0 END ASC,
                    $t.closing_at ASC,
                    $t.id DESC";

        return $this->db->query($sql, [$vendor_id]);
    }

    public function get_vendor_visible_tender(int $tender_id, int $vendor_id)
    {
        $this->auto_progress_workflow();

        $t = $this->db->prefixTable("tenders");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tts = $this->db->prefixTable("tender_target_specialties");
        $vc = $this->db->prefixTable("vendor_categories");
        $vsc = $this->db->prefixTable("vendor_sub_categories");

        $sql = "SELECT
                    $t.*,
                    $tiv.invite_status,
                    $tiv.invited_at,
                    $vc.name AS vendor_category_name,
                    $vsc.name AS vendor_sub_category_name
                FROM $t
                INNER JOIN $tiv
                    ON $tiv.tender_id = $t.id
                   AND $tiv.vendor_id = ?
                   AND $tiv.deleted = 0
                LEFT JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tts
                    WHERE deleted = 0
                    GROUP BY tender_id
                ) tts_max ON tts_max.tender_id = $t.id
                LEFT JOIN $tts target ON target.id = tts_max.max_id
                LEFT JOIN $vc ON $vc.id = target.vendor_category_id AND $vc.deleted = 0
                LEFT JOIN $vsc ON $vsc.id = target.vendor_sub_category_id AND $vsc.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status IN ('published', 'closed', 'awarded')
                  AND $t.id = ?
                LIMIT 1";

        return $this->db->query($sql, [$vendor_id, $tender_id])->getRow();
    }



    public function is_vendor_submission_open($tender): bool
    {
        $this->auto_progress_workflow();

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