<?php

$root = dirname(__DIR__);

$fail = static function (string $message): void {
    fwrite(STDERR, 'Assertion failed: ' . $message . PHP_EOL);
    exit(1);
};
$assertTrue = static function ($condition, string $message) use ($fail): void {
    if (!$condition) {
        $fail($message);
    }
};

require_once $root . '/app/Helpers/csv_security_helper.php';

$dangerous = [
    '=HYPERLINK("https://example.invalid")',
    '+cmd|\' /C calc\'!A0',
    '-1+2',
    '@SUM(1,2)',
    "  \t=WEBSERVICE(\"https://example.invalid\")",
    "\tcmd",
    "\r=1+1",
    "\u{00A0}=1+1",
    "\u{FEFF}@SUM(1,2)",
];
foreach ($dangerous as $value) {
    $assertTrue(str_starts_with(csv_safe_cell($value), "'"), 'dangerous spreadsheet input is neutralized: ' . json_encode($value));
}

foreach (['Acme LLC', '12345', 'https://example.invalid', "'=already-safe"] as $value) {
    $assertTrue(csv_safe_cell($value) === $value, 'ordinary CSV text is preserved: ' . $value);
}
$row = csv_safe_row(['safe', '=1+1', null, true]);
$assertTrue($row === ['safe', "'=1+1", '', '1'], 'rows apply cell hardening consistently');

$exports = [
    'app/Controllers/Gate_pass_department_requests.php' => 'export_list_csv',
    'app/Controllers/Gate_pass_commercial_inbox.php' => 'export_list_csv',
    'app/Controllers/Gate_pass_security_inbox.php' => 'export_list_csv',
    'app/Controllers/Gate_pass_rop_inbox.php' => 'export_list_csv',
    'app/Controllers/Gate_pass_portal.php' => 'export_my_requests_csv',
];
foreach ($exports as $path => $method) {
    $source = file_get_contents($root . '/' . $path);
    $start = strpos($source, 'function ' . $method . '()');
    $end = strpos($source, 'return $this->response->setBody($body);', $start);
    if ($start === false || $end === false) {
        $fail('CSV export method missing: ' . $path . '::' . $method);
    }
    $segment = substr($source, $start, $end - $start);
    $assertTrue(str_contains($segment, "helper('csv_security');"), $path . ' loads the shared CSV helper');
    $assertTrue(substr_count($segment, 'csv_safe_row(') >= 2, $path . ' protects headers and data rows');
    $assertTrue(!str_contains($segment, 'fputcsv($fh, ['), $path . ' has no unprotected CSV row');
}

echo 'Gate-pass CSV security contracts passed.' . PHP_EOL;
