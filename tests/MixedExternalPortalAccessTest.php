<?php

$root = dirname(__DIR__);
$users = file_get_contents($root . "/app/Models/Users_model.php");
$signin = file_get_contents($root . "/app/Controllers/Signin.php");
$security = file_get_contents($root . "/app/Controllers/Security_Controller.php");
$menu = file_get_contents($root . "/app/Libraries/Left_menu.php");
$topbar = file_get_contents($root . "/app/Views/includes/topbar.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$contains('function has_active_vendor_portal_membership', $users, 'active CR access is distinct from vendor identity history');
$contains('function has_active_gate_pass_portal_membership', $users, 'active Gate Pass access is explicitly resolved');
$contains('function has_active_ptw_applicant_portal_membership', $users, 'active PTW access is explicitly resolved');
$contains("assignments.status='active'", $users, 'PTW navigation requires an active assignment');
$contains("status='active' LIMIT 1", $users, 'Gate Pass navigation requires an active assignment');
$contains('has_active_gate_pass_portal_membership($user_id)', $signin, 'an active Gate Pass membership can satisfy an external login');
$contains('has_active_ptw_applicant_portal_membership($user_id)', $signin, 'an active PTW membership can satisfy an external login');
$contains('if ($is_external_portal_user && !$has_active_external_membership)', $signin, 'role-less identities with no active external membership are denied');
$contains('"reason" => "no_active_external_membership"', $signin, 'inactive external login denial is audited without exposing membership details');

$contains('$this->_set_active_external_portal_flags();', $security, 'protected requests resolve current portal access');
$contains('$this->_redirect_external_dashboard($enforceExternalControllerBoundary);', $security, 'dashboard routing and revocation use the authenticated external boundary');
$contains('$this->_confine_vendor_only_identity(false);', $security, 'all external identity histories are classified before a controller decision');
$contains('$controller !== "dashboard" && !in_array(', $security, 'dashboard requests reach the centralized active-membership redirect');
$contains('has_active_vendor_portal_access', $security, 'vendor is selected only when a CR is currently accessible');
$contains('has_active_gate_pass_portal_access', $security, 'Gate Pass remains reachable when vendor access is inactive');
$contains('has_active_ptw_portal_access', $security, 'PTW remains reachable when other external access is inactive');
$contains('if ($isExternalPortalIdentity && !$hasActiveExternalAccess)', $security, 'revoking the last external membership invalidates existing access');
$contains('$this->Users_model->sign_out();', $security, 'a fully revoked external identity loses its existing session');
$contains('app_redirect("forbidden")', $security, 'an external identity with no active portal fails closed');

$contains('$hasActiveVendorPortalAccess', $menu, 'the reduced menu uses current vendor access');
$contains('$hasActiveGatePassPortalAccess', $menu, 'the reduced menu uses current Gate Pass access');
$contains('$hasActivePtwPortalAccess', $menu, 'the reduced menu uses current PTW access');
$contains('$has_active_vendor_portal_access', $topbar, 'topbar portal links use current access rather than historical identity alone');
$contains('"portal_account/change_password"', $topbar, 'non-vendor external identities receive the shared password route');

echo "Mixed external portal access contracts passed." . PHP_EOL;
