<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tenders_model;

class Tender_commercial_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_evaluations_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("commercial_eval", "view");
        return $this->template->rander("tender_commercial_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("commercial_eval", "view");

        $this->Tenders_model->auto_progress_workflow();

        $list = $this->Tender_bids_model->get_unlocked_tenders_for_commercial_user((int) $this->login_user->id);

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function details($id = 0)
    {
        $this->access_only_tender("commercial_eval", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("tender_commercial_inbox");
        }

        $bids = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, (int) $this->login_user->id);

        $pending_bids = [];
        $my_finalized_bids = [];
        $locked_bids = [];
        $approved_count = 0;
        $rejected_count = 0;

        foreach ($bids as $bid) {
            $decision = strtolower(trim((string) ($bid->commercial_decision ?? "")));

            if ($decision === "accepted") {
                $approved_count++;
            } elseif ($decision === "rejected") {
                $rejected_count++;
            }

            if ($decision === "") {
                $pending_bids[] = $bid;
            } elseif ((int) ($bid->decision_evaluator_id ?? 0) === (int) $this->login_user->id) {
                $my_finalized_bids[] = $bid;
            } else {
                $locked_bids[] = $bid;
            }
        }

        $pending_count = count($pending_bids);
        $ready_for_award = $pending_count === 0 && $approved_count === 1;

        return $this->template->rander("tender_commercial_inbox/details", [
            "tender"            => $tender,
            "pending_bids"      => $pending_bids,
            "my_finalized_bids" => $my_finalized_bids,
            "locked_bids"       => $locked_bids,
            "pending_count"     => $pending_count,
            "approved_count"    => $approved_count,
            "rejected_count"    => $rejected_count,
            "ready_for_award"   => $ready_for_award,
        ]);
    }

    function bid_modal_form()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric"
        ]);
        $this->access_only_tender("commercial_eval", "view");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, $user_id);
        if (!$tender) {
            show_404();
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $user_id);
        if (!$bid) {
            show_404();
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "commercial");
        $owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $latest_decision = strtolower(trim((string) ($latest_evaluation->decision ?? "")));

        $editable = false;
        if ($latest_decision === "") {
            $editable = true;
        } elseif ($owner_id === $user_id && !empty($latest_evaluation->id)) {
            $editable = true;
        }

        $active_evaluation = null;
        if ($editable && !empty($latest_evaluation->id) && $owner_id === $user_id) {
            $active_evaluation = $latest_evaluation;
        } elseif ($editable) {
            $active_evaluation = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator($tender_id, $bid_id, $user_id, "commercial");
        } else {
            $active_evaluation = $latest_evaluation;
        }

        return $this->template->view("tender_commercial_inbox/bid_modal_form", [
            "tender"            => $tender,
            "bid"               => $bid,
            "active_evaluation" => $active_evaluation,
            "latest_evaluation" => $latest_evaluation,
            "editable"          => $editable,
        ]);
    }

    function save_bid_evaluation()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric",
        ]);
        $this->access_only_tender("commercial_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $evaluator_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user($tender_id, $evaluator_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to review it."
            ]);
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $evaluator_id);
        if (!$bid) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid not found or you do not have access to review it."
            ]);
        }

        $decision = strtolower(trim((string) $this->request->getPost("decision")));
        $evaluation_comment = trim((string) $this->request->getPost("evaluation_comment"));
        $commercial_score_raw = $this->request->getPost("commercial_score");

        if (!in_array($decision, ["accepted", "rejected"], true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please choose whether the bid is commercially approved or rejected."
            ]);
        }

        if ($commercial_score_raw === "" || $commercial_score_raw === null) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please enter the commercial score."
            ]);
        }

        if (!is_numeric($commercial_score_raw)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Commercial score must be a valid number."
            ]);
        }

        $commercial_score = round((float) $commercial_score_raw, 3);
        if ($commercial_score < 0 || $commercial_score > 100) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Commercial score must be between 0 and 100."
            ]);
        }

        $now = date("Y-m-d H:i:s");
        $db = db_connect();
        $db->transBegin();

        $fresh_bid = $this->Tender_bids_model->get_tender_bid_for_commercial_user($tender_id, $bid_id, $evaluator_id);
        if (!$fresh_bid) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid is no longer available."
            ]);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "commercial");
        $latest_owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $latest_decision = strtolower(trim((string) ($latest_evaluation->decision ?? "")));

        if ($latest_decision !== "" && $latest_owner_id !== $evaluator_id) {
            $owner_name = trim((string) ($latest_evaluation->evaluator_name ?? ""));
            if ($owner_name === "") {
                $owner_name = "another evaluator";
            }

            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "This bid was already finalized by {$owner_name}. You can no longer approve or reject it."
            ]);
        }

        // Only one commercial winner is allowed.
        if ($decision === "accepted") {
            $overview = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, $evaluator_id);

            foreach ($overview as $row) {
                if ((int) $row->id === $bid_id) {
                    continue;
                }

                if (strtolower(trim((string) ($row->commercial_decision ?? ""))) === "accepted") {
                    $db->transRollback();
                    return $this->response->setJSON([
                        "success" => false,
                        "message" => "Another bid has already been commercially approved. Reject that bid first if you want to choose this vendor instead."
                    ]);
                }
            }
        }

        $existing = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator(
            $tender_id,
            $bid_id,
            $evaluator_id,
            "commercial"
        );

        $evaluation_data = [
            "tender_id"     => $tender_id,
            "tender_bid_id" => $bid_id,
            "evaluator_id"  => $evaluator_id,
            "type"          => "commercial",
            "status"        => "submitted",
            "decision"      => $decision,
            "total_score"   => $commercial_score,
            "comments"      => $evaluation_comment ?: null,
            "submitted_at"  => $now,
            "updated_at"    => $now,
            "deleted"       => 0,
        ];

        if (!empty($existing->id)) {
            $evaluation_id = (int) $this->Tender_evaluations_model->ci_save($evaluation_data, (int) $existing->id);
        } else {
            $evaluation_data["created_at"] = $now;
            $evaluation_id = (int) $this->Tender_evaluations_model->ci_save($evaluation_data);
        }

        if (!$evaluation_id) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $overview_after = $this->Tender_bids_model->get_tender_bids_overview_for_commercial_user($tender_id, $evaluator_id);
        $pending_count = 0;
        $approved_count = 0;
        $awarded_bid_id = 0;

        foreach ($overview_after as $row) {
            $row_decision = strtolower(trim((string) ($row->commercial_decision ?? "")));

            if ($row_decision === "") {
                $pending_count++;
            } elseif ($row_decision === "accepted") {
                $approved_count++;
                $awarded_bid_id = (int) $row->id;
            }
        }

        $message = "Commercial evaluation saved successfully.";
        $redirect_url = null;

        if ($pending_count === 0) {
            $this->Tenders_model->ci_save([
                "workflow_stage" => "award_decision",
                "award_ready_at" => $now,
                "updated_at"     => $now,
            ], $tender_id);

            if ($approved_count === 1) {
                $message = "Commercial evaluation saved successfully. Commercial review is complete and the tender is now waiting for Procurement final award decision.";
            } else {
                $message = "Commercial evaluation saved successfully. Commercial review is complete, but no valid winner is available. Procurement must cancel or retender.";
            }
            $redirect_url = get_uri("tender_commercial_inbox");
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $db->transCommit();

        $response = [
            "success" => true,
            "message" => $message
        ];
        
        if ($redirect_url) {
            $response["redirect_url"] = $redirect_url;
        }
        
        return $this->response->setJSON($response);
    }

    public function download_bid_document($id = 0)
    {
        $this->access_only_tender("commercial_eval", "view");
        $this->Tenders_model->auto_progress_workflow();

        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        if (($doc->section ?? "") !== "commercial") {
            app_redirect("forbidden");
        }

        $db = db_connect();
        $tb = $db->prefixTable("tender_bids");

        $bid = $db->query(
            "SELECT *
             FROM $tb
             WHERE id=? AND deleted=0
             LIMIT 1",
            [(int) $doc->tender_bid_id]
        )->getRow();

        if (!$bid) {
            show_404();
        }

        $tender = $this->Tender_bids_model->get_unlocked_tender_for_commercial_user((int) $bid->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        return $this->response->download($full_path, null)->setFileName($doc->original_name ?: basename($full_path));
    }

    private function _make_row($row)
    {
        $stage = trim((string) ($row->workflow_stage ?? "commercial"));
        $stage_label = ucwords(str_replace("_", " ", $stage));
        $status = "<span class='badge bg-info text-dark'>" . esc($stage_label) . "</span>";

        $review = anchor(
            get_uri("tender_commercial_inbox/details/" . (int) $row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            [
                "title" => "Open Commercial Evaluation Page",
                "class" => "edit"
            ]
        );

        return [
            esc($row->reference ?? "-"),
            esc($row->title ?? "-"),
            esc($row->tender_type ?? "-"),
            $status,
            !empty($row->commercial_end_at) ? format_to_datetime($row->commercial_end_at) : "-",
            (int) ($row->accepted_bids_count ?? 0),
            $review
        ];
    }
}