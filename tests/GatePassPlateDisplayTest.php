<?php

require_once __DIR__ . '/../app/Helpers/general_helper.php';

$cases = [
    ['T 90826', ['plate_no' => 'T 90826', 'is_international_plate' => 0, 'international_plate_no' => '']],
    ['T 90826', ['plate_no' => 'T 90826', 'is_international_plate' => 0, 'international_plate_no' => null]],
    ['T 90826', ['plate_no' => 'T 90826', 'is_international_plate' => 0, 'international_plate_no' => 'STALE 1']],
    ['T 90826', ['plate_no' => 'T 90826']],
    ['DXB 123 (UAE)', ['plate_no' => 'OLD 1', 'is_international_plate' => 1, 'international_plate_no' => 'DXB 123', 'plate_country' => 'UAE']],
    ['DXB 123 (UAE)', ['plate_no' => 'DXB 123', 'is_international_plate' => 1, 'international_plate_no' => '', 'plate_country' => 'UAE']],
    ['DXB 123', ['plate_no' => 'DXB 123', 'is_international_plate' => 1, 'international_plate_no' => null]],
    ['-', ['plate_no' => '', 'international_plate_no' => '', 'is_international_plate' => 0]],
    ['-', []],
];

foreach ($cases as $index => [$expected, $row]) {
    $actual = gate_pass_vehicle_plate_display((object) $row);
    if ($actual !== $expected) {
        fwrite(STDERR, 'FAIL case ' . ($index + 1) . ': expected ' . $expected . ', got ' . $actual . PHP_EOL);
        exit(1);
    }
}

echo 'OK: ' . count($cases) . ' plate display cases' . PHP_EOL;
