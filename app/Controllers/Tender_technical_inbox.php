<?php

namespace App\Controllers;

use App\Models\Tender_bids_model;
use App\Models\Tender_bid_documents_model;
use App\Models\Tender_criteria_model;
use App\Models\Tender_evaluations_model;
use App\Models\Tender_evaluation_scores_model;
use App\Models\Tenders_model;

class Tender_technical_inbox extends Security_Controller
{
    protected $Tender_bids_model;
    protected $Tender_bid_documents_model;
    protected $Tender_criteria_model;
    protected $Tender_evaluations_model;
    protected $Tender_evaluation_scores_model;
    protected $Tenders_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Tender_bids_model = new Tender_bids_model();
        $this->Tender_bid_documents_model = new Tender_bid_documents_model();
        $this->Tender_criteria_model = new Tender_criteria_model();
        $this->Tender_evaluations_model = new Tender_evaluations_model();
        $this->Tender_evaluation_scores_model = new Tender_evaluation_scores_model();
        $this->Tenders_model = new Tenders_model();
    }

    function index()
    {
        $this->access_only_tender("technical_eval", "view");
        return $this->template->rander("tender_technical_inbox/index");
    }

    function list_data()
    {
        $this->access_only_tender("technical_eval", "view");

        $this->Tenders_model->auto_progress_workflow();

        $list = $this->Tender_bids_model->get_closed_tenders_for_technical_user((int) $this->login_user->id);

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    function details($id = 0)
    {
        $this->access_only_tender("technical_eval", "view");

        $tender_id = (int) $id;
        if (!$tender_id) {
            show_404();
        }

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, (int) $this->login_user->id);
        if (!$tender) {
            show_404();
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        $bids = $this->Tender_bids_model->get_tender_bids_overview_for_technical_user($tender_id, (int) $this->login_user->id);

        $pending_bids = [];
        $my_finalized_bids = [];
        $locked_bids = [];
        $accepted_count = 0;
        $rejected_count = 0;

        foreach ($bids as $bid) {
            $status = strtolower((string) ($bid->status ?? "submitted"));
            if ($status === "accepted") {
                $accepted_count++;
            } elseif ($status === "rejected") {
                $rejected_count++;
            }

            if ($status === "submitted") {
                $pending_bids[] = $bid;
            } elseif ((int) ($bid->decision_evaluator_id ?? 0) === (int) $this->login_user->id) {
                $my_finalized_bids[] = $bid;
            } else {
                $locked_bids[] = $bid;
            }
        }

        $stage_max_score = 0;
        foreach ($criteria as $criterion) {
            $stage_max_score += (float) ($criterion->weight ?? 0);
        }

        return $this->template->rander("tender_technical_inbox/details", [
            "tender"            => $tender,
            "criteria"          => $criteria,
            "stage_max_score"   => $stage_max_score,
            "pending_bids"      => $pending_bids,
            "my_finalized_bids" => $my_finalized_bids,
            "locked_bids"       => $locked_bids,
            "pending_count"     => count($pending_bids),
            "accepted_count"    => $accepted_count,
            "rejected_count"    => $rejected_count,
        ]);
    }

    function bid_modal_form()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric"
        ]);
        $this->access_only_tender("technical_eval", "view");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $user_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, $user_id);
        if (!$tender) {
            show_404();
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $user_id);
        if (!$bid) {
            show_404();
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        $stage_max_score = 0;
        foreach ($criteria as $criterion) {
            $stage_max_score += (float) ($criterion->weight ?? 0);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "technical");
        $owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $status = strtolower((string) ($bid->status ?? "submitted"));

        $editable = false;
        if ($status === "submitted") {
            $editable = true;
        } elseif (in_array($status, ["accepted", "rejected"], true) && $owner_id === $user_id && !empty($latest_evaluation->id)) {
            $editable = true;
        }

        $active_evaluation = null;
        if ($editable && !empty($latest_evaluation->id) && $owner_id === $user_id) {
            $active_evaluation = $latest_evaluation;
        } elseif ($editable) {
            $active_evaluation = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator($tender_id, $bid_id, $user_id, "technical");
        } else {
            $active_evaluation = $latest_evaluation;
        }

        $scores_by_criterion = [];
        if (!empty($active_evaluation->id)) {
            $grouped = $this->Tender_evaluation_scores_model->get_grouped_by_evaluation_ids([(int) $active_evaluation->id]);
            $scores_by_criterion = get_array_value($grouped, (int) $active_evaluation->id) ?: [];
        }

        return $this->template->view("tender_technical_inbox/bid_modal_form", [
            "tender"              => $tender,
            "bid"                 => $bid,
            "criteria"            => $criteria,
            "stage_max_score"     => $stage_max_score,
            "active_evaluation"   => $active_evaluation,
            "scores_by_criterion" => $scores_by_criterion,
            "latest_evaluation"   => $latest_evaluation,
            "editable"            => $editable,
        ]);
    }

    function save_bid_evaluation()
    {
        $this->validate_submitted_data([
            "tender_id" => "required|numeric",
            "bid_id"    => "required|numeric",
        ]);
        $this->access_only_tender("technical_eval", "update");

        $tender_id = (int) $this->request->getPost("tender_id");
        $bid_id = (int) $this->request->getPost("bid_id");
        $evaluator_id = (int) $this->login_user->id;

        $this->Tenders_model->auto_progress_workflow();

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user($tender_id, $evaluator_id);
        if (!$tender) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Tender not found or you do not have access to review it."
            ]);
        }

        $bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $evaluator_id);
        if (!$bid) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid not found or you do not have access to review it."
            ]);
        }

        $criteria = $this->Tender_criteria_model->ensure_stage_criteria($tender_id, "technical");
        if (empty($criteria)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "No technical criteria found for this tender."
            ]);
        }

        $decision = strtolower(trim((string) $this->request->getPost("decision")));
        $scores_input = (array) $this->request->getPost("scores");
        $criterion_comments_input = (array) $this->request->getPost("criterion_comments");
        $evaluation_comment = trim((string) $this->request->getPost("evaluation_comment"));

        if (!in_array($decision, ["accepted", "rejected"], true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "Please choose whether the bid is technically accepted or rejected."
            ]);
        }

        $score_rows = [];
        $total_score = 0;
        foreach ($criteria as $criterion) {
            $criterion_id = (int) $criterion->id;
            $criterion_name = (string) ($criterion->name ?? ("Criterion #" . $criterion_id));
            $max_score = round((float) ($criterion->weight ?? 0), 3);
            $raw_score = get_array_value($scores_input, $criterion_id);

            if ($raw_score === "" || $raw_score === null) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Please enter a score for '{$criterion_name}'."
                ]);
            }

            if (!is_numeric($raw_score)) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Invalid score for '{$criterion_name}'."
                ]);
            }

            $score = round((float) $raw_score, 3);
            if ($score < 0 || $score > $max_score) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "Score for '{$criterion_name}' must be between 0 and {$max_score}."
                ]);
            }

            $total_score += $score;
            $score_rows[] = [
                "tender_criterion_id" => $criterion_id,
                "score"               => $score,
                "comment"             => trim((string) get_array_value($criterion_comments_input, $criterion_id)),
            ];
        }

        $now = date("Y-m-d H:i:s");
        $db = db_connect();
        $db->transBegin();

        $fresh_bid = $this->Tender_bids_model->get_tender_bid_for_technical_user($tender_id, $bid_id, $evaluator_id);
        if (!$fresh_bid) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => "Bid is no longer available."
            ]);
        }

        $latest_evaluation = $this->Tender_evaluations_model->get_latest_stage_evaluation_for_bid($bid_id, "technical");
        $latest_owner_id = (int) ($latest_evaluation->evaluator_id ?? 0);
        $fresh_status = strtolower((string) ($fresh_bid->status ?? "submitted"));

        if (in_array($fresh_status, ["accepted", "rejected"], true) && $latest_owner_id !== $evaluator_id) {
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

        $tb = $db->prefixTable("tender_bids");
        if ($fresh_status === "submitted") {
            $db->query(
                "UPDATE $tb
                 SET status = ?, updated_at = ?
                 WHERE id = ?
                   AND deleted = 0
                   AND status = 'submitted'",
                [$decision, $now, $bid_id]
            );

            if ((int) $db->affectedRows() !== 1) {
                $db->transRollback();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "This bid was already decided by another evaluator. Please refresh the page."
                ]);
            }
        } else {
            if (!in_array($fresh_status, ["accepted", "rejected"], true) || $latest_owner_id !== $evaluator_id) {
                $db->transRollback();
                return $this->response->setJSON([
                    "success" => false,
                    "message" => "This bid is locked and cannot be edited by you."
                ]);
            }

            $this->Tender_bids_model->ci_save([
                "status"     => $decision,
                "updated_at" => $now,
            ], $bid_id);
        }

        $existing = $this->Tender_evaluations_model->get_one_for_bid_and_evaluator(
            $tender_id,
            $bid_id,
            $evaluator_id,
            "technical"
        );

        $evaluation_data = [
            "tender_id"     => $tender_id,
            "tender_bid_id" => $bid_id,
            "evaluator_id"  => $evaluator_id,
            "type"          => "technical",
            "status"        => "submitted",
            "total_score"   => round($total_score, 3),
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

        $this->Tender_evaluation_scores_model->soft_delete_by_evaluation_id($evaluation_id);
        foreach ($score_rows as $row) {
            $this->Tender_evaluation_scores_model->ci_save([
                "tender_evaluation_id" => $evaluation_id,
                "tender_criterion_id"  => (int) $row["tender_criterion_id"],
                "score"                => $row["score"],
                "comment"              => $row["comment"] ?: null,
                "created_at"           => $now,
                "updated_at"           => $now,
                "deleted"              => 0,
            ]);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred")
            ]);
        }

        $db->transCommit();

        return $this->response->setJSON([
            "success" => true,
            "message" => "Technical evaluation saved successfully."
        ]);
    }

    public function download_bid_document($id = 0)
    {
        $this->access_only_tender("technical_eval", "view");

        $id = (int) $id;
        if (!$id) {
            show_404();
        }

        $doc = $this->Tender_bid_documents_model->get_one($id);
        if (!$doc || (int) ($doc->deleted ?? 0) === 1) {
            show_404();
        }

        if (($doc->section ?? "") !== "technical") {
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

        $tender = $this->Tender_bids_model->get_closed_tender_for_technical_user((int) $bid->tender_id, (int) $this->login_user->id);
        if (!$tender) {
            app_redirect("forbidden");
        }

        $full_path = WRITEPATH . "uploads/" . ltrim((string) $doc->path, "/");
        if (!is_file($full_path)) {
            show_404();
        }

        $download_name = $doc->original_name ?: basename($full_path);

        return $this->response->download($full_path, null)->setFileName($download_name);
    }

    private function _make_row($row)
{
    $stage = trim((string) ($row->workflow_stage ?? "technical"));
    $stage_label = ucwords(str_replace("_", " ", $stage));
    $status = "<span class='badge bg-info text-dark'>" . esc($stage_label) . "</span>";

    $review = anchor(
        get_uri("tender_technical_inbox/details/" . (int) $row->id),
        "<i data-feather='eye' class='icon-16'></i>",
        [
            "title" => "Open Technical Evaluation Page",
            "class" => "edit"
        ]
    );

    return [
        esc($row->reference ?? "-"),
        esc($row->title ?? "-"),
        esc($row->tender_type ?? "-"),
        $status,
        !empty($row->technical_end_at) ? format_to_datetime($row->technical_end_at) : (!empty($row->closing_at) ? format_to_datetime($row->closing_at) : "-"),
        (int) ($row->submitted_bids_count ?? 0),
        $review
    ];
}
}