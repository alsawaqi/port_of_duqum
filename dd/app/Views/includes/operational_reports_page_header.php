<?php
$reports_label = app_lang("pod_reports");
if (!$reports_label || $reports_label === "default_lang.pod_reports" || $reports_label === "pod_reports") {
    $reports_label = "Operational Reports";
}

$title = $title ?? $reports_label;
$subtitle = $subtitle ?? "Review vendor, tender, gate pass, and PTW signals from one executive workspace.";
$icon = $icon ?? "bar-chart-2";
$actions = $actions ?? "";
$breadcrumbs = $breadcrumbs ?? [
    ["label" => $reports_label],
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
