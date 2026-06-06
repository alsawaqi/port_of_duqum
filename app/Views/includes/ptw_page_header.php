<?php
$title = $title ?? "PTW";
$subtitle = $subtitle ?? "Manage Permit to Work applications, reviews, safety requirements, and approval activity.";
$icon = $icon ?? "shield";
$actions = $actions ?? "";
$breadcrumbs = $breadcrumbs ?? [
    ["label" => "PTW"],
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
