<?php

$signin = file_get_contents(__DIR__ . "/../app/Controllers/Signin.php");
$security = file_get_contents(__DIR__ . "/../app/Controllers/Security_Controller.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};

$assertContains = static function (
    string $needle,
    string $haystack,
    string $message
) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$assertContains('service("throttler")', $signin, "sign-in attempts use the framework throttler");
$assertContains('"signin_pair_{$ip_hash}_{$identity_hash}"', $signin, "sign-in throttling is scoped to IP and canonical email");
$assertContains('"signin_ip_{$ip_hash}"', $signin, "broad guessing is also bounded per IP");
$assertContains('->setStatusCode(429)', $signin, "excess sign-in attempts receive HTTP 429");
$assertContains('"Retry-After"', $signin, "throttled clients receive retry guidance");
$assertContains('$throttler->remove("signin_pair_{$ip_hash}_{$identity_hash}")', $signin, "successful authentication clears the pair failure bucket");
$assertContains(
    "Too many sign-in attempts. Please wait and try again.",
    $signin,
    "throttling does not reveal whether the account exists"
);

$assertContains(
    '$this->Users_model->is_login_enabled((int) $login_user_id)',
    $security,
    "protected requests recheck the live account state"
);
$assertContains('$this->Users_model->sign_out();', $security, "disabled users have their live session removed");

echo "Authentication hardening contracts passed." . PHP_EOL;
