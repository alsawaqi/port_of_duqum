<?php

namespace App\Controllers;

use App\Models\Gate_pass_companies_model;
use App\Models\Ptw_applicant_users_model;

class Ptw_applicant_users extends Security_Controller
{
    protected Ptw_applicant_users_model $Ptw_applicant_users_model;
    protected Gate_pass_companies_model $Gate_pass_companies_model;
    protected $db;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Ptw_applicant_users_model = new Ptw_applicant_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->db = db_connect();
    }

    public function index()
    {
        $this->access_only_ptw("applicant_users", "view");
        return $this->template->rander("ptw_applicant_users/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int) $this->request->getPost("id");
        $this->access_only_ptw("applicant_users", $id ? "update" : "create");
        $modelInfo = $id
            ? $this->Ptw_applicant_users_model->get_details(["id" => $id])->getRow()
            : null;

        $companies = ["0" => "- " . app_lang("select_company") . " -"];
        foreach ($this->Gate_pass_companies_model->get_details()->getResult() as $company) {
            $companies[$company->id] = $company->name;
        }

        return $this->template->view("ptw_applicant_users/modal_form", [
            "model_info" => $modelInfo,
            "company_dropdown" => $companies,
        ]);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "email" => "required|valid_email",
            "status" => "required|in_list[active,inactive]",
        ]);
        $id = (int) $this->request->getPost("id");
        $this->access_only_ptw("applicant_users", $id ? "update" : "create");

        $companyId = (int) $this->request->getPost("company_id");
        if (!$this->Gate_pass_companies_model->get_details(["id" => $companyId])->getRow()) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        return $this->save_operational_user_assignment(
            $this->Ptw_applicant_users_model,
            "ptw_applicant_users",
            ["company_id" => $companyId],
            ["job_title" => "PTW Applicant"]
        );
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_ptw("applicant_users", "delete");
        $id = (int) $this->request->getPost("id");
        $assignment = $this->Ptw_applicant_users_model->get_one($id);
        if (!$assignment || empty($assignment->id) || (int) $assignment->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $saved = $this->Ptw_applicant_users_model->ci_save([
            "status" => "inactive",
            "deleted" => 0,
            "updated_at" => get_current_utc_time(),
        ], $id);

        return $this->response->setJSON([
            "success" => (bool) $saved,
            "message" => $saved ? app_lang("record_saved") : app_lang("error_occurred"),
        ]);
    }

    public function list_data()
    {
        $this->access_only_ptw("applicant_users", "view");
        $result = [];
        foreach ($this->Ptw_applicant_users_model->get_details()->getResult() as $row) {
            $name = trim(($row->first_name ?? "") . " " . ($row->last_name ?? "")) ?: "-";
            $actions = modal_anchor(
                get_uri("ptw_applicant_users/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $row->id]
            );
            $actions .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
                "class" => "delete",
                "title" => app_lang("delete"),
                "data-id" => $row->id,
                "data-action-url" => get_uri("ptw_applicant_users/delete"),
                "data-action" => "delete-confirmation",
            ]);
            $result[] = [
                $row->company_name ?? "-",
                $name,
                $row->email ?? "-",
                $row->phone ?? "-",
                ucfirst((string) ($row->status ?? "inactive")),
                $actions,
            ];
        }

        return $this->response->setJSON(["data" => $result]);
    }
}
