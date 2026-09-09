<?php

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $contents = file_get_contents($root . "/" . $path);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$path}." . PHP_EOL);
        exit(1);
    }

    return $contents;
};

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Missing: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$assertNotContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Unexpected: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$signin = $read("app/Controllers/Signin.php");
$usersModel = $read("app/Models/Users_model.php");
$vendorUsersModel = $read("app/Models/Vendor_users_model.php");
$vendorPortal = $read("app/Controllers/Vendor_portal.php");
$signinForm = $read("app/Views/signin/signin_form.php");
$signinIndex = $read("app/Views/signin/index.php");
$vendorSelection = $read("app/Views/signin/vendor_selection.php");

// One identifier field accepts email or CR; all context follows verified credentials.
$assertContains('"email" => "required"', $signin, "sign-in requires an email");
$assertContains('"password" => "required"', $signin, "sign-in requires a password");
$assertContains('authenticate_signin_credentials($email, $password)', $signin, "email or CR credentials are checked before vendor selection");
$assertContains('WHERE LOWER(email) = ?', $usersModel, "accounts are looked up by canonical email");
$assertNotContains('$this->request->getPost("cr_number")', $signin, "a second CR field cannot override the verified identifier");
$assertNotContains('$this->request->getPost("username")', $signin, "username must not be submitted as a login credential");

// A sole accessible CR is written directly into the authenticated session.
$assertContains('$is_vendor_login && count($memberships) === 1', $signin, "one accessible vendor-only CR is auto-selected");
$assertContains('start_user_session($user_id, $active_vendor_id)', $signin, "auto-selected CR starts the full session");
$assertContains('$session->set(\'active_vendor_id\', $active_vendor_id)', $usersModel, "the selected CR is persisted in session");
$assertContains('!$this->is_login_enabled($user_id)', $usersModel, "a disabled account cannot complete a pending CR selection");

// Multiple CRs produce a short-lived, unauthenticated selection state. The
// full user_id session is deliberately withheld until selection succeeds.
$assertContains('$requires_vendor_selection = $is_vendor_login && count($memberships) > 1', $signin, "vendor-only users with multiple CRs enter the selector flow");
$assertContains('$requested_vendor_id > 0 || ($is_vendor_only_user && count($memberships) > 0)', $signin, "email login preserves staff routing while explicit CR opens the vendor");
$assertContains('is_vendor_only_identity($user_id, $user_info)', $signin, "mixed operational identities are not forced into the vendor flow");
$assertContains('get_accessible_memberships($user_id)) > 0', $usersModel, "legacy authentication cannot bypass vendor approval");
$assertContains('in_array($redirectPath, ["", "signin", "forbidden"], true)', $signin, "login cannot bounce users back to forbidden after successful authentication");
$assertContains('return get_uri($vendor_login ? "vendor_portal" : "dashboard")', $signin, "unsafe post-login redirects fall back to the correct landing page");
$assertContains('$allowedVendorRedirects = [', $signin, "vendor-only logins are confined to vendor-safe redirect targets");
$assertContains('$this->session->remove(["user_id", Vendor_users_model::SESSION_VENDOR_ID])', $signin, "selector state is not a logged-in session");
$assertContains('"pending_vendor_user_id" => $user_id', $signin, "verified identity is retained temporarily");
$assertContains('"pending_vendor_authenticated_at" => time()', $signin, "pending authentication is timestamped");
$assertContains('(time() - $authenticated_at) > 600', $signin, "pending authentication expires");
$assertContains('get_uri("signin/vendor_selection")', $signin, "multi-CR login is sent to the selector");

// The posted vendor id is never trusted. It must resolve to an active,
// accessible membership for this exact user before a full session is created.
$assertContains('get_accessible_membership($user_id, $vendor_id)', $signin, "submitted CR is checked against the identity");
$assertContains('if (!$user_id || !$membership)', $signin, "missing or forged membership is rejected");
$assertContains('That vendor or CR is not available for this account.', $signin, "rejected selection has a safe error");
$assertContains('$builder->where("vendor_memberships.user_id", $userId)', $vendorUsersModel, "membership queries are user-scoped");
$assertContains('$builder->where("vendor_memberships.deleted", 0)', $vendorUsersModel, "deleted memberships are inaccessible");
$assertContains('$builder->whereIn("vendor_memberships.status", $membershipStatuses)', $vendorUsersModel, "membership status is enforced");
$assertContains('$builder->whereIn("vendors.status", $vendorStatuses)', $vendorUsersModel, "vendor status is enforced");

// Every portal request re-resolves the session CR through accessible
// memberships. A revoked/stale selection is removed and cannot remain active.
$assertContains('$this->Vendor_users_model->resolve_context($user_id)', $vendorPortal, "portal revalidates selected context on every access");
$assertContains('get_accessible_membership($userId, $sessionVendorId, $options)', $vendorUsersModel, "session CR is revalidated against live access");
$assertContains('Services::session()->remove(self::SESSION_VENDOR_ID)', $vendorUsersModel, "stale selected CR is cleared");
$assertContains('count($this->Vendor_users_model->get_accessible_memberships($user_id)) > 1', $vendorPortal, "missing multi-CR context returns to selector");
$assertContains('app_redirect("signin/vendor_selection")', $vendorPortal, "portal cannot guess among multiple CRs");

// Browser validation must allow CRs in the shared identifier input.
$assertContains('"type" => "text"', $signinForm, "login accepts both email and CR text");
$assertContains('"placeholder" => app_lang(\'email_or_cr_number\')', $signinForm, "placeholder explains both login methods");
$assertContains('"name" => "email"', $signinForm, "email is posted by the login form");
$assertContains('"name" => "password"', $signinForm, "password is posted by the login form");
$assertNotContains('"name" => "username"', $signinForm, "login has no username input");
$assertNotContains('"name" => "cr_number"', $signinForm, "login uses one identifier input");
$assertContains('$form_type == "vendor_selection"', $signinIndex, "sign-in shell can render vendor selection");
$assertContains('name="vendor_id"', $vendorSelection, "selector posts an internal vendor id");
$assertContains('$membership->vendor_name', $vendorSelection, "selector identifies the vendor company");
$assertContains('$membership->cr_number', $vendorSelection, "selector identifies the CR");

echo "Vendor multi-CR authentication contracts passed." . PHP_EOL;
