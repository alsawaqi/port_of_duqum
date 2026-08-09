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

function form_password($attributes): string
{
    $attributes["type"] = "password";
    return form_input($attributes);
}

function form_upload($attributes): string
{
    $attributes["type"] = "file";
    return form_input($attributes);
}

function view($name, $data = [], $options = []): string
{
    return $name === "signin/re_captcha"
        ? '<div data-test="recaptcha-partial"></div>'
        : "";
}

$vendor_groups_dropdown = ["" => "-"];
$countries_dropdown = ["" => "-"];
$regions_dropdown = ["" => "-"];
$cities_dropdown = ["" => "-"];
$vendor_document_types_dropdown = ["" => "-", 1 => "Commercial Registration"];

ob_start();
include __DIR__ . "/../app/Views/guest_vendor/index.php";
$html = ob_get_clean();

$assertContains = static function (string $needle, string $message) use ($html): void {
    if (strpos($html, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$assertContains('name="password_confirm"', "guest vendor form includes confirm password");
$assertContains('name="password"', "guest vendor form requires an account password");
$assertContains('autocomplete="current-password"', "guest vendor form supports proving an existing account password");
$assertContains('vendor_password_reuse_hint', "guest vendor form explains password reuse");
$assertContains('name="vendor_document_type_id[]"', "guest vendor documents are repeatable by type");
$assertContains('name="file[]"', "guest vendor documents are repeatable by file upload");
$assertContains('name="issued_at[]"', "guest vendor documents are repeatable by issue date");
$assertContains('name="expires_at[]"', "guest vendor documents are repeatable by expiry date");
$assertContains('data-gv-document-add', "guest vendor document section exposes an add document control");
$assertContains('data-test="recaptcha-partial"', "guest vendor registration includes CAPTCHA protection");

echo "Guest vendor view contract passed." . PHP_EOL;
