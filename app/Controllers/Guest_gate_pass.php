<?php

namespace App\Controllers;

use App\Models\Gate_pass_users_model;

class Guest_gate_pass extends App_Controller
{
    protected $db;

    function __construct()
    {
        parent::__construct();
        $this->db = db_connect();
    }

    function index()
    {
        // ✅ Public page (no login required)
        // Use the public layout (no sidebar) like Guest_vendor/Request_estimate.
        $view_data = [];
        $view_data["topbar"] = "includes/public/topbar";
        $view_data["left_menu"] = false;
        return $this->template->rander("guest_gate_pass/index", $view_data);
    }

    function save()
    {
        // Public create-only (same create flow as Gate_pass_visitors::save)
        $this->validate_submitted_data([
            "username" => "required|regex_match[/^[A-Za-z0-9]+$/]",
            "first_name" => "required",
            "last_name" => "permit_empty",
            "email" => "required|valid_email",
            "phone" => "permit_empty",
            "emergency_number" => "permit_empty",
            "otp_channel" => "required|in_list[email,phone]",
            "password" => "permit_empty"
        ]);

        $users_table = $this->db->prefixTable("users");
        $gp_users_table = $this->db->prefixTable("gate_pass_users");

        $username = trim($this->request->getPost("username"));
        $email = strtolower(trim($this->request->getPost("email")));
        $otp_channel = $this->request->getPost("otp_channel");

        // Guest default portal status (matches Gate_pass_visitors statuses)
        // - active: user can login + access gate pass portal immediately
        // - invited: user can login, but portal access is still blocked by Gate_pass_portal (status must be active)
        // - suspended: user can't login
        $portal_status = "active";

        $existing_user = $this->db->query(
            "SELECT id, user_type, deleted FROM $users_table WHERE email=? LIMIT 1",
            [$email]
        )->getRow();

        if ($existing_user && strtolower((string)($existing_user->user_type ?? "")) !== "staff") {
            echo json_encode([
                "success" => false,
                "message" => "This email is linked to a non-staff account and cannot be used for gate pass portal access.",
                "errors" => ["email" => "This email is linked to a non-staff account."]
            ]);
            return;
        }

        if (!$existing_user) {
            $this->validate_submitted_data([
                "password" => "required",
            ]);
        }

        $this->db->transBegin();

        try {
            $user_id = 0;
            if ($existing_user) {
                $user_id = (int)$existing_user->id;

                // revive soft-deleted user if needed
                if ((int)($existing_user->deleted ?? 0) === 1) {
                    $ok = $this->db->table("users")
                        ->where("id", $user_id)
                        ->update(clean_data([
                            "deleted" => 0,
                            "status" => ($portal_status === "suspended") ? "inactive" : "active",
                            "disable_login" => ($portal_status === "suspended") ? 1 : 0
                        ]));
                    if (!$ok) {
                        $err = $this->db->error();
                        throw new \RuntimeException("Failed to restore existing user: " . ($err["message"] ?: "unknown"));
                    }
                }
            } else {
                // Insert user
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
                $user_id = (int)$this->db->insertID();
            }

            $existing_pivot_by_user = $this->db->query(
                "SELECT id, username FROM $gp_users_table WHERE user_id=? LIMIT 1",
                [$user_id]
            )->getRow();

            $existing_pivot_by_username = $this->db->query(
                "SELECT id, user_id FROM $gp_users_table WHERE username=? LIMIT 1",
                [$username]
            )->getRow();

            if ($existing_pivot_by_username && (!$existing_pivot_by_user || (int)$existing_pivot_by_username->id !== (int)$existing_pivot_by_user->id)) {
                $this->db->transRollback();
                echo json_encode([
                    "success" => false,
                    "message" => "Username already exists.",
                    "errors" => ["username" => "Username already exists."]
                ]);
                return;
            }

            if ($existing_pivot_by_user) {
                $ok = $this->db->table("gate_pass_users")
                    ->where("id", (int)$existing_pivot_by_user->id)
                    ->update(clean_data([
                        "username" => $username,
                        "otp_channel" => $otp_channel,
                        "invited_by" => 0,
                        "status" => $portal_status,
                        "deleted" => 0
                    ]));
                if (!$ok) {
                    $err = $this->db->error();
                    throw new \RuntimeException("Pivot update error: " . ($err["message"] ?: "unknown"));
                }
            } else {
                // Insert pivot (gate_pass_users)
                $pivot = [
                    "user_id"     => (int)$user_id,
                    "username"    => $username,
                    "otp_channel" => $otp_channel,
                    "invited_by"  => 0,
                    "status"      => $portal_status,
                    "deleted"     => 0
                ];

                $ok = $this->db->table("gate_pass_users")->insert(clean_data($pivot));
                if (!$ok) {
                    $err = $this->db->error();
                    throw new \RuntimeException("Pivot insert error: " . ($err["message"] ?: "unknown"));
                }
            }

            if ($this->db->transStatus() === false) {
                $err = $this->db->error();
                throw new \RuntimeException("Transaction failed: " . ($err["message"] ?: "unknown"));
            }
            $this->db->transCommit();

            echo json_encode([
                "success" => true,
                "message" => "Account linked successfully. You can now sign in and submit Gate Pass requests."
            ]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
}
