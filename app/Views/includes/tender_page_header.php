<?php
$title = $title ?? "Tender";
$subtitle = $subtitle ?? "Review tender activity, approvals, evaluations, and procurement workflow status.";
$icon = $icon ?? "briefcase";
$actions = $actions ?? "";
$breadcrumbs = $breadcrumbs ?? [
    ["label" => "Tender"],
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
