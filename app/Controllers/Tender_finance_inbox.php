<?php

namespace App\Controllers;

use App\Models\Tender_requests_model;
use App\Models\Tender_request_vendors_model;
use App\Models\Tender_request_approvals_model;
use App\Models\Tender_request_team_members_model;

class Tender_finance_inbox extends Security_Controller
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
        $this->access_only_tender("finance_inbox", "view");
        return $this->template->rander("tender_finance_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("finance_inbox", "view");

        $list = $this->Tender_requests_model->get_details([
            "statuses" => ["manager_approved"]
        ])->getResult();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("finance_inbox", "view");

        $id = (int) $this->request->getPost("id");
        $request = $this->Tender_requests_model->get_details(["id" => $id])->getRow();

        if (!$request) {
            show_404();
        }

        $selected_vendors = [];
        if (($request->tender_type ?? "open") === "close") {
            $selected_vendors = $this->Tender_request_vendors_model->get_selected_vendors($id);
        }

        $approval_history = $this->Tender_request_approvals_model
            ->get_details(["tender_request_id" => $id])
            ->getResult();

        $team_members = $this->Tender_request_team_members_model->get_grouped_members($id);

        return $this->template->view("tender_finance_inbox/modal_form", [
            "request" => $request,
            "selected_vendors" => $selected_vendors,
            "approval_history" => $approval_history,
            "team_members" => $team_members
        ]);
    }

    function approve()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("finance_inbox", "update");
    
        $id = (int) $this->request->getPost("id");
        $request = $this->Tender_requests_model->get_one($id);
    
        if (!$request || !$request->id || (int) ($request->deleted ?? 0) === 1) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender request not found."
            ]);
        }
    
        if (($request->status ?? "") !== "manager_approved") {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Only Department Manager approved requests can be verified by Finance."
            ]);
        }
    
        $db = db_connect();
        $db->transStart();
    
        $this->Tender_requests_model->ci_save([
            "finance_verified_by" => $this->login_user->id,
            "finance_verified_at" => date("Y-m-d H:i:s"),
            "finance_reject_comment" => null,
            "status" => "finance_verified"
        ], $id);
    
        $this->Tender_request_approvals_model->log_stage(
            $id,
            "finance",
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
            "message" => "Finance verified"
        ]);
    }

    function reject()
{
    $this->validate_submitted_data([
        "id" => "required|numeric",
        "comment" => "required"
    ]);
    $this->access_only_tender("finance_inbox", "update");

    $id = (int) $this->request->getPost("id");
    $comment = trim((string) $this->request->getPost("comment"));
    $request = $this->Tender_requests_model->get_one($id);

    if (!$request || !$request->id || (int) ($request->deleted ?? 0) === 1) {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Tender request not found."
        ]);
    }

    if (($request->status ?? "") !== "manager_approved") {
        return $this->response->setJSON([
            "success" => false,
            "message" => "Only Department Manager approved requests can be rejected by Finance."
        ]);
    }

    $db = db_connect();
    $db->transStart();

    $this->Tender_requests_model->ci_save([
        "status" => "rejected",
        "finance_reject_comment" => $comment,
        "finance_verified_by" => $this->login_user->id,
        "finance_verified_at" => date("Y-m-d H:i:s"),
        "committee_approved_by" => null,
        "committee_approved_at" => null,
        "committee_reject_comment" => null,
    ], $id);

    $this->Tender_request_approvals_model->log_stage(
        $id,
        "finance",
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
        "message" => "Rejected by finance"
    ]);
}

    private function _make_row($row)
    {
        $status = "<span class='badge bg-secondary'>" . esc($row->status) . "</span>";

        $review = modal_anchor(
            get_uri("tender_finance_inbox/modal_form"),
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
                "data-action-url" => get_uri("tender_finance_inbox/approve"),
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