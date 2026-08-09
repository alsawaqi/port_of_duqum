<?php

namespace App\Controllers;

use App\Models\Auth_security_model;

/**
 * A deliberately narrow self-service account surface for external portal
 * identities. It never accepts a target user ID: the authenticated session is
 * always the password owner.
 */
class Portal_account extends Security_Controller
{
    private Auth_security_model $authSecurity;

    public function __construct()
    {
        parent::__construct();
        $this->authSecurity = new Auth_security_model();
    }

    public function index()
    {
        return $this->change_password();
    }

    public function change_password()
    {
        return $this->template->rander("portal_account/change_password");
    }

    public function save_password()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            return $this->response
                ->setStatusCode(405)
                ->setHeader("Allow", "POST")
                ->setJSON([
                    "success" => false,
                    "message" => "This action requires a POST request.",
                ]);
        }

        $this->validate_submitted_data([
            "current_password" => "required",
            "new_password" => "required|min_length[10]|max_length[72]",
            "new_password_confirm" => "required|matches[new_password]",
        ]);

        $userId = (int) ($this->login_user->id ?? 0);
        if ($userId < 1) {
            return $this->response->setStatusCode(403)->setJSON([
                "success" => false,
                "message" => app_lang("authentication_failed"),
            ]);
        }

        $currentPassword = (string) $this->request->getPost("current_password");
        $newPassword = (string) $this->request->getPost("new_password");
        $policyErrors = $this->Users_model->password_policy_errors($newPassword);
        if ($policyErrors) {
            $this->authSecurity->audit(
                "password_change",
                "denied",
                $userId,
                "",
                ["reason" => "policy", "channel" => "portal_account"]
            );
            return $this->response->setJSON([
                "success" => false,
                "message" => implode(" ", $policyErrors),
            ]);
        }

        $ipHash = hash("sha256", (string) $this->request->getIPAddress());
        $throttleKey = "portal_password_change_{$userId}_{$ipHash}";
        $throttler = service("throttler");
        if (!$throttler->check($throttleKey, 5, 600)) {
            $this->authSecurity->audit(
                "password_change",
                "denied",
                $userId,
                "",
                ["reason" => "rate_limited", "channel" => "portal_account"]
            );
            return $this->response
                ->setStatusCode(429)
                ->setHeader("Retry-After", (string) max(1, $throttler->getTokenTime()))
                ->setJSON([
                    "success" => false,
                    "message" => "Too many password attempts. Please wait and try again.",
                ]);
        }

        if (!$this->Users_model->verify_user_password($userId, $currentPassword)) {
            $this->authSecurity->audit(
                "password_change",
                "denied",
                $userId,
                "",
                ["reason" => "current_password", "channel" => "portal_account"]
            );
            return $this->response->setJSON([
                "success" => false,
                "message" => "The current password is incorrect.",
            ]);
        }

        if (hash_equals($currentPassword, $newPassword)) {
            return $this->response->setJSON([
                "success" => false,
                "message" => "The new password must be different from the current password.",
            ]);
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        if (!$passwordHash || !$this->Users_model->ci_save(["password" => $passwordHash], $userId)) {
            return $this->response->setStatusCode(500)->setJSON([
                "success" => false,
                "message" => app_lang("error_occurred"),
            ]);
        }

        $throttler->remove($throttleKey);
        $hasActiveVendor = $this->session->has("active_vendor_id");
        $activeVendorId = $hasActiveVendor ? $this->session->get("active_vendor_id") : null;
        $this->session->regenerate(true);
        if ($hasActiveVendor) {
            $this->session->set("active_vendor_id", $activeVendorId);
        }

        $this->authSecurity->audit(
            "password_change",
            "success",
            $userId,
            "",
            ["channel" => "portal_account"]
        );

        return $this->response->setJSON([
            "success" => true,
            "message" => "Your password has been changed successfully.",
        ]);
    }
}
