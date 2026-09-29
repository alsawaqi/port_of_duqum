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
        $view_data["topbar"] = "includes/public/registration_topbar";
        $view_data["left_menu"] = false;
        $view_data["intl_dial_codes"] = require APPPATH . "Config/intl_phone_dial_codes.php";
        return $this->template->rander("guest_gate_pass/index", $view_data);
    }

    function save()
    {
        // Create a new identity, or add requester access after verifying its current password.
        $this->validate_submitted_data([
            "first_name" => "required",
            "last_name" => "permit_empty",
            "email" => "required|valid_email",
            "phone_country_code" => "required|max_length[12]",
            "phone_local" => "required|regex_match[/^\d{4,15}$/]",
            "emergency_country_code" => "required|max_length[12]",
            "emergency_local" => "required|regex_match[/^\d{4,15}$/]",
            "otp_channel" => "required|in_list[email,phone]",
            "password" => "permit_empty",
            "password_confirm" => "permit_empty"
        ]);

        $users_table = $this->db->prefixTable("users");
        $gp_users_table = $this->db->prefixTable("gate_pass_users");

        $email = strtolower(trim($this->request->getPost("email")));
        $password = (string) $this->request->getPost("password");
        $password_confirm = (string) $this->request->getPost("password_confirm");

        $phoneDial = $this->_normalize_dial_code($this->request->getPost("phone_country_code"));
        $emergencyDial = $this->_normalize_dial_code($this->request->getPost("emergency_country_code"));
        if (!$this->_is_allowed_dial($phoneDial) || !$this->_is_allowed_dial($emergencyDial)) {
            echo json_encode([
                "success" => false,
                "message" => app_lang("gate_pass_invalid_country_code"),
            ]);
            return;
        }

        $phone = $this->_merge_e164($phoneDial, (string) $this->request->getPost("phone_local"));
        $emergencyNumber = $this->_merge_e164($emergencyDial, (string) $this->request->getPost("emergency_local"));
        if ($phone === "" || $emergencyNumber === "") {
            echo json_encode([
                "success" => false,
                "message" => app_lang("gate_pass_invalid_phone_numbers"),
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
                echo json_encode(["success" => false, "message" => app_lang("gate_pass_username_allocate_failed")]);
                return;
            }
        }
        $otp_channel = $this->request->getPost("otp_channel");

        // Guest default portal status (matches Gate_pass_visitors statuses)
        // - active: user can login + access gate pass portal immediately
        // - pending: requester access awaits activation
        // - suspended: user can't login
        $portal_status = "active";

        $existing_user = $this->db->query(
            "SELECT id FROM $users_table WHERE LOWER(TRIM(email))=? LIMIT 1",
            [$email]
        )->getRow();

        if ($password === "") {
            echo json_encode([
                "success" => false,
                "message" => app_lang("password_is_required"),
                "errors" => ["password" => app_lang("password_is_required")]
            ]);
            return;
        }

        if (!$existing_user && $password_confirm === "") {
            echo json_encode([
                "success" => false,
                "message" => app_lang("password_confirm_required"),
                "errors" => ["password_confirm" => app_lang("password_confirm_required")]
            ]);
            return;
        }

        if ($password_confirm !== "" && $password !== $password_confirm) {
            echo json_encode([
                "success" => false,
                "message" => app_lang("passwords_do_not_match"),
                "errors" => ["password_confirm" => app_lang("passwords_do_not_match")]
            ]);
            return;
        }

        $policyErrors = $existing_user ? [] : $this->Users_model->password_policy_errors($password);
        if ($policyErrors) {
            $message = implode(" ", $policyErrors);
            echo json_encode([
                "success" => false,
                "message" => esc($message),
                "errors" => ["password" => esc($message)],
            ]);
            return;
        }

        $ReCAPTCHA = new ReCAPTCHA();
        $ReCAPTCHA->validate_recaptcha(true);

        $this->db->transBegin();

        try {
            // Lock the shared identity before linking; never overwrite its profile,
            // phone (used for OTP), privileges, password, or login status.
            $matches = $this->db->query(
                "SELECT * FROM $users_table WHERE LOWER(TRIM(email))=? FOR UPDATE",
                [$email]
            )->getResult();
            if (count($matches) > 1 || ($existing_user && !$matches)) {
                throw new \DomainException("gate_pass_registration_auth_help");
            }
            if ($matches) {
                $existing_user = $matches[0];
                if ((int)$existing_user->deleted !== 0
                    || (string)$existing_user->status !== "active"
                    || (int)$existing_user->disable_login !== 0
                    || (string)$existing_user->user_type !== "staff"
                ) {
                    throw new \DomainException("gate_pass_registration_auth_help");
                }
                $user_id = (int)$existing_user->id;
                $memberships = $this->db->query(
                    "SELECT * FROM $gp_users_table WHERE user_id=? FOR UPDATE", [$user_id]
                )->getResult();
                if (count($memberships) > 1 || ($memberships
                    && ((int)$memberships[0]->deleted !== 0 || $memberships[0]->status !== "active"))) {
                    // Public signup cannot revive a suspended, pending, invited, or removed membership.
                    throw new \DomainException("gate_pass_registration_auth_help");
                }
                if (!$this->Users_model->verify_user_password($user_id, $password)) {
                    throw new \DomainException("gate_pass_registration_auth_help");
                }
            } else {
                $user_data = [
                    "first_name" => $this->request->getPost("first_name"),
                    "last_name"  => $this->request->getPost("last_name"),
                    "email"      => $email,
                    "password"   => password_hash($password, PASSWORD_DEFAULT),
                    "phone" => $phone,
                    "alternative_phone" => $emergencyNumber,
                    "job_title" => "Gate Pass Visitor",
                    // Legacy schema compatibility; Security_Controller treats a
                    // role-less requester pivot as a portal-only identity.
                    "user_type" => "staff",
                    "is_admin"  => 0,
                    "role_id"   => 0,
                    "status" => "active",
                    "disable_login" => 0,
                    "language" => "",
                    "deleted" => 0
                ];

                $ok = $this->db->table("users")->insert(clean_data($user_data));
                if (!$ok) {
                    throw new \RuntimeException("Unable to create the gate-pass identity.");
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

            if (!$existing_pivot_by_user && $existing_pivot_by_username) {
                $this->db->transRollback();
                echo json_encode([
                    "success" => false,
                    "message" => app_lang("gate_pass_username_exists"),
                    "errors" => ["username" => app_lang("gate_pass_username_exists")]
                ]);
                return;
            }

            if ($existing_pivot_by_user) {
                // Already active: leave the existing username and preferences unchanged.
                $ok = true;
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
                "message" => app_lang("gate_pass_account_linked_successfully")
            ]);
        } catch (\DomainException $e) {
            $this->db->transRollback();
            $message = app_lang("gate_pass_registration_auth_help");
            echo json_encode(["success" => false, "message" => $message,
                "errors" => ["password" => $message]]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "Guest gate-pass registration failed: {exception}", [
                "exception" => $e,
            ]);
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
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
