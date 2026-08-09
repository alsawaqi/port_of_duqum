<?php

$root = dirname(__DIR__);
$notifications = file_get_contents($root . "/app/Helpers/notifications_helper.php");
$pusher = file_get_contents($root . "/app/Libraries/Pusher_connect.php");

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$contains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message . " Missing: " . $needle);
    }
};

$contains("function is_allowed_slack_webhook_url", $notifications, "Slack destinations have a centralized allowlist");
$contains('["hooks.slack.com", "hooks.slack-gov.com"]', $notifications, "only official Slack webhook hosts are allowed");
$contains('preg_match(\'#^/services/', $notifications, "Slack webhook paths are structurally constrained");
$contains('CURLOPT_SSL_VERIFYPEER => true', $notifications, "Slack verifies the TLS certificate");
$contains('CURLOPT_SSL_VERIFYHOST => 2', $notifications, "Slack verifies the TLS hostname");
$contains('CURLOPT_PROTOCOLS => CURLPROTO_HTTPS', $notifications, "Slack cannot downgrade to another protocol");
$contains('CURLOPT_FOLLOWLOCATION => false', $notifications, "Slack cannot redirect to a private destination");
$contains('CURLOPT_TIMEOUT => 10', $notifications, "Slack requests are bounded");

$contains("preg_match('/^[A-Za-z0-9-]{1,64}$/D'", $pusher, "Pusher instance IDs cannot alter the destination URL");
$contains('CURLOPT_SSL_VERIFYPEER => true', $pusher, "Pusher verifies the TLS certificate");
$contains('CURLOPT_SSL_VERIFYHOST => 2', $pusher, "Pusher verifies the TLS hostname");
$contains('CURLOPT_PROTOCOLS => CURLPROTO_HTTPS', $pusher, "Pusher is HTTPS-only");
$contains('return $httpCode >= 200 && $httpCode < 300;', $pusher, "Pusher requires a successful provider response");

echo "Outbound integration SSRF and TLS hardening contracts passed." . PHP_EOL;
