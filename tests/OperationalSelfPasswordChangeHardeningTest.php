<?php

$root = dirname(__DIR__);
$controller = file_get_contents($root . "/app/Controllers/Security_Controller.php");
$view = file_get_contents($root . "/app/Views/includes/operational_user_identity_fields.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) === false) {
        $fail($message . " Missing: " . $needle);
    }
};
$assertNotContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (strpos($haystack, $needle) !== false) {
        $fail($message . " Unexpected: " . $needle);
    }
};

$start = strpos($controller, "protected function resolve_operational_assignment_user");
$end = strpos($controller, "protected function save_operational_user_assignment", $start ?: 0);
if ($start === false || $end === false || $end <= $start) {
    $fail("the shared operational-user resolution method should be inspectable");
}
$method = substr($controller, $start, $end - $start);
$saveStart = $end;
$saveEnd = strpos(
    $controller,
    "// ---------------------------------------------------------",
    $saveStart
);
if ($saveEnd === false || $saveEnd <= $saveStart) {
    $fail("the shared operational-user persistence method should be inspectable");
}
$saveMethod = substr($controller, $saveStart, $saveEnd - $saveStart);

$assertContains(
    '$current_user_id === (int) ($this->login_user->id ?? 0)',
    $method,
    "reauthentication is limited to a true self-targeted password change"
);
$assertContains(
    '$this->request->getPost("current_password")',
    $method,
    "the current credential is read separately from the new password"
);
$assertContains(
    'verify_user_password(',
    $method,
    "the supplied current password is verified against the target user ID"
);
$assertContains(
    'operational_password_change_{$current_user_id}_{$ip_hash}',
    $method,
    "current-password guesses are throttled by account and IP"
);
$assertContains(
    '$this->response->setStatusCode(429)',
    $method,
    "rate-limited self changes return the appropriate HTTP status"
);
$assertContains(
    '"channel" => "operational_assignment"',
    $method,
    "failed and successful self-change events identify the shared editor"
);
$assertContains(
    '$this->Users_model->ci_save($user_data, $current_user_id)',
    $method,
    "password persistence uses the session-version-aware user model"
);
$assertContains(
    '$this->session->regenerate(true)',
    $saveMethod,
    "the retained self session receives a fresh identifier after commit"
);
$assertContains(
    '$this->session->remove("auth_session_version")',
    $saveMethod,
    "a rolled-back password write discards the tentative session version"
);
$assertContains(
    '"password_changed"',
    $saveMethod,
    "a committed self password change is audited"
);
$assertNotContains(
    'clean_data($current_password)',
    $method,
    "the current password is not altered by HTML sanitization"
);
$assertNotContains(
    '"current_password" =>',
    $method,
    "the current password is never copied into an audit or persistence payload"
);

$assertContains(
    '(int) ($model_info->user_id ?? 0) === $sessionUserId',
    $view,
    "the current-password field is rendered only for a self assignment"
);
$assertContains(
    '"name" => "current_password"',
    $view,
    "the shared form exposes the reauthentication field"
);
$assertContains(
    '"autocomplete" => "current-password"',
    $view,
    "the reauthentication field has correct browser semantics"
);
$assertContains(
    'setRequired($currentPassword, hasPassword)',
    $view,
    "the form requires current password only when a new self password is entered"
);

echo "Operational self-password hardening contracts passed." . PHP_EOL;
