<?php
$gate_pass_label = app_lang("gate_pass");
if (!$gate_pass_label || $gate_pass_label === "default_lang.gate_pass" || $gate_pass_label === "gate_pass") {
    $gate_pass_label = "Gate Pass";
}

$title = $title ?? $gate_pass_label;
$subtitle = $subtitle ?? "Track visitor access, approval stages, fees, scans, and issued gate passes from one workspace.";
$icon = $icon ?? "shield";
$actions = $actions ?? "";
$breadcrumbs = $breadcrumbs ?? [
    ["label" => $gate_pass_label],
    ["label" => $title]
];

echo view("includes/pod_page_header", [
    "title" => $title,
    "subtitle" => $subtitle,
    "icon" => $icon,
    "breadcrumbs" => $breadcrumbs,
    "actions" => $actions
]);
?>
