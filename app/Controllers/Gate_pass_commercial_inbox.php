<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_commercial_users_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Gate_pass_request_visitors_model;
use App\Models\Gate_pass_request_vehicles_model;
use App\Models\Gate_pass_reasons_model;

class Gate_pass_commercial_inbox extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_commercial_users_model;
    protected $Gate_pass_request_approvals_model;
    protected $Gate_pass_request_visitors_model;
    protected $Gate_pass_request_vehicles_model;
    protected $Gate_pass_reasons_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_commercial_users_model = new Gate_pass_commercial_users_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Gate_pass_request_visitors_model = new Gate_pass_request_visitors_model();
        $this->Gate_pass_request_vehicles_model = new Gate_pass_request_vehicles_model();
        $this->Gate_pass_reasons_model = new Gate_pass_reasons_model();

        if (!$this->login_user->is_admin && !$this->Gate_pass_commercial_users_model->is_commercial_user($this->login_user->id)) {
            app_redirect("forbidden");
        }
    }

    public function index()
    {
        return $this->template->rander("gate_pass_commercial_inbox/index");
    }

    public function list_data()
    {
        $options = [
            "stage" => "commercial",
            "statuses" => ["department_approved"],
        ];

        if (!$this->login_user->is_admin) {
            $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
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
            get_uri("gate_pass_commercial_inbox/details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $fee_btn = modal_anchor(
            get_uri("gate_pass_commercial_inbox/fee_modal_form"),
            "<i data-feather='dollar-sign' class='icon-16'></i> " . app_lang("set_fee"),
            [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("set_fee"),
                "data-post-id" => $data->id,
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
            $data->currency ?? "-",
            $data->fee_amount !== null && $data->fee_amount !== "" ? (string)$data->fee_amount : "-",
            !empty($data->fee_is_waived) ? app_lang("yes") : app_lang("no"),
            $view_btn . " " . $fee_btn,
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
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["approval_history"] = $this->Gate_pass_request_approvals_model
            ->get_details(["gate_pass_request_id" => $request->id])
            ->getResult();

        $view_data["status_label"] = $this->_format_gate_pass_status($request->status ?? "");

        return $this->template->rander("gate_pass_commercial_inbox/details", $view_data);
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
        return $this->template->view("gate_pass_commercial_inbox/approval_history_modal", $view_data);
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
            $result[] = $this->_make_visitor_row($row);
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
            $result[] = $this->_make_vehicle_row($row);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_visitor_row($row)
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

    private function _make_vehicle_row($row)
    {
        return [
            $row->plate_no ?? "-",
            $row->make ?? "-",
            $row->model ?? "-",
            $row->color ?? "-"
        ];
    }

    public function fee_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", ["heading" => "Not found", "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->template->view("errors/html/error_general", ["heading" => app_lang("error"), "message" => "Request is not in commercial stage."]);
        }
        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["currency_options"] = ["OMR" => "OMR", "USD" => "USD", "EUR" => "EUR", "GBP" => "GBP"];
        $view_data["reason_options"] = $this->_get_rejection_reason_options();
        return $this->template->view("gate_pass_commercial_inbox/fee_modal_form", $view_data);
    }

    public function save_fee()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "currency" => "required|max_length[10]",
            "fee_amount" => "required|numeric",
            "fee_is_waived" => "permit_empty|in_list[0,1]",
            "fee_waived_reason" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $currency = strtoupper(trim((string)$this->request->getPost("currency")));
        $fee_amount = (float)$this->request->getPost("fee_amount");
        $fee_is_waived = (int)$this->request->getPost("fee_is_waived") === 1 ? 1 : 0;
        $fee_waived_reason = trim((string)$this->request->getPost("fee_waived_reason"));

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }
        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON(["success" => false, "message" => "Request is not in commercial stage."]);
        }
        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $fee_data = [
            "currency" => $currency,
            "fee_amount" => max(0, $fee_amount),
            "fee_is_waived" => $fee_is_waived,
            "fee_waived_by" => $fee_is_waived ? (int)$this->login_user->id : null,
            "fee_waived_reason" => $fee_is_waived ? ($fee_waived_reason ?: null) : null,
            "updated_at" => get_current_utc_time(),
        ];
        $this->Gate_pass_requests_model->ci_save($fee_data, $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }


    public function approve_waiver()
{
    $this->validate_submitted_data([
        "gate_pass_request_id" => "required|numeric"
    ]);

    $request_id = (int)$this->request->getPost("gate_pass_request_id");

    $request = $this->Gate_pass_requests_model->get_one($request_id);
    if (!$request || $request->deleted) {
        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("record_not_found")
        ]);
    }

    // Must be in commercial stage after department approval
    if ($request->stage !== "commercial" || $request->status !== "department_approved") {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Request is not in commercial stage."
        ]);
    }

    if (!$this->_can_act_on_request($request)) {
        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("forbidden")
        ]);
    }

    $fee_amount = (float)($request->fee_amount ?? 0);
    $is_waived  = !empty($request->fee_is_waived);

    // If not waived and fee > 0 → payment must happen in portal, so block commercial approval
    if (!$is_waived && $fee_amount > 0) {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Payment is required (fee is not waived)."
        ]);
    }

    // Approve and move to security
    $update = [
        "status" => "commercial_approved",
        "stage" => "security",
        "stage_updated_at" => get_current_utc_time(),
    ];

    $this->Gate_pass_requests_model->ci_save($update, $request_id);

    $approval_data = [
        "gate_pass_request_id" => $request_id,
        "stage" => "commercial",
        "decision" => "approved",
        "reason_id" => null,
        "comment" => "",
        "decided_by" => $this->login_user->id,
        "decided_at" => get_current_utc_time(),
        "ip_address" => $this->request->getIPAddress(),
        "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
    ];
    $approval_data = clean_data($approval_data);
    $this->Gate_pass_request_approvals_model->ci_save($approval_data);

    return $this->response->setJSON([
        "success" => true,
        "message" => app_lang("record_saved"),
        "id" => $request_id
    ]);
}

    public function reject_request()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "reason_id" => "required|numeric",
            "comment" => "permit_empty",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $reason_id = (int)$this->request->getPost("reason_id");
        $comment = trim((string)$this->request->getPost("comment"));

        if ($reason_id < 1) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("reject_reason_required"),
            ]);
        }

        $reason = $this->Gate_pass_reasons_model
            ->get_details(["id" => $reason_id, "only_active" => 1])
            ->getRow();
        if (!$reason) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("invalid_request"),
            ]);
        }

        $request = $this->Gate_pass_requests_model->get_one($request_id);
        if (!$request || $request->deleted) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("record_not_found"),
            ]);
        }

        if ($request->stage !== "commercial") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Request is not in commercial stage.",
            ]);
        }

        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("forbidden"),
            ]);
        }

        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "rejected",
            "reason_id" => $reason_id,
            "comment" => $comment !== "" ? $comment : (string)$reason->title,
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $approval_data = clean_data($approval_data);
        $saved_id = $this->Gate_pass_request_approvals_model->ci_save($approval_data);
        if (!$saved_id) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $this->Gate_pass_requests_model->ci_save(["status" => "rejected"], $request_id);

        return $this->response->setJSON([
            "success" => true,
            "message" => app_lang("record_saved"),
            "id" => $request_id,
        ]);
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
        $assignments = $this->Gate_pass_commercial_users_model->get_user_assignments($this->login_user->id)->getResult();
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
