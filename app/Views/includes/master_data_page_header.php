<?php
$master_label = app_lang("master_data");
if (!$master_label || $master_label === "default_lang.master_data") {
    $master_label = "Master Data";
}

$title = $title ?? $master_label;
$subtitle = $subtitle ?? "Maintain the operational reference data used across permits, vendors, locations, and approvals.";
$icon = $icon ?? "database";
$actions = $actions ?? "";
$breadcrumbs = $breadcrumbs ?? [
    ["label" => $master_label],
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
