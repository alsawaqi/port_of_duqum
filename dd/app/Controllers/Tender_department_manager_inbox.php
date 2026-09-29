<?php

namespace App\Controllers;

use App\Models\Tender_requests_model;
use App\Models\Tender_request_vendors_model;
use App\Models\Tender_request_approvals_model;
use App\Models\Tender_request_team_members_model;

class Tender_department_manager_inbox extends Security_Controller
{
    protected $Tender_requests_model;
    protected $Tender_request_vendors_model;
    protected $Tender_request_approvals_model;
    protected $Tender_request_team_members_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_requests_model = new Tender_requests_model();
        $this->Tender_request_vendors_model = new Tender_request_vendors_model();
        $this->Tender_request_approvals_model = new Tender_request_approvals_model();
        $this->Tender_request_team_members_model = new Tender_request_team_members_model();
    }

    function index()
    {
        $this->access_only_tender("manager_inbox", "view");
        return $this->template->rander("tender_department_manager_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("manager_inbox", "view");

        $options = [
            "statuses" => ["submitted"]
        ];

        if (!$this->login_user->is_admin) {
            $options["department_manager_user_id"] = (int) $this->login_user->id;
        }

        $list = $this->Tender_requests_model->get_details($options)->getResult();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("manager_inbox", "view");

        $id = (int) $this->request->getPost("id");
        $request = $this->Tender_requests_model->get_details(["id" => $id])->getRow();

        if (!$request) {
            show_404();
        }

        if (
            !$this->login_user->is_admin &&
            (int) ($request->department_manager_user_id ?? 0) !== (int) $this->login_user->id
        ) {
            app_redirect("forbidden");
            exit;
        }

        $selected_vendors = [];
        if (($request->tender_type ?? "open") === "close") {
            $selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($id);
        }

        $approval_history = $this->Tender_request_approvals_model
            ->get_details(["tender_request_id" => $id])
            ->getResult();

        $team_members = $this->Tender_request_team_members_model->get_grouped_members($id);

        return $this->template->view("tender_department_manager_inbox/modal_form", [
            "request" => $request,
            "selected_vendors" => $selected_vendors,
            "approval_history" => $approval_history,
            "team_members" => $team_members
        ]);
    }

    function approve()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("manager_inbox", "update");

        $id = (int) $this->request->getPost("id");
        $request = $this->Tender_requests_model->get_one($id);

        if (!$request || !$request->id || (int) ($request->deleted ?? 0) === 1) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender request not found."
            ]);
        }

        if (($request->status ?? "") !== "submitted") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Only submitted requests can be approved by Department Manager."
            ]);
        }

        if (
            !$this->login_user->is_admin &&
            (int) ($request->department_manager_user_id ?? 0) !== (int) $this->login_user->id
        ) {
            app_redirect("forbidden");
            exit;
        }

        $db = db_connect();
        $db->transStart();

        $this->Tender_requests_model->ci_save([
            "status" => "manager_approved",
            "department_manager_signed_at" => date("Y-m-d H:i:s"),
            "department_manager_reject_comment" => null,
            "finance_verified_by" => null,
            "finance_verified_at" => null,
            "finance_reject_comment" => null,
            "committee_approved_by" => null,
            "committee_approved_at" => null,
            "committee_reject_comment" => null,
        ], $id);

        $this->Tender_request_approvals_model->log_stage(
            $id,
            "manager",
            "approved",
            null,
            (int) $this->login_user->id
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Department Manager approved"
        ]);
    }

    function reject()
    {
        $this->validate_submitted_data([
            "id" => "required|numeric",
            "comment" => "required"
        ]);
        $this->access_only_tender("manager_inbox", "update");

        $id = (int) $this->request->getPost("id");
        $comment = trim((string) $this->request->getPost("comment"));
        $request = $this->Tender_requests_model->get_one($id);

        if (!$request || !$request->id || (int) ($request->deleted ?? 0) === 1) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender request not found."
            ]);
        }

        if (($request->status ?? "") !== "submitted") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Only submitted requests can be rejected by Department Manager."
            ]);
        }

        if (
            !$this->login_user->is_admin &&
            (int) ($request->department_manager_user_id ?? 0) !== (int) $this->login_user->id
        ) {
            app_redirect("forbidden");
            exit;
        }

        $db = db_connect();
        $db->transStart();

        $this->Tender_requests_model->ci_save([
            "status" => "rejected",
            "department_manager_signed_at" => null,
            "department_manager_reject_comment" => $comment,
            "finance_verified_by" => null,
            "finance_verified_at" => null,
            "finance_reject_comment" => null,
            "committee_approved_by" => null,
            "committee_approved_at" => null,
            "committee_reject_comment" => null,
        ], $id);

        $this->Tender_request_approvals_model->log_stage(
            $id,
            "manager",
            "rejected",
            $comment,
            (int) $this->login_user->id
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Rejected by Department Manager"
        ]);
    }

    private function _make_row($row)
    {
        $status = "<span class='badge bg-secondary'>" . esc($row->status) . "</span>";

        $review = modal_anchor(
            get_uri("tender_department_manager_inbox/modal_form"),
            "<i data-feather='eye' class='icon-16'></i>",
            [
                "title" => "Review",
                "data-post-id" => $row->id,
                "class" => "edit"
            ]
        );

        $approve = js_anchor(
            "<i data-feather='check' class='icon-16'></i>",
            [
                "title" => "Approve",
                "class" => "approve",
                "data-id" => $row->id,
                "data-action-url" => get_uri("tender_department_manager_inbox/approve"),
                "data-action" => "post"
            ]
        );

        $reject = js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "title" => "Reject",
                "class" => "reject",
                "data-id" => $row->id
            ]
        );

        return [
            $row->reference,
            $row->subject,
            $row->budget_omr,
            $row->tender_fee,
            $status,
            $review . " " . $approve . " " . $reject
        ];
    }
}