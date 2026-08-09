<?php

$fail = static function (string $message): void {
    fwrite(STDERR, "Assertion failed: {$message}" . PHP_EOL);
    exit(1);
};
$assertContains = static function (string $needle, string $haystack, string $message) use ($fail): void {
    if (!str_contains($haystack, $needle)) {
        $fail($message);
    }
};

$department = (string) file_get_contents(__DIR__ . "/../app/Controllers/Gate_pass_department_requests.php");
$commercial = (string) file_get_contents(__DIR__ . "/../app/Controllers/Gate_pass_commercial_inbox.php");

$assertContains('_can_view_department_request($request)', $department, 'department child and document routes share a stage-scoped authorization decision');
$assertContains('(string)($request->stage ?? "") !== "department"', $department, 'department access fails closed outside the department stage');
$assertContains('_resolve_department_upload_path(', $department, 'department documents use canonical contained-path resolution');
$assertContains('"Cache-Control", "private, no-store"', $department, 'department identity documents are not cached');

$assertContains('_can_view_commercial_request($request)', $commercial, 'commercial child and document routes share a stage-scoped authorization decision');
$assertContains('(string)($request->stage ?? "") === "commercial"', $commercial, 'commercial access requires the commercial stage');
$assertContains('(string)($request->status ?? "") === "department_approved"', $commercial, 'commercial access requires the expected workflow state');
$assertContains('_resolve_commercial_upload_path(', $commercial, 'commercial documents use canonical contained-path resolution');
$assertContains('"Cache-Control", "private, no-store"', $commercial, 'commercial identity documents are not cached');

echo "Gate pass stage-scoped document authorization contracts passed." . PHP_EOL;
