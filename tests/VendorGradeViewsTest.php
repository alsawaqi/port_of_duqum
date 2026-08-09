<?php

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function app_lang($key): string
{
    return (string)$key;
}

function get_uri($uri): string
{
    return "/" . ltrim((string)$uri, "/");
}

function echo_uri($uri): void
{
    echo get_uri($uri);
}

function view($name, $data = []): string
{
    return '';
}

function csrf_token(): string
{
    return "csrf_test";
}

function csrf_hash(): string
{
    return "csrf_hash";
}

function form_open($uri, $attributes = []): string
{
    $attrs = "";
    foreach ($attributes as $key => $value) {
        $attrs .= " " . esc($key) . "=\"" . esc($value) . "\"";
    }

    return "<form action=\"" . esc($uri) . "\" method=\"post\"" . $attrs . ">";
}

function form_close(): string
{
    return "</form>";
}

function form_dropdown($name, $options, $selected = "", $extra = ""): string
{
    return "<select name=\"" . esc($name) . "\" " . $extra . "></select>";
}

function form_input($attributes): string
{
    $attrs = "";
    foreach ($attributes as $key => $value) {
        if (is_bool($value)) {
            if ($value) {
                $attrs .= " " . esc($key) . "=\"" . esc($key) . "\"";
            }
        } else {
            $attrs .= " " . esc($key) . "=\"" . esc($value) . "\"";
        }
    }

    return "<input" . $attrs . ">";
}

function form_textarea($attributes): string
{
    $value = (string)($attributes["value"] ?? "");
    unset($attributes["value"]);

    $attrs = "";
    foreach ($attributes as $key => $valueAttr) {
        $attrs .= " " . esc($key) . "=\"" . esc($valueAttr) . "\"";
    }

    return "<textarea" . $attrs . ">" . esc($value) . "</textarea>";
}

function form_password($attributes): string
{
    $attributes["type"] = "password";
    return form_input($attributes);
}

function modal_anchor($url, $title, $attributes = []): string
{
    $attrs = "";
    foreach ($attributes as $key => $value) {
        $attrs .= " " . esc($key) . "=\"" . esc($value) . "\"";
    }

    return "<a href=\"" . esc($url) . "\"" . $attrs . ">" . $title . "</a>";
}

$assertContains = static function (string $needle, string $message, string $html): void {
    if (strpos($html, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$model_info = (object)[
    "id" => "",
    "name" => "",
    "code" => "",
    "description" => "",
    "sort" => "",
    "is_active" => 1,
];

ob_start();
include __DIR__ . "/../app/Views/vendor_grades/modal_form.php";
$grade_modal = ob_get_clean();

$assertContains('action="/vendor_grades/save"', "vendor grade modal posts to grade save endpoint", $grade_modal);
$assertContains('name="name"', "vendor grade modal captures grade name", $grade_modal);
$assertContains('name="code"', "vendor grade modal captures grade code", $grade_modal);
$assertContains('name="description"', "vendor grade modal captures description", $grade_modal);
$assertContains('name="sort"', "vendor grade modal captures display order", $grade_modal);
$assertContains('name="is_active"', "vendor grade modal captures active status", $grade_modal);

$model_info = (object)[
    "id" => 8,
    "vendor_group_id" => 2,
    "vendor_grade_id" => 3,
    "vendor_name" => "Demo Vendor",
    "email" => "vendor@example.com",
    "country_id" => "",
    "region_id" => "",
    "city_id" => "",
    "address" => "",
    "po_box" => "",
    "postal_code" => "",
    "currency" => "OMR",
    "payment_terms" => "45",
];
$vendor_groups_dropdown = ["" => "-", 2 => "Local (L)"];
$vendor_grades_dropdown = ["" => "-", 3 => "A - Excellent"];
$countries_dropdown = ["" => "-"];
$regions_dropdown = ["" => "-"];
$cities_dropdown = ["" => "-"];
$currency_dropdown = ["" => "-", "OMR" => "OMR"];
$payment_terms_dropdown = ["" => "-", "45" => "45"];

ob_start();
include __DIR__ . "/../app/Views/vendors/modal_form.php";
$vendor_modal = ob_get_clean();

$assertContains('name="vendor_grade_id"', "vendor modal allows assigning a grade", $vendor_modal);

$can_create_vendors = true;
$can_view_vendors = true;
$can_update_vendors = true;
$can_delete_vendors = false;

ob_start();
include __DIR__ . "/../app/Views/vendors/index.php";
$vendors_index = ob_get_clean();

$assertContains('vendors/update_grade', "vendor list can update grade inline", $vendors_index);
$assertContains('vendors/block_modal_form', "vendor list exposes block action modal", $vendors_index);
$assertContains('vendors/unblock', "vendor list exposes unblock action", $vendors_index);

echo "Vendor grade view contracts passed." . PHP_EOL;
