<?php

$controller = file_get_contents(__DIR__ . "/../app/Controllers/Vendor_portal.php");
$view = file_get_contents(__DIR__ . "/../app/Views/vendor_portal/change_password.php");
$topbar = file_get_contents(__DIR__ . "/../app/Views/includes/topbar.php");

$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        fwrite(STDERR, "Missing: {$needle}" . PHP_EOL);
        exit(1);
    }
};

$assertContains('strtolower($this->request->getMethod()) !== "post"', $controller, "password mutation requires POST");
$assertContains('"new_password" => "required|min_length[10]|max_length[72]"', $controller, "new password length is validated server-side");
$assertContains('password_policy_errors($new_password)', $controller, "the shared policy prevents bcrypt truncation and weak passwords");
$assertContains('"new_password_confirm" => "required|matches[new_password]"', $controller, "password confirmation is validated server-side");
$assertContains('$user_id = (int) ($this->login_user->id ?? 0)', $controller, "password target is always the logged-in user");
$assertContains('verify_user_password($user_id, $current_password)', $controller, "current password is verified");
$assertContains('service("throttler")', $controller, "current-password failures are rate limited");
$assertContains('"password_change_{$user_id}_{$ip_hash}"', $controller, "password throttling is scoped to the user and source IP");
$assertContains('->setStatusCode(429)', $controller, "excess password attempts receive HTTP 429");
$assertContains('hash_equals($current_password, $new_password)', $controller, "current password cannot be reused");
$assertContains('password_hash($new_password, PASSWORD_DEFAULT)', $controller, "new password uses PHP password hashing");
$assertContains('ci_save(["password" => $password_hash], $user_id)', $controller, "password is saved by logged-in user ID");
$assertContains('$this->session->regenerate(true)', $controller, "session ID is regenerated after the change");
$assertContains('$this->session->set("active_vendor_id", $active_vendor_id)', $controller, "active CR context is retained");

$throttlePosition = strpos($controller, 'if (!$throttler->check($throttle_key, 5, 600))');
$verifyPosition = strpos($controller, 'if (!$this->Users_model->verify_user_password($user_id, $current_password))');
if ($throttlePosition === false || $verifyPosition === false || $throttlePosition >= $verifyPosition) {
    fwrite(STDERR, "Assertion failed: password attempts must be rate-limited before current-password verification" . PHP_EOL);
    exit(1);
}

$assertContains('form_open(get_uri("vendor_portal/save_password")', $view, "vendor password form posts to the confined controller");
$assertContains('name" => "current_password"', $view, "form asks for the current password");
$assertContains('name" => "new_password"', $view, "form asks for a new password");
$assertContains('name" => "new_password_confirm"', $view, "form confirms the new password");
$assertContains('"autocomplete" => "current-password"', $view, "current password has the correct autocomplete purpose");
$assertContains('"autocomplete" => "new-password"', $view, "new password has the correct autocomplete purpose");
$assertContains('applies to every CR linked to your email', $view, "view explains that passwords are identity-scoped");
$assertContains('get_uri("vendor_portal/change_password")', $topbar, "vendor dropdown exposes password change");

echo "Vendor password change contracts passed." . PHP_EOL;
