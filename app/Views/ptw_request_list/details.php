<?php $app = $application; ?>

<div id="page-content" class="page-wrapper clearfix">
    <div class="card mb15">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("ptw_application_details"); ?> - <?php echo esc((string)($app->reference ?? "-")); ?></h1>

            <div class="title-button-group">
                <?php echo anchor(get_uri("ptw_request_list"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang("back"), ["class" => "btn btn-default"]); ?>
            </div>
        </div>

        <div class="p15">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr><th width="35%"><?php echo app_lang("reference"); ?></th><td><?php echo esc((string)($app->reference ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("company_name"); ?></th><td><?php echo esc((string)($app->company_name ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("applicant_name"); ?></th><td><?php echo esc((string)($app->applicant_name ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("applicant_position"); ?></th><td><?php echo esc((string)($app->applicant_position ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("contact_number"); ?></th><td><?php echo esc((string)($app->contact_phone ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("email"); ?></th><td><?php echo esc((string)($app->contact_email ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("status"); ?></th><td><?php echo esc((string)($app->status ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("stage"); ?></th><td><?php echo esc((string)($app->stage ?? "-")); ?></td></tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr><th width="35%"><?php echo app_lang("work_location"); ?></th><td><?php echo esc((string)($app->exact_location ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("work_supervisor_name"); ?></th><td><?php echo esc((string)($app->work_supervisor_name ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("supervisor_contact_details"); ?></th><td><?php echo esc((string)($app->supervisor_contact_details ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("total_workers"); ?></th><td><?php echo esc((string)($app->total_workers ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("starting_date_time"); ?></th><td><?php echo !empty($app->work_from) ? format_to_datetime($app->work_from) : "-"; ?></td></tr>
                        <tr><th><?php echo app_lang("completion_date_time"); ?></th><td><?php echo !empty($app->work_to) ? format_to_datetime($app->work_to) : "-"; ?></td></tr>
                        <tr><th><?php echo app_lang("map_location"); ?></th><td><?php echo esc((string)($app->location_sector_name ?? "-")); ?></td></tr>
                        <tr><th><?php echo app_lang("location_description"); ?></th><td><?php echo esc((string)($app->location_description ?? "-")); ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="mt15">
                <h4><?php echo app_lang("work_description"); ?></h4>
                <div class="p10 b-a" style="min-height:80px;">
                    <?php echo nl2br(esc((string)($app->work_description ?? "-"))); ?>
                </div>
            </div>

            <?php
            $definitions_grouped = $definitions_grouped ?? [];
            $responses_by_definition = $responses_by_definition ?? [];
            $attachments_by_response = $attachments_by_response ?? [];
            ?>

            <div class="mt15">
                <h4>PTW Requirements &amp; Attachments</h4>

                <?php if (!empty($app->signature_file_path ?? "")): ?>
                    <div class="mb10">
                        <strong>Applicant Signature:</strong>
                        <?php echo anchor(
                            get_uri("ptw_portal/download_signature/" . (int)$app->id),
                            "<i data-feather='download' class='icon-14'></i> " . esc((string)($app->signature_file_name ?: "Download Signature")),
                            ["class" => "link"]
                        ); ?>
                    </div>
                <?php endif; ?>

                <?php foreach ([
                    "hazard_document" => "Hazards & Attachments",
                    "ppe" => "Proposed PPE",
                    "preparation" => "Work Area Preparations",
                ] as $cat => $label): ?>
                    <div class="card mb15">
                        <div class="card-header"><strong><?php echo esc($label); ?></strong></div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th width="90" class="text-center">Checked</th>
                                        <th>Text</th>
                                        <th width="260">Attachment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rows = $definitions_grouped[$cat] ?? []; ?>
                                    <?php if (!empty($rows)): ?>
                                        <?php foreach ($rows as $def): ?>
                                            <?php
                                            $resp = $responses_by_definition[(int)$def->id] ?? null;
                                            $att = null;
                                            if ($resp && !empty($resp->id)) {
                                                $att = $attachments_by_response[(int)$resp->id] ?? null;
                                            }
                                            ?>
                                            <tr>
                                                <td><?php echo esc((string)($def->label ?? "-")); ?></td>
                                                <td class="text-center"><?php echo ($resp && (int)($resp->is_checked ?? 0) === 1) ? "Yes" : "No"; ?></td>
                                                <td><?php echo nl2br(esc((string)($resp->value_text ?? ""))); ?></td>
                                                <td>
                                                    <?php if ($att && !empty($att->id)): ?>
                                                        <?php echo anchor(
                                                            get_uri("ptw_portal/download_attachment/" . (int)$att->id),
                                                            "<i data-feather='paperclip' class='icon-14'></i> " . esc((string)($att->file_name ?: "Download")),
                                                            ["class" => "link"]
                                                        ); ?>
                                                    <?php elseif (!empty($resp->attachment_path ?? "")): ?>
                                                        <?php echo anchor(
                                                            get_uri("ptw_portal/download_attachment/" . (int)$resp->id),
                                                            "<i data-feather='paperclip' class='icon-14'></i> Download",
                                                            ["class" => "link"]
                                                        ); ?>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center text-off"><?php echo app_lang("no_records_found"); ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt15">
                <h4><?php echo app_lang("hsse_review_history"); ?></h4>
                <?php echo $this->include("ptw_request_list/_reviews_table", ["reviews" => $hsse_reviews]); ?>
            </div>

            <div class="mt15">
                <h4><?php echo app_lang("hmo_review_history"); ?></h4>
                <?php echo $this->include("ptw_request_list/_reviews_table", ["reviews" => $hmo_reviews]); ?>
            </div>

            <div class="mt15">
                <h4><?php echo app_lang("terminal_review_history"); ?></h4>
                <?php echo $this->include("ptw_request_list/_reviews_table", ["reviews" => $terminal_reviews]); ?>
            </div>

            <div class="mt15">
                <h4>PTW Audit Log</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Meta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($audit_logs)): foreach ($audit_logs as $log): ?>
                                <tr>
                                    <td><?php echo !empty($log->created_at) ? format_to_datetime($log->created_at) : "-"; ?></td>
                                    <td><?php echo esc((string)($log->user_name ?? "-")); ?></td>
                                    <td><?php echo esc((string)($log->action ?? "-")); ?></td>
                                    <td style="max-width:420px; white-space:pre-wrap;"><?php echo esc((string)($log->meta ?? "")); ?></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="4" class="text-center text-off"><?php echo app_lang("no_records_found"); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    if (window.feather) feather.replace();
});
</script>