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

$assertContains = static function (string $needle, array $haystack, string $message): void {
    if (!in_array($needle, $haystack, true)) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        fwrite(STDERR, "Haystack: " . implode(" | ", $haystack) . PHP_EOL);
        exit(1);
    }
};

$assertSame(
    "1 day 2 hours 15 minutes",
    ptw_duration_between("2026-05-09 08:00:00", "2026-05-10 10:15:00"),
    "duration is shown in business-readable words"
);
$assertSame(
    "2 hours 5 minutes",
    ptw_duration_from_seconds(7500),
    "duration can be rendered from accumulated review seconds"
);

$items = ptw_readable_audit_meta_items(json_encode([
    "stage" => "hsse",
    "decision" => "revise",
    "revision_no" => 2,
    "remarks" => "Please update the JSA",
    "status_change_reason" => "Missing safety attachment",
    "terminal_approval_required" => 0,
]));

$flattened = array_map(static fn($item) => $item["label"] . ": " . $item["value"], $items);
$assertContains("Stage: HSSE", $flattened, "stage is readable");
$assertContains("Decision: Revision requested", $flattened, "decision is readable");
$assertContains("Revision: 2", $flattened, "revision number is readable");
$assertContains("Comments: Please update the JSA", $flattened, "remarks are labeled as comments");
$assertContains("Reason: Missing safety attachment", $flattened, "status reason is readable");
$assertContains("Terminal approval: Not required", $flattened, "boolean terminal flag is readable");

echo "PTW readable history helpers passed." . PHP_EOL;
