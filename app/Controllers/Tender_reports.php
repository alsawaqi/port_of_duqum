<?php

namespace App\Controllers;

use App\Libraries\Upload_security;
use App\Libraries\UploadSecurityException;
use App\Libraries\Runtime_schema_guard;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_bid_openings_model;
use App\Models\Tender_communications_model;
use App\Models\Tender_documents_model;
use App\Models\Tender_evaluation_attachments_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_rfq_details_model;
use App\Models\Tender_rfq_items_model;
use App\Models\Tenders_model;
use CodeIgniter\HTTP\Files\UploadedFile;

class Tender_reports extends Security_Controller
{
    protected $db;
    protected $Tenders_model;
    protected $Tender_bid_documents_model;
    protected $Tender_bid_openings_model;
    protected $Tender_communications_model;
    protected $Tender_documents_model;
    protected $Tender_evaluation_attachments_model;
    protected $Tender_evaluations_model;
    protected $Tender_rfq_details_model;
    protected $Tender_rfq_items_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->db = db_connect();
        $this->Tenders_model = new Tenders_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_bid_openings_model = new Tender_bid_openings_model();
        $this->Tender_communications_model = new Tender_communications_model();
        $this->Tender_documents_model = new Tender_documents_model();
        $this->Tender_evaluation_attachments_model = new Tender_evaluation_attachments_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tender_rfq_details_model = new Tender_rfq_details_model();
        $this->Tender_rfq_items_model = new Tender_rfq_items_model();
        $this->_ensure_workflow_history_table();
    }

    public function index()
    {
        $this->_access_reports();
        return $this->template->rander("tender_reports/index");
    }

    public function list_data()
    {
        $this->_access_reports();

        $status = strtolower(trim((string) $this->request->getPost("status")));
        $type = strtolower(trim((string) $this->request->getPost("tender_type")));
        $stage = strtolower(trim((string) $this->request->getPost("workflow_stage")));

        $where = ["t.deleted = 0"];
        $params = [];

        if (in_array($status, ["draft", "published", "closed", "awarded", "cancelled"], true)) {
            $where[] = "t.status = ?";
            $params[] = $status;
        }

        if (in_array($type, ["open", "close"], true)) {
            $where[] = "t.tender_type = ?";
            $params[] = $type;
        }

        if (in_array($stage, ["bidding", "technical_3key", "technical", "commercial", "award_decision"], true)) {
            $where[] = "t.workflow_stage = ?";
            $params[] = $stage;
        }

        $t = $this->db->prefixTable("tenders");
        $req = $this->db->prefixTable("tender_requests");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $te = $this->db->prefixTable("tender_evaluations");
        $where[] = $this->tender_company_scope_sql(
            "COALESCE(t.company_id, req.company_id)",
            "reports",
            $params
        );

        $sql = "SELECT
                    t.id,
                    t.reference,
                    COALESCE(t.title, req.subject) AS title,
                    t.tender_type,
                    t.status,
                    t.workflow_stage,
                    t.published_at,
                    t.closing_at,
                    t.created_at,
                    t.updated_at,
                    company.name AS company_name,
                    department.name AS department_name,
                    COALESCE(invites.invited_count, 0) AS invited_count,
                    COALESCE(bids.submitted_count, 0) AS submitted_count,
                    COALESCE(bids.accepted_count, 0) AS technically_accepted_count,
                    COALESCE(bids.rejected_count, 0) AS technically_rejected_count,
                    COALESCE(commercial.commercial_finalized_count, 0) AS commercial_finalized_count
                FROM $t t
                LEFT JOIN $req req
                    ON req.id = t.tender_request_id
                   AND req.deleted = 0
                LEFT JOIN $companies company
                    ON company.id = COALESCE(t.company_id, req.company_id)
                   AND company.deleted = 0
                LEFT JOIN $departments department
                    ON department.id = COALESCE(t.department_id, req.department_id)
                   AND department.deleted = 0
                LEFT JOIN (
                    SELECT tender_id, COUNT(DISTINCT vendor_id) AS invited_count
                    FROM $tiv
                    WHERE deleted = 0
                    GROUP BY tender_id
                ) invites
                    ON invites.tender_id = t.id
                LEFT JOIN (
                    SELECT
                        tender_id,
                        COUNT(DISTINCT id) AS submitted_count,
                        SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count
                    FROM $tb
                    WHERE deleted = 0
                      AND status <> 'draft'
                    GROUP BY tender_id
                ) bids
                    ON bids.tender_id = t.id
                LEFT JOIN (
                    SELECT tender_id, COUNT(DISTINCT tender_bid_id) AS commercial_finalized_count
                    FROM $te
                    WHERE deleted = 0
                      AND type = 'commercial'
                      AND status = 'submitted'
                    GROUP BY tender_id
                ) commercial
                    ON commercial.tender_id = t.id
                WHERE " . implode(" AND ", $where) . "
                ORDER BY COALESCE(t.created_at, t.published_at) DESC, t.id DESC";

        $rows = $this->db->query($sql, $params)->getResult();
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->_make_register_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function details($id = 0)
    {
        $this->_access_reports();

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }
        $this->require_tender_scope($tender_id, "reports");

        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            show_404();
        }

        $vendors = $this->_get_vendor_participation($tender_id);
        $communications = $this->_get_communications($tender_id);
        $can_update_procurement = $this->can_tender("procurement", "update");

        return $this->template->rander("tender_reports/details", [
            "tender" => $tender,
            "summary" => $this->_get_summary($tender_id),
            "timeline" => $this->_get_stage_timeline($tender),
            "teams" => $this->_get_team_members($tender_id),
            "vendors" => $vendors,
            "weighted_evaluation_scores" => $this->_get_weighted_evaluation_scores($tender, $vendors),
            "technical_evaluations" => $this->_get_evaluations($tender_id, "technical"),
            "technical_evaluation_attachments" => $this->Tender_evaluation_attachments_model->get_by_tender_grouped_by_evaluation_id($tender_id, "technical"),
            "commercial_evaluations" => $this->_get_evaluations($tender_id, "commercial"),
            "commercial_evaluation_attachments" => $this->Tender_evaluation_attachments_model->get_by_tender_grouped_by_evaluation_id($tender_id, "commercial"),
            "communications" => $communications,
            "communication_attachments" => $this->Tender_communications_model->get_attachments_map(array_map(fn($item) => (int) $item->id, $communications)),
            "extensions" => $this->_get_extensions($tender_id),
            "workflow_history" => $this->_get_workflow_history($tender_id),
            "opening_audit" => $this->_get_opening_audit($tender_id),
            "opening_session" => $this->Tender_bid_openings_model->get_active_session($tender_id, "technical"),
            "opening_signatures" => $this->_get_opening_signatures($tender_id),
            "proposal_review" => $this->_get_latest_proposal_review($tender_id),
            "tender_documents" => $this->_get_tender_documents($tender_id),
            "document_access" => $this->_get_document_access_map($tender),
            "can_override_workflow" => $can_update_procurement,
            "can_reply_clarifications" => $can_update_procurement,
            "rfq_detail" => $this->Tender_rfq_details_model->get_by_tender($tender_id),
            "rfq_items" => $this->Tender_rfq_items_model->get_by_tender($tender_id),
        ]);
    }

    public function preview_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);

        return $this->template->view(
            "tender_procurement_manager_inbox/file_preview",
            $this->_make_tender_document_preview_data(
                $context["doc"],
                $context["full_path"],
                get_uri("tender_reports/view_tender_document/" . (int) $id)
            )
        );
    }

    public function view_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->_serve_tender_document_file($context["doc"], $context["full_path"], false);
    }

    public function download_tender_document($id = 0)
    {
        $context = $this->_get_accessible_tender_document_context((int) $id);
        return $this->_serve_tender_document_file($context["doc"], $context["full_path"], true);
    }

    public function save_update()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "subject" => "required",
            "message" => "required",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $this->require_tender_scope($tender_id, "reports");
        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if ((string) ($tender->status ?? "") === "cancelled") {
            return $this->response->setJSON(["success" => false, "message" => "Cancelled tenders cannot receive vendor updates."]);
        }

        try {
            $update_files = $this->_collect_update_files();
            $security = new Upload_security();
            foreach ($update_files as $update_file) {
                $security->validateUploadedFile($update_file, Upload_security::CONTEXT_GENERIC);
            }
        } catch (UploadSecurityException $e) {
            log_message("notice", "Tender report update attachment rejected.");
            return $this->response->setStatusCode(422)->setJSON([
                "success" => false,
                "message" => "One or more attachments could not be accepted.",
            ]);
        }

        $type = strtolower(trim((string) $this->request->getPost("update_type")));
        if (!in_array($type, ["circular", "addendum"], true)) {
            $type = "circular";
        }

        $subject = trim((string) $this->request->getPost("subject"));
        $message = trim((string) $this->request->getPost("message"));
        if ($subject === "" || $message === "") {
            return $this->response->setJSON(["success" => false, "message" => "Subject and message are required."]);
        }

        $now = date("Y-m-d H:i:s");
        $this->db->transBegin();
        $saved = $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => $tender_id,
            "vendor_id" => null,
            "parent_id" => null,
            "type" => $type,
            "subject" => $subject,
            "message" => $message,
            "sent_to_all" => 1,
            "is_vendor_visible" => 1,
            "published_at" => $now,
            "created_by" => $this->login_user->id,
            "status" => "published",
            "created_at" => $now,
            "deleted" => 0,
        ]));

        if (!$saved) {
            $this->db->transRollback();
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        $stored_paths = [];
        try {
            $stored_paths = $this->_save_update_files((int) $saved, $tender_id, $update_files);
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Tender update attachment persistence failed.");
            }
            $this->db->transCommit();
        } catch (UploadSecurityException $e) {
            $this->db->transRollback();
            $this->_remove_stored_files($stored_paths);
            log_message("notice", "Tender report update attachment rejected during storage.");
            return $this->response->setStatusCode(422)->setJSON([
                "success" => false,
                "message" => "One or more attachments could not be accepted.",
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->_remove_stored_files($stored_paths);
            log_message("error", "Tender report update attachment storage failed.");
            return $this->response->setStatusCode(500)->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => ucwords($type) . " published to vendor portal.",
            "redirect_url" => get_uri("tender_reports/details/" . $tender_id . "#tender-report-communications"),
        ]);
    }

    public function download_update_attachment($id = 0)
    {
        $this->_access_reports();

        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_communications_model->get_attachment($id);
        if (!$attachment) {
            show_404();
        }
        $this->require_tender_scope((int) $attachment->tender_id, "reports");

        if (!$this->_get_tender_report((int) $attachment->tender_id)) {
            app_redirect("forbidden");
        }

        if ((string) ($attachment->disk ?? "local") !== "local") {
            show_404();
        }
        $path_suffix = "tender_" . (int) $attachment->tender_id
            . "/communication_" . (int) $attachment->communication_id;
        $security = new Upload_security();
        $full_path = $security->resolveStoredFile((string) $attachment->path, "tender_clarifications/" . $path_suffix)
            ?: $security->resolveStoredFile((string) $attachment->path, "tender_updates/" . $path_suffix);
        if (!$full_path) {
            show_404();
        }

        return $this->response
            ->download($full_path, null)
            ->setFileName($attachment->original_name ?: basename($full_path))
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setHeader("Cache-Control", "private, no-store");
    }

    /** @return UploadedFile[] */
    private function _collect_update_files(): array
    {
        $files = method_exists($this->request, "getFileMultiple")
            ? ($this->request->getFileMultiple("update_files") ?: [])
            : (($this->request->getFiles()["update_files"] ?? []) ?: []);

        if (!$files) {
            return [];
        }

        if (!is_array($files)) {
            $files = [$files];
        }
        if (count($files) > 10) {
            throw new UploadSecurityException("Too many update attachments were submitted.");
        }

        $collected = [];
        $total_size = 0;
        foreach ($files as $file) {
            if ($file instanceof UploadedFile && $file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->hasMoved()) {
                throw new UploadSecurityException("An update attachment is invalid or incomplete.");
            }
            $total_size += (int) $file->getSize();
            if ($total_size > 50 * 1024 * 1024) {
                throw new UploadSecurityException("The combined update attachments are too large.");
            }
            $collected[] = $file;
        }

        return $collected;
    }

    /** @param UploadedFile[] $files @return string[] absolute paths */
    private function _save_update_files(int $communication_id, int $tender_id, array $files): array
    {
        if (!$files) {
            return [];
        }

        // Global updates share the protected communications attachment root so
        // the existing vendor-authorized download route can serve them.
        $relative_dir = "tender_clarifications/tender_" . $tender_id . "/communication_" . $communication_id . "/";
        $upload_dir = WRITEPATH . "uploads/" . $relative_dir;

        $saved_files = [];
        $stored_paths = [];
        $security = new Upload_security();
        try {
            foreach ($files as $file) {
                $stored = $security->storeUploadedFile(
                    $file,
                    $upload_dir,
                    Upload_security::CONTEXT_GENERIC,
                    "tu_"
                );
                $stored_paths[] = $stored["path"];
                $saved_files[] = [
                    "disk" => "local",
                    "path" => $relative_dir . $stored["stored_name"],
                    "original_name" => $stored["original_name"],
                    "mime_type" => $stored["detected_mime"],
                    "size_bytes" => $stored["size_bytes"],
                ];
            }

            $this->Tender_communications_model->save_attachments((int) $communication_id, $tender_id, null, $saved_files, (int) $this->login_user->id);
        } catch (\Throwable $e) {
            $this->_remove_stored_files($stored_paths);
            throw $e;
        }

        return $stored_paths;
    }

    private function _remove_stored_files(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public function approve_vendor_participation()
    {
        return $this->_save_vendor_participation_decision("approved");
    }

    public function reject_vendor_participation()
    {
        return $this->_save_vendor_participation_decision("rejected");
    }

    private function _save_vendor_participation_decision(string $decision)
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "vendor_id" => "required|numeric",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $vendor_id = (int) $this->request->getPost("vendor_id");
        $this->require_tender_scope($tender_id, "reports");
        $tender = $this->_get_tender_report($tender_id);

        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if ((string) ($tender->status ?? "") === "cancelled") {
            return $this->response->setJSON(["success" => false, "message" => "Cancelled tenders cannot receive participation decisions."]);
        }

        $target_status = $decision === "approved" ? "approved" : "rejected";
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $now = date("Y-m-d H:i:s");

        $this->db->query(
            "UPDATE $tiv
             SET invite_status=?,
                 invited_by=?,
                 invited_at=COALESCE(invited_at, ?)
             WHERE tender_id=?
               AND vendor_id=?
               AND deleted=0
               AND invite_status='pending_approval'",
            [$target_status, $this->login_user->id, $now, $tender_id, $vendor_id]
        );

        if ((int) $this->db->affectedRows() < 1) {
            $existing = $this->db->query(
                "SELECT invite_status
                 FROM $tiv
                 WHERE tender_id=? AND vendor_id=? AND deleted=0
                 LIMIT 1",
                [$tender_id, $vendor_id]
            )->getRow();

            if ($existing && strtolower((string) $existing->invite_status) === $target_status) {
                return $this->response->setJSON([
                    "success" => true,
                    "message" => "Vendor participation is already " . $target_status . ".",
                    "redirect_url" => get_uri("tender_reports/details/" . $tender_id . "#tender-report-vendors"),
                ]);
            }

            return $this->response->setJSON([
                "success" => false,
                "message" => "Only pending approval requests can be " . $target_status . "."
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Vendor participation " . $target_status . ".",
            "redirect_url" => get_uri("tender_reports/details/" . $tender_id . "#tender-report-vendors"),
        ]);
    }

    public function save_stage_override()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "workflow_stage" => "required",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $this->require_tender_scope($tender_id, "reports");
        $target_stage = $this->_normalize_workflow_stage($this->request->getPost("workflow_stage"));
        $reason = trim((string) $this->request->getPost("reason"));
        $open_until = $this->_normalize_override_until($this->request->getPost("open_until"));

        if (!$target_stage) {
            return $this->response->setJSON(["success" => false, "message" => "Select a valid workflow stage."]);
        }

        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if ((string) ($tender->status ?? "") === "cancelled") {
            return $this->response->setJSON(["success" => false, "message" => "Cancelled tenders cannot be reopened by workflow override."]);
        }

        $bid_counts = $this->_get_bid_counts($tender_id);
        if ($target_stage !== "bidding" && (int) ($bid_counts["total_count"] ?? 0) < 1) {
            return $this->response->setJSON(["success" => false, "message" => "At least one submitted bid is required before opening post-submission stages."]);
        }

        if ($target_stage === "commercial" && (int) ($bid_counts["accepted_count"] ?? 0) < 1) {
            return $this->response->setJSON(["success" => false, "message" => "At least one technically accepted bid is required before opening the commercial stages."]);
        }

        $now = date("Y-m-d H:i:s");
        $payload = [
            "workflow_stage" => $target_stage,
            "updated_at" => $now,
        ];

        $this->db->transStart();

        switch ($target_stage) {
            case "bidding":
                $payload["status"] = "published";
                $payload["closing_at"] = $open_until;
                break;

            case "technical_3key":
                $payload["status"] = "closed";
                $payload["bid_opening_at"] = $open_until;
                break;

            case "technical":
                $payload["status"] = "closed";
                $payload["technical_start_at"] = $now;
                $payload["technical_end_at"] = $open_until;
                $payload["technical_eval_deadline"] = $open_until;
                $payload["technical_locked_at"] = null;
                break;

            case "commercial":
                $payload["status"] = "closed";
                $payload["commercial_unlocked_at"] = $now;
                $payload["commercial_start_at"] = $now;
                $payload["commercial_end_at"] = $open_until;
                $payload["commercial_eval_deadline"] = $open_until;
                $payload["award_ready_at"] = null;
                break;

            case "award_decision":
                $payload["status"] = "closed";
                $payload["award_ready_at"] = $now;
                break;
        }

        $alignment = $this->_align_stage_schedule($tender, $target_stage, $payload);
        $payload = $alignment["payload"];
        $adjustments = $alignment["adjustments"];

        $this->Tenders_model->ci_save($this->_build_stage_override_manager_payload([
            "workflow_stage" => $target_stage,
            "open_until" => $open_until,
            "reason" => $reason,
            "tender_fields" => $payload,
            "adjustments" => $adjustments,
            "from_status" => (string) ($tender->status ?? ""),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "requested_at" => $now,
        ], $now), $tender_id);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->response->setJSON(["success" => false, "message" => "Database error."]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Workflow stage change submitted for procurement manager approval: " . $this->_workflow_stage_label($target_stage) . ".",
            "redirect_url" => get_uri("tender_reports/details/" . $tender_id),
        ]);
    }

    public function approve_late_evaluation()
    {
        return $this->_save_late_evaluation_review("accepted");
    }

    public function reject_late_evaluation()
    {
        return $this->_save_late_evaluation_review("rejected");
    }

    private function _save_late_evaluation_review(string $decision)
    {
        $this->validate_submitted_data([
            "evaluation_id" => "required|numeric",
            "comment" => "permit_empty",
        ]);
        $this->access_only_tender("procurement", "update");

        $evaluation_id = (int) $this->request->getPost("evaluation_id");
        $comment = trim((string) $this->request->getPost("comment"));
        $evaluation = $this->_get_late_evaluation_for_review($evaluation_id);
        if (!$evaluation) {
            return $this->response->setJSON(["success" => false, "message" => "Late evaluation not found."]);
        }
        $this->require_tender_scope((int) $evaluation->tender_id, "reports");

        if ((int) ($evaluation->submitted_after_deadline ?? 0) !== 1) {
            return $this->response->setJSON(["success" => false, "message" => "Only late evaluations require procurement review."]);
        }

        $current_status = (string) ($evaluation->late_review_status ?? "");
        if ($current_status !== "" && $current_status !== "pending") {
            return $this->response->setJSON(["success" => false, "message" => "This late evaluation has already been reviewed."]);
        }

        $now = date("Y-m-d H:i:s");
        $te = $this->db->prefixTable("tender_evaluations");
        $tb = $this->db->prefixTable("tender_bids");

        $this->db->transStart();
        $this->db->table($te)
            ->where("id", $evaluation_id)
            ->where("tender_id", (int) $evaluation->tender_id)
            ->where("tender_bid_id", (int) $evaluation->tender_bid_id)
            ->where("deleted", 0)
            ->update(clean_data([
                "late_review_status" => $decision,
                "late_reviewed_by" => (int) $this->login_user->id,
                "late_reviewed_at" => $now,
                "late_review_comment" => $comment ?: null,
                "updated_at" => $now,
            ]));

        if ($decision === "accepted" && (string) ($evaluation->type ?? "") === "technical" && in_array((string) ($evaluation->decision ?? ""), ["accepted", "rejected"], true)) {
            $this->db->table($tb)
                ->where("id", (int) $evaluation->tender_bid_id)
                ->where("tender_id", (int) $evaluation->tender_id)
                ->where("deleted", 0)
                ->update(clean_data([
                    "status" => (string) $evaluation->decision,
                    "updated_at" => $now,
                ]));
        }

        $this->_record_late_evaluation_review_history($evaluation, $decision, $comment, $now);
        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->response->setJSON(["success" => false, "message" => "Database error."]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Late evaluation " . ($decision === "accepted" ? "accepted" : "rejected") . ".",
            "redirect_url" => get_uri("tender_reports/details/" . (int) $evaluation->tender_id . "#tender-report-evaluations"),
        ]);
    }

    public function save_manual_bid_opening_form()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $this->require_tender_scope($tender_id, "reports");
        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if ((string) ($tender->status ?? "") !== "closed" || (string) ($tender->workflow_stage ?? "") !== "technical_3key") {
            return $this->response->setJSON(["success" => false, "message" => "Manual opening forms can only be accepted during the 3-key bid opening stage."]);
        }

        $file = $this->request->getFile("manual_bid_opening_form");
        if (!$file instanceof UploadedFile || !$file->isValid() || $file->hasMoved()) {
            return $this->response->setJSON(["success" => false, "message" => "Upload the signed bid opening form."]);
        }

        $relative_dir = "tender_opening_forms/tender_" . $tender_id . "/";
        try {
            $stored = (new Upload_security())->storeUploadedFile(
                $file,
                WRITEPATH . "uploads/" . $relative_dir,
                Upload_security::CONTEXT_SECURITY_DOCUMENT,
                "opening_form_",
                ["pdf", "jpg", "jpeg", "png"]
            );
        } catch (UploadSecurityException $e) {
            log_message("notice", "Manual tender opening form rejected.");
            return $this->response->setStatusCode(422)->setJSON([
                "success" => false,
                "message" => "The signed opening form could not be accepted.",
            ]);
        }

        $previous_session = $this->Tender_bid_openings_model->get_active_session($tender_id, "technical");
        $path = $relative_dir . $stored["stored_name"];
        $this->db->transBegin();
        try {
            $opening_id = $this->Tender_bid_openings_model->mark_manual_form_accepted(
                $tender_id,
                (int) $this->login_user->id,
                $path,
                $stored["original_name"]
            );
            if (!$opening_id || $this->db->transStatus() === false) {
                throw new \RuntimeException("Manual tender opening form persistence failed.");
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->_remove_stored_files([$stored["path"]]);
            log_message("error", "Manual tender opening form could not be recorded.");
            return $this->response->setStatusCode(500)->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $previous_path = (string) ($previous_session->manual_form_path ?? "");
        if ($previous_path !== "" && $previous_path !== $path) {
            $previous_full_path = (new Upload_security())->resolveStoredFile(
                $previous_path,
                "tender_opening_forms/tender_" . $tender_id
            );
            if ($previous_full_path) {
                @unlink($previous_full_path);
            }
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Manual bid opening form uploaded. Procurement can now start technical review.",
            "redirect_url" => get_uri("tender_reports/details/" . $tender_id . "#tender-report-audit"),
        ]);
    }

    public function download_manual_bid_opening_form($id = 0)
    {
        $this->_access_reports();
        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }
        $this->require_tender_scope($tender_id, "reports");
        if (!$this->_get_tender_report($tender_id)) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, "technical");
        if (!$session || empty($session->manual_form_path)) {
            show_404();
        }
        $full_path = (new Upload_security())->resolveStoredFile(
            (string) $session->manual_form_path,
            "tender_opening_forms/tender_" . $tender_id
        );
        if (!$full_path) {
            show_404();
        }

        $name = str_replace(["\r", "\n", chr(34)], "", (string) ($session->manual_form_original_name ?: basename($full_path)));
        return $this->response
            ->download($full_path, null)
            ->setFileName($name)
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setHeader("Cache-Control", "private, no-store");
    }

    public function start_technical_review()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
        ]);
        $this->access_only_tender("procurement", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $this->require_tender_scope($tender_id, "reports");
        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "Tender not found."]);
        }

        if ((string) ($tender->status ?? "") !== "closed" || (string) ($tender->workflow_stage ?? "") !== "technical_3key") {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not waiting for bid opening completion."]);
        }

        $session = $this->Tender_bid_openings_model->get_completed_session_for_technical_start($tender_id);
        if (!$session) {
            return $this->response->setJSON(["success" => false, "message" => "Complete committee signatures or upload a manual signed opening form first."]);
        }

        $generated_form_confirmed = (int) $this->request->getPost("generated_bid_opening_form_confirmed") === 1;
        $manual_opening_form = (string) ($session->status ?? "") === "manual_accepted";
        $technical_proposals_reviewed = (int) $this->request->getPost("technical_proposals_reviewed") === 1 || $generated_form_confirmed || $manual_opening_form;
        $commercial_proposals_reviewed = (int) $this->request->getPost("commercial_proposals_reviewed") === 1 || $generated_form_confirmed || $manual_opening_form;

        if (!$generated_form_confirmed && !$manual_opening_form) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Procurement must confirm the generated bid opening form before sending the tender for technical evaluation."
            ]);
        }

        if (!$technical_proposals_reviewed || !$commercial_proposals_reviewed) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Procurement must confirm both technical and commercial proposal review before sending the bids for evaluation."
            ]);
        }

        $review_note = trim((string) $this->request->getPost("proposal_review_note"));
        $technical_end_at = $this->_normalize_override_until($this->request->getPost("technical_end_at"));
        $now = date("Y-m-d H:i:s");

        $this->db->transBegin();
        $this->_record_procurement_proposal_review($tender, $session, $review_note, $now, $generated_form_confirmed);
        $started = $this->Tenders_model->start_technical_review_after_opening($tender_id, (int) $this->login_user->id, $technical_end_at);

        if (!$started) {
            $this->db->transRollback();
            return $this->response->setJSON(["success" => false, "message" => "Technical review could not be started."]);
        }

        $this->db->transCommit();
        if ($this->db->transStatus() === false) {
            return $this->response->setJSON(["success" => false, "message" => "Database error."]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Procurement proposal review recorded. The technical team can now evaluate the bids.",
            "redirect_url" => get_uri("tender_reports/details/" . $tender_id),
        ]);
    }

    public function bid_opening_form($id = 0, $stage = "technical")
    {
        $this->_access_reports();

        $tender_id = (int) $id;
        $stage = "technical";
        if (!$tender_id) {
            show_404();
        }
        $this->require_tender_scope($tender_id, "reports");

        $tender = $this->_get_tender_report($tender_id);
        if (!$tender) {
            show_404();
        }

        return $this->template->rander("tender_reports/bid_opening_form", [
            "tender" => $tender,
            "stage" => $stage,
            "vendors" => $this->_get_vendor_participation($tender_id),
            "teams" => $this->_get_team_members($tender_id),
            "opening_audit" => $this->_get_opening_audit($tender_id),
            "opening_session" => $this->Tender_bid_openings_model->get_active_session($tender_id, "technical"),
            "signature_rows" => $this->_get_opening_signatures($tender_id),
            "signature_image_route" => "tender_reports/signature_image",
            "manual_form_download_url" => get_uri("tender_reports/download_manual_bid_opening_form/" . $tender_id),
        ]);
    }

    public function signature_image($id = 0)
    {
        $this->_access_reports();
        $context = $this->_get_opening_signature_context((int) $id);
        if (!$context || !$this->_get_tender_report((int) $context->tender_id)) {
            show_404();
        }
        $this->require_tender_scope((int) $context->tender_id, "reports");

        $full_path = (new Upload_security())->resolveStoredFile(
            (string) $context->signature_image_path,
            "tender_opening_signatures/opening_" . (int) $context->opening_id
        );
        $info = $full_path ? @getimagesize($full_path) : false;
        if (!$full_path || !is_array($info) || (int) ($info[2] ?? 0) !== IMAGETYPE_PNG) {
            show_404();
        }

        return $this->response
            ->download($full_path, null)
            ->setFileName("signature.png")
            ->setContentType("image/png")
            ->setHeader("X-Content-Type-Options", "nosniff")
            ->setHeader("Cache-Control", "private, no-store")
            ->inline();
    }

    public function download_bid_document($id = 0)
    {
        $this->_access_reports();
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $doc = $this->_get_bid_document_with_tender($id);
        if (!$doc) {
            show_404();
        }
        $this->require_tender_scope((int) $doc->tender_id, "reports");

        $access = $this->_get_document_access_map($doc);
        $section = (string) ($doc->section ?? "");
        if (empty($access[$section])) {
            app_redirect("forbidden");
        }

        $expected_path = "tender_bids/tender_" . (int) $doc->tender_id
            . "/vendor_" . (int) $doc->vendor_id;
        $full_path = (new Upload_security())->resolveStoredFile((string) $doc->path, $expected_path);
        if (!$full_path) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($doc->original_name ?: basename($full_path));
    }

    public function download_evaluation_attachment($id = 0)
    {
        $this->_access_reports();
        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $attachment = $this->Tender_evaluation_attachments_model->get_attachment($id);
        if (!$attachment) {
            show_404();
        }
        $evaluation = $this->Tender_evaluations_model->get_one((int) ($attachment->tender_evaluation_id ?? 0));
        if (
            !$evaluation
            || (int) ($evaluation->deleted ?? 0) === 1
            || !in_array(strtolower((string) ($evaluation->type ?? "")), ["technical", "commercial"], true)
            || (int) ($evaluation->tender_id ?? 0) !== (int) ($attachment->tender_id ?? 0)
            || (int) ($evaluation->tender_bid_id ?? 0) !== (int) ($attachment->tender_bid_id ?? 0)
            || !$this->_evaluation_bid_matches_tender(
                (int) ($evaluation->tender_bid_id ?? 0),
                (int) ($evaluation->tender_id ?? 0)
            )
        ) {
            show_404();
        }
        $this->require_tender_scope((int) $attachment->tender_id, "reports");

        $expected_path = "tender_evaluation_findings/tender_" . (int) $attachment->tender_id
            . "/evaluation_" . (int) $attachment->tender_evaluation_id;
        $full_path = (new Upload_security())->resolveStoredFile((string) $attachment->path, $expected_path);
        if (!$full_path) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($attachment->original_name ?: basename($full_path));
    }

    private function _get_accessible_tender_document_context(int $id): array
    {
        $this->_access_reports();
        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1 || !$this->_get_tender_report((int) ($doc->tender_id ?? 0))) {
            show_404();
        }
        $this->require_tender_scope((int) $doc->tender_id, "reports");

        $full_path = (new Upload_security())->resolveStoredFile(
            (string) ($doc->path ?? ""),
            "tender_documents"
        );
        if (!$full_path) {
            show_404();
        }

        return ["doc" => $doc, "full_path" => $full_path];
    }

    private function _evaluation_bid_matches_tender(int $bid_id, int $tender_id): bool
    {
        if (!$bid_id || !$tender_id) {
            return false;
        }

        $tb = $this->db->prefixTable("tender_bids");
        return (bool) $this->db->query(
            "SELECT id
             FROM $tb
             WHERE id=?
               AND tender_id=?
               AND deleted=0
             LIMIT 1",
            [$bid_id, $tender_id]
        )->getRow();
    }

    private function _make_tender_document_preview_data($doc, string $full_path, string $file_url): array
    {
        $mime = function_exists("mime_content_type") ? mime_content_type($full_path) : "";
        $mime = strtolower((string) ($mime ?: "application/octet-stream"));
        $image_mimes = ["image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp"];

        return [
            "file_url" => $file_url,
            "is_image_file" => in_array($mime, $image_mimes, true),
            "is_iframe_preview_available" => $mime === "application/pdf",
            "is_google_preview_available" => false,
            "is_viewable_video_file" => false,
            "is_google_drive_file" => false,
        ];
    }

    private function _serve_tender_document_file($doc, string $full_path, bool $download)
    {
        $mime = function_exists("mime_content_type") ? mime_content_type($full_path) : "";
        $mime = $mime ?: "application/octet-stream";
        $name = str_replace(["\r", "\n", chr(34)], "", (string) ($doc->original_name ?: basename($full_path)));
        $inline_mimes = ["application/pdf", "image/jpeg", "image/png", "image/gif", "image/webp", "image/bmp"];
        $inline = !$download && in_array(strtolower($mime), $inline_mimes, true);

        $response = $this->response
            ->download($full_path, null)
            ->setFileName($name)
            ->setContentType($mime, "")
            ->setHeader("X-Content-Type-Options", "nosniff");

        return $inline ? $response->inline() : $response;
    }

    private function _access_reports(): void
    {
        if (
            !$this->can_tender("procurement", "view")
            && !$this->can_tender("procurement_manager_inbox", "view")
        ) {
            app_redirect("forbidden");
            exit;
        }
    }

    private function _ensure_workflow_history_table(): void
    {
        Runtime_schema_guard::requireTablesAndColumns($this->db, [
            "tender_workflow_history" => [
                "id", "tender_id", "action_type", "from_status", "to_status", "from_stage",
                "to_stage", "open_until", "reason", "details", "created_by", "created_at", "deleted",
            ],
        ], "tender reports workflow history");
    }

    private function _workflow_stage_options(): array
    {
        return [
            "bidding" => "Bid Submission / Bidding",
            "technical_3key" => "Bid Opening",
            "technical" => "Technical Evaluation",
            "commercial" => "Commercial Evaluation",
            "award_decision" => "Award Decision",
        ];
    }

    private function _workflow_stage_label(string $stage): string
    {
        $options = $this->_workflow_stage_options();
        return $options[$stage] ?? ucwords(str_replace("_", " ", $stage));
    }

    private function _normalize_workflow_stage($stage): ?string
    {
        $stage = strtolower(trim((string) $stage));
        return array_key_exists($stage, $this->_workflow_stage_options()) ? $stage : null;
    }

    private function _normalize_override_until($value): string
    {
        $value = trim((string) $value);
        $timestamp = $value !== "" ? strtotime($value) : false;
        if (!$timestamp || $timestamp <= time()) {
            $timestamp = strtotime("+1 day");
        }

        return date("Y-m-d H:i:s", $timestamp);
    }

    private function _get_bid_counts(int $tender_id): array
    {
        $tb = $this->db->prefixTable("tender_bids");
        $row = $this->db->query(
            "SELECT
                COUNT(DISTINCT id) AS total_count,
                SUM(CASE WHEN status='submitted' THEN 1 ELSE 0 END) AS submitted_count,
                SUM(CASE WHEN status='accepted' THEN 1 ELSE 0 END) AS accepted_count
             FROM $tb
             WHERE deleted=0
               AND tender_id=?
               AND status <> 'draft'",
            [$tender_id]
        )->getRowArray();

        return $row ?: ["total_count" => 0, "submitted_count" => 0, "accepted_count" => 0];
    }

    private function _build_stage_override_manager_payload(array $payload, string $now): array
    {
        return [
            "procurement_manager_status" => "pending",
            "procurement_manager_action" => "stage_override",
            "procurement_manager_payload" => json_encode($payload),
            "procurement_manager_submitted_by" => (int) $this->login_user->id,
            "procurement_manager_submitted_at" => $now,
            "procurement_manager_reviewed_by" => null,
            "procurement_manager_reviewed_at" => null,
            "procurement_manager_comment" => null,
            "updated_at" => $now,
        ];
    }

    private function _expire_opening_sessions(int $tender_id, string $stage): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");
        $now = date("Y-m-d H:i:s");

        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status IN ('codes_generated', 'unlocked')",
            [$now, $tender_id, $stage]
        );
    }

    private function _ensure_override_opening(int $tender_id, string $stage, string $now): void
    {
        $tbo = $this->db->prefixTable("tender_bid_openings");

        $existing = $this->db->query(
            "SELECT id
             FROM $tbo
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status='unlocked'
             ORDER BY id DESC
             LIMIT 1",
            [$tender_id, $stage]
        )->getRow();

        if ($existing) {
            return;
        }

        $this->db->query(
            "UPDATE $tbo
             SET status='expired',
                 updated_at=?
             WHERE deleted=0
               AND tender_id=?
               AND stage=?
               AND status='codes_generated'",
            [$now, $tender_id, $stage]
        );

        $this->db->query(
            "INSERT INTO $tbo
                (tender_id, stage, status, chairman_code, secretary_code, member_code, generated_by, generated_at, expires_at, unlocked_at, created_at, updated_at, deleted)
             VALUES
                (?, ?, 'unlocked', ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)",
            [
                $tender_id,
                $stage,
                "OVERRIDE",
                "OVERRIDE",
                "OVERRIDE",
                $this->login_user->id,
                $now,
                $now,
                $now,
                $now,
                $now,
            ]
        );
    }

    private function _stage_date_label(string $field): string
    {
        $labels = [
            "closing_at" => "Submission Deadline",
            "bid_opening_at" => "Technical 3-Key Opening Deadline",
            "technical_start_at" => "Technical Evaluation Start",
            "technical_end_at" => "Technical Evaluation End",
            "technical_eval_deadline" => "Technical Evaluation Deadline",
            "technical_locked_at" => "Technical Lock",
            "commercial_unlocked_at" => "Commercial Unlock",
            "commercial_start_at" => "Commercial Evaluation Start",
            "commercial_end_at" => "Commercial Evaluation End",
            "commercial_eval_deadline" => "Commercial Evaluation Deadline",
            "award_ready_at" => "Award Decision Ready",
        ];

        return $labels[$field] ?? ucwords(str_replace("_", " ", $field));
    }

    private function _stage_date_value($tender, array $payload, string $field): ?string
    {
        if (array_key_exists($field, $payload)) {
            return $payload[$field] ?: null;
        }

        return !empty($tender->{$field}) ? (string) $tender->{$field} : null;
    }

    private function _push_stage_field_after($tender, array &$payload, array &$adjustments, string $field, string $after, int $gap_minutes = 5): string
    {
        $minimum = date("Y-m-d H:i:s", strtotime($after . " +" . $gap_minutes . " minutes"));
        $current = $this->_stage_date_value($tender, $payload, $field);

        if (!$current || strtotime((string) $current) <= strtotime($after)) {
            $payload[$field] = $minimum;
            $adjustments[] = $this->_stage_date_label($field) . " adjusted to " . date("Y-m-d H:i", strtotime($minimum));
            return $minimum;
        }

        return date("Y-m-d H:i:s", strtotime((string) $current));
    }

    private function _align_stage_schedule($tender, string $target_stage, array $payload): array
    {
        $adjustments = [];

        if (in_array($target_stage, ["bidding", "technical_3key"], true)) {
            $closing_at = $this->_stage_date_value($tender, $payload, "closing_at");
            if ($closing_at) {
                $bid_opening_at = $this->_push_stage_field_after($tender, $payload, $adjustments, "bid_opening_at", $closing_at);
            } else {
                $bid_opening_at = $this->_stage_date_value($tender, $payload, "bid_opening_at");
            }
        } else {
            $bid_opening_at = $this->_stage_date_value($tender, $payload, "bid_opening_at");
        }

        if (in_array($target_stage, ["bidding", "technical_3key", "technical"], true)) {
            if (!$bid_opening_at) {
                $bid_opening_at = date("Y-m-d H:i:s");
            }

            $technical_end_at = $this->_push_stage_field_after($tender, $payload, $adjustments, "technical_eval_deadline", $bid_opening_at);
            $payload["technical_end_at"] = $technical_end_at;

            $commercial_unlocked_at = $this->_stage_date_value($tender, $payload, "commercial_unlocked_at") ?: $bid_opening_at;
            $payload["commercial_unlocked_at"] = $commercial_unlocked_at;
            $commercial_start_at = $this->_push_stage_field_after($tender, $payload, $adjustments, "commercial_start_at", $technical_end_at);
            $commercial_end_at = $this->_push_stage_field_after($tender, $payload, $adjustments, "commercial_eval_deadline", $commercial_start_at);
            $payload["commercial_end_at"] = $commercial_end_at;
            $this->_push_stage_field_after($tender, $payload, $adjustments, "award_ready_at", $commercial_end_at);
        } elseif ($target_stage === "commercial") {
            $commercial_end_at = $this->_stage_date_value($tender, $payload, "commercial_eval_deadline") ?: $this->_stage_date_value($tender, $payload, "commercial_end_at");
            if ($commercial_end_at) {
                $payload["commercial_end_at"] = $commercial_end_at;
                $this->_push_stage_field_after($tender, $payload, $adjustments, "award_ready_at", $commercial_end_at);
            }
        }

        return [
            "payload" => $payload,
            "adjustments" => $adjustments,
        ];
    }

    private function _workflow_history_details($tender, array $payload, array $adjustments): string
    {
        $fields = [
            "closing_at",
            "bid_opening_at",
            "technical_start_at",
            "technical_end_at",
            "technical_eval_deadline",
            "technical_locked_at",
            "commercial_unlocked_at",
            "commercial_start_at",
            "commercial_end_at",
            "commercial_eval_deadline",
            "award_ready_at",
        ];

        $lines = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $old = !empty($tender->{$field}) ? date("Y-m-d H:i", strtotime((string) $tender->{$field})) : "-";
            $new = !empty($payload[$field]) ? date("Y-m-d H:i", strtotime((string) $payload[$field])) : "-";
            if ($old !== $new) {
                $lines[] = $this->_stage_date_label($field) . ": " . $old . " to " . $new;
            }
        }

        if ($adjustments) {
            $lines[] = "Automatic downstream alignment:";
            foreach ($adjustments as $adjustment) {
                $lines[] = "- " . $adjustment;
            }
        }

        return implode("\n", $lines);
    }

    private function _record_workflow_history($tender, array $payload, string $target_stage, string $open_until, string $reason, array $adjustments, string $now): void
    {
        $table = $this->db->prefixTable("tender_workflow_history");

        $this->db->table($table)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "stage_override",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($payload["status"] ?? ($tender->status ?? "")),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => $target_stage,
            "open_until" => $open_until,
            "reason" => $reason ?: null,
            "details" => $this->_workflow_history_details($tender, $payload, $adjustments) ?: null,
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _record_stage_override($tender, string $target_stage, string $open_until, string $reason, string $now, array $adjustments = []): void
    {
        $from = $this->_workflow_stage_label((string) ($tender->workflow_stage ?? ""));
        $to = $this->_workflow_stage_label($target_stage);
        $message = "Workflow stage manually opened by procurement.\n";
        $message .= "From: " . $from . "\n";
        $message .= "To: " . $to . "\n";

        if (in_array($target_stage, ["bidding", "technical", "commercial"], true)) {
            $message .= "Open until: " . date("Y-m-d H:i", strtotime($open_until)) . "\n";
        }

        if ($reason !== "") {
            $message .= "Reason: " . $reason . "\n";
        }

        if ($adjustments) {
            $message .= "Downstream schedule alignment:\n" . implode("\n", $adjustments);
        }

        $this->Tender_communications_model->ci_save(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "vendor_id" => null,
            "parent_id" => null,
            "type" => "circular",
            "subject" => "Internal Workflow Override - " . ($tender->reference ?? "Tender"),
            "message" => $message,
            "sent_to_all" => 0,
            "is_vendor_visible" => 0,
            "published_at" => $now,
            "created_by" => $this->login_user->id,
            "status" => "internal",
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _record_procurement_proposal_review($tender, $opening_session, string $review_note, string $now, bool $generated_form_confirmed = false): void
    {
        $table = $this->db->prefixTable("tender_workflow_history");
        $details = [
            "Procurement reviewed the opened technical and commercial proposal documents before releasing bids to the concerned evaluation department.",
            "Bid opening status: " . ucwords(str_replace("_", " ", (string) ($opening_session->status ?? "completed"))),
        ];

        if ($generated_form_confirmed) {
            $details[] = "Generated bid opening form confirmed by procurement.";
        }

        if (!empty($opening_session->signed_at)) {
            $details[] = "Committee signed at: " . date("Y-m-d H:i", strtotime((string) $opening_session->signed_at));
        }

        if (!empty($opening_session->manual_form_uploaded_at)) {
            $details[] = "Manual opening form uploaded at: " . date("Y-m-d H:i", strtotime((string) $opening_session->manual_form_uploaded_at));
        }

        $this->db->table($table)->insert(clean_data([
            "tender_id" => (int) ($tender->id ?? 0),
            "action_type" => "procurement_proposal_review",
            "from_status" => (string) ($tender->status ?? ""),
            "to_status" => (string) ($tender->status ?? ""),
            "from_stage" => (string) ($tender->workflow_stage ?? ""),
            "to_stage" => (string) ($tender->workflow_stage ?? ""),
            "open_until" => null,
            "reason" => $review_note ?: null,
            "details" => implode("\n", $details),
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _make_register_row($row): array
    {
        $view = anchor(
            get_uri("tender_reports/details/" . (int) $row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["title" => "View Tender Register", "class" => "btn btn-default btn-sm"]
        );

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            esc($row->company_name ?? "-"),
            esc($row->department_name ?? "-"),
            strtoupper(esc($row->tender_type ?? "open")),
            $this->_status_badge((string) ($row->status ?? "")),
            $this->_stage_badge((string) ($row->workflow_stage ?? "")),
            (int) ($row->invited_count ?? 0),
            (int) ($row->submitted_count ?? 0),
            !empty($row->closing_at) ? format_to_datetime($row->closing_at) : "-",
            $view,
        ];
    }

    private function _get_tender_report(int $tender_id)
    {
        $t = $this->db->prefixTable("tenders");
        $req = $this->db->prefixTable("tender_requests");
        $companies = $this->db->prefixTable("companies");
        $departments = $this->db->prefixTable("departments");
        $vendors = $this->db->prefixTable("vendors");
        $users = $this->db->prefixTable("users");
        $scope_params = [];
        $company_scope = $this->tender_company_scope_sql(
            "COALESCE(t.company_id, req.company_id)",
            "reports",
            $scope_params
        );

        return $this->db->query(
            "SELECT
                t.*,
                req.reference AS request_reference,
                req.request_date,
                req.budget_omr,
                COALESCE(t.tender_fee, req.tender_fee) AS tender_fee,
                req.announcement,
                COALESCE(t.evaluation_method, req.evaluation_method) AS evaluation_method,
                COALESCE(t.technical_weight, req.technical_weight) AS technical_weight,
                COALESCE(t.commercial_weight, req.commercial_weight) AS commercial_weight,
                req.estimated_previous_amount,
                req.estimated_previous_notes,
                company.name AS company_name,
                department.name AS department_name,
                award_vendor.vendor_name AS award_vendor_name,
                TRIM(CONCAT(COALESCE(creator.first_name, ''), ' ', COALESCE(creator.last_name, ''))) AS created_by_name
             FROM $t t
             LEFT JOIN $req req
                ON req.id = t.tender_request_id
               AND req.deleted = 0
             LEFT JOIN $companies company
                ON company.id = COALESCE(t.company_id, req.company_id)
               AND company.deleted = 0
             LEFT JOIN $departments department
                ON department.id = COALESCE(t.department_id, req.department_id)
               AND department.deleted = 0
             LEFT JOIN $vendors award_vendor
                ON award_vendor.id = t.award_vendor_id
               AND award_vendor.deleted = 0
             LEFT JOIN $users creator
                ON creator.id = t.created_by
             WHERE t.deleted = 0
               AND t.id = ?
               AND $company_scope
             LIMIT 1",
            array_merge([$tender_id], $scope_params)
        )->getRow();
    }

    private function _get_summary(int $tender_id): array
    {
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $te = $this->db->prefixTable("tender_evaluations");
        $tc = $this->db->prefixTable("tender_communications");

        $row = $this->db->query(
            "SELECT
                (SELECT COUNT(DISTINCT vendor_id) FROM $tiv WHERE deleted = 0 AND tender_id = ?) AS invited_count,
                (SELECT COUNT(DISTINCT id) FROM $tb WHERE deleted = 0 AND tender_id = ? AND status <> 'draft') AS submitted_count,
                (SELECT COUNT(DISTINCT id) FROM $tb WHERE deleted = 0 AND tender_id = ? AND status = 'accepted') AS technical_accepted_count,
                (SELECT COUNT(DISTINCT id) FROM $tb WHERE deleted = 0 AND tender_id = ? AND status = 'rejected') AS technical_rejected_count,
                (SELECT COUNT(DISTINCT tender_bid_id) FROM $te WHERE deleted = 0 AND tender_id = ? AND type = 'technical' AND status = 'submitted') AS technical_evaluation_count,
                (SELECT COUNT(DISTINCT tender_bid_id) FROM $te WHERE deleted = 0 AND tender_id = ? AND type = 'commercial' AND status = 'submitted') AS commercial_evaluation_count,
                (SELECT COUNT(DISTINCT id) FROM $tc WHERE deleted = 0 AND tender_id = ?) AS communication_count",
            [$tender_id, $tender_id, $tender_id, $tender_id, $tender_id, $tender_id, $tender_id]
        )->getRowArray();

        return $row ?: [];
    }

    private function _get_team_members(int $tender_id): array
    {
        $ttm = $this->db->prefixTable("tender_team_members");
        $users = $this->db->prefixTable("users");

        $rows = $this->db->query(
            "SELECT
                $ttm.team_role,
                $users.id AS user_id,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS full_name,
                $users.email
             FROM $ttm
             INNER JOIN $users
                ON $users.id = $ttm.user_id
               AND $users.deleted = 0
             WHERE $ttm.deleted = 0
               AND $ttm.is_active = 1
               AND $ttm.tender_id = ?
             ORDER BY $ttm.team_role ASC, $users.first_name ASC",
            [$tender_id]
        )->getResult();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->team_role][] = $row;
        }

        return $grouped;
    }

    private function _get_tender_documents(int $tender_id): array
    {
        $td = $this->db->prefixTable("tender_documents");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $td.*,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS uploaded_by_name
             FROM $td
             LEFT JOIN $users ON $users.id = $td.uploaded_by
             WHERE $td.deleted = 0
               AND $td.tender_id = ?
             ORDER BY $td.created_at DESC, $td.id DESC",
            [$tender_id]
        )->getResult();
    }

    private function _get_vendor_participation(int $tender_id): array
    {
        $tender_id = (int) $tender_id;
        $tiv = $this->db->prefixTable("tender_invited_vendors");
        $tb = $this->db->prefixTable("tender_bids");
        $vendors = $this->db->prefixTable("vendors");
        $tbd = $this->db->prefixTable("tender_bid_documents");
        $te = $this->db->prefixTable("tender_evaluations");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                src.vendor_id,
                $vendors.vendor_name,
                $vendors.email,
                invite.invite_status,
                invite.invited_at,
                bid.id AS bid_id,
                bid.status AS bid_status,
                bid.submitted_at,
                bid.total_amount,
                bid.currency,
                docs.technical_doc_id,
                docs.commercial_unpriced_doc_id,
                docs.commercial_priced_doc_id,
                docs.commercial_legacy_doc_id,
                docs.bank_guarantee_doc_id,
                tech_eval.decision AS technical_decision,
                tech_eval.total_score AS technical_score,
                tech_eval.comments AS technical_comments,
                tech_eval.submitted_at AS technical_submitted_at,
                TRIM(CONCAT(COALESCE(tech_user.first_name, ''), ' ', COALESCE(tech_user.last_name, ''))) AS technical_evaluator_name,
                comm_eval.decision AS commercial_decision,
                comm_eval.total_score AS commercial_score,
                comm_eval.comments AS commercial_comments,
                comm_eval.submitted_at AS commercial_submitted_at,
                TRIM(CONCAT(COALESCE(comm_user.first_name, ''), ' ', COALESCE(comm_user.last_name, ''))) AS commercial_evaluator_name
             FROM (
                SELECT vendor_id FROM $tiv WHERE deleted = 0 AND tender_id = $tender_id
                UNION
                SELECT vendor_id FROM $tb WHERE deleted = 0 AND tender_id = $tender_id
             ) src
             INNER JOIN $vendors
                ON $vendors.id = src.vendor_id
               AND $vendors.deleted = 0
             LEFT JOIN $tiv invite
                ON invite.tender_id = $tender_id
               AND invite.vendor_id = src.vendor_id
               AND invite.deleted = 0
             LEFT JOIN (
                SELECT vendor_id, MAX(id) AS max_id
                FROM $tb
                WHERE deleted = 0
                  AND tender_id = $tender_id
                GROUP BY vendor_id
             ) latest_bid
                ON latest_bid.vendor_id = src.vendor_id
             LEFT JOIN $tb bid
                ON bid.id = latest_bid.max_id
             LEFT JOIN (
                SELECT
                    tender_bid_id,
                    MAX(CASE WHEN section = 'technical' THEN id ELSE NULL END) AS technical_doc_id,
                    MAX(CASE WHEN section = 'commercial_unpriced' THEN id ELSE NULL END) AS commercial_unpriced_doc_id,
                    MAX(CASE WHEN section = 'commercial_priced' THEN id ELSE NULL END) AS commercial_priced_doc_id,
                    MAX(CASE WHEN section = 'commercial' THEN id ELSE NULL END) AS commercial_legacy_doc_id,
                    MAX(CASE WHEN section = 'bank_guarantee' THEN id ELSE NULL END) AS bank_guarantee_doc_id
                FROM $tbd
                WHERE deleted = 0
                GROUP BY tender_bid_id
             ) docs
                ON docs.tender_bid_id = bid.id
             LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = 'technical'
                  AND (submitted_after_deadline = 0 OR late_review_status = 'accepted')
                GROUP BY tender_bid_id
             ) latest_tech
                ON latest_tech.tender_bid_id = bid.id
             LEFT JOIN $te tech_eval
                ON tech_eval.id = latest_tech.max_id
             LEFT JOIN $users tech_user
                ON tech_user.id = tech_eval.evaluator_id
             LEFT JOIN (
                SELECT tender_bid_id, MAX(id) AS max_id
                FROM $te
                WHERE deleted = 0
                  AND type = 'commercial'
                  AND (submitted_after_deadline = 0 OR late_review_status = 'accepted')
                GROUP BY tender_bid_id
             ) latest_comm
                ON latest_comm.tender_bid_id = bid.id
             LEFT JOIN $te comm_eval
                ON comm_eval.id = latest_comm.max_id
             LEFT JOIN $users comm_user
                ON comm_user.id = comm_eval.evaluator_id
             ORDER BY $vendors.vendor_name ASC"
        )->getResult();
    }

    private function _get_weighted_evaluation_scores($tender, array $vendors): array
    {
        $technical_weight = $this->_weight_value($tender->technical_weight ?? null, 70);
        $commercial_weight = $this->_weight_value($tender->commercial_weight ?? null, 30);

        if (($technical_weight + $commercial_weight) <= 0) {
            $technical_weight = 70.0;
            $commercial_weight = 30.0;
        }

        $technical_max = $this->_stage_score_max((int) ($tender->id ?? 0), "technical");
        $commercial_max = 100.0;
        $rows = [];

        foreach ($vendors as $vendor) {
            if (empty($vendor->bid_id)) {
                continue;
            }

            $technical_score = $this->_nullable_float($vendor->technical_score ?? null);
            $commercial_score = $this->_nullable_float($vendor->commercial_score ?? null);

            $technical_percent = $this->_score_percent($technical_score, $technical_max);
            $commercial_percent = $this->_score_percent($commercial_score, $commercial_max);
            $technical_weighted = $this->_weighted_score_points($technical_score, $technical_max, $technical_weight);
            $commercial_weighted = $this->_weighted_score_points($commercial_score, $commercial_max, $commercial_weight);
            $is_complete = $technical_weighted !== null && $commercial_weighted !== null;

            $rows[] = [
                "vendor_id" => (int) ($vendor->vendor_id ?? 0),
                "bid_id" => (int) ($vendor->bid_id ?? 0),
                "vendor_name" => (string) ($vendor->vendor_name ?? "-"),
                "technical_score" => $technical_score,
                "technical_score_max" => $technical_max,
                "technical_percent" => $technical_percent,
                "technical_weight" => $technical_weight,
                "technical_weighted" => $technical_weighted,
                "technical_decision" => (string) ($vendor->technical_decision ?? ""),
                "commercial_score" => $commercial_score,
                "commercial_score_max" => $commercial_max,
                "commercial_percent" => $commercial_percent,
                "commercial_weight" => $commercial_weight,
                "commercial_weighted" => $commercial_weighted,
                "commercial_decision" => (string) ($vendor->commercial_decision ?? ""),
                "final_weighted_score" => $is_complete ? round($technical_weighted + $commercial_weighted, 3) : null,
                "max_weighted_score" => round($technical_weight + $commercial_weight, 3),
                "is_complete" => $is_complete,
            ];
        }

        usort($rows, function ($a, $b) {
            if ((bool) $a["is_complete"] !== (bool) $b["is_complete"]) {
                return (bool) $a["is_complete"] ? -1 : 1;
            }

            $a_score = $a["final_weighted_score"];
            $b_score = $b["final_weighted_score"];
            if ($a_score !== $b_score) {
                return ((float) $b_score <=> (float) $a_score);
            }

            return strcasecmp((string) $a["vendor_name"], (string) $b["vendor_name"]);
        });

        return $rows;
    }

    private function _stage_score_max(int $tender_id, string $type): float
    {
        $criteria = $this->db->prefixTable("tender_criteria");
        $row = $this->db->query(
            "SELECT SUM(weight) AS max_score
             FROM $criteria
             WHERE deleted=0
               AND tender_id=?
               AND type=?",
            [$tender_id, $type]
        )->getRow();

        $max = (float) ($row->max_score ?? 0);
        return $max > 0 ? $max : 100.0;
    }

    private function _weighted_score_points(?float $score, float $score_max, float $weight): ?float
    {
        $percent = $this->_score_percent($score, $score_max);
        if ($percent === null) {
            return null;
        }

        return round(($percent / 100) * $weight, 3);
    }

    private function _score_percent(?float $score, float $score_max): ?float
    {
        if ($score === null || $score_max <= 0) {
            return null;
        }

        return round(max(0, min(100, ($score / $score_max) * 100)), 3);
    }

    private function _nullable_float($value): ?float
    {
        if ($value === null || $value === "") {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function _weight_value($value, float $fallback): float
    {
        if ($value === null || $value === "" || !is_numeric($value)) {
            return $fallback;
        }

        return max(0, (float) $value);
    }

    private function _get_evaluations(int $tender_id, string $type): array
    {
        $te = $this->db->prefixTable("tender_evaluations");
        $tb = $this->db->prefixTable("tender_bids");
        $vendors = $this->db->prefixTable("vendors");
        $users = $this->db->prefixTable("users");
        $scores = $this->db->prefixTable("tender_evaluation_scores");
        $criteria = $this->db->prefixTable("tender_criteria");

        return $this->db->query(
            "SELECT
                $te.id,
                $te.tender_bid_id,
                $te.type,
                $te.status,
                $te.decision,
                $te.total_score,
                $te.comments,
                $te.review_started_at,
                $te.review_duration_seconds,
                $te.deadline_at,
                $te.submitted_after_deadline,
                $te.late_review_status,
                $te.late_reviewed_by,
                $te.late_reviewed_at,
                $te.late_review_comment,
                $te.submitted_at,
                $vendors.vendor_name,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS evaluator_name,
                GROUP_CONCAT(
                    DISTINCT CONCAT($criteria.name, ': ', FORMAT($scores.score, 3))
                    ORDER BY $criteria.sort_order ASC
                    SEPARATOR ' | '
                ) AS score_breakdown
             FROM $te
             INNER JOIN $tb
                ON $tb.id = $te.tender_bid_id
               AND $tb.deleted = 0
               AND $tb.tender_id = $te.tender_id
             INNER JOIN $vendors
                ON $vendors.id = $tb.vendor_id
               AND $vendors.deleted = 0
             LEFT JOIN $users
                ON $users.id = $te.evaluator_id
             LEFT JOIN $scores
                ON $scores.tender_evaluation_id = $te.id
               AND $scores.deleted = 0
             LEFT JOIN $criteria
                ON $criteria.id = $scores.tender_criterion_id
               AND $criteria.deleted = 0
             WHERE $te.deleted = 0
               AND $te.tender_id = ?
               AND $te.type = ?
             GROUP BY
                $te.id,
                $te.tender_bid_id,
                $te.type,
                $te.status,
                $te.decision,
                $te.total_score,
                $te.comments,
                $te.review_started_at,
                $te.review_duration_seconds,
                $te.deadline_at,
                $te.submitted_after_deadline,
                $te.late_review_status,
                $te.late_reviewed_by,
                $te.late_reviewed_at,
                $te.late_review_comment,
                $te.submitted_at,
                $vendors.vendor_name,
                evaluator_name
             ORDER BY $te.submitted_at DESC, $te.id DESC",
            [$tender_id, $type]
        )->getResult();
    }

    private function _get_late_evaluation_for_review(int $evaluation_id)
    {
        $te = $this->db->prefixTable("tender_evaluations");
        $tb = $this->db->prefixTable("tender_bids");
        $t = $this->db->prefixTable("tenders");
        $vendors = $this->db->prefixTable("vendors");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $te.*,
                $tb.vendor_id,
                $tb.status AS bid_status,
                $vendors.vendor_name,
                $t.reference,
                $t.title,
                $t.status AS tender_status,
                $t.workflow_stage,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS evaluator_name
             FROM $te
             INNER JOIN $tb
                ON $tb.id = $te.tender_bid_id
               AND $tb.deleted = 0
               AND $tb.tender_id = $te.tender_id
             INNER JOIN $t
                ON $t.id = $te.tender_id
               AND $t.deleted = 0
             LEFT JOIN $vendors
                ON $vendors.id = $tb.vendor_id
               AND $vendors.deleted = 0
             LEFT JOIN $users
                ON $users.id = $te.evaluator_id
             WHERE $te.deleted = 0
               AND $te.id = ?
             LIMIT 1",
            [$evaluation_id]
        )->getRow();
    }

    private function _record_late_evaluation_review_history($evaluation, string $decision, string $comment, string $now): void
    {
        $table = $this->db->prefixTable("tender_workflow_history");
        $details = [
            ucfirst((string) ($evaluation->type ?? "evaluation")) . " late evaluation reviewed by procurement.",
            "Vendor: " . ((string) ($evaluation->vendor_name ?? "-")),
            "Evaluator: " . (trim((string) ($evaluation->evaluator_name ?? "")) ?: "-"),
            "Late review decision: " . ucfirst($decision),
            "Evaluation decision: " . ucfirst((string) ($evaluation->decision ?? "-")),
        ];

        if ($comment !== "") {
            $details[] = "Procurement comment: " . $comment;
        }

        $this->db->table($table)->insert(clean_data([
            "tender_id" => (int) ($evaluation->tender_id ?? 0),
            "action_type" => "late_" . (string) ($evaluation->type ?? "evaluation") . "_review_" . $decision,
            "from_status" => (string) ($evaluation->tender_status ?? ""),
            "to_status" => (string) ($evaluation->tender_status ?? ""),
            "from_stage" => (string) ($evaluation->workflow_stage ?? ""),
            "to_stage" => (string) ($evaluation->workflow_stage ?? ""),
            "open_until" => $evaluation->deadline_at ?? null,
            "reason" => $comment ?: null,
            "details" => implode("\n", $details),
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "deleted" => 0,
        ]));
    }

    private function _get_communications(int $tender_id): array
    {
        $tc = $this->db->prefixTable("tender_communications");
        $vendors = $this->db->prefixTable("vendors");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $tc.*,
                $vendors.vendor_name,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS created_by_name
             FROM $tc
             LEFT JOIN $vendors
                ON $vendors.id = $tc.vendor_id
             LEFT JOIN $users
                ON $users.id = $tc.created_by
             WHERE $tc.deleted = 0
               AND $tc.tender_id = ?
             ORDER BY COALESCE($tc.published_at, $tc.created_at) DESC, $tc.id DESC",
            [$tender_id]
        )->getResult();
    }

    private function _get_extensions(int $tender_id): array
    {
        $ext = $this->db->prefixTable("tender_extensions");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $ext.*,
                TRIM(CONCAT(COALESCE(created.first_name, ''), ' ', COALESCE(created.last_name, ''))) AS created_by_name,
                TRIM(CONCAT(COALESCE(approved.first_name, ''), ' ', COALESCE(approved.last_name, ''))) AS approved_by_name
             FROM $ext
             LEFT JOIN $users created
                ON created.id = $ext.created_by
             LEFT JOIN $users approved
                ON approved.id = $ext.approved_by
             WHERE $ext.deleted = 0
               AND $ext.tender_id = ?
             ORDER BY $ext.created_at DESC, $ext.id DESC",
            [$tender_id]
        )->getResult();
    }

    private function _get_workflow_history(int $tender_id): array
    {
        $history = $this->db->prefixTable("tender_workflow_history");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $history.*,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS created_by_name,
                $users.email AS created_by_email
             FROM $history
             LEFT JOIN $users
                ON $users.id = $history.created_by
             WHERE $history.deleted = 0
               AND $history.tender_id = ?
             ORDER BY $history.created_at DESC, $history.id DESC",
            [$tender_id]
        )->getResult();
    }

    private function _get_latest_proposal_review(int $tender_id)
    {
        $history = $this->db->prefixTable("tender_workflow_history");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $history.*,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS created_by_name,
                $users.email AS created_by_email
             FROM $history
             LEFT JOIN $users
                ON $users.id = $history.created_by
             WHERE $history.deleted = 0
               AND $history.tender_id = ?
               AND $history.action_type = 'procurement_proposal_review'
             ORDER BY $history.created_at DESC, $history.id DESC
             LIMIT 1",
            [$tender_id]
        )->getRow();
    }

    private function _get_opening_audit(int $tender_id): array
    {
        $opening = $this->db->prefixTable("tender_bid_openings");
        $entry = $this->db->prefixTable("tender_bid_opening_entries");
        $users = $this->db->prefixTable("users");

        return $this->db->query(
            "SELECT
                $opening.id AS opening_id,
                $opening.stage AS opening_stage,
                $opening.status AS opening_status,
                $opening.generated_at,
                $opening.expires_at,
                $opening.unlocked_at,
                $opening.signed_at,
                $opening.manual_form_path,
                $opening.manual_form_original_name,
                $opening.manual_form_uploaded_at,
                $entry.role,
                $entry.is_valid,
                $entry.confirmed_at,
                $entry.signature_name,
                $entry.signature_statement,
                $entry.signed_at AS entry_signed_at,
                $entry.ip_address,
                TRIM(CONCAT(COALESCE($users.first_name, ''), ' ', COALESCE($users.last_name, ''))) AS member_name,
                $users.email AS member_email
             FROM $opening
             LEFT JOIN $entry
                ON $entry.tender_bid_opening_id = $opening.id
               AND $entry.deleted = 0
             LEFT JOIN $users
                ON $users.id = $entry.user_id
             WHERE $opening.deleted = 0
               AND $opening.tender_id = ?
             ORDER BY $opening.id DESC, $entry.confirmed_at ASC, $entry.id ASC",
            [$tender_id]
        )->getResult();
    }

    private function _get_opening_signatures(int $tender_id): array
    {
        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, "technical");
        if (!$session) {
            return [];
        }

        return $this->Tender_bid_openings_model->get_signature_rows((int) $session->id);
    }

    private function _get_opening_signature_context(int $entry_id)
    {
        if (!$entry_id) {
            return null;
        }

        $entries = $this->db->prefixTable("tender_bid_opening_entries");
        $openings = $this->db->prefixTable("tender_bid_openings");
        return $this->db->query(
            "SELECT
                $entries.id,
                $entries.signature_image_path,
                $openings.id AS opening_id,
                $openings.tender_id
             FROM $entries
             INNER JOIN $openings
                ON $openings.id = $entries.tender_bid_opening_id
               AND $openings.deleted = 0
             WHERE $entries.id = ?
               AND $entries.deleted = 0
               AND $entries.is_valid = 1
               AND $entries.signed_at IS NOT NULL
               AND $entries.signature_image_path IS NOT NULL
             LIMIT 1",
            [$entry_id]
        )->getRow();
    }

    private function _get_bid_document_with_tender(int $document_id)
    {
        $tbd = $this->db->prefixTable("tender_bid_documents");
        $tb = $this->db->prefixTable("tender_bids");
        $t = $this->db->prefixTable("tenders");

        return $this->db->query(
            "SELECT
                $tbd.*,
                $tb.tender_id,
                $tb.vendor_id,
                $t.status,
                $t.workflow_stage,
                $t.technical_start_at,
                $t.commercial_unlocked_at,
                $t.closing_at
             FROM $tbd
             INNER JOIN $tb
                ON $tb.id = $tbd.tender_bid_id
               AND $tb.deleted = 0
             INNER JOIN $t
                ON $t.id = $tb.tender_id
               AND $t.deleted = 0
             WHERE $tbd.deleted = 0
               AND $tbd.id = ?
             LIMIT 1",
            [$document_id]
        )->getRow();
    }

    private function _get_stage_timeline($tender): array
    {
        $rank = $this->_stage_rank((string) ($tender->workflow_stage ?? ""));
        $status = (string) ($tender->status ?? "");
        $now = time();

        $site_visit_status = empty($tender->site_visit_at)
            ? "pending"
            : (strtotime((string) $tender->site_visit_at) <= $now ? "completed" : "scheduled");

        return [
            $this->_timeline_item("Created", "Procurement tender draft created.", $tender->created_at ?? null, null, !empty($tender->created_at) ? "completed" : "pending"),
            $this->_timeline_item("Published", "Tender made visible to target vendors.", $tender->published_at ?? $tender->release_at ?? null, null, !empty($tender->published_at) ? "completed" : ($status === "draft" ? "pending" : "completed")),
            $this->_timeline_item("Site Visit", "Site visit notice/deadline captured for vendors.", $tender->site_visit_at ?? null, null, $site_visit_status),
            $this->_timeline_item("Clarifications", "Vendor questions and procurement responses window.", $tender->published_at ?? null, $tender->clarification_deadline ?? null, !empty($tender->clarification_deadline) && strtotime((string) $tender->clarification_deadline) <= $now ? "completed" : "scheduled"),
            $this->_timeline_item("Bid Submission", "Vendor bid upload window.", $tender->published_at ?? $tender->release_at ?? null, $tender->closing_at ?? null, in_array($status, ["closed", "awarded", "cancelled"], true) || $rank >= 1 ? "completed" : ($status === "published" ? "active" : "pending")),
            $this->_timeline_item("Bid Opening", "Technical and commercial bid packages unlock once chairman, secretary, and ITC member sign.", $tender->closing_at ?? null, $tender->technical_start_at ?? $tender->bid_opening_at ?? null, !empty($tender->technical_start_at) || $rank > 1 ? "completed" : ($rank === 1 ? "active" : "pending")),
            $this->_timeline_item("Technical Evaluation", "Technical team scoring and accept/reject decision.", $tender->technical_start_at ?? null, $tender->technical_locked_at ?? $tender->technical_end_at ?? null, $rank > 2 ? "completed" : ($rank === 2 ? "active" : "pending")),
            $this->_timeline_item("Commercial Evaluation", "Commercial scoring, pricing review, and shortlist.", $tender->commercial_start_at ?? null, $tender->award_ready_at ?? $tender->commercial_end_at ?? null, $rank > 4 || $status === "awarded" ? "completed" : ($rank === 4 ? "active" : "pending")),
            $this->_timeline_item("Award Decision", "Manual award decision and final status update.", $tender->award_ready_at ?? null, $tender->loa_issued_at ?? null, $status === "awarded" ? "completed" : ($rank === 5 ? "active" : ($status === "cancelled" ? "cancelled" : "pending"))),
        ];
    }

    private function _timeline_item(string $title, string $description, $start_at, $end_at, string $status): array
    {
        return [
            "title" => $title,
            "description" => $description,
            "start_at" => $start_at,
            "end_at" => $end_at,
            "status" => $status,
            "duration" => $this->_duration_label($start_at, $end_at),
        ];
    }

    private function _duration_label($start_at, $end_at): string
    {
        if (empty($start_at) || empty($end_at)) {
            return "-";
        }

        $start = strtotime((string) $start_at);
        $end = strtotime((string) $end_at);
        if (!$start || !$end || $end < $start) {
            return "-";
        }

        $minutes = (int) floor(($end - $start) / 60);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . "d";
        }
        if ($hours > 0) {
            $parts[] = $hours . "h";
        }
        if (!$parts && $mins > 0) {
            $parts[] = $mins . "m";
        }

        return $parts ? implode(" ", $parts) : "0m";
    }

    private function _get_document_access_map($tender): array
    {
        $tender_id = (int) ($tender->id ?? ($tender->tender_id ?? 0));
        $rank = $this->_stage_rank((string) ($tender->workflow_stage ?? ""));
        $status = (string) ($tender->status ?? "");
        $procurement_can_review_opened_bids = (
            $this->can_tender("procurement", "view")
            || $this->can_tender("procurement_manager_inbox", "view")
        ) && $this->_is_bid_opening_completed($tender_id);
        $technical_open = $procurement_can_review_opened_bids || !empty($tender->technical_start_at) || $rank >= 2 || $status === "awarded";
        $commercial_open = $procurement_can_review_opened_bids || !empty($tender->commercial_unlocked_at) || $rank >= 4 || $status === "awarded";

        return [
            "technical" => $technical_open,
            "commercial" => $commercial_open,
            "commercial_priced" => $commercial_open,
            "commercial_unpriced" => $commercial_open,
            "bank_guarantee" => $commercial_open,
        ];
    }

    private function _is_bid_opening_completed(int $tender_id): bool
    {
        if (!$tender_id) {
            return false;
        }

        return (bool) $this->Tender_bid_openings_model->get_completed_session_for_technical_start($tender_id);
    }

    private function _stage_rank(string $stage): int
    {
        return match ($stage) {
            "technical_3key" => 1,
            "technical" => 2,
            "commercial" => 4,
            "award_decision" => 5,
            default => 0,
        };
    }

    private function _status_badge(string $status): string
    {
        $class = match ($status) {
            "draft" => "bg-secondary",
            "published" => "bg-primary",
            "closed" => "bg-dark",
            "awarded" => "bg-success",
            "cancelled" => "bg-danger",
            default => "bg-secondary",
        };

        return "<span class='badge $class'>" . esc(ucfirst($status ?: "-")) . "</span>";
    }

    private function _stage_badge(string $stage): string
    {
        $class = match ($stage) {
            "technical_3key" => "bg-warning text-dark",
            "technical" => "bg-info text-dark",
            "commercial" => "bg-primary",
            "award_decision" => "bg-success",
            default => "bg-light text-dark",
        };

        $label = match ($stage) {
            "technical_3key" => "Bid Opening",
            default => ucwords(str_replace("_", " ", $stage ?: "bidding")),
        };

        return "<span class='badge $class'>" . esc($label) . "</span>";
    }
}
