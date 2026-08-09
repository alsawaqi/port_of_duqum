<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . "/app/Controllers/Portal_account.php");
$security = file_get_contents($root . "/app/Controllers/Security_Controller.php");
$menu = file_get_contents($root . "/app/Libraries/Left_menu.php");
$topbar = file_get_contents($root . "/app/Views/includes/topbar.php");
$view = file_get_contents($root . "/app/Views/portal_account/change_password.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};
$notContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (str_contains($haystack, $needle)) {
        $fail($message . " Unexpected: " . $needle);
    }
};

$contains('class Portal_account extends Security_Controller', $controller, 'the account surface requires an authenticated session');
$contains('strtolower($this->request->getMethod()) !== "post"', $controller, 'password mutation is POST-only');
$contains('$userId = (int) ($this->login_user->id ?? 0)', $controller, 'the authenticated identity is the only password target');
$notContains('getPost("user_id")', $controller, 'a client cannot choose another password target');
$contains('password_policy_errors($newPassword)', $controller, 'the shared strong-password policy is enforced');
$contains('verify_user_password($userId, $currentPassword)', $controller, 'the current password is required');
$contains('portal_password_change_{$userId}_{$ipHash}', $controller, 'current-password guesses are rate limited by user and IP');
$contains('->setStatusCode(429)', $controller, 'rate limiting returns HTTP 429');
$contains('password_hash($newPassword, PASSWORD_DEFAULT)', $controller, 'the new password is one-way hashed');
$contains('ci_save(["password" => $passwordHash], $userId)', $controller, 'the session-version-aware user model persists the password');
$contains('$this->session->regenerate(true)', $controller, 'the retained session gets a fresh identifier');
$contains('["channel" => "portal_account"]', $controller, 'changes and denials are security audited');
$contains('"portal_account", "notifications"', $security, 'external identities may reach only the narrow account endpoint in addition to their portals');

$contains('$isGatePassPortalIdentity', $menu, 'gate-pass identity navigation is classified');
$contains('$isPtwPortalIdentity', $menu, 'PTW identity navigation is classified');
$contains('($isVendorPortalIdentity || $isGatePassPortalIdentity || $isPtwPortalIdentity)', $menu, 'all external-only identities receive a reduced menu');
$contains('"url" => "portal_account/change_password"', $menu, 'the reduced menu includes self password change');
$contains('$is_external_portal_only_identity', $topbar, 'the top bar recognizes every external-only identity');
$contains('get_uri("portal_account/change_password")', $topbar, 'non-vendor portal users can change their password');
$contains('form_open(get_uri("portal_account/save_password")', $view, 'the self-service form posts to the narrow controller');
$contains('"autocomplete" => "current-password"', $view, 'the current credential field has safe browser semantics');
$contains('"autocomplete" => "new-password"', $view, 'the new credential field has safe browser semantics');

echo "External portal account hardening contracts passed." . PHP_EOL;
