<?php

$signin = file_get_contents(dirname(__DIR__) . "/app/Controllers/Signin.php");
$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $message) use ($signin, $fail): void {
    if (!str_contains($signin, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$contains('$baseParts = parse_url(base_url())', 'redirect validation uses the configured application origin');
$contains('hash_equals($baseHost, $redirectHost)', 'redirect host must exactly match');
$contains('hash_equals($baseScheme, $redirectScheme)', 'redirect cannot downgrade HTTPS or switch protocols');
$contains('$basePort === $redirectPort', 'redirect port must stay on the configured origin');
$contains('empty($redirectParts["user"])', 'redirect userinfo is rejected');
$contains('empty($redirectParts["pass"])', 'redirect credentials are rejected');
$contains("preg_match('/[\\r\\n]/'", 'redirect header injection is rejected');

echo "Sign-in redirect origin hardening contracts passed." . PHP_EOL;
