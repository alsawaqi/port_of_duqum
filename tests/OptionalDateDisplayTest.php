<?php

function get_array_value($array, $key) { return $array[$key] ?? null; }
function get_setting($key) {
    return ['date_format' => 'Y-m-d', 'time_format' => '24_hours', 'timezone' => 'Asia/Muscat'][$key] ?? null;
}
require dirname(__DIR__) . '/app/Helpers/date_time_helper.php';
$checks = 0;
$check = function ($actual, $expected, $label) use (&$checks) {
    $checks++;
    if ($actual !== $expected) throw new RuntimeException($label . ': ' . var_export($actual, true));
};
foreach ([null, '', '0000-00-00', '0000-00-00 00:00:00', '2026-00-08', '2026-09-00',
    '2026-02-29', '2026-04-31', '2026-13-01', '2026-09-32', '2026-1e-01', 'garbage'] as $date) {
    $check(filter_valid_datetime_string($date), '', 'Invalid date rejected');
    $check(format_to_date($date, false), '', 'No invented date');
    $check(format_to_datetime($date), '', 'No invented date/time');
}
foreach (['2024-02-29', '2000-02-29', '2026-09-08', '2026-12-31', '1900-01-01'] as $date) {
    $check(format_to_date($date, false), $date, 'Real dates retain their value');
}
$check(format_to_datetime('2026-09-08 12:34:56', false), '2026-09-08 12:34', 'UTC display preserved');
$check(format_to_datetime('2026-09-08 22:34:56'), '2026-09-09 02:34', 'Local timezone conversion preserved');
$check(format_to_datetime('2026-09-08 00:00:00', false), '2026-09-08 00:00', 'Midnight preserved');
echo "Optional date display: {$checks} checks passed.\n";
