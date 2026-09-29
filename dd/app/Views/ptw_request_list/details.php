<?php
$app = $application;
$back_html = anchor(get_uri("ptw_request_list"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang("back"), ["class" => "btn btn-outline-light"]);
$actions_html = "";
if (strtolower((string)($app->status ?? "")) === "approved" && strtolower((string)($app->stage ?? "")) === "completed") {
    $actions_html .= anchor(
        get_uri("ptw_portal/download_final_permit/" . (int)$app->id),
        "<i data-feather='file-text' class='icon-16'></i> Issued Permit",
        ["class" => "btn btn-light", "target" => "_blank", "rel" => "noopener"]
    );
}

echo view("ptw_common/details_panel", [
    "app" => $app,
    "page_title" => "PTW Master Details",
    "current_stage" => strtolower((string)($app->stage ?? "")),
    "actions_html" => $actions_html,
    "back_html" => $back_html,
    "definitions_grouped" => $definitions_grouped ?? [],
    "responses_by_definition" => $responses_by_definition ?? [],
    "attachments_by_response" => $attachments_by_response ?? [],
    "review_groups" => $review_groups ?? [
        "hsse" => $hsse_reviews ?? [],
        "hmo" => $hmo_reviews ?? [],
        "terminal" => $terminal_reviews ?? [],
    ],
    "audit_logs" => $audit_logs ?? [],
]);
