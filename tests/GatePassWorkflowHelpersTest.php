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

$assertTrue = static function ($actual, string $message): void {
    if ($actual !== true) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$assertFalse = static function ($actual, string $message): void {
    if ($actual !== false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$assertSame(1, gate_pass_visit_duration_days("2026-05-10 00:00:00", "2026-05-10 23:59:59"), "same-day visits count as one inclusive day");
$assertSame(3, gate_pass_visit_duration_days("2026-05-10 09:00:00", "2026-05-12 17:00:00"), "multi-day visits count inclusive calendar days");
$assertSame(0, gate_pass_visit_duration_days("2026-05-12 00:00:00", "2026-05-10 23:59:59"), "reversed visits are invalid");
$assertSame("3 days", gate_pass_visit_duration_label("2026-05-10 09:00:00", "2026-05-12 17:00:00"), "duration label is human-readable");

$notYet = gate_pass_validity_status("2026-05-11 00:00:00", "2026-05-12 23:59:59", "2026-05-10 12:00:00");
$assertFalse($notYet["is_valid"], "future gate pass is not valid today");
$assertSame("not_yet_valid", $notYet["status"], "future gate pass reports not-yet-valid status");

$valid = gate_pass_validity_status("2026-05-10 00:00:00", "2026-05-12 23:59:59", "2026-05-10 12:00:00");
$assertTrue($valid["is_valid"], "gate pass is valid inside assigned period");
$assertSame("valid", $valid["status"], "inside period reports valid status");

$expired = gate_pass_validity_status("2026-05-08 00:00:00", "2026-05-09 23:59:59", "2026-05-10 12:00:00");
$assertFalse($expired["is_valid"], "expired gate pass is not valid");
$assertSame("expired", $expired["status"], "expired pass reports expired status");

$targets = gate_pass_issue_targets([
    (object) ["id" => 101, "full_name" => "Visitor One", "deleted" => 0],
    (object) ["id" => 102, "full_name" => "Visitor Two", "deleted" => 0],
]);
$assertSame(2, count($targets), "one issue target is created per visitor");
$assertSame(101, $targets[0]["visitor_id"], "first target keeps visitor id");
$assertSame(102, $targets[1]["visitor_id"], "second target keeps visitor id");

$fallbackTargets = gate_pass_issue_targets([]);
$assertSame(1, count($fallbackTargets), "request-level fallback pass is available when no visitor rows exist");
$assertSame(null, $fallbackTargets[0]["visitor_id"], "fallback issue target has no visitor id");

$assertSame(
    [101, 102],
    gate_pass_scan_visitor_ids_to_log(["101", "102", "999", "101"], null, [101, 102, 103]),
    "scan logs only selected visitors that belong to the request"
);
$assertSame(
    [102],
    gate_pass_scan_visitor_ids_to_log([], 102, [101, 102, 103]),
    "visitor-specific QR defaults the scan log to its assigned visitor"
);
$assertSame(
    [],
    gate_pass_scan_visitor_ids_to_log([], null, [101, 102, 103]),
    "request with visitors requires a visitor selection when QR is not visitor-specific"
);
$assertSame(
    [null],
    gate_pass_scan_visitor_ids_to_log([], null, []),
    "vehicle-only or legacy request-level scans can still be logged"
);

$internationalPlate = gate_pass_prepare_vehicle_plate_payload(true, "", "", "United Arab Emirates", "dubai / 12345");
$assertTrue($internationalPlate["ok"], "international plate payload accepts country plus free-text plate");
$assertSame(1, $internationalPlate["data"]["is_international_plate"], "international plate payload marks the vehicle as international");
$assertSame("United Arab Emirates", $internationalPlate["data"]["plate_country"], "international plate payload keeps selected country");
$assertSame("DUBAI / 12345", $internationalPlate["data"]["international_plate_no"], "international plate payload normalizes free-text plate");
$assertSame("DUBAI / 12345 (United Arab Emirates)", gate_pass_vehicle_plate_display((object) $internationalPlate["data"]), "international plate display includes free-text plate and country");

echo "Gate pass workflow helpers passed." . PHP_EOL;
