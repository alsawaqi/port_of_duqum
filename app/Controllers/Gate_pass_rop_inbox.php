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
        return $this->template->rander("gate_pass_rop_inbox/index");
    }

    public function list_data()
    {
        // Show all requests in rop stage regardless of status
        $options = [
            "stage" => "rop",
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_rop_users_model->get_user_assignments($this->login_user->id)->getResult();
            $company_ids = [];
            foreach ($assignments as $a) {
                $company_ids[] = (int)$a->company_id;
            }
            if (empty($company_ids)) {
                echo json_encode(["data" => []]);
                return;
            }
            $options["company_ids"] = $company_ids;
        }

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

        return [
            $data->reference ?? "-",
            $data->company_name ?? "-",
            $data->department_name ?? "-",
            $requester_name,
            ($data->requester_phone ?? '') ?: '-',
            $data->visit_from ? format_to_datetime($data->visit_from) : "-",
            $data->visit_to ? format_to_datetime($data->visit_to) : "-",
            $this->_format_gate_pass_status($data->status ?? ""),
            $view_btn . " " . $review_btn . " " . $visitor_block_btn,
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

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = $this->_format_gate_pass_status($request->status ?? "");

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
        return [
            $row->full_name ?? "-",
            $row->id_type ?? "-",
            $row->id_number ?? "-",
            $row->nationality ?? "-",
            $row->phone ?? "-",
            $row->role ?? "-",
            $blocked_badge,
            $block_reason !== "" ? esc($block_reason) : "-",
            $primary
        ];
    }

    private function _make_vehicle_row_readonly($row)
    {
        return [
            $row->plate_no ?? "-",
            $row->make ?? "-",
            $row->model ?? "-",
            $row->color ?? "-"
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
                    "valid_from" => $request->visit_from ?? $now,
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
        $assignments = $this->Gate_pass_rop_users_model->get_user_assignments($this->login_user->id)->getResult();
        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id) {
                return true;
            }
        }
        return false;
    }

    private function _format_gate_pass_status($status, $empty_value = "-")
    {
        $status = strtolower(trim((string) $status));
        if ($status === "" || $status === "-") {
            return $empty_value;
        }
        $lang_key = "gate_pass_status_" . $status;
        $translated = app_lang($lang_key);
        if ($translated && $translated !== $lang_key) {
            return $translated;
        }
        if ($status === "rop_approved") {
            return "ROP Approved";
        }
        return ucwords(str_replace("_", " ", $status));
    }
}
