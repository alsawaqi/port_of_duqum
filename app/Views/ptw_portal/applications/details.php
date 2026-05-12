<?php
$app = $app ?? null;
$back_html = anchor(get_uri("ptw_portal"), "<i data-feather='arrow-left' class='icon-14'></i> Back", ["class" => "btn btn-outline-light"]);
$actions_html = "";
if (!empty($can_edit)) {
    $actions_html .= anchor(get_uri("ptw_portal/application_form/" . (int)$app->id), "<i data-feather='edit' class='icon-14'></i> Edit", ["class" => "btn btn-light"]);
}
if (!empty($can_download_final_permit)) {
    $actions_html .= anchor(get_uri("ptw_portal/download_final_permit/" . (int)$app->id), "<i data-feather='file-text' class='icon-14'></i> Issued Permit", ["class" => "btn btn-success", "target" => "_blank", "rel" => "noopener"]);
}

echo view("ptw_common/details_panel", [
    "app" => $app,
    "page_title" => "PTW Details",
    "current_stage" => strtolower((string)($app->stage ?? "")),
    "actions_html" => $actions_html,
    "back_html" => $back_html,
    "definitions_grouped" => $definitions_grouped ?? [],
    "responses_by_definition" => $responses_by_definition ?? ($responses_index ?? []),
    "attachments_by_response" => $attachments_by_response ?? [],
    "review_groups" => $review_groups ?? ["hsse" => [], "hmo" => [], "terminal" => []],
    "audit_logs" => $audit_logs ?? [],
]);
