<?php

namespace App\Controllers { class Security_Controller {} }
namespace {
    function get_array_value($array, $key) { return $array[$key] ?? null; }
    function get_setting($key) { return ['date_format' => 'Y-m-d'][$key] ?? null; }
    function esc($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    require dirname(__DIR__) . '/app/Helpers/date_time_helper.php';
    require dirname(__DIR__) . '/app/Controllers/Vendor_portal.php';
    $reflection = new ReflectionClass(App\Controllers\Vendor_portal::class);
    $portal = $reflection->newInstanceWithoutConstructor();
    $badge = $reflection->getMethod('_document_expiry_badge');
    $badge->setAccessible(true);
    $checks = 0;
    $check = function ($condition, $label) use (&$checks) {
        $checks++;
        if (!$condition) throw new RuntimeException($label);
    };
    foreach ([null, '', '0000-00-00', '0000-00-00 00:00:00', '2026-02-30', 'invalid'] as $date) {
        $check($badge->invoke($portal, $date) === '-', 'Missing/invalid date must not be marked expired');
    }
    $past = date('Y-m-d', strtotime('-1 day'));
    $near = date('Y-m-d', strtotime('+10 days'));
    $future = date('Y-m-d', strtotime('+60 days'));
    $check(str_contains($badge->invoke($portal, $past), $past . ' (Expired)'), 'Real past expiry is still expired');
    $check(str_contains($badge->invoke($portal, $near), $near . ' (10d)'), 'Upcoming expiry retains warning');
    $check(str_contains($badge->invoke($portal, $future), 'bg-success'), 'Future expiry stays valid');
    echo "Vendor document expiry display: {$checks} checks passed.\n";
}
