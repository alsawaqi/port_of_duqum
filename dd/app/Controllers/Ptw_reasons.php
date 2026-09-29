<?php

namespace App\Controllers;

use App\Models\Ptw_reasons_model;

class Ptw_reasons extends Security_Controller
{
    protected $Ptw_reasons_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Ptw_reasons_model = new Ptw_reasons_model();
    }

    public function index()
    {
        $this->access_only_ptw("reasons", "view");
        return $this->template->rander("ptw_reasons/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int)$this->request->getPost("id");
        $this->access_only_ptw("reasons", $id ? "update" : "create");
        $model_info = $id ? $this->Ptw_reasons_model->get_details(["id" => $id])->getRow() : null;

        return $this->template->view("ptw_reasons/modal_form", [
            "model_info" => $model_info,
        ]);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "stage" => "required",
            "reason_type" => "required",
            "title" => "required|max_length[255]",
            "sort_order" => "permit_empty|numeric",
        ]);

        $id = (int)$this->request->getPost("id");
        $this->access_only_ptw("reasons", $id ? "update" : "create");

        $allowedStages = ["hsse", "hmo", "terminal", "any"];
        $allowedTypes = ["revise", "reject"];

        $stage = strtolower(trim((string)$this->request->getPost("stage")));
        $reason_type = strtolower(trim((string)$this->request->getPost("reason_type")));

        if (!in_array($stage, $allowedStages, true) || !in_array($reason_type, $allowedTypes, true)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $data = [
            "stage" => $stage,
            "reason_type" => $reason_type,
            "title" => trim((string)$this->request->getPost("title")),
            "sort_order" => (int)$this->request->getPost("sort_order"),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0,
        ];

        $data = clean_data($data);
        $save_id = $this->Ptw_reasons_model->ci_save($data, $id);

        if ($save_id) {
            return $this->response->setJSON([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved"),
            ]);
        }

        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("error_occurred"),
        ]);
    }

    public function list_data()
    {
        $this->access_only_ptw("reasons", "view");

        $list = $this->Ptw_reasons_model->get_details()->getResult();

        $result = [];
        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_ptw("reasons", "delete");

        $id = (int)$this->request->getPost("id");

        if ($this->Ptw_reasons_model->delete($id)) {
            return $this->response->setJSON([
                "success" => true,
                "message" => app_lang("record_deleted"),
            ]);
        }

        return $this->response->setJSON([
            "success" => false,
            "message" => app_lang("record_cannot_be_deleted"),
        ]);
    }

    private function _row_data($id)
    {
        $row = $this->Ptw_reasons_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($row);
    }

    private function _make_row($data)
    {
        $status = !empty($data->is_active)
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $type_badge = strtolower((string)$data->reason_type) === "revise"
            ? "<span class='badge bg-warning text-dark'>Revise</span>"
            : "<span class='badge bg-danger'>Reject</span>";

        $stage_badge = "<span class='badge bg-info'>" . strtoupper((string)$data->stage) . "</span>";

        $options = modal_anchor(
            get_uri("ptw_reasons/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "class" => "delete",
                "title" => app_lang("delete"),
                "data-id" => $data->id,
                "data-action-url" => get_uri("ptw_reasons/delete"),
                "data-action" => "delete-confirmation",
            ]
        );

        return [
            esc($data->title ?? "-"),
            $stage_badge,
            $type_badge,
            (int)($data->sort_order ?? 0),
            $status,
            $options,
        ];
    }
}