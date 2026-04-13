<?php

namespace App\Controllers;

use App\Libraries\ReCAPTCHA;

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
        $view_data["intl_dial_codes"] = require APPPATH . "Config/intl_phone_dial_codes.php";
        return $this->template->rander("guest_gate_pass/index", $view_data);
    }

    function save()
    {
        // Public create-only (same create flow as Gate_pass_visitors::save)
        $this->validate_submitted_data([
            "first_name" => "required",
            "last_name" => "permit_empty",
            "email" => "required|valid_email",
            "phone_country_code" => "required|max_length[12]",
            "phone_local" => "required|regex_match[/^\d{4,15}$/]",
            "emergency_country_code" => "required|max_length[12]",
            "emergency_local" => "required|regex_match[/^\d{4,15}$/]",
            "otp_channel" => "required|in_list[email,phone]",
            "password" => "permit_empty"
        ]);

        $users_table = $this->db->prefixTable("users");
        $gp_users_table = $this->db->prefixTable("gate_pass_users");

        $email = strtolower(trim($this->request->getPost("email")));

        $phoneDial = $this->_normalize_dial_code($this->request->getPost("phone_country_code"));
        $emergencyDial = $this->_normalize_dial_code($this->request->getPost("emergency_country_code"));
        if (!$this->_is_allowed_dial($phoneDial) || !$this->_is_allowed_dial($emergencyDial)) {
            echo json_encode([
                "success" => false,
                "message" => "Invalid country code selected.",
            ]);
            return;
        }

        $phone = $this->_merge_e164($phoneDial, (string) $this->request->getPost("phone_local"));
        $emergencyNumber = $this->_merge_e164($emergencyDial, (string) $this->request->getPost("emergency_local"));
        if ($phone === "" || $emergencyNumber === "") {
            echo json_encode([
                "success" => false,
                "message" => "Please enter a valid phone number and emergency number.",
            ]);
            return;
        }

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
                "SELECT id FROM $gp_users_table WHERE username=? LIMIT 1",
                [$try]
            )->getRow();
            if (!$taken) {
                $username = $try;
                break;
            }
            $suffix++;
            if ($suffix > 9999) {
                echo json_encode(["success" => false, "message" => "Could not allocate a login username from your email. Please contact support."]);
                return;
            }
        }
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

        if (get_setting("re_captcha_secret_key")) {
            $ReCAPTCHA = new ReCAPTCHA();
            $ReCAPTCHA->validate_recaptcha(true);
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

                    "phone" => $phone,
                    "alternative_phone" => $emergencyNumber,

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

    /** @var list<string>|null */
    private static $allowedDialCache = null;

    private function _dial_code_whitelist(): array
    {
        if (self::$allowedDialCache === null) {
            $list = require APPPATH . "Config/intl_phone_dial_codes.php";
            self::$allowedDialCache = array_values(array_unique(array_column($list, "code")));
        }

        return self::$allowedDialCache;
    }

    private function _normalize_dial_code(?string $dial): string
    {
        $dial = trim((string) $dial);
        if ($dial === "") {
            return "";
        }
        if ($dial[0] !== "+") {
            $dial = "+" . ltrim($dial, "+");
        }

        return $dial;
    }

    private function _is_allowed_dial(string $dial): bool
    {
        return in_array($dial, $this->_dial_code_whitelist(), true);
    }

    private function _merge_e164(string $dial, string $localDigits): string
    {
        $localDigits = preg_replace('/\D+/', "", $localDigits) ?? "";
        if ($dial === "" || $localDigits === "") {
            return "";
        }

        return $dial . $localDigits;
    }
}
