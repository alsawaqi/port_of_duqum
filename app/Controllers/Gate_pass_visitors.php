<?php

namespace App\Controllers;

use App\Models\Gate_pass_users_model;

class Gate_pass_visitors extends Security_Controller
{
    protected $Gate_pass_users_model;
    protected $db;

    function __construct()
    {
        parent::__construct();

        $this->access_only_team_members();
        $this->Gate_pass_users_model = new Gate_pass_users_model();
        $this->db = db_connect();
    }

    function index()
    {
        $this->access_only_gate_pass("visitors", "view");
        return $this->template->rander("gate_pass_visitors/index");
    }

    function modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);
        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("visitors", $id ? "update" : "create");

        $view_data["model_info"] = $id
            ? $this->Gate_pass_users_model->get_details(["id" => $id])->getRow()
            : null;

        return $this->template->view("gate_pass_visitors/modal_form", $view_data);
    }

    function save()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "username" => "permit_empty|regex_match[/^[A-Za-z0-9]+$/]",
            "first_name" => "required",
            "last_name" => "permit_empty",
            "email" => "required|valid_email",
            "phone" => "permit_empty",
            "emergency_number" => "permit_empty",
            "otp_channel" => "required|in_list[email,phone]",
            "portal_status" => "required|in_list[invited,active,suspended]"
        ]);

        $id = $this->request->getPost("id");
        $this->access_only_gate_pass("visitors", $id ? "update" : "create");
        $is_create = !$id;

        if ($is_create) {
            $this->validate_submitted_data([
                "password" => "required"
            ]);
        }

        $email = strtolower(trim($this->request->getPost("email")));
        $username = trim((string)$this->request->getPost("username"));
        $portal_status = $this->request->getPost("portal_status");
        $otp_channel = $this->request->getPost("otp_channel");

        $users_table = $this->db->prefixTable("users");
        $gp_table = $this->db->prefixTable("gate_pass_users");

        if ($username === "") {
            $local = preg_replace('/[^A-Za-z0-9]/', '', strstr($email, "@", true) ?: "");
            if ($local === "") {
                $local = "gatepass";
            }
            $base = substr($local, 0, 40);
            $username = $base;
            $suffix = 0;
            while (true) {
                $try = $suffix === 0 ? $username : ($base . $suffix);
                $taken = $this->db->query(
                    "SELECT id FROM $gp_table WHERE username=? AND deleted=0 " . ($id ? "AND id<>?" : ""),
                    $id ? [$try, $id] : [$try]
                )->getRow();
                if (!$taken) {
                    $username = $try;
                    break;
                }
                $suffix++;
                if ($suffix > 9999) {
                    echo json_encode(["success" => false, "message" => "Could not allocate username. Please enter a username."]);
                    return;
                }
            }
        }

        // Check username uniqueness
        $q = $this->db->query(
            "SELECT id FROM $gp_table WHERE username=? AND deleted=0 " . ($id ? "AND id<>?" : ""),
            $id ? [$username, $id] : [$username]
        )->getRow();
        if ($q) {
            echo json_encode(["success" => false, "message" => "Username already exists."]);
            return;
        }

        $this->db->transBegin();

        try {
            if ($is_create) {
                // Check email uniqueness in users table
                $exists = $this->db->query("SELECT id FROM $users_table WHERE email=? AND deleted=0", [$email])->getRow();
                if ($exists) {
                    throw new \RuntimeException(app_lang("email_already_exists"));
                }

                $password = $this->request->getPost("password");

                $user_data = [
                    "first_name" => $this->request->getPost("first_name"),
                    "last_name"  => $this->request->getPost("last_name"),
                    "email"      => $email,
                    "password"   => password_hash($password, PASSWORD_DEFAULT),

                    "phone" => $this->request->getPost("phone"),
                    "alternative_phone" => $this->request->getPost("emergency_number"),

                    "job_title" => "Gate Pass Visitor",
                    "user_type" => "staff",
                    "is_admin"  => 0,
                    "role_id"   => 0,

                    "status" => ($portal_status === "suspended") ? "inactive" : "active",
                    "disable_login" => ($portal_status === "suspended") ? 1 : 0,
                    "language" => "",
                    "deleted" => 0
                ];

                $ok = $this->db->table("users")->insert(clean_data($user_data));
                if (!$ok) {
                    $err = $this->db->error();
                    throw new \RuntimeException("User insert error: " . ($err["message"] ?: "unknown"));
                }

                $user_id = $this->db->insertID();

                $pivot = [
                    "user_id"    => (int)$user_id,
                    "username"   => $username,
                    "otp_channel"=> $otp_channel,
                    "invited_by" => (int)$this->login_user->id,
                    "status"     => $portal_status,
                   
                    "deleted"    => 0
                ];

                $ok = $this->db->table("gate_pass_users")->insert(clean_data($pivot));
                if (!$ok) {
                    $err = $this->db->error();
                    throw new \RuntimeException("Pivot insert error: " . ($err["message"] ?: "unknown"));
                }

                $save_id = $this->db->insertID();

            } else {
                $row = $this->Gate_pass_users_model->get_details(["id" => $id])->getRow();
                if (!$row) {
                    throw new \RuntimeException("Record not found.");
                }

                // Email uniqueness excluding this user_id
                $exists = $this->db->query(
                    "SELECT id FROM $users_table WHERE email=? AND deleted=0 AND id<>?",
                    [$email, $row->user_id]
                )->getRow();
                if ($exists) {
                    throw new \RuntimeException(app_lang("email_already_exists"));
                }

                $user_update = [
                    "first_name" => $this->request->getPost("first_name"),
                    "last_name"  => $this->request->getPost("last_name"),
                    "email"      => $email,
                    "phone"      => $this->request->getPost("phone"),
                    "alternative_phone" => $this->request->getPost("emergency_number"),
                    "status" => ($portal_status === "suspended") ? "inactive" : "active",
                    "disable_login" => ($portal_status === "suspended") ? 1 : 0,
                ];

                $password = $this->request->getPost("password");
                if ($password) {
                    $user_update["password"] = password_hash($password, PASSWORD_DEFAULT);
                }

                $this->db->table("users")
                    ->where("id", (int)$row->user_id)
                    ->update(clean_data($user_update));

                $pivot_update = [
                    "username" => $username,
                    "otp_channel" => $otp_channel,
                    "status" => $portal_status
                ];

                $this->db->table("gate_pass_users")
                    ->where("id", (int)$id)
                    ->update(clean_data($pivot_update));

                $save_id = $id;
            }

            if ($this->db->transStatus() === false) {
                $err = $this->db->error();
                throw new \RuntimeException("Transaction failed: " . ($err["message"] ?: "unknown"));
            }

            $this->db->transCommit();

            echo json_encode([
                "success" => true,
                "data" => $this->_row_data($save_id),
                "id" => $save_id,
                "message" => app_lang("record_saved")
            ]);
            return;

        } catch (\Throwable $e) {
            $this->db->transRollback();
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
            return;
        }
    }

    function list_data()
    {
        $this->access_only_gate_pass("visitors", "view");
        $list_data = $this->Gate_pass_users_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    function delete()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $this->access_only_gate_pass("visitors", "delete");
        $id = $this->request->getPost("id");

        $row = $this->Gate_pass_users_model->get_details(["id" => $id])->getRow();
        if (!$row) {
            echo json_encode(["success" => false, "message" => "Record not found."]);
            return;
        }

        $this->db->transBegin();

        // Soft delete pivot
        $this->db->table("gate_pass_users")
            ->where("id", (int)$id)
            ->update(["deleted" => 1]);

        // Soft delete user (optional but recommended since it's a visitor-only account)
        $this->db->table("users")
            ->where("id", (int)$row->user_id)
            ->update(["deleted" => 1, "disable_login" => 1]);

        $this->db->transCommit();

        echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
    }

    private function _row_data($id)
    {
        $data = $this->Gate_pass_users_model->get_details(["id" => $id])->getRow();
        return $this->_make_row($data);
    }

    private function _make_row($data)
    {
        $portal_status = "<span class='badge bg-info'>" . esc($data->status) . "</span>";
        if ($data->status === "active") $portal_status = "<span class='badge bg-success'>active</span>";
        if ($data->status === "suspended") $portal_status = "<span class='badge bg-danger'>suspended</span>";

        $options = modal_anchor(
            get_uri("gate_pass_visitors/modal_form"),
            "<i data-feather='edit' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
        );

        $options .= js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            [
                "title" => app_lang("delete"),
                "class" => "delete",
                "data-id" => $data->id,
                "data-action-url" => get_uri("gate_pass_visitors/delete"),
                "data-action" => "delete-confirmation"
            ]
        );

        $full_name = trim($data->first_name . " " . $data->last_name);

        return [
            $data->username,
            $full_name,
            $data->email,
            $data->phone ?: "-",
            isset($data->alternative_phone) && $data->alternative_phone !== "" ? $data->alternative_phone : "-",
            strtoupper($data->otp_channel),
            $portal_status,
            $options
        ];
    }
}
