<?php

namespace App\Controllers;

use App\Models\Ptw_applications_model;
use App\Models\Ptw_reviews_model;
use App\Models\Ptw_terminal_users_model;
use App\Models\Ptw_reasons_model;
use App\Models\Ptw_audit_logs_model;
use App\Models\Ptw_requirement_definitions_model;
use App\Models\Ptw_requirement_responses_model;
use App\Models\Ptw_attachments_model;

class Ptw_terminal_inbox extends Security_Controller
{
    protected $Ptw_applications_model;
    protected $Ptw_reviews_model;
    protected $Ptw_terminal_users_model;
    protected $Ptw_reasons_model;
    protected $Ptw_audit_logs_model;
    protected $Ptw_requirement_definitions_model;
    protected $Ptw_requirement_responses_model;
    protected $Ptw_attachments_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Ptw_applications_model = new Ptw_applications_model();
        $this->Ptw_reviews_model = new Ptw_reviews_model();
        $this->Ptw_terminal_users_model = new Ptw_terminal_users_model();
        $this->Ptw_reasons_model = new Ptw_reasons_model();
        $this->Ptw_audit_logs_model = new Ptw_audit_logs_model();
        $this->Ptw_requirement_definitions_model = new Ptw_requirement_definitions_model();
        $this->Ptw_requirement_responses_model = new Ptw_requirement_responses_model();
        $this->Ptw_attachments_model = new Ptw_attachments_model();
        $this->db = db_connect();
    }

    private function _ensure_terminal_access()
    {
        if ($this->login_user->is_admin) {
            return;
        }

        if (!$this->Ptw_terminal_users_model->is_terminal_user($this->login_user->id)) {
            app_redirect("forbidden");
            exit;
        }
    }

    public function index()
    {
        $this->_ensure_terminal_access();
        return $this->template->rander("ptw_terminal_inbox/index");
    }

    public function list_data()
    {
        $this->_ensure_terminal_access();

        $list = $this->Ptw_applications_model->get_details([
            "stage" => "terminal",
            "statuses" => ["submitted", "in_review", "revise"]
        ])->getResult();

        $result = [];
        foreach ($list as $row) {
            if ($this->_can_act_on_application($row)) {
                $result[] = $this->_make_row($row);
            }
        }

        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_row($data)
    {
        $view_btn = anchor(
            get_uri("ptw_terminal_inbox/details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $review_btn = modal_anchor(
            get_uri("ptw_terminal_inbox/approval_modal_form"),
            "<i data-feather='check-square' class='icon-16'></i> " . app_lang("review"),
            [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("review"),
                "data-post-id" => $data->id,
            ]
        );

        return [
            $data->reference ?? "-",
            $data->company_name ?? "-",
            $data->applicant_name ?? "-",
            $data->exact_location ?? "-",
            !empty($data->work_from) ? format_to_datetime($data->work_from) : "-",
            !empty($data->work_to) ? format_to_datetime($data->work_to) : "-",
            $this->_format_ptw_status($data->status ?? "-"),
            $view_btn . " " . $review_btn
        ];
    }

    public function details($id = 0)
    {
        $this->_ensure_terminal_access();
    
        $id = (int)$id;
        if (!$id) {
            app_redirect("forbidden");
        }
    
        $application = $this->Ptw_applications_model->get_details(["id" => $id])->getRow();
        if (!$application || (int)$application->deleted) {
            app_redirect("forbidden");
        }
    
        if (!$this->_can_act_on_application($application)) {
            app_redirect("forbidden");
        }
    
        if (($application->stage ?? "") !== "terminal") {
            app_redirect("forbidden");
        }
    
        $reviews = $this->Ptw_reviews_model->get_details([
            "ptw_application_id" => $application->id,
            "stage" => "terminal"
        ])->getResult();
    
        // Load PTW checklist + attachments (same source as portal)
        $defs = $this->Ptw_requirement_definitions_model->get_active_definitions()->getResult();
        $responses_rows = $this->Ptw_requirement_responses_model->get_by_application($application->id)->getResult();
        $attachments_rows = $this->Ptw_attachments_model->get_by_application($application->id)->getResult();
    
        $responses_by_definition = [];
        foreach ($responses_rows as $row) {
            $responses_by_definition[(int)($row->ptw_requirement_definition_id ?? 0)] = $row;
        }
    
        $attachments_by_response = [];
        foreach ($attachments_rows as $att) {
            $response_id = (int)($att->ptw_requirement_response_id ?? 0);
            if ($response_id > 0) {
                $attachments_by_response[$response_id] = $att; // latest attachment wins
            }
        }
    
        return $this->template->rander("ptw_terminal_inbox/details", [
            "application" => $application,
            "reviews" => $reviews,
            "definitions_grouped" => $this->_group_definitions($defs),
            "responses_by_definition" => $responses_by_definition,
            "attachments_by_response" => $attachments_by_response,
        ]);
    }


    private function _group_definitions(array $defs): array
{
    $grouped = [
        "hazard_document" => [],
        "ppe" => [],
        "preparation" => [],
        "other" => [],
    ];

    foreach ($defs as $d) {
        $cat = (string)($d->category ?? "other");
        if (!isset($grouped[$cat])) {
            $grouped[$cat] = [];
        }
        $grouped[$cat][] = $d;
    }

    return $grouped;
}

    public function approval_modal_form()
    {
        $this->_ensure_terminal_access();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int)$this->request->getPost("id");
        $application = $this->Ptw_applications_model->get_details(["id" => $id])->getRow();

        if (!$application || (int)$application->deleted) {
            return $this->template->view("errors/html/error_general", [
                "heading" => "Not found",
                "message" => app_lang("record_not_found")
            ]);
        }

        if (!$this->_can_act_on_application($application)) {
            app_redirect("forbidden");
        }

        if (($application->stage ?? "") !== "terminal") {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => "Application is not in Terminal stage."
            ]);
        }

        $reason_options = ["0" => "- " . app_lang("select") . " -"];
        $reasons = $this->Ptw_reasons_model->get_details([
            "stage" => "terminal",
            "only_active" => 1
        ])->getResult();

        foreach ($reasons as $r) {
            $reason_options[(string)$r->id] = $r->title;
        }

        return $this->template->view("ptw_terminal_inbox/approval_modal_form", [
            "application" => $application,
            "reason_options" => $reason_options
        ]);
    }

    public function save_review()
    {
        $this->_ensure_terminal_access();

        $this->validate_submitted_data([
            "ptw_application_id" => "required|numeric",
            "decision" => "required|in_list[approved,revise,rejected]",
        ]);

        $application_id = (int)$this->request->getPost("ptw_application_id");
        $decision = trim((string)$this->request->getPost("decision"));
        $remarks = trim((string)$this->request->getPost("remarks"));
        $reason_id = (int)$this->request->getPost("reason_id");
        $status_change_reason = trim((string)$this->request->getPost("status_change_reason"));

        $application = $this->Ptw_applications_model->get_one($application_id);
        if (!$application || (int)$application->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if (($application->stage ?? "") !== "terminal") {
            return $this->response->setJSON(["success" => false, "message" => "Application is not in Terminal stage."]);
        }

        if (!$this->_can_act_on_application($application)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        if (strtolower(trim((string)($application->status ?? ""))) === "rejected") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Application is already rejected and cannot be reviewed again."
            ]);
        }

        // BRD: reason is mandatory for Reject/Revise
        if (in_array($decision, ["revise", "rejected"], true)) {
            if ($status_change_reason === "" && $reason_id < 1) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Status change reason is required for Revise/Reject."
                ]);
            }
        }

        // If reason dropdown selected, use it as fallback text
        if ($status_change_reason === "" && $reason_id > 0) {
            $selected_reason = $this->Ptw_reasons_model->get_one($reason_id);
            if ($selected_reason && !(int)$selected_reason->deleted) {
                $status_change_reason = (string)$selected_reason->title;
            }
        }

        $now = get_current_utc_time();

        $this->db->transStart();

        // Open or create current review row
        $open_review = $this->Ptw_reviews_model->get_open_review($application_id, "terminal")->getRow();

        if ($open_review) {
            $review_id = (int)$open_review->id;
            $received_at = $open_review->received_at ?: $now;
            $revision_no = (int)$open_review->revision_no;
        } else {
            $revision_no = $this->Ptw_reviews_model->get_next_revision_no($application_id, "terminal");

            $new_review_data = [
                "ptw_application_id" => $application_id,
                "stage"              => "terminal",
                "revision_no"        => $revision_no,
                "reviewer_id"        => $this->login_user->id,
                "received_at"        => $now,
                "created_at"         => $now,
                "updated_at"         => $now,
                "deleted"            => 0,
            ];
            $review_id = $this->Ptw_reviews_model->ci_save($new_review_data);

            $received_at = $now;
        }

        if ($decision === "approved") {
            // Close the review row — final approval
            $review_update = [
                "reviewer_id"          => $this->login_user->id,
                "received_at"          => $received_at,
                "completed_at"         => $now,
                "decision"             => "approved",
                "remarks"              => $remarks ?: null,
                "status_change_reason" => $status_change_reason ?: null,
                "reviewed_at"          => $now,
                "updated_at"           => $now,
            ];
            $this->Ptw_reviews_model->ci_save($review_update, $review_id);

            // Terminal approved -> PTW fully completed
            $app_update = [
                "updated_at"   => $now,
                "stage"        => "completed",
                "status"       => "approved",
                "completed_at" => $now,
            ];
        } elseif ($decision === "rejected") {
            // Reject is final for PTW workflow.
            $review_update = [
                "reviewer_id"          => $this->login_user->id,
                "received_at"          => $received_at,
                "completed_at"         => $now,
                "decision"             => "rejected",
                "remarks"              => $remarks ?: null,
                "status_change_reason" => $status_change_reason ?: null,
                "reviewed_at"          => $now,
                "updated_at"           => $now,
            ];
            $this->Ptw_reviews_model->ci_save($review_update, $review_id);

            $app_update = [
                "updated_at"   => $now,
                "stage"        => "terminal",
                "status"       => "rejected",
                "completed_at" => null,
            ];
        } else {
            // Revise: keep review row open for current stage revision cycle.
            $review_update = [
                "reviewer_id"          => $this->login_user->id,
                "received_at"          => $received_at,
                "remarks"              => $remarks ?: null,
                "status_change_reason" => $status_change_reason ?: null,
                "reviewed_at"          => $now,
                "updated_at"           => $now,
            ];
            $this->Ptw_reviews_model->ci_save($review_update, $review_id);

            // Stay in Terminal stage and wait for contractor resubmission.
            $app_update = [
                "updated_at"   => $now,
                "stage"        => "terminal",
                "status"       => "revise",
                "completed_at" => null,
            ];
        }
        $this->Ptw_applications_model->ci_save($app_update, $application_id);

        // Audit log
        $audit_data = [
            "ptw_application_id" => $application_id,
            "user_id"            => $this->login_user->id,
            "action" => "terminal_" . $decision,
            "meta"   => json_encode([
                "stage"                => "terminal",
                "decision"             => $decision,
                "revision_no"          => $revision_no,
                "remarks"              => $remarks,
                "status_change_reason" => $status_change_reason,
            ]),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr((string)$this->request->getUserAgent()->getAgentString(), 0, 512),
            "created_at" => $now,
            "updated_at" => $now,
            "deleted"    => 0,
        ];
        $this->Ptw_audit_logs_model->ci_save($audit_data);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved")]);
    }

    public function review_history_modal()
    {
        $this->_ensure_terminal_access();
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int)$this->request->getPost("id");
        $application = $this->Ptw_applications_model->get_details(["id" => $id])->getRow();

        if (!$application || (int)$application->deleted) {
            return $this->template->view("errors/html/error_general", [
                "heading" => "Not found",
                "message" => app_lang("record_not_found")
            ]);
        }

        if (!$this->_can_act_on_application($application)) {
            app_redirect("forbidden");
        }

        $reviews = $this->Ptw_reviews_model->get_details([
            "ptw_application_id" => $id,
            "stage" => "terminal"
        ])->getResult();

        return $this->template->view("ptw_terminal_inbox/review_history_modal", [
            "application" => $application,
            "reviews" => $reviews
        ]);
    }

    private function _can_act_on_application($application): bool
    {
        if ($this->login_user->is_admin) {
            return true;
        }

        // NOTE: Current PTW table stores company_name text (not company_id).
        // So we match assignment company name with application company_name.
        $app_company = strtolower(trim((string)($application->company_name ?? "")));
        if ($app_company === "") {
            return false;
        }

        $assignments = $this->Ptw_terminal_users_model->get_user_assignments($this->login_user->id)->getResult();
        foreach ($assignments as $a) {
            $assigned_company = strtolower(trim((string)($a->company_name ?? "")));
            if ($assigned_company !== "" && $assigned_company === $app_company) {
                return true;
            }
        }

        return false;
    }

    private function _format_ptw_status($status)
    {
        $status = strtolower(trim((string)$status));
        if ($status === "") {
            return "-";
        }

        $map = [
            "draft" => "Draft",
            "submitted" => "Submitted",
            "in_review" => "In Review",
            "revise" => "Revise",
            "rejected" => "Rejected",
            "approved" => "Approved",
        ];

        return $map[$status] ?? ucwords(str_replace("_", " ", $status));
    }
}