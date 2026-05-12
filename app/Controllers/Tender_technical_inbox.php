<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_criteria_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_evaluation_attachments_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_evaluation_scores_model;
use App\Models\Tenders_model;

class Tender_technical_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_communications_model;
    protected $Tender_criteria_model;
    protected $Tender_documents_model;
    protected $Tender_evaluation_attachments_model;
    protected $Tender_evaluations_model;
    protected $Tender_evaluation_scores_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_criteria_model = new Tender_criteria_model();
        $this->Tender_documents_model = new Tender_documents_model();
        $this->Tender_evaluation_attachments_model = new Tender_evaluation_attachments_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tender_evaluation_scores_model = new Tender_evaluation_scores_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("technical_eval", "view");
        return $this->template->rander("tender_technical_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("technical_eval", "view");

        $this->Tenders_model->auto_progress_workflow();

        $list = $this->Tender_bids_model->get_closed_tenders_for_technical_user((int) $this->login_user->id);

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function details($id = 0)
    {
        $this->access_only_tender("technical_eval", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, (int) $this->login_user->id);
        if (!$tender) {
            show_404();
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        $tender_documents = $this->Tender_documents_model->get_details(["tender_id" => $tender_id])->getResult();
        $bids = $this->Tender_bids_model->get_tender_bids_overview_for_technical_user($tender_id, (int) $this->login_user->id);

        $pending_bids = [];
        $my_finalized_bids = [];
        $locked_bids = [];
        $accepted_count = 0;
        $rejected_count = 0;

        foreach ($bids as $bid) {
            $status = strtolower((string) ($bid->status ?? "submitted"));
            if ($status === "accepted") {
                $accepted_count++;
            } elseif ($status === "rejected") {
                $rejected_count++;
            }

            if ($status === "submitted" || (in_array($status, ["accepted", "rejected"], true) && (int) ($bid->decision_evaluator_id ?? 0) === 0)) {
                $pending_bids[] = $bid;
            } elseif ((int) ($bid->decision_evaluator_id ?? 0) === (int) $this->login_user->id) {
                $my_finalized_bids[] = $bid;
            } else {
                $locked_bids[] = $bid;
            }
        }

        $stage_max_score = 0;
        foreach ($criteria as $criterion) {
            $stage_max_score += (float) ($criterion->weight ?? 0);
        }

        return $this->template->rander("tender_technical_inbox/details", [
            "tender"            => $tender,
            "tender_documents"  => $tender_documents,
            "criteria"          => $criteria,
            "stage_max_score"   => $stage_max_score,
            "pending_bids"      => $pending_bids,
            "my_finalized_bids" => $my_finalized_bids,
            "locked_bids"       => $locked_bids,
            "pending_count"     => count($pending_bids),
            "accepted_count"    => $accepted_count,
            "rejected_count"    => $rejected_count,
        ]);
    }

    function bid_modal_form()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric"
        ]);
        $this->access_only_tender("technical_eval", "view");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, $user_id);
        if (!$tender) {
            show_404();
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $user_id);
        if (!$bid) {
            show_404();
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        $stage_max_score = 0;
        foreach ($criteria as $criterion) {
            $stage_max_score += (float) ($criterion->weight ?? 0);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "technical");
        $owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $status = strtolower((string) ($bid->status ?? "submitted"));

        $editable = false;
        if ($status === "submitted") {
            $editable = true;
        } elseif (in_array($status, ["accepted", "rejected"], true) && (($owner_id === $user_id && !empty($latest_evaluation->id)) || empty($latest_evaluation->id))) {
            $editable = true;
        }

        $active_evaluation = null;
        if ($editable && !empty($latest_evaluation->id) && $owner_id === $user_id) {
            $active_evaluation = $latest_evaluation;
        } elseif ($editable) {
            $active_evaluation = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator($tender_id, $bid_id, $user_id, "technical");
        } else {
            $active_evaluation = $latest_evaluation;
        }

        $scores_by_criterion = [];
        $finding_attachments = [];
        $internal_messages = $this->Tender_communications_model->get_internal_conversation($tender_id, "technical", $bid_id);
        $internal_attachments = $this->Tender_communications_model->get_attachments_map(array_map(fn($message) => (int) $message->id, $internal_messages));
        if (!empty($active_evaluation->id)) {
            $grouped = $this->Tender_evaluation_scores_model->get_grouped_by_evaluation_ids([(int) $active_evaluation->id]);
            $scores_by_criterion = get_array_value($grouped, (int) $active_evaluation->id) ?: [];

            $grouped_attachments = $this->Tender_evaluation_attachments_model->get_grouped_by_evaluation_ids([(int) $active_evaluation->id]);
            $finding_attachments = get_array_value($grouped_attachments, (int) $active_evaluation->id) ?: [];
        }

        return $this->template->view("tender_technical_inbox/bid_modal_form", [
            "tender"              => $tender,
            "bid"                 => $bid,
            "criteria"            => $criteria,
            "stage_max_score"     => $stage_max_score,
            "active_evaluation"   => $active_evaluation,
            "scores_by_criterion" => $scores_by_criterion,
            "finding_attachments" => $finding_attachments,
            "internal_messages"    => $internal_messages,
            "internal_attachments" => $internal_attachments,
            "latest_evaluation"   => $latest_evaluation,
            "editable"            => $editable,
        ]);
    }

    function save_bid_evaluation()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric",
        ]);
        $this->access_only_tender("technical_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $evaluator_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, $evaluator_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to review it."
            ]);
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $evaluator_id);
        if (!$bid) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid not found or you do not have access to review it."
            ]);
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        if (empty($criteria)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "No technical criteria found for this tender."
            ]);
        }

        $decision = strtolower(trim((string) $this->request->getPost("decision")));
        $scores_input = (array) $this->request->getPost("scores");
        $criterion_comments_input = (array) $this->request->getPost("criterion_comments");
        $evaluation_comment = trim((string) $this->request->getPost("evaluation_comment"));

        if (!in_array($decision, ["accepted", "rejected"], true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please choose whether the bid is technically accepted or rejected."
            ]);
        }

        $score_rows = [];
        $total_score = 0;
        foreach ($criteria as $criterion) {
            $criterion_id = (int) $criterion->id;
            $criterion_name = (string) ($criterion->name ?? ("Criterion #" . $criterion_id));
            $max_score = round((float) ($criterion->weight ?? 0), 3);
            $raw_score = get_array_value($scores_input, $criterion_id);

            if ($raw_score === "" || $raw_score === null) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Please enter a score for '{$criterion_name}'."
                ]);
            }

            if (!is_numeric($raw_score)) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Invalid score for '{$criterion_name}'."
                ]);
            }

            $score = round((float) $raw_score, 3);
            if ($score < 0 || $score > $max_score) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Score for '{$criterion_name}' must be between 0 and {$max_score}."
                ]);
            }

            $total_score += $score;
            $score_rows[] = [
                "tender_criterion_id" => $criterion_id,
                "score"               => $score,
                "comment"             => trim((string) get_array_value($criterion_comments_input, $criterion_id)),
            ];
        }

        $now = date("Y-m-d H:i:s");
        $late_review = $this->_late_review_metadata($tender, "technical", $now);
        $db = db_connect();
        $db->transBegin();

        $fresh_bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $evaluator_id);
        if (!$fresh_bid) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid is no longer available."
            ]);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "technical");
        $latest_owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $has_latest_evaluation = !empty($latest_evaluation->id);
        $fresh_status = strtolower((string) ($fresh_bid->status ?? "submitted"));

        if (in_array($fresh_status, ["accepted", "rejected"], true) && $latest_owner_id !== $evaluator_id && (!$late_review["is_late"] || $has_latest_evaluation)) {
            $owner_name = trim((string) ($latest_evaluation->evaluator_name ?? ""));
            if ($owner_name === "") {
                $owner_name = "another evaluator";
            }

            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "This bid was already finalized by {$owner_name}. You can no longer approve or reject it."
            ]);
        }

        $existing = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator(
            $tender_id,
            $bid_id,
            $evaluator_id,
            "technical"
        );
        $review_started_at = !empty($existing->review_started_at) ? (string) $existing->review_started_at : $late_review["review_started_at"];
        $review_duration_seconds = $this->_review_duration_seconds($review_started_at, $now);

        if (!$late_review["is_late"]) {
            $tb = $db->prefixTable("tender_bids");
            if ($fresh_status === "submitted") {
                $db->query(
                    "UPDATE $tb
                     SET status = ?, updated_at = ?
                     WHERE id = ?
                       AND deleted = 0
                       AND status = 'submitted'",
                    [$decision, $now, $bid_id]
                );

                if ((int) $db->affectedRows() !== 1) {
                    $db->transRollback();
                    return $this->response->setJSON([
                        "success" => false,
                        "message" => "This bid was already decided by another evaluator. Please refresh the page."
                    ]);
                }
            } else {
                if (!in_array($fresh_status, ["accepted", "rejected"], true) || $latest_owner_id !== $evaluator_id) {
                    $db->transRollback();
                    return $this->response->setJSON([
                        "success" => false,
                        "message" => "This bid is locked and cannot be edited by you."
                    ]);
                }

                $this->Tender_bids_model->ci_save([
                    "status"     => $decision,
                    "updated_at" => $now,
                ], $bid_id);
            }
        }

        $evaluation_data = [
            "tender_id"     => $tender_id,
            "tender_bid_id" => $bid_id,
            "evaluator_id"  => $evaluator_id,
            "type"          => "technical",
            "status"        => "submitted",
            "decision"      => $decision,
            "total_score"   => round($total_score, 3),
            "comments"      => $evaluation_comment ?: null,
            "review_started_at" => $review_started_at,
            "review_duration_seconds" => $review_duration_seconds,
            "deadline_at" => $late_review["deadline_at"],
            "submitted_after_deadline" => $late_review["is_late"] ? 1 : 0,
            "late_review_status" => $late_review["is_late"] ? "pending" : null,
            "late_reviewed_by" => null,
            "late_reviewed_at" => null,
            "late_review_comment" => null,
            "submitted_at"  => $now,
            "updated_at"    => $now,
            "deleted"       => 0,
        ];

        if (!empty($existing->id)) {
            $evaluation_id = (int) $this->Tender_evaluations_model->ci_save($evaluation_data, (int) $existing->id);
        } else {
            $evaluation_data["created_at"] = $now;
            $evaluation_id = (int) $this->Tender_evaluations_model->ci_save($evaluation_data);
        }

        $this->Tender_evaluation_scores_model->soft_delete_by_evaluation_id($evaluation_id);
        foreach ($score_rows as $row) {
            $this->Tender_evaluation_scores_model->ci_save([
                "tender_evaluation_id" => $evaluation_id,
                "tender_criterion_id"  => (int) $row["tender_criterion_id"],
                "score"                => $row["score"],
                "comment"              => $row["comment"] ?: null,
                "created_at"           => $now,
                "updated_at"           => $now,
                "deleted"              => 0,
            ]);
        }

        $this->_save_technical_finding_files($evaluation_id, $tender_id, $bid_id);
        $this->_record_evaluation_history($db, $tender, $fresh_bid, $evaluation_id, "technical", $late_review, $decision, round($total_score, 3), $evaluation_comment, $now);

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $db->transCommit();
        $this->Tenders_model->auto_progress_workflow();

        return $this->response->setJSON([
            "success" => true,
            "message" => $late_review["is_late"] ? "Late technical evaluation saved and sent to procurement for review." : "Technical evaluation saved successfully."
        ]);
    }

    private function _late_review_metadata($tender, string $type, string $now): array
    {
        $deadline = $type === "technical"
            ? ($tender->technical_end_at ?? $tender->technical_eval_deadline ?? null)
            : ($tender->commercial_end_at ?? $tender->commercial_eval_deadline ?? null);
        $started_at = $type === "technical"
            ? ($tender->technical_start_at ?? $tender->bid_opening_at ?? $tender->closing_at ?? $now)
            : ($tender->commercial_start_at ?? $tender->commercial_unlocked_at ?? $now);

        $deadline = $this->_normalize_audit_datetime($deadline);
        $started_at = $this->_normalize_audit_datetime($started_at) ?: $now;
        $stage = (string) ($tender->workflow_stage ?? "");
        $late_stage = $type === "technical"
            ? !in_array($stage, ["technical"], true)
            : !in_array($stage, ["commercial"], true);
        $late_deadline = $deadline && strtotime($now) > strtotime($deadline);

        return [
            "deadline_at" => $deadline,
            "review_started_at" => $started_at,
            "is_late" => (bool) ($late_deadline || $late_stage),
        ];
    }

    private function _normalize_audit_datetime($value): ?string
    {
        if (!$value) {
            return null;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date("Y-m-d H:i:s", $timestamp);
    }

    private function _review_duration_seconds(?string $started_at, string $now): ?int
    {
        if (!$started_at) {
            return null;
        }

        $start_time = strtotime($started_at);
        $end_time = strtotime($now);
        if ($start_time === false || $end_time === false || $end_time < $start_time) {
            return null;
        }

        return $end_time - $start_time;
    }

    private function _record_evaluation_history($db, $tender, $bid, int $evaluation_id, string $type, array $late_review, string $decision, float $score, string $comment, string $now): void
    {
        $table = $db->prefixTable("tender_workflow_history");
        $duration = $this->_review_duration_seconds($late_review["review_started_at"] ?? null, $now);
        $details = [
            ucfirst($type) . " evaluation submitted for bid #" . (int) ($bid->id ?? 0) . ".",
            "Decision: " . ucfirst($decision),
            "Score: " . number_format($score, 3),
            "Review duration: " . ($duration === null ? "-" : $this->_format_duration($duration)),
        ];

        if (!empty($late_review["is_late"])) {
            $details[] = "Submitted after deadline; waiting for procurement late review.";
        }

        if ($comment !== "") {
            $details[] = "Comments: " . $comment;
        }

        $db->table($table)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => $type . "_evaluation_submitted",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($tender->status ?? ""),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => (string) ($tender->workflow_stage ?? ""),
            "open_until" => $late_review["deadline_at"] ?? null,
            "reason" => !empty($late_review["is_late"]) ? "Late evaluation pending procurement review" : null,
            "details" => implode("\n", $details),
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _format_duration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . "d";
        }
        if ($hours > 0) {
            $parts[] = $hours . "h";
        }
        $parts[] = $minutes . "m";

        return implode(" ", $parts);
    }

    public function request_clarification()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "message"   => "required",
        ]);
        $this->access_only_tender("technical_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;
        $message = trim((string) $this->request->getPost("message"));
        $subject = trim((string) $this->request->getPost("subject"));

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, $user_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to request clarification."
            ]);
        }

        $bid = null;
        if ($bid_id > 0) {
            $bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $user_id);
            if (!$bid) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Bid not found or you do not have access to request clarification."
                ]);
            }
        }

        $now = date("Y-m-d H:i:s");
        $saved = $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => $tender_id,
            "vendor_id" => $bid ? (int) ($bid->vendor_id ?? 0) : null,
            "tender_bid_id" => $bid ? $bid_id : null,
            "type" => "technical_clarification_request",
            "clarification_scope" => "technical",
            "internal_audience" => "technical",
            "subject" => $subject ?: ($bid ? ("Technical clarification request - " . ($bid->vendor_name ?? "Vendor")) : "General technical clarification request"),
            "message" => $message,
            "parent_id" => null,
            "sent_to_all" => 0,
            "is_vendor_visible" => 0,
            "status" => "pending_procurement",
            "created_by" => $user_id,
            "created_at" => $now,
            "published_at" => $now,
            "deleted" => 0,
        ]));

        if (!$saved) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $this->_save_clarification_files((int) $saved, $tender_id, $bid ? (int) ($bid->vendor_id ?? 0) : null);

        return $this->response->setJSON([
            "success" => true,
            "message" => "Clarification request sent to procurement.",
            "redirect_url" => get_uri("tender_technical_inbox/details/" . $tender_id),
        ]);
    }

    public function download_finding_document($id = 0)
    {
        $this->access_only_tender("technical_eval", "view");
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_evaluation_attachments_model->get_attachment($id);
        if (!$attachment) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user((int) $attachment->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $attachment->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($attachment->original_name ?: basename($full_path));
    }

    public function download_clarification_attachment($id = 0)
    {
        $this->access_only_tender("technical_eval", "view");
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_communications_model->get_attachment($id);
        if (
            !$attachment
            || (int) ($attachment->is_vendor_visible ?? 0) === 1
            || (string) ($attachment->internal_audience ?? "") !== "technical"
        ) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user((int) $attachment->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $attachment->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($attachment->original_name ?: basename($full_path));
    }

    public function preview_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->template->view(
            "tender_technical_inbox/file_preview",
            $this->_make_file_preview_data($context["doc"], get_uri("tender_technical_inbox/view_tender_document/" . (int) $id))
        );
    }

    public function view_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->_serve_document_file($context["doc"], $context["full_path"], false);
    }

    public function download_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->_serve_document_file($context["doc"], $context["full_path"], true);
    }

    public function preview_bid_document($id = 0)
    {
        $context = $this->_get_accessible_bid_document_context((int) $id);
        return $this->template->view(
            "tender_technical_inbox/file_preview",
            $this->_make_file_preview_data($context["doc"], get_uri("tender_technical_inbox/view_bid_document/" . (int) $id))
        );
    }

    public function view_bid_document($id = 0)
    {
        $context = $this->_get_accessible_bid_document_context((int) $id);
        return $this->_serve_document_file($context["doc"], $context["full_path"], false);
    }

    public function download_bid_document($id = 0)
    {
        $context = $this->_get_accessible_bid_document_context((int) $id);
        return $this->_serve_document_file($context["doc"], $context["full_path"], true);
    }

    private function _get_accessible_tender_document_context(int $id): array
    {
        $this->access_only_tender("technical_eval", "view");

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user((int) $doc->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = getcwd() . "/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return ["doc" => $doc, "full_path" => $full_path];
    }

    private function _get_accessible_bid_document_context(int $id): array
    {
        $this->access_only_tender("technical_eval", "view");

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        if (($doc->section ?? "") !== "technical") {
            app_redirect("forbidden");
        }

        $db = db_connect();
        $tb = $db->prefixTable("tender_bids");

        $bid = $db->query(
            "SELECT *
             FROM $tb
             WHERE id=? AND deleted=0
             LIMIT 1",
            [(int) $doc->tender_bid_id]
        )->getRow();

        if (!$bid) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user((int) $bid->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return ["doc" => $doc, "full_path" => $full_path];
    }

    private function _make_file_preview_data($doc, string $file_url): array
    {
        $file_name = (string) ($doc->original_name ?? basename((string) ($doc->path ?? "")));

        return [
            "file_url" => $file_url,
            "is_image_file" => is_image_file($file_name),
            "is_iframe_preview_available" => is_iframe_preview_available($file_name),
            "is_google_preview_available" => is_google_preview_available($file_name),
            "is_viewable_video_file" => is_viewable_video_file($file_name),
            "is_google_drive_file" => false,
        ];
    }

    private function _serve_document_file($doc, string $full_path, bool $download)
    {
        $mime = !empty($doc->mime_type ?? "")
            ? (string) $doc->mime_type
            : (function_exists("mime_content_type") ? mime_content_type($full_path) : "application/octet-stream");
        $name = $doc->original_name ?: basename($full_path);
        $inline = !$download && (
            strpos($mime, "image/") === 0
            || strpos($mime, "video/") === 0
            || strpos($mime, "audio/") === 0
            || $mime === "application/pdf"
            || strpos($mime, "text/") === 0
        );

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($full_path));
    }

    private function _save_technical_finding_files(int $evaluation_id, int $tender_id, int $bid_id): void
    {
        $files = method_exists($this->request, "getFileMultiple")
            ? ($this->request->getFileMultiple("technical_finding_files") ?: [])
            : (($this->request->getFiles()["technical_finding_files"] ?? []) ?: []);

        if (!$files) {
            return;
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $upload_dir = WRITEPATH . "uploads/tender_evaluation_findings/tender_" . $tender_id . "/evaluation_" . $evaluation_id . "/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $saved_files = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $original_name = $file->getClientName();
            if (function_exists("is_valid_file_to_upload") && !is_valid_file_to_upload($original_name)) {
                continue;
            }

            $extension = $file->getExtension() ?: pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid("tef_", true) . ($extension ? "." . $extension : "");
            $file->move($upload_dir, $new_name);

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_evaluation_findings/tender_" . $tender_id . "/evaluation_" . $evaluation_id . "/" . $new_name,
                "original_name" => $original_name,
                "mime_type" => $file->getClientMimeType(),
                "size_bytes" => $file->getSize(),
            ];
        }

        $this->Tender_evaluation_attachments_model->save_attachments($evaluation_id, $tender_id, $bid_id, $saved_files, (int) $this->login_user->id);
    }

    private function _save_clarification_files(int $communication_id, int $tender_id, ?int $vendor_id): void
    {
        $files = method_exists($this->request, "getFileMultiple")
            ? ($this->request->getFileMultiple("clarification_files") ?: [])
            : (($this->request->getFiles()["clarification_files"] ?? []) ?: []);

        if (!$files) {
            return;
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $upload_dir = WRITEPATH . "uploads/tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }

        $saved_files = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $original_name = $file->getClientName();
            if (function_exists("is_valid_file_to_upload") && !is_valid_file_to_upload($original_name)) {
                continue;
            }

            $extension = $file->getExtension() ?: pathinfo($original_name, PATHINFO_EXTENSION);
            $new_name = uniqid("tc_", true) . ($extension ? "." . $extension : "");
            $file->move($upload_dir, $new_name);

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/" . $new_name,
                "original_name" => $original_name,
                "mime_type" => $file->getClientMimeType(),
                "size_bytes" => $file->getSize(),
            ];
        }

        $this->Tender_communications_model->save_attachments($communication_id, $tender_id, $vendor_id, $saved_files, (int) $this->login_user->id);
    }

    private function _make_row($row)
{
    $stage = trim((string) ($row->workflow_stage ?? "technical"));
    $stage_label = ucwords(str_replace("_", " ", $stage));
    $status = "<span class='badge bg-info text-dark'>" . esc($stage_label) . "</span>";

    $review = anchor(
        get_uri("tender_technical_inbox/details/" . (int) $row->id),
        "<i data-feather='eye' class='icon-16'></i>",
        [
            "title" => "Open Technical Evaluation Page",
            "class" => "edit"
        ]
    );

    return [
        esc($row->reference ?? "-"),
        esc($row->title ?? "-"),
        esc($row->tender_type ?? "-"),
        $status,
        !empty($row->technical_end_at) ? format_to_datetime($row->technical_end_at) : (!empty($row->closing_at) ? format_to_datetime($row->closing_at) : "-"),
        (int) ($row->submitted_bids_count ?? 0),
        $review
    ];
}
}
