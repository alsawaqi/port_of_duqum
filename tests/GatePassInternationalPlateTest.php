<?php

$root = dirname(__DIR__);

$read = function (string $path) use ($root): string {
    $full = $root . "/" . $path;
    return is_file($full) ? file_get_contents($full) : "";
};

$assertContains = function (string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
};

$model = $read("app/Models/Gate_pass_request_vehicles_model.php");
$portalController = $read("app/Controllers/Gate_pass_portal.php");
$securityController = $read("app/Controllers/Gate_pass_security_inbox.php");
$portalView = $read("app/Views/gate_pass_portal/requests/vehicle_modal_form.php");
$securityView = $read("app/Views/gate_pass_security_inbox/vehicle_modal_form.php");
$helper = $read("app/Helpers/general_helper.php");
$english = $read("app/Language/english/custom_lang.php");
$arabic = $read("app/Language/arabic/custom_lang.php");
$migration = $read("app/Database/Migrations/2026_05_22_130000_add_international_plate_to_gate_pass_vehicles.php");
$sql = $read("app/Database/SQL/gate_pass_international_plate_upgrade_pod.sql");

$assertContains("ensure_international_plate_schema", $model, "vehicle model should ensure international plate columns exist");
$assertContains("is_international_plate", $model, "vehicle model should include international plate flag schema");
$assertContains("plate_country", $model, "vehicle model should include plate country schema");
$assertContains("international_plate_no", $model, "vehicle model should include free-text international plate schema");

foreach ([$portalController, $securityController] as $controller) {
    $assertContains("is_international_plate", $controller, "vehicle save should read the international plate checkbox");
    $assertContains("plate_country", $controller, "vehicle save should persist plate country");
    $assertContains("international_plate_no", $controller, "vehicle save should persist free-text international plate");
    $assertContains("gate_pass_prepare_vehicle_plate_payload", $controller, "vehicle save should use shared conditional plate validation");
    $assertContains("plate_prefix", $controller, "Omani plate save should keep existing prefix support");
    $assertContains("plate_digits", $controller, "Omani plate save should keep existing digits support");
}

foreach ([$portalView, $securityView] as $view) {
    $assertContains("name=\"is_international_plate\"", $view, "vehicle form should include international plate checkbox");
    $assertContains("name=\"plate_country\"", $view, "vehicle form should include country dropdown");
    $assertContains("name=\"international_plate_no\"", $view, "vehicle form should include free-text international plate input");
    $assertContains("gp-toggle-international-plate", $view, "vehicle form should toggle Omani and international plate inputs");
    $assertContains("gate_pass_country_options_for_ui", $view, "vehicle form should use a country dropdown source");
}

$assertContains("gate_pass_prepare_vehicle_plate_payload", $helper, "helper should prepare vehicle plate payload");
$assertContains("gate_pass_country_options_for_ui", $helper, "helper should provide countries for international plates");
$assertContains("gate_pass_international_plate_no_is_valid", $helper, "helper should validate international plate text");

$assertContains("gate_pass_international_plate_number", $english, "English language should include international plate label");
$assertContains("gate_pass_international_plate_number", $arabic, "Arabic language should include international plate label");
$assertContains("is_international_plate", $migration, "migration should add international plate columns");
$assertContains("plate_country", $sql, "SQL upgrade should add plate country column");

echo "OK" . PHP_EOL;
