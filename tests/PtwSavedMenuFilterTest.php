<?php

function get_array_value($array, $key) { return $array[$key] ?? null; }
require dirname(__DIR__) . '/app/Libraries/Left_menu.php';
$reflection = new ReflectionClass(App\Libraries\Left_menu::class);
$menu = $reflection->newInstanceWithoutConstructor();
$normalize = $reflection->getMethod('_normalize_ptw_filter_item');
$normalize->setAccessible(true);
$resolve = $reflection->getMethod('_get_item_array_value');
$resolve->setAccessible(true);
$checks = 0;
$check = function ($condition, $message) use (&$checks) {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$item = ['name' => 'gate_pass_filter_requests', 'is_sub_menu' => true];
$ptw = ['name' => 'ptw_request_list', 'url' => 'ptw_request_list', 'class' => 'filter'];
$gate = ['name' => 'gate_pass_filter_requests', 'url' => 'gate_pass_request_list', 'class' => 'filter'];
foreach (['ptw_portal', 'ptw_master'] as $parent) {
    $fixed = $normalize->invoke($menu, $item, $parent);
    $check($fixed['name'] === 'ptw_request_list', 'Saved PTW filter points to PTW');
    $check($resolve->invoke($menu, $fixed, ['ptw_request_list' => $ptw]) === $ptw, 'Authorized PTW menu resolves');
    $check($resolve->invoke($menu, $fixed, ['gate_pass_filter_requests' => $gate]) === [], 'Gate-only permission does not grant PTW');
    $check($resolve->invoke($menu, $fixed, []) === [], 'No permission means no filter');
}
foreach (['gitpass_master', 'gate_pass_rop_requests', 'tender', ''] as $parent) {
    $check($normalize->invoke($menu, $item, $parent) === $item, 'Other menu groups remain unchanged');
}
$custom = $item + ['url' => 'https://example.invalid/custom'];
$check($normalize->invoke($menu, $custom, 'ptw_portal') === $custom, 'Explicit custom link is preserved');
$correct = ['name' => 'ptw_request_list', 'is_sub_menu' => true];
$check($normalize->invoke($menu, $correct, 'ptw_portal') === $correct, 'Already correct menu is unchanged');
echo "PTW saved menu filter: {$checks} checks passed.\n";
