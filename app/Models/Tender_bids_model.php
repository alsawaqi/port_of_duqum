<?php

namespace App\Models;

class Tender_bids_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "tender_bids";
        parent::__construct($this->table);
    }

    public function get_vendor_bid(int $tender_id, int $vendor_id)
    {
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT *
                FROM $tb
                WHERE deleted = 0
                  AND tender_id = ?
                  AND vendor_id = ?
                ORDER BY id DESC
                LIMIT 1";

        return $this->db->query($sql, [$tender_id, $vendor_id])->getRow();
    }

    public function get_closed_tenders_for_technical_user(int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.published_at,
                    $t.closing_at,
                    $t.technical_start_at,
                    $t.technical_end_at,
                    COUNT(DISTINCT CASE WHEN $tb.status = 'submitted' AND $tb.deleted = 0 THEN $tb.id END) AS submitted_bids_count,
                    COUNT(DISTINCT CASE WHEN $tb.deleted = 0 THEN $tb.id END) AS total_bids_count
                FROM $t
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'technical_evaluator'
                   AND $ttm.user_id = ?
                LEFT JOIN $tb
                    ON $tb.tender_id = $t.id
                   AND $tb.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status = 'closed'
                  AND $t.workflow_stage = 'technical'
                GROUP BY
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.published_at,
                    $t.closing_at,
                    $t.technical_start_at,
                    $t.technical_end_at
                HAVING COUNT(DISTINCT CASE WHEN $tb.deleted = 0 THEN $tb.id END) > 0
                ORDER BY
                    CASE WHEN $t.technical_end_at IS NULL THEN 1 ELSE 0 END ASC,
                    $t.technical_end_at ASC,
                    $t.id DESC";

        return $this->db->query($sql, [$user_id])->getResult();
    }

    public function get_closed_tender_for_technical_user(int $tender_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.published_at,
                    $t.closing_at,
                    $t.technical_start_at,
                    $t.technical_end_at,
                    COUNT(DISTINCT CASE WHEN $tb.status = 'submitted' AND $tb.deleted = 0 THEN $tb.id END) AS submitted_bids_count,
                    COUNT(DISTINCT CASE WHEN $tb.deleted = 0 THEN $tb.id END) AS total_bids_count
                FROM $t
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'technical_evaluator'
                   AND $ttm.user_id = ?
                LEFT JOIN $tb
                    ON $tb.tender_id = $t.id
                   AND $tb.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status = 'closed'
                  AND $t.workflow_stage = 'technical'
                  AND $t.id = ?
                GROUP BY
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.published_at,
                    $t.closing_at,
                    $t.technical_start_at,
                    $t.technical_end_at
                LIMIT 1";

        return $this->db->query($sql, [$user_id, $tender_id])->getRow();
    }

    public function get_submitted_bids_for_technical_review(int $tender_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");

        $sql = "SELECT
                    $tb.id,
                    $tb.tender_id,
                    $tb.vendor_id,
                    $tb.status,
                    $tb.submitted_at,
                    $tb.total_amount,
                    $tb.currency,
                    $v.vendor_name,
                    tech_doc.id AS technical_doc_id,
                    tech_doc.original_name AS technical_doc_name,
                    tech_doc.path AS technical_doc_path,
                    tech_doc.mime_type AS technical_doc_mime_type,
                    tech_doc.size_bytes AS technical_doc_size_bytes
                FROM $tb
                INNER JOIN $t
                    ON $t.id = $tb.tender_id
                   AND $t.deleted = 0
                   AND $t.status = 'closed'
                   AND $t.workflow_stage = 'technical'
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'technical_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN $v
                    ON $v.id = $tb.vendor_id
                   AND $v.deleted = 0
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $tbd
                    WHERE deleted = 0
                      AND section = 'technical'
                    GROUP BY tender_bid_id
                ) tech_doc_max
                    ON tech_doc_max.tender_bid_id = $tb.id
                LEFT JOIN $tbd tech_doc
                    ON tech_doc.id = tech_doc_max.max_id
                WHERE $tb.deleted = 0
                  AND $tb.status = 'submitted'
                  AND $tb.tender_id = ?
                ORDER BY $tb.submitted_at ASC, $tb.id ASC";

        return $this->db->query($sql, [$user_id, $tender_id])->getResult();
    }

    public function is_technical_evaluation_complete(int $tender_id): bool
    {
        $tb = $this->db->prefixTable("tender_bids");

        $row = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM $tb
             WHERE deleted = 0
               AND tender_id = ?
               AND status = 'submitted'",
            [$tender_id]
        )->getRow();

        return ((int) ($row->total ?? 0) === 0);
    }

    public function get_tenders_ready_for_3key_opening(int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.status,
                    $t.workflow_stage,
                    $t.closing_at,
                    $t.committee_3key_start_at,
                    $t.committee_3key_end_at,
                    COUNT(DISTINCT $tb.id) AS bids_count,
                    SUM(CASE WHEN $tb.status = 'submitted' THEN 1 ELSE 0 END) AS pending_technical_count,
                    SUM(CASE WHEN $tb.status = 'accepted' THEN 1 ELSE 0 END) AS accepted_bids_count
                FROM $t
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.user_id = ?
                   AND $ttm.team_role IN ('chairman', 'secretary', 'itc_member')
                INNER JOIN $tb
                    ON $tb.tender_id = $t.id
                   AND $tb.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status = 'closed'
                  AND $t.workflow_stage = 'committee_3key'
                GROUP BY
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.status,
                    $t.workflow_stage,
                    $t.closing_at,
                    $t.committee_3key_start_at,
                    $t.committee_3key_end_at
                HAVING COUNT(DISTINCT $tb.id) > 0
                   AND SUM(CASE WHEN $tb.status = 'submitted' THEN 1 ELSE 0 END) = 0
                   AND SUM(CASE WHEN $tb.status = 'accepted' THEN 1 ELSE 0 END) > 0
                ORDER BY
                    CASE WHEN $t.committee_3key_end_at IS NULL THEN 1 ELSE 0 END ASC,
                    $t.committee_3key_end_at ASC,
                    $t.id DESC";

        return $this->db->query($sql, [$user_id])->getResult();
    }

    public function get_unlocked_tenders_for_commercial_user(int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.closing_at,
                    $t.commercial_start_at,
                    $t.commercial_end_at,
                    COUNT(DISTINCT CASE WHEN $tb.status = 'accepted' THEN $tb.id END) AS accepted_bids_count
                FROM $t
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'commercial_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tbo
                    WHERE deleted = 0
                      AND stage = 'commercial'
                      AND status = 'unlocked'
                    GROUP BY tender_id
                ) latest_opening
                    ON latest_opening.tender_id = $t.id
                INNER JOIN $tbo opening
                    ON opening.id = latest_opening.max_id
                LEFT JOIN $tb
                    ON $tb.tender_id = $t.id
                   AND $tb.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status = 'closed'
                  AND $t.workflow_stage = 'commercial'
                GROUP BY
                    $t.id,
                    $t.reference,
                    $t.title,
                    $t.tender_type,
                    $t.status,
                    $t.workflow_stage,
                    $t.closing_at,
                    $t.commercial_start_at,
                    $t.commercial_end_at
                HAVING COUNT(DISTINCT CASE WHEN $tb.status = 'accepted' THEN $tb.id END) > 0
                ORDER BY
                    CASE WHEN $t.commercial_end_at IS NULL THEN 1 ELSE 0 END ASC,
                    $t.commercial_end_at ASC,
                    $t.id DESC";

        return $this->db->query($sql, [$user_id])->getResult();
    }

    public function get_unlocked_tender_for_commercial_user(int $tender_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tb = $this->db->prefixTable("tender_bids");

        $sql = "SELECT
                        $t.id,
                        $t.reference,
                        $t.title,
                        $t.tender_type,
                        $t.status,
                        $t.workflow_stage,
                        $t.closing_at,
                        $t.commercial_start_at,
                        $t.commercial_end_at,
                    COUNT(DISTINCT CASE WHEN $tb.status = 'accepted' THEN $tb.id END) AS accepted_bids_count
                FROM $t
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'commercial_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tbo
                    WHERE deleted = 0
                      AND stage = 'commercial'
                      AND status = 'unlocked'
                    GROUP BY tender_id
                ) latest_opening
                    ON latest_opening.tender_id = $t.id
                INNER JOIN $tbo opening
                    ON opening.id = latest_opening.max_id
                LEFT JOIN $tb
                    ON $tb.tender_id = $t.id
                   AND $tb.deleted = 0
                WHERE $t.deleted = 0
                  AND $t.status = 'closed'
                  AND $t.workflow_stage = 'commercial'
                  AND $t.id = ?
                GROUP BY
                    $t.id,
$t.reference,
$t.title,
$t.tender_type,
$t.status,
$t.workflow_stage,
$t.closing_at,
$t.commercial_start_at,
$t.commercial_end_at
                LIMIT 1";

        return $this->db->query($sql, [$user_id, $tender_id])->getRow();
    }

    public function get_technically_accepted_bids_for_commercial_review(int $tender_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");

        $sql = "SELECT
                    $tb.id,
                    $tb.tender_id,
                    $tb.vendor_id,
                    $tb.status,
                    $tb.submitted_at,
                    $tb.total_amount,
                    $tb.currency,
                    $v.vendor_name,
                    comm_doc.id AS commercial_doc_id,
                    comm_doc.original_name AS commercial_doc_name,
                    comm_doc.path AS commercial_doc_path,
                    comm_doc.mime_type AS commercial_doc_mime_type,
                    comm_doc.size_bytes AS commercial_doc_size_bytes
                FROM $tb
                INNER JOIN $t
                    ON $t.id = $tb.tender_id
                   AND $t.deleted = 0
                   AND $t.status = 'closed'
                   AND $t.workflow_stage = 'commercial'
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'commercial_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN (
                    SELECT tender_id, MAX(id) AS max_id
                    FROM $tbo
                    WHERE deleted = 0
                      AND stage = 'commercial'
                      AND status = 'unlocked'
                    GROUP BY tender_id
                ) latest_opening
                    ON latest_opening.tender_id = $t.id
                INNER JOIN $tbo opening
                    ON opening.id = latest_opening.max_id
                INNER JOIN $v
                    ON $v.id = $tb.vendor_id
                   AND $v.deleted = 0
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $tbd
                    WHERE deleted = 0
                      AND section = 'commercial'
                    GROUP BY tender_bid_id
                ) comm_doc_max
                    ON comm_doc_max.tender_bid_id = $tb.id
                LEFT JOIN $tbd comm_doc
                    ON comm_doc.id = comm_doc_max.max_id
                WHERE $tb.deleted = 0
                  AND $tb.status = 'accepted'
                  AND $tb.tender_id = ?
                ORDER BY $tb.submitted_at ASC, $tb.id ASC";

        return $this->db->query($sql, [$user_id, $tender_id])->getResult();
    }

    public function get_tender_bids_overview_for_technical_user(int $tender_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");
        $te = $this->db->prefixTable("tender_evaluations");
        $u = $this->db->prefixTable("users");

        $sql = "SELECT
                    $tb.id,
                    $tb.tender_id,
                    $tb.vendor_id,
                    $tb.status,
                    $tb.submitted_at,
                    $tb.total_amount,
                    $tb.currency,
                    $v.vendor_name,
                    tech_doc.id AS technical_doc_id,
                    tech_doc.original_name AS technical_doc_name,
                    tech_doc.path AS technical_doc_path,
                    tech_eval.id AS technical_evaluation_id,
                    tech_eval.evaluator_id AS decision_evaluator_id,
                    tech_eval.total_score AS decision_total_score,
                    tech_eval.comments AS decision_comments,
                    tech_eval.submitted_at AS decision_submitted_at,
                    TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS decision_evaluator_name,
                    $u.email AS decision_evaluator_email
                FROM $tb
                INNER JOIN $t
                    ON $t.id = $tb.tender_id
                   AND $t.deleted = 0
                   AND $t.status = 'closed'
                   AND $t.workflow_stage = 'technical'
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'technical_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN $v
                    ON $v.id = $tb.vendor_id
                   AND $v.deleted = 0
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $tbd
                    WHERE deleted = 0
                      AND section = 'technical'
                    GROUP BY tender_bid_id
                ) tech_doc_max
                    ON tech_doc_max.tender_bid_id = $tb.id
                LEFT JOIN $tbd tech_doc
                    ON tech_doc.id = tech_doc_max.max_id
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $te
                    WHERE deleted = 0
                      AND type = 'technical'
                    GROUP BY tender_bid_id
                ) tech_eval_max
                    ON tech_eval_max.tender_bid_id = $tb.id
                LEFT JOIN $te tech_eval
                    ON tech_eval.id = tech_eval_max.max_id
                LEFT JOIN $u
                    ON $u.id = tech_eval.evaluator_id
                WHERE $tb.deleted = 0
                  AND $tb.tender_id = ?
                  AND $tb.status IN ('submitted', 'accepted', 'rejected')
                ORDER BY
                    CASE WHEN $tb.status = 'submitted' THEN 0 ELSE 1 END ASC,
                    $tb.submitted_at ASC,
                    $tb.id ASC";

        return $this->db->query($sql, [$user_id, $tender_id])->getResult();
    }

    public function get_tender_bid_for_technical_user(int $tender_id, int $bid_id, int $user_id)
    {
        $t = $this->db->prefixTable("tenders");
        $ttm = $this->db->prefixTable("tender_team_members");
        $tb = $this->db->prefixTable("tender_bids");
        $v = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");
        $te = $this->db->prefixTable("tender_evaluations");
        $u = $this->db->prefixTable("users");

        $sql = "SELECT
                    $tb.id,
                    $tb.tender_id,
                    $tb.vendor_id,
                    $tb.status,
                    $tb.submitted_at,
                    $tb.total_amount,
                    $tb.currency,
                    $v.vendor_name,
                    tech_doc.id AS technical_doc_id,
                    tech_doc.original_name AS technical_doc_name,
                    tech_doc.path AS technical_doc_path,
                    tech_doc.mime_type AS technical_doc_mime_type,
                    tech_doc.size_bytes AS technical_doc_size_bytes,
                    tech_eval.id AS technical_evaluation_id,
                    tech_eval.evaluator_id AS decision_evaluator_id,
                    tech_eval.total_score AS decision_total_score,
                    tech_eval.comments AS decision_comments,
                    tech_eval.submitted_at AS decision_submitted_at,
                    TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS decision_evaluator_name,
                    $u.email AS decision_evaluator_email
                FROM $tb
                INNER JOIN $t
                    ON $t.id = $tb.tender_id
                   AND $t.deleted = 0
                   AND $t.status = 'closed'
                   AND $t.workflow_stage = 'technical'
                INNER JOIN $ttm
                    ON $ttm.tender_id = $t.id
                   AND $ttm.deleted = 0
                   AND $ttm.is_active = 1
                   AND $ttm.team_role = 'technical_evaluator'
                   AND $ttm.user_id = ?
                INNER JOIN $v
                    ON $v.id = $tb.vendor_id
                   AND $v.deleted = 0
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $tbd
                    WHERE deleted = 0
                      AND section = 'technical'
                    GROUP BY tender_bid_id
                ) tech_doc_max
                    ON tech_doc_max.tender_bid_id = $tb.id
                LEFT JOIN $tbd tech_doc
                    ON tech_doc.id = tech_doc_max.max_id
                LEFT JOIN (
                    SELECT tender_bid_id, MAX(id) AS max_id
                    FROM $te
                    WHERE deleted = 0
                      AND type = 'technical'
                    GROUP BY tender_bid_id
                ) tech_eval_max
                    ON tech_eval_max.tender_bid_id = $tb.id
                LEFT JOIN $te tech_eval
                    ON tech_eval.id = tech_eval_max.max_id
                LEFT JOIN $u
                    ON $u.id = tech_eval.evaluator_id
                WHERE $tb.deleted = 0
                  AND $tb.tender_id = ?
                  AND $tb.id = ?
                  AND $tb.status IN ('submitted', 'accepted', 'rejected')
                LIMIT 1";

        return $this->db->query($sql, [$user_id, $tender_id, $bid_id])->getRow();
    }


    public function get_tender_bids_overview_for_commercial_user(int $tender_id, int $user_id)
{
    $t = $this->db->prefixTable("tenders");
    $ttm = $this->db->prefixTable("tender_team_members");
    $tbo = $this->db->prefixTable("tender_bid_openings");
    $tb = $this->db->prefixTable("tender_bids");
    $v = $this->db->prefixTable("vendors");
    $tbd = $this->db->prefixTable("tender_bid_documents");
    $te = $this->db->prefixTable("tender_evaluations");
    $u = $this->db->prefixTable("users");

    $sql = "SELECT
                $tb.id,
                $tb.tender_id,
                $tb.vendor_id,
                $tb.status,
                $tb.submitted_at,
                $tb.total_amount,
                $tb.currency,
                $v.vendor_name,
                comm_doc.id AS commercial_doc_id,
                comm_doc.original_name AS commercial_doc_name,
                comm_doc.path AS commercial_doc_path,
                comm_eval.id AS commercial_evaluation_id,
                comm_eval.evaluator_id AS decision_evaluator_id,
                comm_eval.decision AS commercial_decision,
                comm_eval.total_score AS decision_total_score,
                comm_eval.comments AS decision_comments,
                comm_eval.submitted_at AS decision_submitted_at,
                TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS decision_evaluator_name,
                $u.email AS decision_evaluator_email
            FROM $tb
            INNER JOIN $t
                ON $t.id = $tb.tender_id
               AND $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'commercial'
            INNER JOIN $ttm
                ON $ttm.tender_id = $t.id
               AND $ttm.deleted = 0
               AND $ttm.is_active = 1
               AND $ttm.team_role = 'commercial_evaluator'
               AND $ttm.user_id = ?
            INNER JOIN (
                SELECT tender_id, MAX(id) AS max_id
                FROM $tbo
                WHERE deleted = 0
                  AND stage = 'commercial'
                  AND status = 'unlocked'
                GROUP BY tender_id
            ) latest_opening
                ON latest_opening.tender_id = $t.id
            INNER JOIN $tbo opening
                ON opening.id = latest_opening.max_id
            INNER JOIN $v
                ON $v.id = $tb.vendor_id
               AND $v.deleted = 0
            LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = 'commercial'
                GROUP BY tender_bid_id
            ) comm_eval_max
                ON comm_eval_max.tender_bid_id = $tb.id
            LEFT JOIN $te comm_eval
                ON comm_eval.id = comm_eval_max.max_id
            LEFT JOIN $u
                ON $u.id = comm_eval.evaluator_id
            LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $tbd
                WHERE deleted = 0
                  AND section = 'commercial'
                GROUP BY tender_bid_id
            ) comm_doc_max
                ON comm_doc_max.tender_bid_id = $tb.id
            LEFT JOIN $tbd comm_doc
                ON comm_doc.id = comm_doc_max.max_id
            WHERE $tb.deleted = 0
              AND $tb.status = 'accepted'
              AND $tb.tender_id = ?
            ORDER BY
                CASE WHEN $tb.total_amount IS NULL THEN 1 ELSE 0 END ASC,
                $tb.total_amount ASC,
                $tb.submitted_at ASC,
                $tb.id ASC";

    return $this->db->query($sql, [$user_id, $tender_id])->getResult();
}

public function get_tender_bid_for_commercial_user(int $tender_id, int $bid_id, int $user_id)
{
    $t = $this->db->prefixTable("tenders");
    $ttm = $this->db->prefixTable("tender_team_members");
    $tbo = $this->db->prefixTable("tender_bid_openings");
    $tb = $this->db->prefixTable("tender_bids");
    $v = $this->db->prefixTable("vendors");
    $tbd = $this->db->prefixTable("tender_bid_documents");
    $te = $this->db->prefixTable("tender_evaluations");
    $u = $this->db->prefixTable("users");

    $sql = "SELECT
                $tb.id,
                $tb.tender_id,
                $tb.vendor_id,
                $tb.status,
                $tb.submitted_at,
                $tb.total_amount,
                $tb.currency,
                $v.vendor_name,
                comm_doc.id AS commercial_doc_id,
                comm_doc.original_name AS commercial_doc_name,
                comm_doc.path AS commercial_doc_path,
                comm_eval.id AS commercial_evaluation_id,
                comm_eval.evaluator_id AS decision_evaluator_id,
                comm_eval.decision AS commercial_decision,
                comm_eval.total_score AS decision_total_score,
                comm_eval.comments AS decision_comments,
                comm_eval.submitted_at AS decision_submitted_at,
                TRIM(CONCAT(COALESCE($u.first_name, ''), ' ', COALESCE($u.last_name, ''))) AS decision_evaluator_name,
                $u.email AS decision_evaluator_email
            FROM $tb
            INNER JOIN $t
                ON $t.id = $tb.tender_id
               AND $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'commercial'
            INNER JOIN $ttm
                ON $ttm.tender_id = $t.id
               AND $ttm.deleted = 0
               AND $ttm.is_active = 1
               AND $ttm.team_role = 'commercial_evaluator'
               AND $ttm.user_id = ?
            INNER JOIN (
                SELECT tender_id, MAX(id) AS max_id
                FROM $tbo
                WHERE deleted = 0
                  AND stage = 'commercial'
                  AND status = 'unlocked'
                GROUP BY tender_id
            ) latest_opening
                ON latest_opening.tender_id = $t.id
            INNER JOIN $tbo opening
                ON opening.id = latest_opening.max_id
            INNER JOIN $v
                ON $v.id = $tb.vendor_id
               AND $v.deleted = 0
            LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = 'commercial'
                GROUP BY tender_bid_id
            ) comm_eval_max
                ON comm_eval_max.tender_bid_id = $tb.id
            LEFT JOIN $te comm_eval
                ON comm_eval.id = comm_eval_max.max_id
            LEFT JOIN $u
                ON $u.id = comm_eval.evaluator_id
            LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $tbd
                WHERE deleted = 0
                  AND section = 'commercial'
                GROUP BY tender_bid_id
            ) comm_doc_max
                ON comm_doc_max.tender_bid_id = $tb.id
            LEFT JOIN $tbd comm_doc
                ON comm_doc.id = comm_doc_max.max_id
            WHERE $tb.deleted = 0
              AND $tb.status = 'accepted'
              AND $tb.tender_id = ?
              AND $tb.id = ?
            LIMIT 1";

    return $this->db->query($sql, [$user_id, $tender_id, $bid_id])->getRow();
}
}