<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_openings_model;
use App\Models\Tenders_model;

class Tender_committee_opening_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_openings_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_openings_model = new Tender_bid_openings_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("committee", "view");
        return $this->template->rander("tender_committee_opening_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("committee", "view");
        $this->Tenders_model->auto_progress_workflow();

        $rows = $this->Tender_bids_model->get_tenders_ready_for_3key_opening((int) $this->login_user->id);
        $result = [];

        foreach ($rows as $row) {
            $session = $this->Tender_bid_openings_model->get_active_session((int) $row->id);
            $status = $session ? $session->status : "pending";

            $result[] = [
                esc($row->reference),
                esc($row->title),
                (int) $row->bids_count,
                "<span class='badge bg-secondary'>" . esc(ucfirst($status)) . "</span>",
                !empty($row->committee_3key_end_at) ? format_to_datetime($row->committee_3key_end_at) : "-",
                modal_anchor(
                    get_uri("tender_committee_opening_inbox/modal_form"),
                    "<i data-feather='unlock' class='icon-16'></i>",
                    [
                        "title" => "3-Key Commercial Opening",
                        "data-post-id" => $row->id,
                        "class" => "edit"
                    ]
                )
            ];
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("committee", "view");
        $this->Tenders_model->auto_progress_workflow();

        $tender_id = (int) $this->request->getPost("id");
        $tender = $this->_get_committee_stage_tender($tender_id);

        if (!$tender) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id);
        $confirm_map = $session ? $this->Tender_bid_openings_model->get_confirmation_map((int) $session->id) : [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        $role = $this->_get_current_committee_role($tender_id);

        return $this->template->view("tender_committee_opening_inbox/modal_form", [
            "tender" => $tender,
            "session" => $session,
            "confirm_map" => $confirm_map,
            "my_role" => $role
        ]);
    }

    function generate_codes()
    {
        $this->validate_submitted_data(["tender_id" => "required|numeric"]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $this->Tenders_model->auto_progress_workflow();
        $tender_id = (int) $this->request->getPost("tender_id");

        if (!$this->_get_current_committee_role($tender_id)) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the committee 3-key stage."]);
        }

        if (!$this->Tender_bids_model->is_technical_evaluation_complete($tender_id)) {
            return $this->response->setJSON(["success" => false, "message" => "Technical evaluation is not completed yet."]);
        }

        $id = $this->Tender_bid_openings_model->create_new_session($tender_id, (int) $this->login_user->id);

        return $this->response->setJSON([
            "success" => true,
            "message" => "3-key codes generated.",
            "opening_id" => $id
        ]);
    }

    function confirm_codes()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "chairman_code" => "required",
            "secretary_code" => "required",
            "member_code" => "required"
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $this->Tenders_model->auto_progress_workflow();
        $tender_id = (int) $this->request->getPost("tender_id");
        $role = $this->_get_current_committee_role($tender_id);

        if (!$role) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the committee 3-key stage."]);
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id);
        if (!$session || $session->status !== "codes_generated") {
            return $this->response->setJSON(["success" => false, "message" => "No active 3-key session found."]);
        }

        if ($this->Tender_bid_openings_model->user_already_confirmed((int) $session->id, (int) $this->login_user->id)) {
            return $this->response->setJSON(["success" => false, "message" => "You already confirmed this opening session."]);
        }

        $valid =
            trim((string) $this->request->getPost("chairman_code")) === (string) $session->chairman_code &&
            trim((string) $this->request->getPost("secretary_code")) === (string) $session->secretary_code &&
            trim((string) $this->request->getPost("member_code")) === (string) $session->member_code;

        $this->Tender_bid_openings_model->save_confirmation(
            (int) $session->id,
            (int) $this->login_user->id,
            $role,
            trim((string) $this->request->getPost("chairman_code")),
            trim((string) $this->request->getPost("secretary_code")),
            trim((string) $this->request->getPost("member_code")),
            $valid
        );

        if (!$valid) {
            return $this->response->setJSON(["success" => false, "message" => "Invalid codes."]);
        }

        $map = $this->Tender_bid_openings_model->get_confirmation_map((int) $session->id);

        if ($map["chairman"] >= 1 && $map["secretary"] >= 1 && $map["itc_member"] >= 1) {
            $this->Tender_bid_openings_model->unlock_session((int) $session->id);
            $this->Tenders_model->auto_progress_workflow();

            return $this->response->setJSON([
                "success" => true,
                "message" => "Commercial bids unlocked successfully. Tender moved to commercial stage."
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Your confirmation was saved. Waiting for other committee keys."
        ]);
    }

    private function _get_committee_stage_tender(int $tender_id)
    {
        $rows = $this->Tender_bids_model->get_tenders_ready_for_3key_opening((int) $this->login_user->id);
        foreach ($rows as $row) {
            if ((int) $row->id === $tender_id) {
                return $row;
            }
        }

        return null;
    }

    private function _get_current_committee_role(int $tender_id): ?string
    {
        $db = db_connect();
        $ttm = $db->prefixTable("tender_team_members");

        $row = $db->query(
            "SELECT team_role
             FROM $ttm
             WHERE tender_id=?
               AND user_id=?
               AND deleted=0
               AND is_active=1
               AND team_role IN ('chairman','secretary','itc_member')
             ORDER BY id ASC
             LIMIT 1",
            [$tender_id, (int) $this->login_user->id]
        )->getRow();

        return $row->team_role ?? null;
    }
}