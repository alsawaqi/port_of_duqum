<?php

require_once __DIR__ . "/../app/Helpers/general_helper.php";

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$now = "2026-05-10 18:30:00";

$terminal_required = ptw_hmo_approval_application_update((object) ["terminal_approval_required" => 1], $now);
$assertSame("terminal", $terminal_required["stage"] ?? null, "HMO approval goes to Terminal when terminal approval is required");
$assertSame("submitted", $terminal_required["status"] ?? null, "Terminal-required application remains submitted");
$assertSame(null, $terminal_required["completed_at"] ?? null, "Terminal-required application is not completed by HMO");

$terminal_not_required = ptw_hmo_approval_application_update((object) ["terminal_approval_required" => 0], $now);
$assertSame("completed", $terminal_not_required["stage"] ?? null, "HMO approval completes when terminal approval is not required");
$assertSame("approved", $terminal_not_required["status"] ?? null, "Terminal-skipped application is approved by HMO");
$assertSame($now, $terminal_not_required["completed_at"] ?? null, "Terminal-skipped application gets completion time");

echo "PTW terminal approval workflow passed." . PHP_EOL;
