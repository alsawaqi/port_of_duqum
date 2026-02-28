<?php

namespace App\Controllers;

use App\Models\Gate_pass_companies_model;

class Gate_pass_companies extends Security_Controller
{
    protected $Gate_pass_companies_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
    }

    function index()
    {
        $this->access_only_gate_pass("companies", "view");
        return $this->template->rander("gate_pass_companies/index");
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("companies", $id ? "update" : "create");

        $view_data["model_info"] = $this->Gate_pass_companies_model->get_one($id);
        return $this->template->view("gate_pass_companies/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "name" => "required",
            "code" => "required"
        ]);

        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("companies", $id ? "update" : "create");

        $data = [
            "name" => $this->request->getPost("name"),
            "code" => strtoupper(trim($this->request->getPost("code"))),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0
        ];

        $data = clean_data($data);
        $save_id = $this->Gate_pass_companies_model->ci_save($data, $id);

        if ($save_id) {
            echo json_encode([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    function list_data()
    {
        $this->access_only_gate_pass("companies", "view");
        $list_data = $this->Gate_pass_companies_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("companies", "delete");

        $id = $this->request->getPost("id");
        if ($this->Gate_pass_companies_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
        }
    }

    private function _row_data($id)
    {
        $data = $this->Gate_pass_companies_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $options = modal_anchor(
            get_uri("gate_pass_companies/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "title" => app_lang("delete"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("gate_pass_companies/delete"),
                "data-action" => "delete-confirmation"
            ]
        );

        return [
            $data->name,
            $data->code,
            $status,
            $options
        ];
    }
}
