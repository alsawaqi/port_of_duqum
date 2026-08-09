<?php

$source = (string)file_get_contents(__DIR__ . "/../app/Controllers/Cron.php");
$checks = [
    'PHP_SAPI === "cli"' => "CLI scheduler remains supported without an HTTP secret",
    'getMethod()) !== "POST"' => "HTTP cron is POST-only",
    'PODC_CRON_KEY' => "HTTP cron uses an environment-only secret",
    'strlen($expected) < 32' => "weak or missing cron secrets fail closed",
    'hash_equals($expected, $provided)' => "cron secret comparison is timing safe",
    'service("throttler")' => "cron authentication attempts are rate limited",
];
foreach ($checks as $needle => $message) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
        exit(1);
    }
}
echo "Cron endpoint hardening contracts passed." . PHP_EOL;
