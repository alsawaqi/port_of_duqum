<?php

namespace App\Controllers;

use App\Models\Tender_technical_users_model;
use App\Models\Gate_pass_companies_model;

class Tender_technical_users extends Security_Controller
{
    protected $Tender_technical_users_model;
    protected $Gate_pass_companies_model;

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();

        if (!$this->login_user->is_admin) {
            app_redirect("forbidden");
        }

        $this->Tender_technical_users_model = new Tender_technical_users_model();
        $this->Gate_pass_companies_model = new Gate_pass_companies_model();
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
        $tmp = @unserialize($row->permissions);
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
        return $this->template->rander("tender_technical_users/index");
    }

    public function list_data()
    {
        $list = $this->Tender_technical_users_model->get_details()->getResult();
        $result = [];
        foreach ($list as $d) $result[] = $this->_make_row($d);
        return $this->response->setJSON(["data" => $result]);
    }

    public function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = (int)$this->request->getPost("id");

        $model_info = $id ? $this->Tender_technical_users_model->get_details(["id" => $id])->getRow() : null;

        $company_dropdown = ["0" => "- " . app_lang("select_company") . " -"];
        foreach ($this->Gate_pass_companies_model->get_details()->getResult() as $c) $company_dropdown[$c->id] = $c->name;

        return $this->template->view("tender_technical_users/modal_form", [
            "model_info" => $model_info,
            "company_dropdown" => $company_dropdown
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
        $company_id = (int)$this->request->getPost("company_id");
        $first_name = trim($this->request->getPost("first_name"));
        $last_name  = trim($this->request->getPost("last_name"));
        $email      = trim($this->request->getPost("email"));
        $phone      = trim($this->request->getPost("phone"));
        $status     = $this->request->getPost("status");

        $this->db->transStart();
        try {
                $role_id = $this->_ensure_role("Tender Technical Evaluator", [
                
            ]);

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
                    "role_id" => $role_id,
                    "disable_login" => 0,
                    "status" => ($status === "active") ? "active" : "inactive",
                    "job_title" => "Tender Technical User",
                    "language" => (get_setting("language") ?: "english"),
                    "created_at" => get_current_utc_time(),
                    "deleted" => 0
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
                    "deleted" => 0
                ];
                $save_id = $this->Tender_technical_users_model->ci_save($pivot_data);
            } else {
                $pivot = $this->Tender_technical_users_model->get_one($id);
                if (!$pivot || (int)$pivot->deleted) {
                    $this->db->transComplete();
                    return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
                }

                $existing_user = $this->Users_model->get_one($pivot->user_id);
                if ($existing_user && $existing_user->email !== $email && $this->Users_model->is_email_exists($email)) {
                    $this->db->transComplete();
                    return $this->response->setJSON(["success" => false, "message" => app_lang("duplicate_email")]);
                }

                $user_update = [
                    "email" => $email,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "phone" => $phone,
                    "role_id" => $role_id,
                    "status" => ($status === "active") ? "active" : "inactive",
                    "disable_login" => ($status === "active") ? 0 : 1
                ];
                $password = $this->request->getPost("password");
                if ($password) {
                    $user_update["password"] = password_hash($password, PASSWORD_DEFAULT);
                }

                $this->Users_model->ci_save($user_update, $pivot->user_id);

                $pivot_data = [
                    "company_id" => $company_id,
                    "status" => ($status === "active") ? "active" : "inactive",
                    "updated_at" => get_current_utc_time()
                ];
                $save_id = $this->Tender_technical_users_model->ci_save($pivot_data, $id);
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false || !$save_id) {
                return $this->response->setJSON(["success" => false, "message" => app_lang("error_occurred")]);
            }

            return $this->response->setJSON(["success" => true, "message" => app_lang("record_saved")]);
        } catch (\Throwable $e) {
            $this->db->transComplete();
            $msg = (ENVIRONMENT !== 'production') ? $e->getMessage() : app_lang("error_occurred");
            return $this->response->setJSON(["success" => false, "message" => $msg]);
        }
    }

    public function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int)$this->request->getPost("id");

        $pivot = $this->Tender_technical_users_model->get_one($id);
        if (!$pivot || (int)$pivot->deleted) {
            return $this->response->setJSON(["success" => false, "message" => app_lang("record_not_found")]);
        }

        $this->db->transStart();
        $this->Tender_technical_users_model->delete($id);
        if ($pivot->user_id) {
            $user_data = ["disable_login" => 1, "status" => "inactive"];
            $this->Users_model->ci_save($user_data, $pivot->user_id);
        }
        $this->db->transComplete();

        return $this->response->setJSON(["success" => true, "message" => app_lang("record_deleted")]);
    }

    private function _make_row($d)
    {
        $name = trim(($d->first_name ?? "") . " " . ($d->last_name ?? "")); if ($name === "") $name = "-";

        $options = modal_anchor(get_uri("tender_technical_users/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit", "title" => app_lang("edit"), "data-post-id" => $d->id
        ]);
        $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
            "title" => app_lang("delete"), "class" => "delete", "data-id" => $d->id,
            "data-action-url" => get_uri("tender_technical_users/delete"), "data-action" => "delete-confirmation"
        ]);

        return [
            $d->company_name ?? "-",
            $name,
            $d->email ?? "-",
            $d->phone ?? "-",
            ucfirst($d->status ?? "inactive"),
            $options
        ];
    }
         
}