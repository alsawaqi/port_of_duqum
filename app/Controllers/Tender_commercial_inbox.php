<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_bid_item_prices_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_evaluation_attachments_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tenders_model;
use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;

class Tender_commercial_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_bid_item_prices_model;
    protected $Tender_communications_model;
    protected $Tender_documents_model;
    protected $Tender_evaluation_attachments_model;
    protected $Tender_evaluations_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_bid_item_prices_model = new Tender_bid_item_prices_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_documents_model = new Tender_documents_model();
        $this->Tender_evaluation_attachments_model = new Tender_evaluation_attachments_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("commercial_eval", "view");
        return $this->template->rander("tender_commercial_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("commercial_eval", "view");

        $list = $this->Tender_bids_model->get_unlocked_tenders_for_commercial_user((int) $this->login_user->id);

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function details($id = 0)
    {
        $this->access_only_tender("commercial_eval", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("tender_commercial_inbox");
        }

        $tender_documents = $this->Tender_documents_model->get_details(["tender_id" => $tender_id])->getResult();
        $bids = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, (int) $this->login_user->id);

        $pending_bids = [];
        $my_finalized_bids = [];
        $locked_bids = [];
        $approved_count = 0;
        $rejected_count = 0;

        foreach ($bids as $bid) {
            $decision = strtolower(trim((string) ($bid->commercial_decision ?? "")));

            if ($decision === "accepted") {
                $approved_count++;
            } elseif ($decision === "rejected") {
                $rejected_count++;
            }

            if ($decision === "") {
                $pending_bids[] = $bid;
            } elseif ((int) ($bid->decision_evaluator_id ?? 0) === (int) $this->login_user->id) {
                $my_finalized_bids[] = $bid;
            } else {
                $locked_bids[] = $bid;
            }
        }

        $pending_count = count($pending_bids);
        $ready_for_award = $pending_count === 0 && $approved_count === 1;

        return $this->template->rander("tender_commercial_inbox/details", [
            "tender"            => $tender,
            "tender_documents"  => $tender_documents,
            "pending_bids"      => $pending_bids,
            "my_finalized_bids" => $my_finalized_bids,
            "locked_bids"       => $locked_bids,
            "pending_count"     => $pending_count,
            "approved_count"    => $approved_count,
            "rejected_count"    => $rejected_count,
            "ready_for_award"   => $ready_for_award,
        ]);
    }

    function bid_modal_form()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric"
        ]);
        $this->access_only_tender("commercial_eval", "view");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, $user_id);
        if (!$tender) {
            show_404();
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $user_id);
        if (!$bid) {
            show_404();
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "commercial");
        $owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $latest_decision = strtolower(trim((string) ($latest_evaluation->decision ?? "")));

        $editable = false;
        if ($latest_decision === "") {
            $editable = true;
        } elseif ($owner_id === $user_id && !empty($latest_evaluation->id)) {
            $editable = true;
        }

        $active_evaluation = null;
        if ($editable && !empty($latest_evaluation->id) && $owner_id === $user_id) {
            $active_evaluation = $latest_evaluation;
        } elseif ($editable) {
            $active_evaluation = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator($tender_id, $bid_id, $user_id, "commercial");
        } else {
            $active_evaluation = $latest_evaluation;
        }

        $finding_attachments = [];
        if (!empty($active_evaluation->id)) {
            $grouped_attachments = $this->Tender_evaluation_attachments_model->get_grouped_by_evaluation_ids([(int) $active_evaluation->id]);
            $finding_attachments = get_array_value($grouped_attachments, (int) $active_evaluation->id) ?: [];
        }

        $internal_messages = $this->Tender_communications_model->get_internal_conversation($tender_id, "commercial", $bid_id);
        $internal_attachments = $this->Tender_communications_model->get_attachments_map(array_map(fn($message) => (int) $message->id, $internal_messages));

        return $this->template->view("tender_commercial_inbox/bid_modal_form", [
            "tender"            => $tender,
            "bid"               => $bid,
            "bid_item_prices"   => $this->Tender_bid_item_prices_model->get_bid_item_prices($bid_id),
            "active_evaluation" => $active_evaluation,
            "latest_evaluation" => $latest_evaluation,
            "finding_attachments" => $finding_attachments,
            "internal_messages" => $internal_messages,
            "internal_attachments" => $internal_attachments,
            "editable"          => $editable,
        ]);
    }

    function save_bid_evaluation()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric",
        ]);
        $this->access_only_tender("commercial_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $evaluator_id = (int) $this->login_user->id;

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, $evaluator_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to review it."
            ]);
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $evaluator_id);
        if (!$bid) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid not found or you do not have access to review it."
            ]);
        }

        $decision = strtolower(trim((string) $this->request->getPost("decision")));
        $evaluation_comment = trim((string) $this->request->getPost("evaluation_comment"));
        $commercial_score_raw = $this->request->getPost("commercial_score");

        if (!in_array($decision, ["accepted", "rejected"], true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please choose whether the bid is commercially approved or rejected."
            ]);
        }

        if ($commercial_score_raw === "" || $commercial_score_raw === null) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please enter the commercial score."
            ]);
        }

        if (!is_numeric($commercial_score_raw)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Commercial score must be a valid number."
            ]);
        }

        $commercial_score = round((float) $commercial_score_raw, 3);
        if ($commercial_score < 0 || $commercial_score > 100) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Commercial score must be between 0 and 100."
            ]);
        }

        $now = date("Y-m-d H:i:s");
        $late_review = $this->_late_review_metadata($tender, "commercial", $now);
        $db = db_connect();
        $db->transBegin();

        $fresh_bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $evaluator_id);
        if (!$fresh_bid) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid is no longer available."
            ]);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "commercial");
        $latest_owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $latest_decision = strtolower(trim((string) ($latest_evaluation->decision ?? "")));

        if ($latest_decision !== "" && $latest_owner_id !== $evaluator_id) {
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

        // Only one commercial winner is allowed.
        if ($decision === "accepted") {
            $overview = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, $evaluator_id);

            foreach ($overview as $row) {
                if ((int) $row->id === $bid_id) {
                    continue;
                }

                if (strtolower(trim((string) ($row->commercial_decision ?? ""))) === "accepted") {
                    $db->transRollback();
                    return $this->response->setJSON([
                        "success" => false,
                        "message" => "Another bid has already been commercially approved. Reject that bid first if you want to choose this vendor instead."
                    ]);
                }
            }
        }

        $existing = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator(
            $tender_id,
            $bid_id,
            $evaluator_id,
            "commercial"
        );
        $review_started_at = !empty($existing->review_started_at) ? (string) $existing->review_started_at : $late_review["review_started_at"];
        $review_duration_seconds = $this->_review_duration_seconds($review_started_at, $now);

        $evaluation_data = [
            "tender_id"     => $tender_id,
            "tender_bid_id" => $bid_id,
            "evaluator_id"  => $evaluator_id,
            "type"          => "commercial",
            "status"        => "submitted",
            "decision"      => $decision,
            "total_score"   => $commercial_score,
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

        if (!$evaluation_id) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        try {
            $this->_save_commercial_finding_files($evaluation_id, $tender_id, $bid_id);
        } catch (UploadSecurityException $e) {
            log_message('notice', 'Commercial finding attachment rejected.');
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        }
        $this->_record_evaluation_history($db, $tender, $fresh_bid, $evaluation_id, "commercial", $late_review, $decision, $commercial_score, $evaluation_comment, $now);

        $overview_after = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, $evaluator_id);
        $pending_count = 0;
        $approved_count = 0;
        $awarded_bid_id = 0;

        foreach ($overview_after as $row) {
            $row_decision = strtolower(trim((string) ($row->commercial_decision ?? "")));

            if ($row_decision === "") {
                $pending_count++;
            } elseif ($row_decision === "accepted") {
                $approved_count++;
                $awarded_bid_id = (int) $row->id;
            }
        }

        $message = "Commercial evaluation saved successfully.";
        $redirect_url = null;

        if ($pending_count === 0 && !$late_review["is_late"]) {
            $this->Tenders_model->ci_save([
                "workflow_stage" => "award_decision",
                "award_ready_at" => $now,
                "updated_at"     => $now,
            ], $tender_id);

            if ($approved_count === 1) {
                $message = "Commercial evaluation saved successfully. Commercial review is complete and the tender is now waiting for Procurement final award decision.";
            } else {
                $message = "Commercial evaluation saved successfully. Commercial review is complete, but no valid winner is available. Procurement must cancel or retender.";
            }
            $redirect_url = get_uri("tender_commercial_inbox");
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $db->transCommit();

        $response = [
            "success" => true,
            "message" => $late_review["is_late"] ? "Late commercial evaluation saved and sent to procurement for review." : $message
        ];
        
        if ($redirect_url) {
            $response["redirect_url"] = $redirect_url;
        }
        
        return $this->response->setJSON($response);
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
        $this->access_only_tender("commercial_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;
        $message = trim((string) $this->request->getPost("message"));
        $subject = trim((string) $this->request->getPost("subject"));

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, $user_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to request clarification."
            ]);
        }

        $bid = null;
        if ($bid_id > 0) {
            $bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $user_id);
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
            "type" => "commercial_clarification_request",
            "clarification_scope" => "commercial",
            "internal_audience" => "commercial",
            "subject" => $subject ?: ($bid ? ("Commercial clarification request - " . ($bid->vendor_name ?? "Vendor")) : "General commercial clarification request"),
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

        try {
            $this->_save_clarification_files((int) $saved, $tender_id, $bid ? (int) ($bid->vendor_id ?? 0) : null);
        } catch (UploadSecurityException $e) {
            log_message('notice', 'Commercial clarification attachment rejected.');
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'message' => app_lang('invalid_file_type'),
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Clarification request sent to procurement.",
            "redirect_url" => get_uri("tender_commercial_inbox/details/" . $tender_id),
        ]);
    }

    public function preview_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->template->view(
            "tender_commercial_inbox/file_preview",
            $this->_make_file_preview_data($context["doc"], get_uri("tender_commercial_inbox/view_tender_document/" . (int) $id))
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
            "tender_commercial_inbox/file_preview",
            $this->_make_file_preview_data($context["doc"], get_uri("tender_commercial_inbox/view_bid_document/" . (int) $id))
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

    public function download_finding_document($id = 0)
    {
        $this->access_only_tender("commercial_eval", "view");
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_evaluation_attachments_model->get_attachment($id);
        if (!$attachment || (string) ($attachment->evaluation_type ?? "") !== "commercial") {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user((int) $attachment->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = $this->_resolve_tender_upload_path((string)$attachment->path, 'tender_evaluation_findings');
        if (!$full_path) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($attachment->original_name ?: basename($full_path));
    }

    public function download_clarification_attachment($id = 0)
    {
        $this->access_only_tender("commercial_eval", "view");
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_communications_model->get_attachment($id);
        if (
            !$attachment
            || (int) ($attachment->is_vendor_visible ?? 0) === 1
            || (string) ($attachment->internal_audience ?? "") !== "commercial"
        ) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user((int) $attachment->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = $this->_resolve_tender_upload_path((string)$attachment->path, 'tender_clarifications');
        if (!$full_path) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($attachment->original_name ?: basename($full_path));
    }

    private function _get_accessible_tender_document_context(int $id): array
    {
        $this->access_only_tender("commercial_eval", "view");

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user((int) $doc->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = $this->_resolve_tender_source_path($doc);
        if (!$full_path) {
            show_404();
        }

        return ["doc" => $doc, "full_path" => $full_path];
    }

    private function _get_accessible_bid_document_context(int $id): array
    {
        $this->access_only_tender("commercial_eval", "view");

        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        if (!in_array((string) ($doc->section ?? ""), ["technical", "commercial", "commercial_priced", "commercial_unpriced", "bank_guarantee"], true)) {
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

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user((int) $bid->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = $this->_resolve_tender_upload_path((string)$doc->path, 'tender_bids');
        if (!$full_path) {
            show_404();
        }

        return ["doc" => $doc, "full_path" => $full_path];
    }

    private function _resolve_tender_upload_path(string $relative, string $prefix): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $prefix = trim($prefix, '/') . '/';
        if (!str_starts_with($relative, $prefix)) {
            return null;
        }
        $root = realpath(WRITEPATH . 'uploads/' . rtrim($prefix, '/'));
        $candidate = realpath(WRITEPATH . 'uploads/' . $relative);
        if (!$root || !$candidate || !is_file($candidate)) {
            return null;
        }
        $root = strtolower(rtrim(str_replace('\\', '/', $root), '/') . '/');
        return str_starts_with(strtolower(str_replace('\\', '/', $candidate)), $root) ? $candidate : null;
    }

    private function _resolve_tender_source_path($doc): ?string
    {
        $relative = ltrim(str_replace('\\', '/', (string)($doc->path ?? '')), '/');
        return (new Upload_security())->resolveStoredFile($relative, 'tender_documents');
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
        $mime = function_exists("mime_content_type") ? mime_content_type($full_path) : "";
        $mime = strtolower((string) ($mime ?: "application/octet-stream"));
        $name = str_replace(["\r", "\n", '"'], "", (string) ($doc->original_name ?: basename($full_path)));
        $inline_mimes = [
            "application/pdf", "image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp",
            "video/mp4", "video/webm", "video/ogg", "audio/mpeg", "audio/ogg", "audio/wav", "text/plain",
        ];
        $inline = !$download && in_array($mime, $inline_mimes, true);

        $response = $this->response
            ->download($full_path, null)
            ->setFileName($name)
            ->setContentType($mime, "")
            ->setHeader("X-Content-Type-Options", "nosniff");

        return $inline ? $response->inline() : $response;
    }

    private function _save_commercial_finding_files(int $evaluation_id, int $tender_id, int $bid_id): void
    {
        $files = method_exists($this->request, "getFileMultiple")
            ? ($this->request->getFileMultiple("commercial_finding_files") ?: [])
            : (($this->request->getFiles()["commercial_finding_files"] ?? []) ?: []);

        if (!$files) {
            return;
        }

        if (!is_array($files)) {
            $files = [$files];
        }

        $upload_dir = WRITEPATH . "uploads/tender_evaluation_findings/tender_" . $tender_id . "/evaluation_" . $evaluation_id . "/";

        $saved_files = [];
        $security = new Upload_security();
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $stored = $security->storeUploadedFile(
                $file,
                $upload_dir,
                Upload_security::CONTEXT_SECURITY_DOCUMENT,
                'cef_'
            );
            $new_name = $stored['stored_name'];

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_evaluation_findings/tender_" . $tender_id . "/evaluation_" . $evaluation_id . "/" . $new_name,
                "original_name" => $stored['original_name'],
                "mime_type" => $stored['detected_mime'],
                "size_bytes" => $stored['size_bytes'],
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

        $saved_files = [];
        $security = new Upload_security();
        foreach ($files as $file) {
            if (!$file || !$file->isValid() || $file->hasMoved()) {
                continue;
            }

            $stored = $security->storeUploadedFile(
                $file,
                $upload_dir,
                Upload_security::CONTEXT_SECURITY_DOCUMENT,
                'tc_'
            );
            $new_name = $stored['stored_name'];

            $saved_files[] = [
                "disk" => "local",
                "path" => "tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/" . $new_name,
                "original_name" => $stored['original_name'],
                "mime_type" => $stored['detected_mime'],
                "size_bytes" => $stored['size_bytes'],
            ];
        }

        $this->Tender_communications_model->save_attachments($communication_id, $tender_id, $vendor_id, $saved_files, (int) $this->login_user->id);
    }

    private function _make_row($row)
    {
        $stage = trim((string) ($row->workflow_stage ?? "commercial"));
        $stage_label = ucwords(str_replace("_", " ", $stage));
        $status = "<span class='badge bg-info text-dark'>" . esc($stage_label) . "</span>";

        $review = anchor(
            get_uri("tender_commercial_inbox/details/" . (int) $row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            [
                "title" => "Open Commercial Evaluation Page",
                "class" => "edit"
            ]
        );

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            esc($row->tender_type ?? "-"),
            $status,
            !empty($row->commercial_end_at) ? format_to_datetime($row->commercial_end_at) : "-",
            (int) ($row->accepted_bids_count ?? 0),
            $review
        ];
    }
}
