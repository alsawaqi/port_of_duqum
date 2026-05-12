<?php

function esc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function get_array_value($array, $key)
{
    return is_array($array) && array_key_exists($key, $array) ? $array[$key] : null;
}

function get_uri($uri): string
{
    return "/" . ltrim((string) $uri, "/");
}

function anchor($uri, $label, $attributes = []): string
{
    return "<a href=\"" . esc($uri) . "\">" . $label . "</a>";
}

function form_open_multipart($uri, $attributes = []): string
{
    $attrs = "";
    foreach ($attributes as $key => $value) {
        $attrs .= " " . esc($key) . "=\"" . esc($value) . "\"";
    }

    return "<form action=\"" . esc($uri) . "\" method=\"post\" enctype=\"multipart/form-data\"" . $attrs . ">";
}

function form_close(): string
{
    return "</form>";
}

function ptw_is_other_requirement_definition($definition): bool
{
    return strtolower((string)($definition->label ?? "")) === "other";
}

function ptw_decode_other_requirement_items($response): array
{
    if (!$response || empty($response->value_text)) {
        return [];
    }

    $decoded = json_decode((string)$response->value_text, true);
    return is_array($decoded) && isset($decoded["items"]) ? $decoded["items"] : [];
}

function ptw_virtual_other_requirement_definition(string $category): object
{
    return (object) [
        "id" => ["hazard_document" => -9001, "ppe" => -9002, "preparation" => -9003][$category],
        "category" => $category,
        "code" => "ptw_default_other_" . $category,
        "label" => "Other",
        "requires_attachment" => $category === "hazard_document" ? 1 : 0,
        "is_mandatory" => 0,
        "has_text_input" => 1,
        "text_label" => "Specify",
        "allowed_extensions" => $category === "hazard_document" ? "pdf,docx,jpg,jpeg,png,webp" : null,
        "help_text" => "Default Other option",
    ];
}

$makeDef = static function (int $id, string $category, string $code, string $label, int $requiresAttachment = 0): object {
    return (object) [
        "id" => $id,
        "category" => $category,
        "code" => $code,
        "label" => $label,
        "requires_attachment" => $requiresAttachment,
        "is_mandatory" => 0,
        "has_text_input" => 0,
        "text_label" => "Specify",
        "allowed_extensions" => $requiresAttachment ? "pdf,jpg,png" : null,
        "help_text" => null,
    ];
};

$app = null;
$model_info = null;
$responses_index = [];
$duration_days = null;
$ptw_errors = [];
$ptw_field_errors = [];
$ptw_old_input = [];
$submit_stage_label = "HSSE";
$companies_list = [];
$login_user = (object) [
    "first_name" => "Test",
    "last_name" => "User",
    "phone" => "",
    "email" => "test@example.com",
];
$definitions_grouped = [
    "hazard_document" => [$makeDef(101, "hazard_document", "haz_risk_assessment", "Risk Assessment", 1)],
    "ppe" => [$makeDef(102, "ppe", "ppe_helmet", "Helmet")],
    "preparation" => [$makeDef(103, "preparation", "prep_loto", "Lock-out / Tag-out")],
];

ob_start();
include __DIR__ . "/../app/Views/ptw_portal/applications/form.php";
$html = ob_get_clean();

$assertContains = static function (string $needle, string $message) use ($html): void {
    if (strpos($html, $needle) === false) {
        fwrite(STDERR, "Assertion failed: " . $message . PHP_EOL);
        fwrite(STDERR, "Missing: " . $needle . PHP_EOL);
        exit(1);
    }
};

$assertContains('name="other_hazard_document_label[]"', "hazard Other supports repeatable labels");
$assertContains('name="other_hazard_document_file[]"', "hazard Other supports repeatable attachments");
$assertContains('name="other_ppe_label[]"', "PPE Other supports repeatable labels");
$assertContains('name="other_preparation_label[]"', "preparation Other supports repeatable labels");
$assertContains('data-ptw-other-add="hazard_document"', "hazard Other exposes an add-row control");
$assertContains('data-ptw-other-add="ppe"', "PPE Other exposes an add-row control");
$assertContains('data-ptw-other-add="preparation"', "preparation Other exposes an add-row control");

echo "PTW Other view contract passed." . PHP_EOL;
