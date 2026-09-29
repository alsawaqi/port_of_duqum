<?php

namespace App\Controllers;

use App\Models\Tender_department_manager_users_model;
use App\Models\Gate_pass_companies_model;
use App\Models\Gate_pass_departments_model;

class Tender_department_manager_users extends Security_Controller
{
    protected $Tender_department_manager_users_model;
    protected $Gate_pass_companies_model;
    protected $Gate_pass_departments_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->access_only_tender("department_manager_users", "view");

        $this->Tender_department_manager_users_model = new Tender_department_manager_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->Gate_pass_departments_model = new Gate_pass_departments_model();
        $this->db = db_connect();
    }

    private function _ensure_role(string $title, array $keys): int
    {
        $db = $this->db; // you already set $this->db = db_connect() in __construct()
        $roles = $db->prefixTable("roles");

        $row = $db->query(
            "SELECT id, permissions FROM $roles WHERE deleted=0 AND title=? LIMIT 1",
            [$title]
        )->getRow();

        $perms = [];
        if ($row && !empty($row->permissions)) {
            $tmp = @safe_unserialize($row->permissions);
            if (is_array($tmp)) {
                $perms = $tmp;
            }
        }

        foreach ($keys as $k) {
            $perms[$k] = "1";
        }

        $perm_str = serialize($perms);

        if (!$row) {
            $db->query(
                "INSERT INTO $roles (title, permissions, deleted) VALUES (?, ?, 0)",
                [$title, $perm_str]
            );
            return (int) $db->insertID();
        }

        // keep role updated (don’t remove other permissions)
        $db->query(
            "UPDATE $roles SET permissions=? WHERE id=?",
            [$perm_str, (int)$row->id]
        );

        return (int) $row->id;
    }

    public function index()
    {
        return $this->template->rander("tender_department_manager_users/index", [
            "can_create_tender_user" => $this->can_tender("department_manager_users", "create"),
        ]);
    }

    public function list_data()
    {
        $list = $this->Tender_department_manager_users_model->get_details()->getResult();
        $result = [];
        foreach ($list as $d) $result[] = $this->_make_row($d);
        return $this->response->setJSON(["data" => $result]);
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int)$this->request->getPost("id");
        $this->access_only_tender("department_manager_users", $id ? "update" : "create");

        $model_info = $id ? $this->Tender_department_manager_users_model->get_details(["id" => $id])->getRow() : null;

        $company_dropdown = ["0" => "- " . app_lang("select_company") . " -"];
        foreach ($this->Gate_pass_companies_model->get_details()->getResult() as $c) {
            $company_dropdown[$c->id] = $c->name;
        }

        $department_dropdown = ["0" => "- " . app_lang("select_department") . " -"];
        if ($model_info && (int)$model_info->company_id) {
            foreach ($this->Gate_pass_departments_model->get_details(["company_id" => (int)$model_info->company_id])->getResult() as $d) {
                $department_dropdown[$d->id] = $d->name;
            }
        }

        return $this->template->view("tender_department_manager_users/modal_form", [
            "model_info" => $model_info,
            "company_dropdown" => $company_dropdown,
            "department_dropdown" => $department_dropdown
        ]);
    }

    public function departments_by_company($company_id = 0)
    {
        $this->access_only_tender("department_manager_users", "view");

        $company_id = (int)($company_id ?: $this->request->getGet("company_id"));
        $options = ["results" => []];
        if (!$company_id) return $this->response->setJSON($options);

        foreach ($this->Gate_pass_departments_model->get_details(["company_id" => $company_id])->getResult() as $d) {
            $options["results"][] = ["id" => $d->id, "text" => $d->name];
        }
        return $this->response->setJSON($options);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "department_id" => "required|numeric",
            "email" => "required|valid_email",
            "status" => "required",
        ]);

        $id = (int)$this->request->getPost("id");
        $this->access_only_tender("department_manager_users", $id ? "update" : "create");
        $company_id = (int)$this->request->getPost("company_id");
        $department_id = (int)$this->request->getPost("department_id");

        // validate department belongs to company
        $dept = $this->Gate_pass_departments_model->get_one($department_id);
        if (!$dept || (int)$dept->company_id !== $company_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("invalid_department")]);
        }

        try {
        $role_id = $this->_ensure_role("Tender Department Manager", [
            "can_view_tender_manager_inbox",
            "can_update_tender_manager_inbox",
        ]);

        return $this->save_operational_user_assignment(
            $this->Tender_department_manager_users_model,
            "tender_department_manager_users",
            ["company_id" => $company_id, "department_id" => $department_id],
            ["job_title" => "Tender Department Manager", "role_id" => $role_id]
        );

        } catch (\Throwable $e) {
            $msg = (ENVIRONMENT !== 'production') ? $e->getMessage() : app_lang("error_occurred");
            return $this->response->setJSON(["success" => false, "message" => $msg]);
        }
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_tender("department_manager_users", "delete");
        $id = (int)$this->request->getPost("id");

        $pivot = $this->Tender_department_manager_users_model->get_one($id);
        if (!$pivot || (int)$pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();
        $this->Tender_department_manager_users_model->delete($id);
        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }

    private function _make_row($d)
    {
        $name = trim(($d->first_name ?? "") . " " . ($d->last_name ?? "")); if ($name === "") $name = "-";

        $options = "";
        if ($this->can_tender("department_manager_users", "update")) {
            $options .= modal_anchor(get_uri("tender_department_manager_users/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                "class" => "edit", "title" => app_lang("edit"), "data-post-id" => $d->id
            ]);
        }
        if ($this->can_tender("department_manager_users", "delete")) {
            $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
                "title" => app_lang("delete"), "class" => "delete", "data-id" => $d->id,
                "data-action-url" => get_uri("tender_department_manager_users/delete"), "data-action" => "delete-confirmation"
            ]);
        }

        return [
            $d->company_name ?? "-",
            $d->department_name ?? "-",
            $name,
            $d->email ?? "-",
            $d->phone ?? "-",
            ucfirst($d->status ?? "inactive"),
            $options
        ];
    }

}
