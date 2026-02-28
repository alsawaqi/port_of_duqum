<?php

namespace App\Controllers;

use App\Models\Gate_pass_commercial_users_model;
use App\Models\Gate_pass_companies_model;

class Gate_pass_commercial_users extends Security_Controller
{
    protected $Gate_pass_commercial_users_model;
    protected $Gate_pass_companies_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
        $this->Gate_pass_commercial_users_model = new Gate_pass_commercial_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->db = db_connect();
    }

    public function index()
    {
        $this->access_only_gate_pass("commercial_users", "view");
        return $this->template->rander("gate_pass_commercial_users/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("commercial_users", $id ? "update" : "create");

        $model_info = null;
        if ($id) {
            $model_info = $this->Gate_pass_commercial_users_model->get_details(["id" => $id])->getRow();
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
        return $this->template->view("gate_pass_commercial_users/modal_form", $view_data);
    }

    public function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "company_id" => "required|numeric",
            "first_name" => "required",
            "last_name" => "required",
            "email" => "required|valid_email",
            "status" => "required",
        ]);

        $id = (int)$this->request->getPost("id");
        $this->access_only_gate_pass("commercial_users", $id ? "update" : "create");
        $company_id = (int)$this->request->getPost("company_id");
        $first_name = trim($this->request->getPost("first_name"));
        $last_name = trim($this->request->getPost("last_name"));
        $email = trim($this->request->getPost("email"));
        $phone = trim($this->request->getPost("phone"));
        $status = $this->request->getPost("status");

        $this->db->transStart();

        if (!$id) {
            $password = $this->request->getPost("password");
            if (!$password) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("password_is_required")]);
            }
            if ($this->Users_model->is_email_exists($email)) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("duplicate_email")]);
            }

            $user_data = [
                "email" => $email,
                "password" => password_hash($password, PASSWORD_DEFAULT),
                "first_name" => $first_name,
                "last_name" => $last_name,
                "phone" => $phone,
                "user_type" => "staff",
                "is_admin" => 0,
                "role_id" => 0,
                "disable_login" => 0,
                "status" => ($status === "active") ? "active" : "inactive",
                "job_title" => "Gate Pass Commercial User",
                "created_at" => get_current_utc_time(),
                "deleted" => 0,
            ];
            $user_id = $this->Users_model->ci_save($user_data);
            if (!$user_id) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
            }

            $pivot_data = [
                "user_id" => $user_id,
                "company_id" => $company_id,
                "status" => ($status === "active") ? "active" : "inactive",
                "created_at" => get_current_utc_time(),
                "deleted" => 0,
            ];
            $save_id = $this->Gate_pass_commercial_users_model->ci_save($pivot_data);
        } else {
            $pivot = $this->Gate_pass_commercial_users_model->get_one($id);
            if (!$pivot || (int)$pivot->deleted) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
            }
            if ($this->Users_model->get_one($pivot->user_id)->email !== $email && $this->Users_model->is_email_exists($email)) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("duplicate_email")]);
            }

            $user_update = [
                "email" => $email,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "phone" => $phone,
                "status" => ($status === "active") ? "active" : "inactive",
                "disable_login" => ($status === "active") ? 0 : 1,
            ];
            $password = $this->request->getPost("password");
            if ($password) {
                $user_update["password"] = password_hash($password, PASSWORD_DEFAULT);
            }
            $this->Users_model->ci_save($user_update, $pivot->user_id);

            $pivot_update = [
                "company_id" => $company_id,
                "status" => ($status === "active") ? "active" : "inactive",
                "updated_at" => get_current_utc_time(),
            ];
            $save_id = $this->Gate_pass_commercial_users_model->ci_save($pivot_update, $id);
        }

        $this->db->transComplete();
        if ($this->db->transStatus() === false || !$save_id) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
        }
        return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved")]);
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("commercial_users", "delete");
        $id = (int)$this->request->getPost("id");

        $pivot = $this->Gate_pass_commercial_users_model->get_one($id);
        if (!$pivot || (int)$pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();
        $this->Gate_pass_commercial_users_model->delete($id);
        if ($pivot->user_id) {
            $this->Users_model->ci_save(["disable_login" => 1, "status" => "inactive"], $pivot->user_id);
        }
        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }

    public function list_data()
    {
        $this->access_only_gate_pass("commercial_users", "view");
        $list_data = $this->Gate_pass_commercial_users_model->get_details()->getResult();
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
                get_uri("gate_pass_commercial_users/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            );
            $options .= js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("gate_pass_commercial_users/delete"), "data-action" => "delete-confirmation"]
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
