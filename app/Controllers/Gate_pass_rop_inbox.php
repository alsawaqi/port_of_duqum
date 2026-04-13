<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_rop_users_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_passes_model;
use App\Models\Gate_pass_reasons_model;

class Gate_pass_rop_inbox extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_rop_users_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_passes_model;
    protected $Gate_pass_reasons_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_rop_users_model = new Gate_pass_rop_users_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_passes_model = new Gate_passes_model();
        $this->Gate_pass_reasons_model = new Gate_pass_reasons_model();

        if (!$this->login_user->is_admin && !$this->Gate_pass_rop_users_model->is_rop_user($this->login_user->id)) {
            app_redirect("forbidden");
        }
    }

    public function index()
    {
        $Stats = new \App\Models\Pod_dashboard_stats_model();
        // ROP stage is port-wide: KPIs cover all companies (no per-company filter).
        $view_data["kpis"] = $Stats->gate_pass_kpis(["stages" => ["rop"]]);

        return $this->template->rander("gate_pass_rop_inbox/index", $view_data);
    }

    public function export_list_csv()
    {
        $options = [
            "stage" => "rop",
            "exclude_statuses" => ["returned"],
        ];

        $list = $this->Gate_pass_requests_model->get_details($options)->getResult();

        $filename = "gate_pass_rop_" . date("Y-m-d") . ".csv";
        $this->response->setHeader("Content-Type", "text/csv; charset=UTF-8");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");

        $fh = fopen("php://temp", "r+");
        fputcsv($fh, ["reference", "created_at", "company", "department", "requester", "phone", "status", "stage", "visit_from", "visit_to"]);
        foreach ($list as $r) {
            $requester_name = trim(($r->requester_first_name ?? "") . " " . ($r->requester_last_name ?? ""));
            if ($requester_name === "") {
                $requester_name = $r->requester_name ?? "";
            }
            fputcsv($fh, [
                $r->reference ?? "",
                gate_pass_request_created_at_pick($r) ?? "",
                $r->company_name ?? "",
                $r->department_name ?? "",
                $requester_name,
                ($r->requester_phone ?? "") ?: "",
                $r->status ?? "",
                $r->stage ?? "",
                $r->visit_from ?? "",
                $r->visit_to ?? "",
            ]);
        }
        rewind($fh);
        $body = stream_get_contents($fh);
        fclose($fh);

        return $this->response->setBody($body);
    }

    public function list_data()
    {
        // All requests currently in the ROP stage (any company). Access is limited to admins and ROP users.
        $options = [
            "stage" => "rop",
            "exclude_statuses" => ["returned"],
        ];

        $list = $this->Gate_pass_requests_model->get_details($options)->getResult();
        $result = [];
        foreach ($list as $data) {
            $result[] = $this->_make_row($data);
        }
        echo json_encode(["data" => $result]);
    }

    private function _make_row($data)
    {
        $view_btn = anchor(
            get_uri("gate_pass_rop_inbox/details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $review_btn = modal_anchor(
            get_uri("gate_pass_rop_inbox/approval_modal_form"),
            "<i data-feather='check-square' class='icon-16'></i> " . app_lang("review"),
            [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("review"),
                "data-post-id" => $data->id,
            ]
        );

        $visitor_block_btn = modal_anchor(
            get_uri("gate_pass_rop_inbox/visitor_block_modal_form"),
            "<i data-feather='slash' class='icon-16'></i> Block Visitor",
            [
                "class" => "btn btn-warning btn-sm",
                "title" => "Block/Unblock Visitor",
                "data-post-request_id" => $data->id,
            ]
        );

        $requester_name = trim(($data->requester_first_name ?? '') . ' ' . ($data->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $data->requester_name ?? '-';
        }

        $created_disp = gate_pass_request_created_display($data);

        return [
            $data->reference ?? "-",
            $created_disp,
            $data->company_name ?? "-",
            $data->department_name ?? "-",
            $requester_name,
            ($data->requester_phone ?? '') ?: '-',
            $data->visit_from ? format_to_datetime($data->visit_from) : "-",
            $data->visit_to ? format_to_datetime($data->visit_to) : "-",
            gate_pass_request_status_display($data),
            '<div class="gp-rop-action-btns">' . $view_btn . $review_btn . $visitor_block_btn . '</div>',
        ];
    }

    public function details($id = 0)
    {
        $id = (int)$id;
        if (!$id) app_redirect("forbidden");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            app_redirect("forbidden");
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if ($request->stage !== "rop") {
            app_redirect("forbidden");
        }
        if (($request->status ?? "") === "returned") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = gate_pass_request_status_display($request);

        return $this->template->rander("gate_pass_rop_inbox/details", $view_data);
    }

    public function approval_history_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();
        return $this->template->view("gate_pass_rop_inbox/approval_history_modal", $view_data);
    }

    public function visitors_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted || !$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["data" => []]);
        }
        $list_data = $this->Gate_pass_request_visitors_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_visitor_row_readonly($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    public function vehicles_list_data($request_id = 0)
    {
        $request_id = (int)$request_id;
        if (!$request_id) {
            return $this->response->setJSON(["data" => []]);
        }
        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted || !$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["data" => []]);
        }
        $list_data = $this->Gate_pass_request_vehicles_model->get_details(["gate_pass_request_id" => $request_id])->getResult();
        $result = [];
        foreach ($list_data as $row) {
            $result[] = $this->_make_vehicle_row_readonly($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_visitor_row_readonly($row)
    {
        $is_blocked = (int)($row->is_blocked ?? 0) === 1;
        $blocked_badge = $is_blocked
            ? "<span class='badge bg-danger'>" . app_lang("blocked") . "</span>"
            : "<span class='badge bg-success'>Clear</span>";
        $block_reason = trim((string)($row->block_reason ?? ""));
        $primary = !empty($row->is_primary) ? "<span class='badge bg-success'>" . app_lang("primary") . "</span>" : "";
        $attachments_btn = modal_anchor(
            get_uri("gate_pass_rop_inbox/visitor_attachments_modal"),
            "<i data-feather='paperclip' class='icon-16'></i> " . app_lang("attachments"),
            ["class" => "btn btn-default btn-sm", "title" => app_lang("visitor_attachments"), "data-modal-title" => app_lang("visitor_attachments"), "data-post-id" => $row->id]
        );
        return [
            $row->full_name ?? "-",
            $row->id_type ?? "-",
            $row->id_number ?? "-",
            $row->nationality ?? "-",
            $row->phone ?? "-",
            $row->role ?? "-",
            $blocked_badge,
            $block_reason !== "" ? esc($block_reason) : "-",
            $primary,
            $attachments_btn,
        ];
    }

    private function _make_vehicle_row_readonly($row)
    {
        $mul = !empty($row->mulkiyah_attachment_path)
            ? "<span class='badge bg-success'>" . app_lang("yes") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("no") . "</span>";
        return [
            $row->plate_no ?? "-",
            $mul,
        ];
    }

    public function approval_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }

        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        if ($request->stage !== "rop") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in ROP stage."]);
        }
        if (($request->status ?? "") === "returned") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => app_lang("error_occurred")]);
        }

        $view_data["request"] = $request;
        $view_data["reason_options"] = $this->_get_rejection_reason_options();
        return $this->template->view("gate_pass_rop_inbox/approval_modal_form", $view_data);
    }

    public function save_approval()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "decision" => "required|in_list[approved,rejected,returned]",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $decision = $this->request->getPost("decision"); // approved|rejected|returned
        $comment = trim((string)$this->request->getPost("comment"));
        $reason_id = (int)$this->request->getPost("reason_id");

        if ($decision === "returned" && !$comment) {
            echo json_encode(["success" => false, "message" => "Comment is required for Return."]);
            return;
        }

        $selected_reason = null;
        if ($decision === "rejected") {
            if ($reason_id < 1) {
                echo json_encode(["success" => false, "message" => app_lang("reject_reason_required")]);
                return;
            }

            $selected_reason = $this->Gate_pass_reasons_model
                ->get_details(["id" => $reason_id, "only_active" => 1])
                ->getRow();
            if (!$selected_reason) {
                echo json_encode(["success" => false, "message" => app_lang("invalid_request")]);
                return;
            }

            if ($comment === "") {
                $comment = (string)$selected_reason->title;
            }
        } else {
            $reason_id = 0;
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            echo json_encode(["success" => false, "message" => app_lang("record_not_found")]);
            return;
        }

        if (!$this->_can_act_on_request($request)) {
            echo json_encode(["success" => false, "message" => app_lang("forbidden")]);
            return;
        }

        if ($request->stage !== "rop") {
            echo json_encode(["success" => false, "message" => "Request is not in ROP stage."]);
            return;
        }

        if (($request->status ?? "") === "returned") {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "rop",
            "decision" => $decision,
            "reason_id" => $reason_id > 0 ? $reason_id : null,
            "comment" => $comment,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];

        $save_id = $this->Gate_pass_request_approvals_model->ci_save($approval_data);
        if (!$save_id) {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            return;
        }

        if ($decision === "approved") {
            $update = ["status" => "rop_approved", "stage" => "issued"];
            $this->Gate_pass_requests_model->ci_save($update, $request_id);

            // Create pod_gate_passes record with qr_token for QR code download
            $existing = $this->Gate_passes_model->get_by_request_id($request_id);
            if (!$existing) {
                $request = $this->Gate_pass_requests_model->get_one($request_id);
                $qr_token = $this->Gate_passes_model->generate_qr_token();
                $gate_pass_no = $this->Gate_passes_model->generate_gate_pass_no($request_id);
                $now = get_current_utc_time();
                $pass_data = [
                    "gate_pass_request_id" => $request_id,
                    "gate_pass_no" => $gate_pass_no,
                    "qr_token" => $qr_token,
                    "status" => "active",
                    // Scannable immediately after issue; visit window still shown from the request on scan UI
                    "valid_from" => $now,
                    "valid_to" => $request->visit_to ?? null,
                    "issued_by" => $this->login_user->id,
                    "issued_at" => $now,
                    "created_at" => $now,
                    "updated_at" => $now,
                    "deleted" => 0,
                ];
                $this->Gate_passes_model->ci_save($pass_data);
            }
        } else {
            $update = ["status" => $decision];
            $this->Gate_pass_requests_model->ci_save($update, $request_id);
        }

        echo json_encode(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    public function visitor_attachments_modal()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $visitor_id = (int)$this->request->getPost("id");

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || !empty($visitor->deleted)) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $visitor->gate_pass_request_id])->getRow();
        if (!$request || !empty($request->deleted)) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if (($request->stage ?? "") !== "rop") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in ROP stage."]);
        }

        $view_data["visitor"] = $visitor;
        return $this->template->view("gate_pass_rop_inbox/visitor_attachments_modal", $view_data);
    }

    public function visitor_attachment_download($visitor_id = 0, $field = "")
    {
        $visitor_id = (int)$visitor_id;
        $allowed_fields = ["id_attachment_path", "visa_attachment_path", "photo_attachment_path", "driving_license_attachment_path"];
        if (!$visitor_id || !in_array($field, $allowed_fields, true)) {
            show_404();
        }

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || empty($visitor->{$field})) {
            show_404();
        }

        $relPath = $visitor->{$field};
        $relPath = preg_replace("#\.\.+#", "", $relPath);
        $relPath = ltrim($relPath, "/");
        $fullPath = WRITEPATH . "uploads/" . $relPath;
        if (!is_file($fullPath)) {
            show_404();
        }

        $request = $this->Gate_pass_requests_model->get_details(["id" => $visitor->gate_pass_request_id])->getRow();
        if (!$request || $request->deleted) {
            show_404();
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if (($request->stage ?? "") !== "rop") {
            show_404();
        }

        $mime = function_exists("mime_content_type") ? mime_content_type($fullPath) : "application/octet-stream";
        $name = basename($relPath);
        $download = (int)($this->request->getGet("download") ?? 0) === 1;
        $inline = !$download && (str_starts_with($mime, "image/") || $mime === "application/pdf");

        return $this->response
            ->setHeader("Content-Type", $mime)
            ->setHeader("Content-Disposition", ($inline ? "inline" : "attachment") . '; filename="' . addslashes($name) . '"')
            ->setBody(file_get_contents($fullPath));
    }

    public function visitor_block_modal_form()
    {
        $this->validate_submitted_data(["request_id" => "required|numeric"]);
        $request_id = (int)$this->request->getPost("request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }
        if ($request->stage !== "rop") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in ROP stage."]);
        }

        $view_data["request"] = $request;
        $view_data["visitors"] = $this->Gate_pass_request_visitors_model
            ->get_details(["gate_pass_request_id" => $request_id])
            ->getResult();
        return $this->template->view("gate_pass_rop_inbox/visitor_block_modal_form", $view_data);
    }

    public function save_visitor_block()
    {
        $this->validate_submitted_data([
            "request_id" => "required|numeric",
            "visitor_id" => "required|numeric",
            "block_action" => "required|in_list[block,unblock]",
            "block_reason" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("request_id");
        $visitor_id = (int)$this->request->getPost("visitor_id");
        $block_action = strtolower(trim((string)$this->request->getPost("block_action")));
        $block_reason = trim((string)$this->request->getPost("block_reason"));

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }
        if ($request->stage !== "rop") {
            return $this->response->setJSON(["success" => false, "message" => "Request is not in ROP stage."]);
        }

        $visitor = $this->Gate_pass_request_visitors_model->get_details(["id" => $visitor_id])->getRow();
        if (!$visitor || (int)$visitor->gate_pass_request_id !== $request_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if ($block_action === "block" && $block_reason === "") {
            return $this->response->setJSON(["success" => false, "message" => "Block reason is required."]);
        }

        $data = [];
        if ($block_action === "block") {
            $data = [
                "is_blocked" => 1,
                "block_reason" => $block_reason,
                "blocked_by" => $this->login_user->id,
                "blocked_at" => get_current_utc_time(),
            ];
        } else {
            $data = [
                "is_blocked" => 0,
                "block_reason" => null,
                "blocked_by" => null,
                "blocked_at" => null,
            ];
        }

        $data = clean_data($data);
        $ok = $this->Gate_pass_request_visitors_model->ci_save($data, $visitor_id);
        if (!$ok) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }

        gate_pass_audit_log_visitor_block(
            (int)$this->login_user->id,
            $request,
            $visitor,
            $block_action,
            $block_reason,
            "rop"
        );

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved")]);
    }

    private function _get_rejection_reason_options(): array
    {
        $options = ["0" => "- " . app_lang("select") . " -"];
        $reasons = $this->Gate_pass_reasons_model->get_details(["only_active" => 1])->getResult();
        foreach ($reasons as $reason) {
            $options[(string)$reason->id] = $reason->title;
        }
        return $options;
    }

    private function _can_act_on_request($request): bool
    {
        if ($this->login_user->is_admin) {
            return true;
        }
        if ((string)($request->stage ?? "") !== "rop") {
            return false;
        }

        return $this->Gate_pass_rop_users_model->is_rop_user((int)$this->login_user->id);
    }
}
