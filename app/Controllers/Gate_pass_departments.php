<?php

namespace App\Controllers;

use App\Models\Gate_pass_departments_model;
use App\Models\Gate_pass_companies_model;

class Gate_pass_departments extends Security_Controller
{
    protected $Gate_pass_departments_model;
    protected $Gate_pass_companies_model;

    function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Gate_pass_departments_model = new Gate_pass_departments_model();
        $this->Gate_pass_companies_model   = new Gate_pass_companies_model();
    }

    function index()
    {
        $this->access_only_gate_pass("departments", "view");
        return $this->template->rander("gate_pass_departments/index");
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("departments", $id ? "update" : "create");

        $model_info = $this->Gate_pass_departments_model->get_one($id);

        // companies for dropdown
        $companies = $this->Gate_pass_companies_model->get_details()->getResult();
        $company_dropdown = ["" => "- " . app_lang("select") . " -"];
        foreach ($companies as $c) {
                $company_dropdown[$c->id] = $c->name . " (" . $c->code . ")";
            }

        $view_data["model_info"] = $model_info;
        $view_data["company_dropdown"] = $company_dropdown;

        return $this->template->view("gate_pass_departments/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "name" => "required",
            "code" => "required"
        ]);

        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("departments", $id ? "update" : "create");

        $data = [
            "company_id" => (int)$this->request->getPost("company_id"),
            "name" => $this->request->getPost("name"),
            "code" => strtoupper(trim($this->request->getPost("code"))),
            "is_active" => $this->request->getPost("is_active") ? 1 : 0
        ];

        $data = clean_data($data);
        $save_id = $this->Gate_pass_departments_model->ci_save($data, $id);

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
        $this->access_only_gate_pass("departments", "view");
        $list_data = $this->Gate_pass_departments_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("departments", "delete");
        $id = $this->request->getPost("id");

        if ($this->Gate_pass_departments_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
        }
    }

    // Optional: For Gate Pass Request form later (Company -> Departments)
    function departments_by_company($company_id = 0)
    {
        $rows = $this->Gate_pass_departments_model->get_details(["company_id" => (int)$company_id])->getResult();
        $out = [];
        foreach ($rows as $r) {
            if ((int)$r->is_active !== 1) continue;
            $out[] = ["id" => (int)$r->id, "text" => $r->name];
        }
        echo json_encode($out);
    }

    private function _row_data($id)
    {
        $data = $this->Gate_pass_departments_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $status = $data->is_active
            ? "<span class='badge bg-success'>" . app_lang("active") . "</span>"
            : "<span class='badge bg-secondary'>" . app_lang("inactive") . "</span>";

        $options = modal_anchor(
            get_uri("gate_pass_departments/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "title" => app_lang("delete"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("gate_pass_departments/delete"),
                "data-action" => "delete-confirmation"
            ]
        );

        return [
            $data->company_name . " (" . $data->company_code . ")",
            $data->name,
            $data->code,
            $status,
            $options
        ];
    }
}
