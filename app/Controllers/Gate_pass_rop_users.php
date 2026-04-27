<?php

namespace App\Controllers;

use App\Models\Gate_pass_rop_users_model;
use App\Models\Gate_pass_companies_model;

class Gate_pass_rop_users extends Security_Controller
{
    protected $Gate_pass_rop_users_model;
    protected $Gate_pass_companies_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Gate_pass_rop_users_model = new Gate_pass_rop_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->db = db_connect();
    }

    public function index()
    {
        $this->access_only_gate_pass("rop_users", "view");
        return $this->template->rander("gate_pass_rop_users/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("rop_users", $id ? "update" : "create");

        $model_info = null;
        if ($id) {
            $model_info = $this->Gate_pass_rop_users_model->get_details(["id" => $id])->getRow();
        }

        $company_dropdown = ["0" => "- " . app_lang("select_company") . " -"];
        $companies = $this->Gate_pass_companies_model->get_details()->getResult();
        foreach ($companies as $c) {
            $company_dropdown[$c->id] = $c->name;
        }

        $view_data = [
            "model_info" => $model_info,
            "company_dropdown" => $company_dropdown,
        ];
        return $this->template->view("gate_pass_rop_users/modal_form", $view_data);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "email" => "required|valid_email",
            "status" => "required",
        ]);

        $id = (int)$this->request->getPost("id");
        $this->access_only_gate_pass("rop_users", $id ? "update" : "create");
        $company_id = (int)$this->request->getPost("company_id");

        return $this->save_operational_user_assignment(
            $this->Gate_pass_rop_users_model,
            "gate_pass_rop_users",
            ["company_id" => $company_id],
            ["job_title" => "Gate Pass ROP User"]
        );
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("rop_users", "delete");
        $id = (int)$this->request->getPost("id");

        $pivot = $this->Gate_pass_rop_users_model->get_one($id);
        if (!$pivot || (int)$pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();
        $this->Gate_pass_rop_users_model->delete($id);
        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }

    public function list_data()
    {
        $this->access_only_gate_pass("rop_users", "view");
        $list_data = $this->Gate_pass_rop_users_model->get_details()->getResult();
        $result = [];
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }
        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_row($data)
    {
        $name = trim(($data->first_name ?? "") . " " . ($data->last_name ?? ""));
        if ($name === "") $name = "-";

        $options = "";
        if (!empty($data->id)) {
            $options .= modal_anchor(
                get_uri("gate_pass_rop_users/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            );
            $options .= js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("gate_pass_rop_users/delete"), "data-action" => "delete-confirmation"]
            );
        }

        return [
            $data->company_name ?? "-",
            $name,
            $data->email ?? "-",
            $data->phone ?? "-",
            ucfirst($data->status ?? "inactive"),
            $options,
        ];
    }
}
