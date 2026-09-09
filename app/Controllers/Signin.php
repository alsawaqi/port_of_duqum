<?php

namespace App\Controllers;

use App\Libraries\ReCAPTCHA;
use App\Models\Auth_security_model;
use App\Models\Vendor_users_model;

class Signin extends App_Controller {

    private $signin_validation_errors;
    private Auth_security_model $Auth_security_model;
    private Vendor_users_model $Vendor_users_model;

    function __construct() {
        parent::__construct();
        $this->signin_validation_errors = array();
        $this->Auth_security_model = new Auth_security_model();
        $this->Vendor_users_model = new Vendor_users_model();
        helper('email');
    }

    function index() {
        if ($this->Users_model->login_user_id()) {
            app_redirect('dashboard');
        } else if ($this->_pending_mfa_user_id()) {
            app_redirect('signin/mfa_challenge');
        } else if ($this->_pending_vendor_user_id()) {
            app_redirect('signin/vendor_selection');
        } else {

            $view_data["redirect"] = "";
            if (isset($_REQUEST["redirect"])) {
                $view_data["redirect"] = $_REQUEST["redirect"];
            }

            $this->validate_submitted_data(array(
                "redirect" => "valid_url_strict"
            ), false, false);

            return $this->template->view('signin/index', $view_data);
        }
    }

    private function signin_error_response($errors) {
        if ($this->request->isAJAX()) {
            $messages = is_array($errors) ? $errors : array($errors);
            $messages = array_map(function ($message) {
                return esc($message);
            }, $messages);

            return $this->response->setJSON(array("success" => false, "message" => implode("<br />", $messages)));
        }

        $this->session->setFlashdata("signin_validation_errors", is_array($errors) ? $errors : array($errors));
        app_redirect('signin');
    }

    private function get_signin_redirect_url(
        bool $vendor_login = false,
        ?string $submitted_redirect = null
    ) {
        $redirect = $submitted_redirect !== null
            ? $submitted_redirect
            : $this->request->getPost("redirect");

        if ($redirect) {
            $baseParts = parse_url(base_url());
            $redirectParts = parse_url((string) $redirect);
            $baseScheme = strtolower((string) ($baseParts["scheme"] ?? ""));
            $redirectScheme = strtolower((string) ($redirectParts["scheme"] ?? ""));
            $baseHost = strtolower(rtrim((string) ($baseParts["host"] ?? ""), "."));
            $redirectHost = strtolower(rtrim((string) ($redirectParts["host"] ?? ""), "."));
            $defaultPort = static fn(string $scheme): int => $scheme === "https" ? 443 : 80;
            $basePort = (int) ($baseParts["port"] ?? $defaultPort($baseScheme));
            $redirectPort = (int) ($redirectParts["port"] ?? $defaultPort($redirectScheme));

            if ($baseHost !== ""
                && hash_equals($baseHost, $redirectHost)
                && $baseScheme !== ""
                && hash_equals($baseScheme, $redirectScheme)
                && $basePort === $redirectPort
                && empty($redirectParts["user"])
                && empty($redirectParts["pass"])
                && !preg_match('/[\r\n]/', (string) $redirect)) {
                $redirectPath = strtolower(trim((string) ($redirectParts["path"] ?? ""), "/"));
                $basePath = strtolower(trim((string) ($baseParts["path"] ?? ""), "/"));
                if ($basePath !== "" && str_starts_with($redirectPath, $basePath . "/")) {
                    $redirectPath = substr($redirectPath, strlen($basePath) + 1);
                }
                $redirectPath = preg_replace('#^index\.php/?#', '', $redirectPath) ?: "";

                if (in_array($redirectPath, ["", "signin", "forbidden"], true)) {
                    return get_uri($vendor_login ? "vendor_portal" : "dashboard");
                }

                if ($vendor_login) {
                    $allowedVendorRedirects = [
                        "vendor_portal",
                        "portal_account",
                        "notifications",
                    ];
                    $isAllowedVendorRedirect = false;
                    foreach ($allowedVendorRedirects as $allowedPath) {
                        if ($redirectPath === $allowedPath || str_starts_with($redirectPath, $allowedPath . "/")) {
                            $isAllowedVendorRedirect = true;
                            break;
                        }
                    }
                    if (!$isAllowedVendorRedirect) {
                        return get_uri("vendor_portal");
                    }
                }

                return $redirect;
            }
        }

        return get_uri($vendor_login ? "vendor_portal" : "dashboard");
    }

    private function get_welcome_message(int $user_id = 0) {
        $user_id = $user_id ?: (int) $this->Users_model->login_user_id();
        $user = $user_id ? $this->Users_model->get_one($user_id) : null;
        $name = "";

        if ($user && isset($user->id)) {
            $name = trim($user->first_name . " " . $user->last_name);
        }

        return $name ? sprintf("Welcome back, %s.", clean_data($name)) : "Welcome back.";
    }

    private function has_recaptcha_error() {

        $ReCAPTCHA = new ReCAPTCHA();
        $response = $ReCAPTCHA->validate_recaptcha(false);

        if ($response === true) {
            return true;
        } else {
            array_push($this->signin_validation_errors, $response);
            return false;
        }
    }

    // check authentication
    function authenticate() {
        $validation = $this->validate_submitted_data(array(
            "email" => "required",
            "password" => "required"
        ), true);

        $email = $this->request->getPost("email");
        $password = $this->request->getPost("password");
        if (!$email) {
            //loaded the page directly
            app_redirect('signin');
        }

        if (is_array($validation)) {
            //has validation errors
            $this->signin_validation_errors = $validation;
        }

        // ReCAPTCHA is optional until provider keys are configured. It can be
        // forced fail-closed with PODC_RECAPTCHA_REQUIRED=true.
        $this->has_recaptcha_error();

        //don't check password if there is any error
        if ($this->signin_validation_errors) {
            return $this->signin_error_response($this->signin_validation_errors);
        }

        // Bound online password guessing without revealing whether an identity
        // exists. Pair limits avoid account-wide lockout; the IP bucket also
        // slows broad credential-stuffing attempts.
        $throttler = service("throttler");
        $normalized_email = strtolower(trim((string) $email));
        $ip_hash = $this->Auth_security_model->ip_hash((string) $this->request->getIPAddress());
        $identity_hash = $this->Auth_security_model->identity_hash($normalized_email);
        $persistent_lock = $this->Auth_security_model->login_lock_status($identity_hash);
        if ($persistent_lock["locked"]) {
            $this->Auth_security_model->audit(
                "login_locked",
                "denied",
                0,
                $identity_hash
            );
            $this->response
                ->setStatusCode(429)
                ->setHeader("Retry-After", (string) max(1, $persistent_lock["retry_after"]));
            array_push(
                $this->signin_validation_errors,
                "Too many sign-in attempts. Please wait and try again."
            );
            return $this->signin_error_response($this->signin_validation_errors);
        }

        $pair_allowed = $throttler->check(
            "signin_pair_{$ip_hash}_{$identity_hash}",
            10,
            300
        );
        $pair_retry = $throttler->getTokenTime();
        $ip_allowed = $throttler->check(
            "signin_ip_{$ip_hash}",
            60,
            300
        );
        $ip_retry = $throttler->getTokenTime();

        if (!$pair_allowed || !$ip_allowed) {
            $this->Auth_security_model->audit(
                "login_throttled",
                "denied",
                0,
                $identity_hash
            );
            $this->response
                ->setStatusCode(429)
                ->setHeader("Retry-After", (string) max(1, $pair_retry, $ip_retry));
            array_push(
                $this->signin_validation_errors,
                "Too many sign-in attempts. Please wait and try again."
            );
            return $this->signin_error_response($this->signin_validation_errors);
        }

        $user_info = $this->Users_model->authenticate_signin_credentials($email, $password);
        if (!$user_info) {
            $failure = $this->Auth_security_model->record_login_failure(
                $identity_hash,
                $ip_hash
            );
            $this->Auth_security_model->audit(
                $failure["locked"] ? "login_locked" : "login_failed",
                "denied",
                0,
                $identity_hash,
                ["failed_attempts" => $failure["failed_attempts"]]
            );
            if ($failure["locked"]) {
                $this->response
                    ->setStatusCode(429)
                    ->setHeader("Retry-After", (string) max(1, $failure["retry_after"]));
            }
            array_push($this->signin_validation_errors, filter_var($email, FILTER_VALIDATE_EMAIL)
                ? app_lang("authentication_failed") : app_lang("signin_cr_authentication_failed"));
            return $this->signin_error_response($this->signin_validation_errors);
        }

        $throttler->remove("signin_pair_{$ip_hash}_{$identity_hash}");
        $this->Auth_security_model->clear_login_failures(
            $identity_hash,
            (int) $user_info->id
        );
        $this->Auth_security_model->audit(
            "login_password_verified",
            "success",
            (int) $user_info->id,
            $identity_hash
        );

        if ($this->Auth_security_model->mfa_is_required($user_info)) {
            return $this->_begin_mfa_challenge($user_info, $identity_hash, $ip_hash);
        }

        return $this->_complete_authenticated_login(
            $user_info,
            null,
            (int) ($user_info->authenticated_vendor_id ?? 0)
        );
    }

    private function _begin_mfa_challenge(
        object $user_info,
        string $identity_hash,
        string $ip_hash
    ) {
        $configurationError = $this->Auth_security_model->mfa_configuration_error($user_info);
        if ($configurationError) {
            log_message("error", "MFA sign-in is misconfigured: {message}", [
                "message" => $configurationError,
            ]);
            $this->Auth_security_model->audit(
                "mfa_configuration_error",
                "denied",
                (int) $user_info->id,
                $identity_hash
            );
            return $this->signin_error_response(
                "Sign-in verification is temporarily unavailable. Please contact support."
            );
        }

        $config = $this->Auth_security_model->config();
        $provider = $this->Auth_security_model->mfa_provider($user_info);
        // Correct passwords must not reset the independent OTP delivery limit.
        $otpLimiter = service('throttler');
        $otpKey = 'signin_otp_user_' . (int) $user_info->id;
        if (!$otpLimiter->check($otpKey . '_minute', 1, 60)
            || !$otpLimiter->check($otpKey . '_window', 5, 900)) {
            $this->response->setStatusCode(429)->setHeader('Retry-After', '60');
            return $this->signin_error_response('Please wait before requesting another sign-in code.');
        }
        $destination = $this->Auth_security_model->mfa_destination($user_info, $provider);
        $destinationHint = $this->Auth_security_model->mfa_destination_hint($user_info, $provider);
        $challenge = null;
        $delivered = false;
        try {
            $challenge = $this->Verification_model->issue_mfa_challenge(
                (int) $user_info->id,
                $provider->name(),
                $destinationHint,
                $ip_hash,
                hash("sha256", $this->request->getUserAgent()->getAgentString()),
                $config->mfaHmacKey,
                $config->mfaCodeLength,
                $config->mfaLifetimeSeconds,
                $config->mfaMaxAttempts
            );
            $delivered = $challenge
                && $destination !== null
                && $provider->send(
                    $destination,
                    $challenge["code"],
                    $config->mfaLifetimeSeconds
                );
        } catch (\Throwable $exception) {
            log_message("error", "MFA delivery failed: {message}", [
                "message" => $exception->getMessage(),
            ]);
        }

        if (!$challenge || !$delivered) {
            if ($challenge) {
                $this->Verification_model->revoke_mfa_challenge(
                    $challenge["challenge_id"],
                    (int) $user_info->id
                );
            }
            $this->Auth_security_model->audit(
                "mfa_delivery_failed",
                "denied",
                (int) $user_info->id,
                $identity_hash,
                ["provider" => $provider->name()]
            );
            return $this->signin_error_response(
                "Sign-in verification could not be delivered. Please try again later."
            );
        }

        $this->session->regenerate(true);
        $this->session->remove([
            "user_id",
            Vendor_users_model::SESSION_VENDOR_ID,
            "pending_vendor_user_id",
            "pending_vendor_redirect_url",
            "pending_vendor_authenticated_at",
        ]);
        $this->session->set([
            "pending_mfa_challenge_id" => $challenge["challenge_id"],
            "pending_mfa_user_id" => (int) $user_info->id,
            "pending_mfa_redirect_url" => (string) $this->request->getPost("redirect"),
            "pending_mfa_vendor_id" => (int) ($user_info->authenticated_vendor_id ?? 0),
            "pending_mfa_started_at" => time(),
        ]);
        $this->Auth_security_model->audit(
            "mfa_challenge_sent",
            "success",
            (int) $user_info->id,
            $identity_hash,
            ["provider" => $provider->name()]
        );

        $redirectUrl = get_uri("signin/mfa_challenge");
        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Enter the verification code sent to your registered contact method.",
                "redirect_url" => $redirectUrl,
            ]);
        }

        return redirect()->to($redirectUrl);
    }

    private function _complete_authenticated_login(
        object $user_info,
        ?string $submitted_redirect = null,
        int $requested_vendor_id = 0
    ) {
        $user_id = (int) $user_info->id;
        $memberships = $this->Vendor_users_model->get_accessible_memberships($user_id);
        // A CR chosen by verified credentials must remain accessible after OTP.
        // Revocation denies this login instead of opening a different company.
        if ($requested_vendor_id > 0
            && !$this->Vendor_users_model->get_accessible_membership($user_id, $requested_vendor_id)) {
            $this->Auth_security_model->audit(
                "login_denied", "denied", $user_id,
                $this->Auth_security_model->identity_hash((string) $user_info->email),
                ["reason" => "requested_vendor_unavailable"]
            );
            return $this->signin_error_response(app_lang("authentication_failed"));
        }
        $has_vendor_memberships = $this->Vendor_users_model->has_vendor_memberships($user_id);
        $is_vendor_only_user = $has_vendor_memberships
            && $this->Users_model->is_vendor_only_identity($user_id, $user_info);
        $is_external_portal_user = $is_vendor_only_user
            || $this->Users_model->is_gate_pass_only_identity($user_id, $user_info)
            || $this->Users_model->is_ptw_applicant_only_identity($user_id, $user_info);
        $has_active_external_membership = !empty($memberships)
            || $this->Users_model->has_active_gate_pass_portal_membership($user_id)
            || $this->Users_model->has_active_ptw_applicant_portal_membership($user_id);

        if ($is_external_portal_user && !$has_active_external_membership) {
            $this->Auth_security_model->audit(
                "login_denied",
                "denied",
                $user_id,
                $this->Auth_security_model->identity_hash((string) $user_info->email),
                ["reason" => "no_active_external_membership"]
            );
            array_push($this->signin_validation_errors, app_lang("authentication_failed"));
            return $this->signin_error_response($this->signin_validation_errors);
        }

        // Internal team members may also be linked to a vendor for legitimate
        // operational reasons. Keep their normal dashboard login; vendor-only
        // identities use the vendor portal. Explicit CR sign-in opens that CR.
        $is_vendor_login = $requested_vendor_id > 0 || ($is_vendor_only_user && count($memberships) > 0);
        $requires_vendor_selection = $is_vendor_login && count($memberships) > 1 && !$requested_vendor_id;
        $redirect_url = $this->get_signin_redirect_url(
            $is_vendor_login,
            $submitted_redirect
        );
        $this->session->regenerate(true);

        if ($requires_vendor_selection) {
            $this->session->remove(["user_id", Vendor_users_model::SESSION_VENDOR_ID]);
            $this->session->set([
                "pending_vendor_user_id" => $user_id,
                "pending_vendor_redirect_url" => $redirect_url,
                "pending_vendor_authenticated_at" => time()
            ]);
            $redirect_url = get_uri("signin/vendor_selection");
        } else {
            $active_vendor_id = $requested_vendor_id ?: ($is_vendor_login && count($memberships) === 1
                ? (int) $memberships[0]->vendor_id
                : 0);
            if (!$this->Users_model->start_user_session($user_id, $active_vendor_id)) {
                array_push($this->signin_validation_errors, app_lang("authentication_failed"));
                return $this->signin_error_response($this->signin_validation_errors);
            }
        }

        $this->Auth_security_model->audit(
            "login_success",
            "success",
            $user_id,
            $this->Auth_security_model->identity_hash((string) $user_info->email),
            ["vendor_selection_required" => $requires_vendor_selection]
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(array(
                "success" => true,
                "message" => $requires_vendor_selection
                    ? "Choose the vendor and CR you want to access."
                    : $this->get_welcome_message($user_id),
                "redirect_url" => $redirect_url
            ));
        }

        return redirect()->to($redirect_url);
    }

    function mfa_challenge()
    {
        $userId = $this->_pending_mfa_user_id();
        $challengeId = (string) $this->session->get("pending_mfa_challenge_id");
        $challenge = $userId
            ? $this->Verification_model->get_active_mfa_challenge($challengeId, $userId)
            : null;
        if (!$challenge) {
            $this->_clear_pending_mfa_login();
            $this->session->setFlashdata(
                "signin_validation_errors",
                ["The verification challenge has expired. Please sign in again."]
            );
            app_redirect("signin");
        }

        return $this->template->view("signin/index", [
            "form_type" => "mfa_challenge",
            "destination_hint" => clean_data($challenge->destination_hint),
            "remaining_attempts" => max(
                0,
                (int) $challenge->max_attempts - (int) $challenge->attempts
            ),
        ]);
    }

    function verify_mfa()
    {
        if (strtolower($this->request->getMethod()) !== "post") {
            show_404();
        }

        $config = $this->Auth_security_model->config();
        $this->validate_submitted_data([
            "code" => "required|numeric|exact_length[{$config->mfaCodeLength}]",
        ]);

        $userId = $this->_pending_mfa_user_id();
        $challengeId = (string) $this->session->get("pending_mfa_challenge_id");
        $code = trim((string) $this->request->getPost("code"));
        if (!$userId || !$challengeId) {
            return $this->_mfa_error_response(
                "The verification challenge has expired. Please sign in again.",
                true
            );
        }

        $result = $this->Verification_model->verify_and_consume_mfa_challenge(
            $challengeId,
            $userId,
            $code,
            $config->mfaHmacKey
        );
        if ($result["status"] !== "verified") {
            $terminal = in_array($result["status"], ["expired", "locked"], true);
            $this->Auth_security_model->audit(
                "mfa_challenge_" . $result["status"],
                "denied",
                $userId,
                "",
                ["remaining_attempts" => $result["remaining_attempts"]]
            );
            if ($terminal) {
                $this->_clear_pending_mfa_login();
            }

            $message = $terminal
                ? "The verification challenge has expired. Please sign in again."
                : "The verification code is not valid. "
                    . $result["remaining_attempts"] . " attempt(s) remain.";
            return $this->_mfa_error_response($message, $terminal);
        }

        $submittedRedirect = (string) $this->session->get("pending_mfa_redirect_url");
        $requestedVendorId = (int) $this->session->get("pending_mfa_vendor_id");
        $user = $this->Users_model->get_one($userId);
        if (!$user || !(int) ($user->id ?? 0) || !$this->Users_model->is_login_enabled($userId)) {
            $this->_clear_pending_mfa_login();
            return $this->_mfa_error_response(app_lang("authentication_failed"), true);
        }

        $this->_clear_pending_mfa_login();
        $this->session->regenerate(true);
        $this->Auth_security_model->audit(
            "mfa_challenge_verified",
            "success",
            $userId,
            $this->Auth_security_model->identity_hash((string) $user->email)
        );

        return $this->_complete_authenticated_login($user, $submittedRedirect, $requestedVendorId);
    }

    function vendor_selection()
    {
        $logged_user_id = (int) $this->Users_model->login_user_id();
        $user_id = $this->_vendor_selection_user_id();
        if (!$user_id) {
            app_redirect("signin");
        }

        $memberships = $this->Vendor_users_model->get_accessible_memberships($user_id);
        if (!$memberships) {
            if (!$logged_user_id) {
                $this->_clear_pending_vendor_login();
                $this->session->setFlashdata("signin_validation_errors", [app_lang("authentication_failed")]);
                app_redirect("signin");
            }
            app_redirect("forbidden");
        }

        if (count($memberships) === 1) {
            $vendor_id = (int) $memberships[0]->vendor_id;
            if ($logged_user_id) {
                $this->Vendor_users_model->set_active_vendor_context($logged_user_id, $vendor_id);
                app_redirect("vendor_portal");
            }

            $redirect_url = (string) $this->session->get("pending_vendor_redirect_url");
            $this->session->regenerate(true);
            if (!$this->Users_model->start_user_session($user_id, $vendor_id)) {
                $this->_clear_pending_vendor_login();
                $this->session->setFlashdata("signin_validation_errors", [app_lang("authentication_failed")]);
                app_redirect("signin");
            }
            return redirect()->to($redirect_url ?: get_uri("vendor_portal"));
        }

        $view_data = [
            "form_type" => "vendor_selection",
            "memberships" => $memberships,
            "is_switching_vendor" => $logged_user_id > 0
        ];

        return $this->template->view("signin/index", $view_data);
    }

    function select_vendor()
    {
        $this->validate_submitted_data(["vendor_id" => "required|numeric"]);

        $logged_user_id = (int) $this->Users_model->login_user_id();
        $user_id = $this->_vendor_selection_user_id();
        $vendor_id = (int) $this->request->getPost("vendor_id");
        $membership = $this->Vendor_users_model->get_accessible_membership($user_id, $vendor_id);

        if (!$user_id || !$membership) {
            $message = "That vendor or CR is not available for this account.";
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(["success" => false, "message" => $message]);
            }
            $this->session->setFlashdata("signin_validation_errors", [$message]);
            app_redirect($logged_user_id ? "signin/vendor_selection" : "signin");
        }

        $redirect_url = get_uri("vendor_portal");
        if ($logged_user_id) {
            $this->Vendor_users_model->set_active_vendor_context($logged_user_id, $vendor_id);
        } else {
            $redirect_url = (string) $this->session->get("pending_vendor_redirect_url") ?: $redirect_url;
            $this->session->regenerate(true);
            if (!$this->Users_model->start_user_session($user_id, $vendor_id)) {
                $this->_clear_pending_vendor_login();
                $this->session->setFlashdata("signin_validation_errors", [app_lang("authentication_failed")]);
                app_redirect("signin");
            }
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                "success" => true,
                "message" => "Vendor profile selected.",
                "redirect_url" => $redirect_url
            ]);
        }

        return redirect()->to($redirect_url);
    }

    private function _pending_vendor_user_id(): int
    {
        $user_id = (int) $this->session->get("pending_vendor_user_id");
        $authenticated_at = (int) $this->session->get("pending_vendor_authenticated_at");

        if (!$user_id || !$authenticated_at || (time() - $authenticated_at) > 600) {
            $this->_clear_pending_vendor_login();
            return 0;
        }

        return $user_id;
    }

    private function _pending_mfa_user_id(): int
    {
        $userId = (int) $this->session->get("pending_mfa_user_id");
        $startedAt = (int) $this->session->get("pending_mfa_started_at");
        $challengeId = (string) $this->session->get("pending_mfa_challenge_id");
        $maximumAge = $this->Auth_security_model->config()->mfaLifetimeSeconds + 60;

        if (!$userId
            || !$startedAt
            || !$challengeId
            || (time() - $startedAt) > $maximumAge
            || !$this->Verification_model->get_active_mfa_challenge($challengeId, $userId)) {
            $this->_clear_pending_mfa_login();
            return 0;
        }

        return $userId;
    }

    private function _clear_pending_mfa_login(): void
    {
        $this->session->remove([
            "pending_mfa_challenge_id",
            "pending_mfa_user_id",
            "pending_mfa_redirect_url",
            "pending_mfa_vendor_id",
            "pending_mfa_started_at",
        ]);
    }

    private function _mfa_error_response(string $message, bool $restart)
    {
        if ($this->request->isAJAX()) {
            $response = [
                "success" => false,
                "message" => esc($message),
            ];
            if ($restart) {
                $response["redirect_url"] = get_uri("signin");
            }
            return $this->response->setJSON($response);
        }

        $this->session->setFlashdata("signin_validation_errors", [$message]);
        app_redirect($restart ? "signin" : "signin/mfa_challenge");
    }

    private function _clear_pending_vendor_login(): void
    {
        $this->session->remove([
            "pending_vendor_user_id",
            "pending_vendor_redirect_url",
            "pending_vendor_authenticated_at"
        ]);
    }

    private function _vendor_selection_user_id(): int
    {
        return (int) $this->Users_model->login_user_id() ?: $this->_pending_vendor_user_id();
    }

    function sign_out() {
        $userId = (int) $this->Users_model->login_user_id();
        if ($userId) {
            $user = $this->Users_model->get_one($userId);
            $this->Auth_security_model->audit(
                "logout",
                "success",
                $userId,
                $this->Auth_security_model->identity_hash(
                    (string) ($user->email ?? "")
                )
            );
        }
        $this->Users_model->sign_out();
    }

    //send an email to users mail with reset password link
    function send_reset_password_mail() {
        if (strtolower($this->request->getMethod()) !== "post") {
            show_404();
        }

        $this->validate_submitted_data(array(
            "email" => "required|valid_email|max_length[100]"
        ));

        //if reCaptcha is enabled, check the validation
        $ReCAPTCHA = new ReCAPTCHA();
        $ReCAPTCHA->validate_recaptcha();

        $email = strtolower(trim((string) $this->request->getPost("email")));
        $identityHash = $this->Auth_security_model->identity_hash($email);
        $ipHash = $this->Auth_security_model->ip_hash(
            (string) $this->request->getIPAddress()
        );
        $config = $this->Auth_security_model->config();
        $throttler = service("throttler");
        $identityAllowed = $throttler->check(
            "password_reset_identity_{$identityHash}",
            $config->resetRequestIdentityLimit,
            $config->resetRequestWindowSeconds
        );
        $ipAllowed = $throttler->check(
            "password_reset_ip_{$ipHash}",
            $config->resetRequestIpLimit,
            $config->resetRequestWindowSeconds
        );
        if (!$identityAllowed || !$ipAllowed) {
            $this->Auth_security_model->audit(
                "password_reset_rate_limited",
                "accepted",
                0,
                $identityHash
            );
            return $this->_reset_request_response();
        }

        $user = $this->Users_model->find_password_reset_user($email);
        if (!$user) {
            $this->Auth_security_model->audit(
                "password_reset_requested",
                "accepted",
                0,
                $identityHash
            );
            return $this->_reset_request_response();
        }

        $token = null;
        $delivered = false;
        try {
            $token = $this->Verification_model->issue_password_reset_token(
                (int) $user->id,
                $ipHash,
                $config->resetTokenLifetimeSeconds
            );
            if ($token) {
                $emailTemplate = $this->Email_templates_model->get_final_template(
                    "reset_password",
                    true
                );
                $userLanguage = $user->language;
                $parserData["ACCOUNT_HOLDER_NAME"] = clean_data(
                    $user->first_name . " " . $user->last_name
                );
                $parserData["SIGNATURE"] = get_array_value(
                    $emailTemplate,
                    "signature_{$userLanguage}"
                ) ?: get_array_value($emailTemplate, "signature_default");
                $parserData["LOGO_URL"] = get_logo_url();
                $parserData["SITE_URL"] = get_uri();
                $parserData["RECIPIENTS_EMAIL_ADDRESS"] = $user->email;
                $parserData["RESET_PASSWORD_URL"] = get_uri(
                    "signin/new_password/" . $token
                );
                $message = get_array_value($emailTemplate, "message_{$userLanguage}")
                    ?: get_array_value($emailTemplate, "message_default");
                $subject = get_array_value($emailTemplate, "subject_{$userLanguage}")
                    ?: get_array_value($emailTemplate, "subject_default");
                $message = $this->parser->setData($parserData)->renderString($message);
                $subject = $this->parser->setData($parserData)->renderString($subject);
                $delivered = (bool) send_app_mail($user->email, $subject, $message);
            }
        } catch (\Throwable $exception) {
            log_message("error", "Password reset delivery failed: {message}", [
                "message" => $exception->getMessage(),
            ]);
        }
        if ($token && !$delivered) {
            $this->Verification_model->revoke_password_reset_token($token);
        }

        $this->Auth_security_model->audit(
            $delivered ? "password_reset_sent" : "password_reset_delivery_failed",
            $delivered ? "success" : "failed",
            (int) $user->id,
            $identityHash
        );

        return $this->_reset_request_response();
    }

    private function _reset_request_response()
    {
        // This response is intentionally identical for unknown, ambiguous,
        // throttled, disabled, and successfully mailed accounts.
        return $this->response->setJSON([
            "success" => true,
            "message" => app_lang("reset_info_send"),
        ]);
    }

    //show forgot password recovery form
    function request_reset_password() {
        $view_data["form_type"] = "request_reset_password";
        return $this->template->view('signin/index', $view_data);
    }

    //when user clicks to reset password link from his/her email, redirect to this url
    function new_password($key) {
        $validToken = $this->Verification_model->get_valid_password_reset_token(
            (string) $key
        );
        if ($validToken) {
            return $this->template->view("signin/index", [
                "key" => clean_data($key),
                "form_type" => "new_password",
            ]);
        }

        $view_data["heading"] = "Invalid Request";
        $view_data["message"] = "This password reset link is invalid or has expired.";
        return $this->template->view("errors/html/error_general", $view_data);
    }

    //finally reset the old password and save the new password
    function do_reset_password() {
        if (strtolower($this->request->getMethod()) !== "post") {
            show_404();
        }

        $this->validate_submitted_data(array(
            "key" => "required",
            "password" => "required",
            "retype_password" => "required|matches[password]"
        ));

        $key = (string) $this->request->getPost("key");
        $password = (string) $this->request->getPost("password");
        $policyErrors = $this->Users_model->password_policy_errors($password);
        if ($policyErrors) {
            return $this->response->setJSON([
                "success" => false,
                "message" => esc(implode(" ", $policyErrors)),
            ]);
        }

        $userId = $this->Verification_model->consume_password_reset_token(
            $key,
            password_hash($password, PASSWORD_DEFAULT)
        );
        if (!$userId) {
            $this->Auth_security_model->audit(
                "password_reset_consumption_failed",
                "denied"
            );
            return $this->response->setJSON([
                "success" => false,
                "message" => "This password reset link is invalid or has expired.",
            ]);
        }

        $user = $this->Users_model->get_one($userId);
        $this->Auth_security_model->audit(
            "password_reset_completed",
            "success",
            $userId,
            $this->Auth_security_model->identity_hash((string) ($user->email ?? ""))
        );
        if ((int) $this->session->get("user_id") === $userId) {
            $this->session->remove([
                "user_id",
                "auth_session_version",
                Vendor_users_model::SESSION_VENDOR_ID,
            ]);
            $this->session->regenerate(true);
        }

        return $this->response->setJSON([
            "success" => true,
            "message" => app_lang("password_reset_successfully") . " "
                . anchor("signin", app_lang("signin")),
        ]);
    }
}
