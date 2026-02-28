<?php

namespace App\Controllers;

use App\Models\Ptw_terminal_users_model;
use App\Models\Gate_pass_companies_model;

class Ptw_terminal_users extends Security_Controller
{
    protected $Ptw_terminal_users_model;
    protected $Gate_pass_companies_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        $this->Ptw_terminal_users_model = new Ptw_terminal_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
        $this->db = db_connect();
    }

    public function index()
    {
        $this->access_only_ptw("terminal_users", "view");
        return $this->template->rander("ptw_terminal_users/index");
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int)$this->request->getPost("id");
        $this->access_only_ptw("terminal_users", $id ? "update" : "create");
        $model_info = null;

        if ($id) {
            $model_info = $this->Ptw_terminal_users_model->get_details(["id" => $id])->getRow();
        }

        $company_dropdown = ["0" => "- " . app_lang("select_company") . " -"];
        $companies = $this->Gate_pass_companies_model->get_details()->getResult();
        foreach ($companies as $c) {
            $company_dropdown[$c->id] = $c->name;
        }

        return $this->template->view("ptw_terminal_users/modal_form", [
            "model_info" => $model_info,
            "company_dropdown" => $company_dropdown,
        ]);
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
        $this->access_only_ptw("terminal_users", $id ? "update" : "create");

        $company_id = (int)$this->request->getPost("company_id");
        $first_name = trim((string)$this->request->getPost("first_name"));
        $last_name = trim((string)$this->request->getPost("last_name"));
        $email = trim((string)$this->request->getPost("email"));
        $phone = trim((string)$this->request->getPost("phone"));
        $status = trim((string)$this->request->getPost("status"));

        $this->db->transStart();

        if (!$id) {
            $password = (string)$this->request->getPost("password");
            if ($password === "") {
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
                "disable_login" => ($status === "active") ? 0 : 1,
                "status" => ($status === "active") ? "active" : "inactive",
                "job_title" => "PTW Terminal User",
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

            $save_id = $this->Ptw_terminal_users_model->ci_save($pivot_data);
        } else {
            $pivot = $this->Ptw_terminal_users_model->get_one($id);
            if (!$pivot || (int)$pivot->deleted) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
            }

            $existing_user = $this->Users_model->get_one($pivot->user_id);
            if (!$existing_user || (int)$existing_user->deleted) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
            }

            if ($existing_user->email !== $email && $this->Users_model->is_email_exists($email)) {
                $this->db->transComplete();
                return $this->response->setJSON(["success" => false, "message" => app_lang("duplicate_email")]);
            }

            $user_update = [
                "email" => $email,
                "first_name" => $first_name,
                "last_name" => $last_name,
                "phone" => $phone,
                "disable_login" => ($status === "active") ? 0 : 1,
                "status" => ($status === "active") ? "active" : "inactive",
            ];

            $password = (string)$this->request->getPost("password");
            if ($password !== "") {
                $user_update["password"] = password_hash($password, PASSWORD_DEFAULT);
            }

            $this->Users_model->ci_save($user_update, $pivot->user_id);

            $pivot_update = [
                "company_id" => $company_id,
                "status" => ($status === "active") ? "active" : "inactive",
                "updated_at" => get_current_utc_time(),
            ];

            $save_id = $this->Ptw_terminal_users_model->ci_save($pivot_update, $id);
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
        $this->access_only_ptw("terminal_users", "delete");

        $id = (int)$this->request->getPost("id");
        $pivot = $this->Ptw_terminal_users_model->get_one($id);

        if (!$pivot || (int)$pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();

        $this->Ptw_terminal_users_model->delete($id);
        if ($pivot->user_id) {
            $this->Users_model->ci_save(["disable_login" => 1, "status" => "inactive"], $pivot->user_id);
        }

        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }

    public function list_data()
    {
        $this->access_only_ptw("terminal_users", "view");

        $list = $this->Ptw_terminal_users_model->get_details()->getResult();
        $result = [];

        foreach ($list as $row) {
            $result[] = $this->_make_row($row);
        }

        return $this->response->setJSON(["data" => $result]);
    }

    private function _make_row($data)
    {
        $name = trim(($data->first_name ?? "") . " " . ($data->last_name ?? ""));
        if ($name === "") {
            $name = "-";
        }

        $options = modal_anchor(
            get_uri("ptw_terminal_users/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "class" => "delete",
                "title" => app_lang("delete"),
                "data-id" => $data->id,
                "data-action-url" => get_uri("ptw_terminal_users/delete"),
                "data-action" => "delete-confirmation",
            ]
        );

        return [
            $data->company_name ?? "-",
            $name,
            $data->email ?? "-",
            $data->phone ?? "-",
            ucfirst((string)($data->status ?? "inactive")),
            $options,
        ];
    }
}