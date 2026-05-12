<?php
$app = $application;
$actions_html = modal_anchor(
    get_uri("ptw_hsse_inbox/approval_modal_form"),
    "<i data-feather='check-square' class='icon-16'></i> " . app_lang("review"),
    ["class" => "btn btn-light", "title" => app_lang("review"), "data-post-id" => (int)$app->id]
);
$back_html = anchor(get_uri("ptw_hsse_inbox"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang("back"), ["class" => "btn btn-outline-light"]);

echo view("ptw_common/details_panel", [
    "app" => $app,
    "page_title" => "HSSE Review - PTW Details",
    "current_stage" => "hsse",
    "actions_html" => $actions_html,
    "back_html" => $back_html,
    "definitions_grouped" => $definitions_grouped ?? [],
    "responses_by_definition" => $responses_by_definition ?? [],
    "attachments_by_response" => $attachments_by_response ?? [],
    "review_groups" => $review_groups ?? ["hsse" => $reviews ?? [], "hmo" => [], "terminal" => []],
    "audit_logs" => $audit_logs ?? [],
]);
