<?php

function get_setting($key)
{
    global $left_menu_test_settings;
    return $left_menu_test_settings[$key] ?? "";
}

function get_array_value($data, $key)
{
    return is_array($data) && array_key_exists($key, $data) ? $data[$key] : null;
}

require_once __DIR__ . "/../app/Helpers/safe_serialization_helper.php";
require_once __DIR__ . "/../app/Libraries/Left_menu.php";

$assertSame = static function ($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Expected: " . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, "Actual:   " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
};

$newLeftMenuFor = static function (object $login_user) {
    $reflection = new ReflectionClass(\App\Libraries\Left_menu::class);
    $left_menu = $reflection->newInstanceWithoutConstructor();

    $ci_property = $reflection->getProperty("ci");
    $ci_property->setAccessible(true);
    $ci_property->setValue($left_menu, (object)["login_user" => $login_user]);

    return $left_menu;
};

$readMenuSetting = static function ($left_menu, bool $is_preview = false, string $type = "default"): array {
    $method = new ReflectionMethod($left_menu, "_get_left_menu_from_setting_for_rander");
    $method->setAccessible(true);
    return $method->invoke($left_menu, $is_preview, $type);
};

$default_menu = [["name" => "dashboard"], ["name" => "pod_reports"]];
$user_menu = [["name" => "dashboard"], ["name" => "vendors_master"]];

$left_menu_test_settings = [
    "default_left_menu" => serialize($default_menu),
    "default_client_left_menu" => "",
    "user_1_left_menu" => serialize($user_menu),
    "user_2_left_menu" => serialize($user_menu),
    "user_3_left_menu" => serialize($user_menu),
];

$admin_menu = $newLeftMenuFor((object)[
    "id" => 1,
    "user_type" => "staff",
    "is_admin" => true,
]);

$assertSame(
    $default_menu,
    $readMenuSetting($admin_menu, false, "default"),
    "staff admin runtime sidebar should use the saved default left menu"
);

$settings_admin_menu = $newLeftMenuFor((object)[
    "id" => 2,
    "user_type" => "staff",
    "is_admin" => false,
    "permissions" => ["can_manage_all_kinds_of_settings" => "1"],
]);

$assertSame(
    $default_menu,
    $readMenuSetting($settings_admin_menu, false, "default"),
    "settings admins editing the default menu should also see the saved default left menu"
);

$staff_menu = $newLeftMenuFor((object)[
    "id" => 3,
    "user_type" => "staff",
    "is_admin" => false,
    "permissions" => [],
]);

$assertSame(
    $user_menu,
    $readMenuSetting($staff_menu, false, "default"),
    "non-admin staff runtime sidebar should still honor user-specific left menu"
);

$assertSame(
    $user_menu,
    $readMenuSetting($staff_menu, true, "user"),
    "user preview should still prefer user-specific left menu"
);

echo "Left menu setting resolution passed." . PHP_EOL;
