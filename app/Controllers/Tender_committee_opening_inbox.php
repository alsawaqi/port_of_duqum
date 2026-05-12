<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_bid_openings_model;
use App\Models\Tenders_model;

class Tender_committee_opening_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_bid_openings_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
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
            $opening_stage = (string) ($row->opening_stage ?? "technical");
            $session = $this->Tender_bid_openings_model->get_active_session((int) $row->id, $opening_stage);
            $status = $session ? $session->status : "pending";
            $stage_label = "Bid Opening";

            $result[] = [
                esc($row->reference),
                esc($row->title),
                esc($stage_label),
                (int) $row->bids_count,
                "<span class='badge bg-secondary'>" . esc(ucfirst($status)) . "</span>",
                !empty($row->opening_end_at) ? format_to_datetime($row->opening_end_at) : "-",
                modal_anchor(
                    get_uri("tender_committee_opening_inbox/modal_form"),
                    "<i data-feather='unlock' class='icon-16'></i>",
                    [
                        "title" => "3-Key " . $stage_label,
                        "data-post-id" => $row->id,
                        "data-post-stage" => $opening_stage,
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
        $stage = $this->_normalize_opening_stage($this->request->getPost("stage"));
        $tender = $this->_get_committee_stage_tender($tender_id, $stage);

        if (!$tender) {
            show_404();
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        $confirm_map = $session ? $this->Tender_bid_openings_model->get_confirmation_map((int) $session->id) : [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];
        $signature_map = $session ? $this->Tender_bid_openings_model->get_signature_map((int) $session->id) : [
            "chairman" => 0,
            "secretary" => 0,
            "itc_member" => 0,
        ];

        $role = $this->_get_current_committee_role($tender_id);

        return $this->template->view("tender_committee_opening_inbox/modal_form", [
            "tender" => $tender,
            "session" => $session,
            "confirm_map" => $confirm_map,
            "signature_map" => $signature_map,
            "bid_summary" => $this->Tender_bid_openings_model->get_bid_summary_for_opening($tender_id),
            "signature_rows" => $session ? $this->Tender_bid_openings_model->get_signature_rows((int) $session->id) : [],
            "my_role" => $role,
            "opening_stage" => $stage,
            "opening_title" => "Bid Opening",
        ]);
    }

    function generate_codes()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $this->Tenders_model->auto_progress_workflow();
        $tender_id = (int) $this->request->getPost("tender_id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));

        if (!$this->_get_current_committee_role($tender_id)) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the requested 3-key stage."]);
        }

        $id = $this->Tender_bid_openings_model->create_new_session($tender_id, (int) $this->login_user->id, $stage);

        return $this->response->setJSON([
            "success" => true,
            "message" => "3-key bid opening codes generated.",
            "opening_id" => $id
        ]);
    }

    function confirm_codes()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
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
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));
        $role = $this->_get_current_committee_role($tender_id);

        if (!$role) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the requested 3-key stage."]);
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
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

            return $this->response->setJSON([
                "success" => true,
                "message" => "Bids unlocked successfully. Committee members can now review the full bid package and sign the opening form."
            ]);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => "Your confirmation was saved. Waiting for other committee keys."
        ]);
    }

    function sign_opening()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "opening_stage" => "required",
            "committee_signature_statement" => "required",
        ]);
        $this->access_only_tender("committee", "update");

        if (!$this->can_tender_3key_opening()) {
            app_redirect("forbidden");
        }

        $tender_id = (int) $this->request->getPost("tender_id");
        $stage = $this->_normalize_opening_stage($this->request->getPost("opening_stage"));
        $role = $this->_get_current_committee_role($tender_id);

        if (!$role) {
            return $this->response->setJSON(["success" => false, "message" => "You are not assigned to this ITC opening."]);
        }

        $tender = $this->_get_committee_stage_tender($tender_id, $stage);
        if (!$tender) {
            return $this->response->setJSON(["success" => false, "message" => "This tender is not currently in the bid opening signature stage."]);
        }

        $session = $this->Tender_bid_openings_model->get_active_session($tender_id, $stage);
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed"], true)) {
            return $this->response->setJSON(["success" => false, "message" => "The bid package must be unlocked before signing."]);
        }

        $statement = trim((string) $this->request->getPost("committee_signature_statement"));
        $signature_name = trim((string) $this->request->getPost("signature_name"));
        if ($signature_name === "") {
            $signature_name = trim((string) ($this->login_user->first_name ?? "") . " " . (string) ($this->login_user->last_name ?? ""));
        }
        if ($signature_name === "") {
            $signature_name = $this->login_user->email ?? ("User #" . (int) $this->login_user->id);
        }

        $saved = $this->Tender_bid_openings_model->save_signature(
            (int) $session->id,
            (int) $this->login_user->id,
            $role,
            $statement,
            $signature_name
        );

        if (!$saved) {
            return $this->response->setJSON(["success" => false, "message" => "Confirm the 3-key codes before signing the opening form."]);
        }

        $complete = $this->Tender_bid_openings_model->all_required_signatures_completed((int) $session->id);

        return $this->response->setJSON([
            "success" => true,
            "message" => $complete
                ? "All committee signatures are complete. Procurement can now download the bid opening form and start technical review."
                : "Your signature was saved. Waiting for the remaining committee signatures.",
            "reload" => true,
        ]);
    }

    function download_bid_document($id = 0)
    {
        $this->access_only_tender("committee", "view");
        $doc_id = (int) $id;
        if (!$doc_id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($doc_id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        $db = db_connect();
        $tb = $db->prefixTable("tender_bids");
        $t = $db->prefixTable("tenders");
        $ttm = $db->prefixTable("tender_team_members");

        $context = $db->query(
            "SELECT $t.id AS tender_id
             FROM $tb
             INNER JOIN $t
                ON $t.id = $tb.tender_id
               AND $t.deleted = 0
               AND $t.status = 'closed'
               AND $t.workflow_stage = 'technical_3key'
             INNER JOIN $ttm
                ON $ttm.tender_id = $t.id
               AND $ttm.deleted = 0
               AND $ttm.is_active = 1
               AND $ttm.user_id = ?
               AND $ttm.team_role IN ('chairman', 'secretary', 'itc_member')
             WHERE $tb.deleted = 0
               AND $tb.id = ?
             LIMIT 1",
            [(int) $this->login_user->id, (int) $doc->tender_bid_id]
        )->getRow();

        if (!$context) {
            app_redirect("forbidden");
        }

        $session = $this->Tender_bid_openings_model->get_active_session((int) $context->tender_id, "technical");
        if (!$session || !in_array((string) $session->status, ["unlocked", "signed"], true)) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($doc->original_name ?: basename($full_path));
    }

    private function _get_committee_stage_tender(int $tender_id, string $stage)
    {
        $rows = $this->Tender_bids_model->get_tenders_ready_for_3key_opening((int) $this->login_user->id);
        foreach ($rows as $row) {
            if ((int) $row->id === $tender_id && (string) ($row->opening_stage ?? "") === $stage) {
                return $row;
            }
        }

        return null;
    }

    private function _normalize_opening_stage($stage): string
    {
        $stage = strtolower(trim((string) $stage));
        return $stage === "technical" ? "technical" : "technical";
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
