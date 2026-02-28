<?php

namespace App\Controllers;

use App\Models\Gate_pass_requests_model;
use App\Models\Gate_pass_commercial_users_model;
use App\Models\Gate_pass_request_approvals_model;
use App\Models\Users_model;

class Gate_pass_commercial_inbox extends Security_Controller
{
    protected $Gate_pass_requests_model;
    protected $Gate_pass_commercial_users_model;
    protected $Gate_pass_request_approvals_model;
    protected $Users_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Gate_pass_requests_model = new Gate_pass_requests_model();
        $this->Gate_pass_commercial_users_model = new Gate_pass_commercial_users_model();
        $this->Gate_pass_request_approvals_model = new Gate_pass_request_approvals_model();
        $this->Users_model = new Users_model();

        if (
            !$this->login_user->is_admin &&
            !$this->Gate_pass_commercial_users_model->is_commercial_user($this->login_user->id)
        ) {
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
            $assignments = $this->Gate_pass_commercial_users_model
                ->get_user_assignments($this->login_user->id)
                ->getResult();

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

        $list_data = $this->Gate_pass_requests_model->get_details($options)->getResult();

        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    private function _make_row($data)
    {
        $view_btn = anchor(
            get_uri("gate_pass_portal/request_details/" . $data->id),
            "<i data-feather='eye' class='icon-16'></i> " . app_lang("view"),
            ["class" => "btn btn-default btn-sm", "title" => app_lang("view")]
        );

        $fee_btn = modal_anchor(
            get_uri("gate_pass_commercial_inbox/fee_modal_form"),
            "<i data-feather='dollar-sign' class='icon-16'></i> Fee",
            [
                "class" => "btn btn-primary btn-sm",
                "title" => "Fee",
                "data-post-id" => $data->id,
            ]
        );

        $requester_name = trim(($data->requester_first_name ?? '') . ' ' . ($data->requester_last_name ?? ''));
        if ($requester_name === '') {
            $requester_name = $data->requester_name ?? '-';
        }

        $waived_badge = "<span class='badge badge-soft-secondary'>" . app_lang("no") . "</span>";
        if (!empty($data->fee_is_waived)) {
            $waived_badge = "<span class='badge badge-soft-success'>" . app_lang("yes") . "</span>";
        }

        $fee_amount = "-";
        if ($data->fee_amount !== null && $data->fee_amount !== "") {
            $fee_amount = is_numeric($data->fee_amount)
                ? number_format((float)$data->fee_amount, 2)
                : (string)$data->fee_amount;
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
            $fee_amount,
            $waived_badge,
            $view_btn . " " . $fee_btn,
        ];
    }

    public function fee_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $id])->getRow();
        if (!$request || $request->deleted) {
            return $this->template->view("errors/html/error_general", [
                "heading" => "Not found",
                "message" => app_lang("record_not_found")
            ]);
        }

        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->template->view("errors/html/error_general", [
                "heading" => app_lang("error"),
                "message" => "Request is not in commercial stage."
            ]);
        }

        if (!$this->_can_act_on_request($request)) {
            app_redirect("forbidden");
        }

        $view_data["request"] = $request;
        $view_data["currency_options"] = ["OMR" => "OMR", "USD" => "USD", "EUR" => "EUR", "GBP" => "GBP"];

        // Waiver metadata (optional)
        $view_data["waived_by_name"] = "";
        if (!empty($request->fee_waived_by)) {
            $u = $this->Users_model->get_one((int)$request->fee_waived_by);
            if ($u && empty($u->deleted)) {
                $view_data["waived_by_name"] = trim(($u->first_name ?? "") . " " . ($u->last_name ?? ""));
            }
        }

        return $this->template->view("gate_pass_commercial_inbox/fee_modal_form", $view_data);
    }

    public function save_fee()
    {
        $this->validate_submitted_data([
            "gate_pass_request_id" => "required|numeric",
            "currency" => "required",
            "fee_amount" => "required|numeric",
        ]);

        $request_id = (int)$this->request->getPost("gate_pass_request_id");
        $currency = trim((string)$this->request->getPost("currency"));
        $fee_amount = (float)$this->request->getPost("fee_amount");

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

        // IMPORTANT: If Dept waived it, Commercial shouldn't edit the fee.
        if (!empty($request->fee_is_waived)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Fee is waived. You can't edit the fee from Commercial."
            ]);
        }

        $data = [
            "currency" => $currency,
            "fee_amount" => $fee_amount,
        ];

        $this->Gate_pass_requests_model->ci_save($data, $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    /**
     * Commercial approves a waived (or zero-fee) request and sends it to Security.
     * - If NOT waived and fee > 0, we block (because payment is required in Portal).
     */
    public function approve_waiver()
    {
        $this->validate_submitted_data(["gate_pass_request_id" => "required|numeric"]);
        $request_id = (int)$this->request->getPost("gate_pass_request_id");

        $request = $this->Gate_pass_requests_model->get_details(["id" => $request_id])->getRow();
        if (!$request || !empty($request->deleted)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        if ($request->stage !== "commercial" || $request->status !== "department_approved") {
            return $this->response->setJSON(["success" => false, "message" => "Request is not in commercial stage."]);
        }

        if (!$this->_can_act_on_request($request)) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("forbidden")]);
        }

        $is_waived = !empty($request->fee_is_waived);
        $is_zero_fee = isset($request->fee_amount) && (float)$request->fee_amount <= 0;

        if (!$is_waived && !$is_zero_fee) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Payment is required for this request (fee is not waived)."
            ]);
        }

        // Log approval decision (commercial)
        $approval_data = [
            "gate_pass_request_id" => $request_id,
            "stage" => "commercial",
            "decision" => "approved",
            "comment" => $is_waived
                ? ("Fee waived. " . trim((string)($request->fee_waived_reason ?? "")))
                : "Zero fee.",
            "decided_by" => $this->login_user->id,
            "decided_at" => get_current_utc_time(),
            "ip_address" => $this->request->getIPAddress(),
            "user_agent" => substr($this->request->getUserAgent()->getAgentString(), 0, 500),
        ];
        $this->Gate_pass_request_approvals_model->ci_save($approval_data);

        // Move to Security
        $update = [
            "status" => "commercial_approved",
            "stage" => "security",
            "stage_updated_at" => get_current_utc_time(),
        ];
        $this->Gate_pass_requests_model->ci_save($update, $request_id);

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved"), "id" => $request_id]);
    }

    private function _can_act_on_request($request): bool
    {
        if ($this->login_user->is_admin) {
            return true;
        }

        $assignments = $this->Gate_pass_commercial_users_model
            ->get_user_assignments($this->login_user->id)
            ->getResult();

        foreach ($assignments as $a) {
            if ((int)$a->company_id === (int)$request->company_id) {
                return true;
            }
        }
        return false;
    }
}
