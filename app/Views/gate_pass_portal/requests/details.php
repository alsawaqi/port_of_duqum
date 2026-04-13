<?php
$is_requester = !empty($is_requester);
$requester_can_edit = !empty($requester_can_edit);
$can_approve = !empty($can_approve);
$portal_is_admin = !empty($portal_is_admin);
$show_activity_log = $is_requester || $portal_is_admin;
$show_gate_scan_log = !empty($show_gate_scan_log);
$scan_log_rows = $scan_log_rows ?? [];
$show_approval_box = ($request->stage ?? "") === "department"
    && ($request->status === "submitted");

$status = $request->status ?? "";
$stage  = $request->stage ?? "";

$can_edit_request_core = !empty($can_edit_request_core);
$req_type_raw = strtolower(trim((string)($request->request_type ?? "both")));
$portal_request_includes_vehicles = ($req_type_raw !== "person");

$show_add_visitor_vehicle = $requester_can_edit && (
    $status === "returned"
    || ($stage === "department" && $status === "draft")
);
$show_submit_to_department = $requester_can_edit && (
    $status === "returned"
    || ($stage === "department" && $status === "draft")
);

if (!isset($status_label)) {
    $status_label = gate_pass_request_status_display($request);
}

$status_class = "badge-soft-secondary";
if ($status === "submitted") $status_class = "badge-soft-warning";
if ($status === "returned")  $status_class = "badge-soft-danger";
if ($status === "approved" || $status === "department_approved" || $status === "commercial_approved" || $status === "security_approved" || $status === "rop_approved") $status_class = "badge-soft-success";
if ($status === "rejected")  $status_class = "badge-soft-danger";
if ($status === "issued")    $status_class = "badge-soft-success";

$show_pay_button = $is_requester && gate_pass_portal_can_pay_fee($request);
$show_fee_waiver_pending_notice = $is_requester && $status === "department_approved" && gate_pass_fee_waiver_pending($request);

$stage_class = "badge-soft-secondary";
if ($stage === "department") $stage_class = "badge-soft-primary";
if ($stage === "commercial") $stage_class = "badge-soft-info";
if ($stage === "security")   $stage_class = "badge-soft-warning";
if ($stage === "rop")        $stage_class = "badge-soft-danger";
if ($stage === "issued")     $stage_class = "badge-soft-success";

$show_qr_section = ($status === "rop_approved" && !empty($gate_pass) && !empty($gate_pass->qr_token));
$qr_image_url = $show_qr_section ? ("https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($gate_pass->qr_token)) : "";

$approval_history = $approval_history ?? [];
$latest_return = null;
if ($is_requester && $status === "returned" && !empty($approval_history)) {
    foreach (array_reverse($approval_history) as $ah) {
        if (strtolower(trim((string)($ah->decision ?? ""))) === "returned") {
            $latest_return = $ah;
            break;
        }
    }
}
$return_stage_slug = strtolower(preg_replace('/[^a-z]/', '', (string)($latest_return->stage ?? $stage ?? "")));
$return_stage_lang_key = "gate_pass_stage_" . ($return_stage_slug !== "" ? $return_stage_slug : "department");
$return_stage_label = app_lang($return_stage_lang_key);
if ($return_stage_label === $return_stage_lang_key) {
    $return_stage_label = esc((string)($latest_return->stage ?? $stage ?? ""));
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-portal-pro gp-detail-page">

    <div class="card gp-card gp-detail-hero mb15">
        <div class="gp-detail-hero-top">
            <div class="gp-detail-title-block">
                <h1 class="gp-detail-h1">
                    <?php echo app_lang("gate_pass_request_details"); ?>
                    <span class="gp-detail-ref">#<?php echo esc($request->reference); ?></span>
                </h1>
            </div>
            <div class="gp-detail-hero-aside ms-auto text-end">
                <div class="gp-detail-badge-row gp-detail-badge-row-hero">
                    <?php if ($status): ?>
                        <span class="badge <?php echo $status_class; ?> gp-detail-badge">
                            <i data-feather="info" class="icon-14"></i>
                            <?php echo esc($status_label); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($stage): ?>
                        <span class="badge <?php echo $stage_class; ?> gp-detail-badge">
                            <i data-feather="layers" class="icon-14"></i>
                            <?php echo esc($stage); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($show_activity_log): ?>
                <button type="button"
                        class="btn btn-primary gp-detail-activity-btn"
                        data-bs-toggle="modal"
                        data-bs-target="#gp-activity-log-modal">
                    <i data-feather="activity" class="icon-16"></i>
                    <?php echo app_lang("gate_pass_activity_log"); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($is_requester && $status === "returned" && $latest_return): ?>
        <div class="px22 pb10" style="padding-left:22px;padding-right:22px;">
            <div class="alert alert-warning mb0">
                <div class="fw-bold mb5"><?php echo app_lang("gate_pass_return_notice_title"); ?></div>
                <div class="mb5"><?php echo app_lang("gate_pass_return_notice_from"); ?>: <strong><?php echo $return_stage_label; ?></strong></div>
                <?php
                $ret_who = trim((string)($latest_return->first_name ?? "") . " " . (string)($latest_return->last_name ?? ""));
                $ret_when = !empty($latest_return->decided_at) ? format_to_datetime($latest_return->decided_at) : "";
                ?>
                <?php if ($ret_who !== ""): ?>
                    <div class="text-off small mb5"><?php echo esc($ret_who); ?><?php echo $ret_when !== "" ? " · " . esc($ret_when) : ""; ?></div>
                <?php endif; ?>
                <?php if (!empty($latest_return->comment)): ?>
                    <div class="mt8 pt8" style="border-top:1px solid rgba(0,0,0,.08);">
                        <?php echo nl2br(esc($latest_return->comment)); ?>
                    </div>
                <?php endif; ?>
                <div class="mt8 small mb0 text-off"><?php echo app_lang("gate_pass_return_notice_footer"); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="gp-detail-meta">
            <?php if (gate_pass_request_created_at_pick($request)): ?>
            <span class="gp-chip"><i data-feather="calendar" class="icon-14"></i><?php echo app_lang("created_at"); ?>: <?php echo gate_pass_request_created_display($request); ?></span>
            <?php endif; ?>
        </div>

        <?php
        $has_toolbar = $show_add_visitor_vehicle || $show_submit_to_department || $show_pay_button || !empty($show_fee_waiver_pending_notice) || $is_requester;
        ?>
        <?php if ($has_toolbar): ?>
        <div class="gp-detail-toolbar">
            <?php if ($show_add_visitor_vehicle): ?>
            <div class="gp-detail-toolbar-block">
                <div class="gp-detail-toolbar-heading"><?php echo app_lang("gate_pass_detail_section_prepare"); ?></div>
                <div class="gp-detail-btn-row">
                    <?php echo modal_anchor(
                        get_uri("gate_pass_portal/visitor_modal_form"),
                        "<i data-feather='user-plus' class='icon-16'></i> " . app_lang("add_visitor"),
                        ["class" => "btn btn-default gp-detail-btn-secondary", "data-post-gate_pass_request_id" => $request->id, "title" => app_lang("add_visitor")]
                    ); ?>
                    <?php if ($portal_request_includes_vehicles): ?>
                    <?php echo modal_anchor(
                        get_uri("gate_pass_portal/vehicle_modal_form"),
                        "<i data-feather='truck' class='icon-16'></i> " . app_lang("add_vehicle"),
                        ["class" => "btn btn-default gp-detail-btn-secondary", "data-post-gate_pass_request_id" => $request->id, "title" => app_lang("add_vehicle")]
                    ); ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($show_submit_to_department): ?>
            <div class="gp-detail-toolbar-block gp-detail-toolbar-submit">
                <div class="gp-detail-toolbar-heading"><?php echo app_lang("gate_pass_detail_section_submit"); ?></div>
                <p class="gp-detail-hint"><?php echo $status === "returned"
                    ? app_lang("gate_pass_resubmit_hint")
                    : app_lang("gate_pass_submit_to_department_hint"); ?></p>
                <button type="button" id="gp-submit-dept-btn" class="btn btn-success gp-detail-btn-primary">
                    <i data-feather="send" class="icon-16"></i> <?php echo $status === "returned"
                        ? app_lang("gate_pass_resubmit_to_reviewer")
                        : app_lang("gate_pass_submit_to_department"); ?>
                </button>
            </div>
            <?php endif; ?>

            <?php if (!empty($show_fee_waiver_pending_notice) && $show_fee_waiver_pending_notice): ?>
            <div class="gp-detail-toolbar-block w-100">
                <div class="alert alert-warning mb0">
                    <?php echo app_lang("gate_pass_fee_waiver_pending_requester"); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($show_pay_button): ?>
            <div class="gp-detail-toolbar-block">
                <div class="gp-detail-toolbar-heading"><?php echo app_lang("gate_pass_detail_section_payment"); ?></div>
                <div class="gp-detail-btn-row">
                    <button type="button" id="gp-pay-btn" class="btn btn-primary gp-detail-btn-pay">
                        <i data-feather="credit-card" class="icon-16"></i> <?php echo app_lang("pay"); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

           <?php if ($is_requester): ?>
             <!-- 
            <div class="gp-detail-toolbar-block gp-detail-toolbar-more">
                <div class="gp-detail-toolbar-heading"><?php echo app_lang("gate_pass_detail_section_more"); ?></div>
                <div class="gp-detail-more-row">
                    <button type="button" id="gp-dup-request-btn" class="btn btn-default btn-sm gp-detail-btn-ghost" title="<?php echo app_lang("gate_pass_duplicate_request"); ?>">
                        <i data-feather="copy" class="icon-16"></i> <?php echo app_lang("gate_pass_duplicate_request"); ?>
                    </button>
                    <span class="gp-detail-more-hint"><?php echo app_lang("gate_pass_duplicate_request_hint"); ?></span>
                </div>
            </div>
             -->
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php echo view("gate_pass_includes/request_information_card", [
        "request" => $request,
        "can_edit_request_core" => $can_edit_request_core,
    ]); ?>

    <?php if ($show_gate_scan_log): ?>
    <div class="card gp-card mb15">
        <div class="gp-section-title">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="radio" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("gate_pass_scan_log_title"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("gate_pass_scan_log_hint"); ?></div>
                </div>
            </div>
        </div>
        <div class="p20 pt10">
            <?php if (empty($scan_log_rows)): ?>
                <p class="text-off mb0"><?php echo app_lang("gate_pass_scan_log_empty"); ?></p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb0">
                        <thead>
                            <tr>
                                <th><?php echo app_lang("gate_pass_scan_time"); ?></th>
                                <th><?php echo app_lang("gate_pass_scan_action"); ?></th>
                                <th><?php echo app_lang("gate_pass_scan_performed_by"); ?></th>
                                <th><?php echo app_lang("note"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scan_log_rows as $log): ?>
                                <?php
                                $act = strtolower(trim((string)($log->action ?? "")));
                                $ak = "gate_pass_scan_action_" . $act;
                                $action_disp = app_lang($ak);
                                if ($action_disp === $ak) {
                                    $action_disp = (string)($log->action ?? "-");
                                }
                                $who = trim(trim((string)($log->performed_by_first_name ?? "")) . " " . trim((string)($log->performed_by_last_name ?? "")));
                                $when = !empty($log->recorded_at) ? format_to_datetime($log->recorded_at) : "-";
                                $note = trim((string)($log->note ?? ""));
                                ?>
                                <tr>
                                    <td><?php echo esc($when); ?></td>
                                    <td><?php echo esc($action_disp); ?></td>
                                    <td><?php echo esc($who !== "" ? $who : ("#" . (int)($log->performed_by ?? 0))); ?></td>
                                    <td><?php echo $note !== "" ? nl2br(esc($note)) : "—"; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    $visitor_rows_for_attachments = $visitor_rows_for_attachments ?? [];
    $vehicle_rows_for_attachments = $vehicle_rows_for_attachments ?? [];
    $vf_attach_fields = [
        "id_attachment_path" => app_lang("id_attachment"),
        "visa_attachment_path" => app_lang("visa_attachment"),
        "photo_attachment_path" => app_lang("photo_attachment"),
        "driving_license_attachment_path" => app_lang("driving_license_attachment"),
    ];
    ?>
    <?php if (count($visitor_rows_for_attachments) || count($vehicle_rows_for_attachments)): ?>
    <div class="card gp-card mb15">
        <div class="gp-section-title">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="paperclip" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("gate_pass_documents_on_file_title"); ?></h4>
                    <div class="text-off font-13">
                        <?php
                        if ($portal_is_admin && !$is_requester) {
                            echo app_lang("gate_pass_documents_on_file_admin_hint");
                        } elseif (!$is_requester) {
                            echo app_lang("gate_pass_documents_on_file_reviewer_hint");
                        } else {
                            echo app_lang("gate_pass_documents_on_file_hint");
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="p20 pt10">
            <?php foreach ($visitor_rows_for_attachments as $vrow): ?>
                <div class="mb15 pb15" style="border-bottom:1px solid rgba(0,0,0,.06);">
                    <div class="fw-semibold mb8"><?php echo app_lang("visitors"); ?>: <?php echo esc($vrow->full_name ?? "-"); ?></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb0">
                            <thead><tr><th><?php echo app_lang("attachment"); ?></th><th class="text-center w180"><?php echo app_lang("actions"); ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($vf_attach_fields as $field => $flabel): ?>
                                <?php $p = isset($vrow->{$field}) ? trim((string)$vrow->{$field}) : ""; ?>
                                <?php if ($p !== ""): ?>
                                <tr>
                                    <td><?php echo esc($flabel); ?></td>
                                    <td class="text-center">
                                        <?php
                                        $base = get_uri("gate_pass_portal/visitor_attachment_download/" . (int)$vrow->id . "/" . $field);
                                        ?>
                                        <a href="<?php echo esc($base); ?>" class="btn btn-default btn-sm" target="_blank"><?php echo app_lang("view"); ?></a>
                                        <a href="<?php echo esc($base . "?download=1"); ?>" class="btn btn-default btn-sm" target="_blank"><?php echo app_lang("download"); ?></a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php foreach ($vehicle_rows_for_attachments as $veh): ?>
                <?php $mp = gate_pass_vehicle_mulkiyah_path_value($veh); ?>
                <?php if ($mp === "") { continue; } ?>
                <div class="mb0">
                    <div class="fw-semibold mb8"><?php echo app_lang("vehicles"); ?>: <?php echo esc($veh->plate_no ?? "-"); ?></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb0">
                            <thead><tr><th><?php echo app_lang("attachment"); ?></th><th class="text-center w180"><?php echo app_lang("actions"); ?></th></tr></thead>
                            <tbody>
                                <tr>
                                    <td><?php echo app_lang("gate_pass_mulkiyah_attachment"); ?></td>
                                    <td class="text-center">
                                        <?php $vb = get_uri("gate_pass_portal/vehicle_attachment_download/" . (int)$veh->id . "/mulkiyah_attachment_path"); ?>
                                        <a href="<?php echo esc($vb); ?>" class="btn btn-default btn-sm" target="_blank"><?php echo app_lang("view"); ?></a>
                                        <a href="<?php echo esc($vb . "?download=1"); ?>" class="btn btn-default btn-sm" target="_blank"><?php echo app_lang("download"); ?></a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approval Box -->
    <?php if ($show_approval_box && $can_approve): ?>
        <div class="card gp-card mb15 gp-accent">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="check-square" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("department_review"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("add_comment_if_needed"); ?></div>
                    </div>
                </div>
            </div>

            <div class="p20 pt10">
                <?php echo form_open(get_uri("gate_pass_portal/save_approval"), ["id" => "gp-portal-approval-form", "class" => "general-form", "role" => "form"]); ?>
                <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

                <div class="row">
                    <div class="col-md-4 col-sm-12 mb15">
                        <label class="form-label gp-label2"><?php echo app_lang("decision"); ?></label>
                        <select name="decision" id="gp-decision" class="form-control gp-control" required>
                            <option value="approved"><?php echo app_lang("approve"); ?></option>
                            <option value="returned"><?php echo app_lang("return_for_review"); ?></option>
                            <option value="rejected"><?php echo app_lang("reject"); ?></option>
                        </select>
                        <div class="gp-hint"><?php echo app_lang("workflow_action_hint"); ?></div>
                    </div>

                    <div class="col-md-8 col-sm-12 mb15">
                        <label class="form-label gp-label2"><?php echo app_lang("comment"); ?></label>
                        <textarea name="comment" id="gp-comment" class="form-control gp-control" rows="3"
                                  placeholder="<?php echo app_lang("comment"); ?>..."></textarea>
                        <div class="gp-hint" id="gp-comment-hint">
                            <?php echo app_lang("comment_optional"); ?>
                        </div>
                    </div>
                </div>

                <div class="gp-footer">
                    <button type="submit" class="btn btn-primary gp-btn-primary gp-pro-btn">
                        <i data-feather="send" class="icon-14"></i> <?php echo app_lang("submit"); ?>
                    </button>
                </div>

                <?php echo form_close(); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Approval History (Timeline) -->
    <?php if (!empty($approval_history) && is_array($approval_history)): ?>
        <div class="card gp-card mb15">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="activity" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("approval_history"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("all_actions_logged"); ?></div>
                    </div>
                </div>
            </div>

            <div class="p20 pt10">
                <div class="gp-timeline">
                    <?php foreach ($approval_history as $a): ?>
                        <?php
                        $decision = $a->decision ?? "";
                        $badge = "badge-soft-secondary";
                        $icon  = "circle";

                        if ($decision === "approved") { $badge = "badge-soft-success"; $icon = "check-circle"; }
                        if ($decision === "rejected") { $badge = "badge-soft-danger";  $icon = "x-circle"; }
                        if ($decision === "returned") { $badge = "badge-soft-warning"; $icon = "corner-up-left"; }
                        if ($decision === "fee_waiver_rejected") { $badge = "badge-soft-info"; $icon = "credit-card"; }

                        $decision_label = gate_pass_approval_decision_label($decision);
                        $who = trim(($a->first_name ?? "") . " " . ($a->last_name ?? ""));
                        $when = !empty($a->decided_at) ? format_to_datetime($a->decided_at) : "-";
                        $reason_title = trim((string)($a->reason_title ?? ""));
                        ?>
                        <div class="gp-timeline-item">
                            <div class="gp-timeline-dot <?php echo $badge; ?>">
                                <i data-feather="<?php echo $icon; ?>" class="icon-16"></i>
                            </div>

                            <div class="gp-timeline-body">
                                <div class="gp-timeline-top">
                                    <div class="gp-timeline-title">
                                        <span class="badge <?php echo $badge; ?>"><?php echo esc($decision_label); ?></span>
                                        <span class="text-off">•</span>
                                        <span class="text-off"><?php echo app_lang("stage"); ?>:</span>
                                        <b><?php echo esc($a->stage ?? "-"); ?></b>
                                    </div>
                                    <div class="gp-timeline-meta">
                                        <span class="text-off"><?php echo esc($who ?: "-"); ?></span>
                                        <span class="gp-dot">•</span>
                                        <span class="text-off"><?php echo esc($when); ?></span>
                                    </div>
                                </div>

                                <?php if ($reason_title !== "" || !empty($a->comment)): ?>
                                    <div class="gp-timeline-comment">
                                        <?php if ($reason_title !== ""): ?>
                                            <div><b><?php echo app_lang("reason"); ?>:</b> <?php echo esc($reason_title); ?></div>
                                        <?php endif; ?>
                                        <?php echo nl2br(esc($a->comment)); ?>
                                    </div>
                                <?php else: ?>
                                    <div class="gp-timeline-comment gp-muted">
                                        <?php echo app_lang("no_comment"); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Gate Pass QR Code (only when status is rop_approved) -->
    <?php if ($show_qr_section): ?>
        <div class="card gp-card mb15">
            <div class="gp-section-title">
                <div class="d-flex align-items-center">
                    <span class="gp-section-icon"><i data-feather="maximize-2" class="icon-18"></i></span>
                    <div>
                        <h4 class="mt0 mb0"><?php echo app_lang("gate_pass_qr_code"); ?></h4>
                        <div class="text-off font-13"><?php echo app_lang("gate_pass_qr_code_hint"); ?></div>
                    </div>
                </div>
            </div>
            <div class="p20 pt10">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <?php if ($qr_image_url): ?>
                        <div class="gp-qr-wrap">
                            <img src="<?php echo esc($qr_image_url); ?>" alt="QR Code" class="gp-qr-img" width="200" height="200" />
                        </div>
                    <?php endif; ?>
                    <div class="d-flex flex-wrap align-items-stretch gap-2">
                        <a href="<?php echo get_uri("gate_pass_portal/download_qr/" . (int)$request->id); ?>" class="btn btn-primary gp-btn-primary gp-pro-btn" target="_blank" download>
                            <i data-feather="download" class="icon-16"></i> <?php echo app_lang("download_qr_code"); ?>
                        </a>
                        <a href="<?php echo get_uri("gate_pass_portal/download_gate_pass_pdf/" . (int)$request->id); ?>" class="btn btn-default gp-detail-btn-secondary gp-pro-btn" target="_blank" rel="noopener">
                            <i data-feather="file-text" class="icon-16"></i> <?php echo app_lang("gate_pass_download_pdf"); ?>
                        </a>
                    </div>
                    <p class="text-off font-12 mb0 mt10"><?php echo app_lang("gate_pass_pdf_hint"); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Visitors -->
    <div class="card gp-card mb15 gp-dt-card">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="users" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("visitors"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("visitor_list"); ?></div>
                </div>
            </div>
        </div>

        <div class="gp-dt-panel table-responsive p15 p-sm-12 gp-table-wrap">
            <table id="gp-visitors-table" class="display gp-dt-table" width="100%" cellspacing="0"></table>
        </div>
    </div>

    <?php if ($portal_request_includes_vehicles): ?>
    <!-- Vehicles -->
    <div class="card gp-card gp-dt-card">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="truck" class="icon-18"></i></span>
                <div>
                    <h4 class="mt0 mb0"><?php echo app_lang("vehicles"); ?></h4>
                    <div class="text-off font-13"><?php echo app_lang("vehicle_list"); ?></div>
                </div>
            </div>
        </div>

        <div class="gp-dt-panel table-responsive p15 p-sm-12 gp-table-wrap">
            <table id="gp-vehicles-table" class="display gp-dt-table" width="100%" cellspacing="0"></table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($show_activity_log): ?>
    <div class="modal fade gp-activity-log-modal-root" id="gp-activity-log-modal" tabindex="-1" aria-labelledby="gp-activity-log-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gp-activity-log-modal-label"><?php echo app_lang("gate_pass_activity_log"); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo app_lang("close"); ?>"></button>
                </div>
                <div class="modal-body pt10">
                    <p class="text-muted small mb12"><?php echo app_lang("all_actions_logged"); ?></p>
                    <div class="gp-dt-panel gp-audit-modal-dt table-responsive gp-table-wrap">
                        <table id="gp-audit-table" class="display gp-dt-table" width="100%" cellspacing="0"></table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<style>
/* ===== Gate Pass Premium UI ===== */
.gp-detail-page { --gp-d-ink: #0f172a; --gp-d-muted: #64748b; --gp-d-line: rgba(15,23,42,.08); --gp-d-radius: 14px; }

/*
 * .gp-pro-page uses a transform animation (custom-style.css). Any ancestor with
 * transform makes position:fixed modals position against that box instead of the
 * viewport — the activity log looked like a full-width strip. Reset here + move modal to body in JS.
 */
#page-content.gp-detail-page.gp-pro-page {
    animation: none !important;
    transform: none !important;
    opacity: 1 !important;
}

.gp-card { border-radius: var(--gp-d-radius); border: 1px solid rgba(0,0,0,.06); overflow: hidden; }

/* Request details hero */
.gp-detail-hero { border: 1px solid var(--gp-d-line); box-shadow: 0 10px 36px rgba(15,23,42,.06); }
.gp-detail-hero-top {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
    padding: 20px 22px 14px;
    background: linear-gradient(180deg, rgba(248,250,252,.95), #fff);
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-detail-h1 {
    margin: 0; font-size: 1.35rem; font-weight: 800; letter-spacing: -.02em; color: var(--gp-d-ink); line-height: 1.25;
}
.gp-detail-ref { font-weight: 700; color: var(--gp-d-muted); margin-inline-start: 6px; }
.gp-detail-badge-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.gp-detail-badge-row-hero { justify-content: flex-end; }
.gp-detail-hero-aside {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    max-width: 100%;
}
.gp-detail-activity-btn {
    font-weight: 700;
    padding: 10px 18px;
    border-radius: 11px;
    box-shadow: 0 6px 20px rgba(37, 99, 235, 0.35);
    white-space: nowrap;
}
.gp-detail-activity-btn i { vertical-align: middle; margin-inline-end: 6px; }
.gp-detail-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 11px; border-radius: 999px; font-weight: 650; font-size: 12px; }

.gp-detail-meta {
    display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 22px 16px;
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-chip { display:inline-flex; align-items:center; gap:8px; padding: 7px 12px; border-radius: 999px; background: rgba(15,23,42,.04); border: 1px solid var(--gp-d-line); font-size: 13px; color: var(--gp-d-ink); }
.gp-dot { opacity: .45; }

/* Toolbar: stacked sections, clear reading order */
.gp-detail-toolbar {
    display: flex; flex-direction: column; gap: 0;
    padding: 0 22px;
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-detail-toolbar-block {
    padding: 16px 0;
    border-bottom: 1px solid rgba(15,23,42,.06);
}
.gp-detail-toolbar-block:last-of-type { border-bottom: none; }
.gp-detail-toolbar-heading {
    font-size: 11px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--gp-d-muted);
    margin-bottom: 10px;
}
.gp-detail-btn-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
.gp-detail-btn-secondary {
    border-radius: 10px; font-weight: 650; padding: 9px 14px;
    border: 1px solid rgba(15,23,42,.12); background: #fff;
}
.gp-detail-btn-secondary:hover { background: rgba(15,23,42,.04); border-color: rgba(15,23,42,.18); }

.gp-detail-toolbar-submit .gp-detail-hint {
    margin: 0 0 12px; font-size: 13px; line-height: 1.5; color: var(--gp-d-muted); max-width: 720px;
}
.gp-detail-btn-primary {
    border-radius: 11px; font-weight: 750; padding: 11px 20px;
    box-shadow: 0 6px 18px rgba(25, 135, 84, .28);
}
.gp-detail-btn-pay {
    border-radius: 11px; font-weight: 750; padding: 10px 18px;
    box-shadow: 0 6px 18px rgba(37, 99, 235, .25);
}

.gp-detail-toolbar-more { padding-bottom: 18px; }
.gp-detail-more-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px; }
.gp-detail-btn-ghost { border-radius: 9px; border: 1px dashed rgba(15,23,42,.2); background: transparent; color: var(--gp-d-muted); }
.gp-detail-btn-ghost:hover { border-style: solid; background: rgba(15,23,42,.04); color: var(--gp-d-ink); }
.gp-detail-more-hint { font-size: 12px; color: var(--gp-d-muted); max-width: 520px; line-height: 1.45; }

/* Date / fee summary strip */
.gp-detail-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    padding: 16px 22px 18px;
    background: rgba(248,250,252,.65);
}
.gp-detail-summary-item {
    padding: 12px 14px; border-radius: 11px;
    border: 1px solid var(--gp-d-line); background: #fff;
}
.gp-detail-summary-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--gp-d-muted); margin-bottom: 4px; }
.gp-detail-summary-value { font-size: 15px; font-weight: 750; color: var(--gp-d-ink); }

.gp-btn { border-radius: 10px; font-weight: 600; transition: all .2s ease; }
.gp-btn:hover { transform: translateY(-1px); }
.gp-btn-primary { border-radius: 12px; padding: 10px 14px; font-weight: 600; box-shadow: 0 8px 18px rgba(37, 99, 235, .22); }

.gp-section-title { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,.06); background: rgba(0,0,0,.02); }
.gp-section-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-section-icon { width:36px; height:36px; border-radius: 12px; background: rgba(0,0,0,.04); display:flex; align-items:center; justify-content:center; margin-right: 10px; }

.gp-accent { border-left: 5px solid rgba(13,110,253,.45); }
.gp-label2 { font-weight: 700; font-size: 13px; }
.gp-control { border-radius: 12px; padding: 10px 12px; }
.gp-hint { font-size: 12px; color: rgba(0,0,0,.55); margin-top: 6px; }
.gp-footer { display:flex; justify-content:flex-end; margin-top: 6px; }

.gp-table-wrap { overflow-x:auto; -webkit-overflow-scrolling: touch; }
#page-content .table-responsive { min-height: 0; }

/* Visitors / vehicles DataTables (this page) */
.gp-detail-page .gp-dt-card .gp-dt-panel {
    background: linear-gradient(180deg, rgba(248,250,252,.92) 0%, #fff 14px);
    border-top: 1px solid rgba(15,23,42,.06);
}
.gp-detail-page .gp-dt-table.dataTable thead th {
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--gp-d-muted);
    border-bottom: 1px solid rgba(15,23,42,.1) !important;
    background: rgba(248,250,252,.98) !important;
    padding: 11px 12px !important;
}
.gp-detail-page .gp-dt-table.dataTable tbody td {
    padding: 10px 12px !important;
    vertical-align: middle;
    border-color: rgba(15,23,42,.06) !important;
    font-size: 13px;
}
.gp-detail-page .gp-dt-table.dataTable tbody tr:nth-child(even) td {
    background: rgba(248,250,252,.4);
}
.gp-detail-page .gp-dt-table.dataTable tbody tr:hover td {
    background: rgba(59,130,246,.06) !important;
}
/* Compact pagination & tools (overrides global gp-* rules on this page) */
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .dataTables_paginate .paginate_button {
    min-width: 26px !important;
    height: 26px !important;
    line-height: 24px !important;
    padding: 0 6px !important;
    margin: 0 2px !important;
    font-size: 12px !important;
    border-radius: 7px !important;
    font-weight: 600 !important;
}
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .dataTables_length select {
    min-height: 30px !important;
    padding: 4px 28px 4px 10px !important;
    font-size: 12px !important;
    border-radius: 8px !important;
}
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .dataTables_filter input {
    min-height: 30px !important;
    font-size: 13px !important;
    border-radius: 8px !important;
    padding: 5px 10px !important;
}
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .dataTables_info {
    font-size: 12px;
    color: var(--gp-d-muted);
    padding-top: .55em;
}
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .datatable-tools,
.gp-detail-page div[id^="gp-"][id$="-table_wrapper"] .table-bottom {
    padding-top: 8px;
    padding-bottom: 4px;
}
#gp-activity-log-modal .gp-audit-modal-dt { min-height: 120px; }
#gp-activity-log-modal.modal .modal-body { max-height: min(70vh, 520px); }
#gp-activity-log-modal .filter-section-container { padding: 6px 0 2px; }
#gp-activity-log-modal .datatable-tools { margin-top: 4px; padding-top: 6px !important; }

/* Timeline */
.gp-timeline { display:flex; flex-direction:column; gap:14px; }
.gp-timeline-item { display:flex; gap:12px; }
.gp-timeline-dot { width:40px; height:40px; border-radius: 14px; display:flex; align-items:center; justify-content:center; border:1px solid rgba(0,0,0,.06); background:#fff; flex: 0 0 auto; }
.gp-timeline-body { flex:1; border:1px solid rgba(0,0,0,.06); border-radius: 14px; padding: 12px 14px; background:#fff; }
.gp-timeline-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap; }
.gp-timeline-title { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.gp-timeline-meta { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.gp-timeline-comment { margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(0,0,0,.03); border: 1px solid rgba(0,0,0,.06); }
.gp-muted { color: rgba(0,0,0,.55); }
.gp-qr-wrap { padding: 8px; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; background: #fff; }
.gp-qr-img { display: block; border-radius: 8px; }

/* Soft badges */
.badge-soft-secondary { background: rgba(108,117,125,.15); color:#2f343a; border:1px solid rgba(108,117,125,.25); }
.badge-soft-primary { background: rgba(13,110,253,.15); color:#084298; border:1px solid rgba(13,110,253,.25); }
.badge-soft-success { background: rgba(25,135,84,.15); color:#0f5132; border:1px solid rgba(25,135,84,.25); }
.badge-soft-warning { background: rgba(255,193,7,.20); color:#664d03; border:1px solid rgba(255,193,7,.35); }
.badge-soft-danger { background: rgba(220,53,69,.15); color:#842029; border:1px solid rgba(220,53,69,.25); }
.badge-soft-info { background: rgba(13,202,240,.15); color:#055160; border:1px solid rgba(13,202,240,.25); }

/* Responsive */
@media (max-width: 576px) {
  .gp-detail-hero-top { padding: 16px 16px 12px; }
  .gp-detail-hero-aside { width: 100%; align-items: stretch; }
  .gp-detail-badge-row-hero { justify-content: flex-start; }
  .gp-detail-activity-btn { width: 100%; justify-content: center; }
  .gp-detail-meta, .gp-detail-toolbar { padding-left: 16px; padding-right: 16px; }
  .gp-detail-summary { padding: 14px 16px 16px; }
  .gp-section-title { padding: 14px 16px; }
  .gp-timeline-dot { width:36px; height:36px; border-radius: 12px; }
  .gp-detail-btn-row { flex-direction: column; align-items: stretch; }
  .gp-detail-btn-row .btn { width: 100%; justify-content: center; }
}
</style>

<script>
$(document).ready(function () {

    <?php if ($show_activity_log): ?>
    var $gpActivityLogModal = $("#gp-activity-log-modal");
    if ($gpActivityLogModal.length) {
        $gpActivityLogModal.appendTo(document.body);
    }
    <?php endif; ?>

    $("#gp-visitors-table").appTable({
        source: "<?php echo_uri('gate_pass_portal/visitors_list_data/'.$request->id); ?>",
        displayLength: 8,
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('full_name'); ?>" },
            { title: "<?php echo app_lang('id_type'); ?>" },
            { title: "<?php echo app_lang('id_number'); ?>" },
            { title: "<?php echo app_lang('nationality'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('role'); ?>" },
            { title: "<?php echo app_lang('blocked'); ?>" },
            { title: "<?php echo app_lang('reason'); ?>" },
            { title: "<?php echo app_lang('primary'); ?>", class: "text-center" },
            { title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120" }
        ]
    });

    <?php if ($portal_request_includes_vehicles): ?>
    $("#gp-vehicles-table").appTable({
        source: "<?php echo_uri('gate_pass_portal/vehicles_list_data/'.$request->id); ?>",
        displayLength: 8,
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('plate_no'); ?>" },
            { title: "<?php echo app_lang('gate_pass_mulkiyah_attachment'); ?>", class: "text-center w100" },
            { title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120" }
        ]
    });
    <?php endif; ?>

    <?php if ($show_activity_log): ?>
    $("#gp-activity-log-modal").on("shown.bs.modal", function () {
        if (!$.fn.DataTable || !$.fn.DataTable.isDataTable("#gp-audit-table")) {
            $("#gp-audit-table").appTable({
                source: "<?php echo_uri('gate_pass_portal/request_audit_list_data/'.$request->id); ?>",
                displayLength: 8,
                columnShowHideOption: false,
                stateSave: false,
                columns: [
                    { title: "<?php echo app_lang('date'); ?>" },
                    { title: "<?php echo app_lang('user'); ?>" },
                    { title: "<?php echo app_lang('action'); ?>" },
                    { title: "<?php echo app_lang('details'); ?>" }
                ],
                order: [[0, "desc"]]
            });
        } else {
            $("#gp-audit-table").DataTable().columns.adjust();
        }
        if (typeof feather !== "undefined") feather.replace();
    });
    <?php endif; ?>

    <?php if ($is_requester): ?>
    $("#gp-dup-request-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);
        $.ajax({
            url: "<?php echo get_uri('gate_pass_portal/duplicate_request'); ?>",
            type: "POST",
            data: { id: "<?php echo (int)$request->id; ?>", "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>" },
            dataType: "json",
            success: function (res) {
                if (res.success && res.redirect) {
                    window.location.href = res.redirect;
                } else {
                    appAlert.error(res.message || "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });
    <?php endif; ?>

    <?php if ($show_submit_to_department): ?>
    $("#gp-submit-dept-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);
        $.ajax({
            url: "<?php echo get_uri('gate_pass_portal/submit_to_department'); ?>",
            type: "POST",
            data: { gate_pass_request_id: "<?php echo (int)$request->id; ?>", "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>" },
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    appAlert.success(res.message);
                    window.location.reload();
                } else {
                    appAlert.error(res.message || "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });
    <?php endif; ?>

    <?php if ($show_pay_button): ?>
    $("#gp-pay-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);
        $.ajax({
            url: "<?php echo get_uri('gate_pass_portal/save_payment'); ?>",
            type: "POST",
            data: { gate_pass_request_id: "<?php echo (int)$request->id; ?>", "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>" },
            dataType: "json",
            success: function (res) {
                if (res.success) {
                    appAlert.success(res.message);
                    window.location.reload();
                } else {
                    appAlert.error(res.message || "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });
    <?php endif; ?>

    <?php if ($show_approval_box && $can_approve): ?>
    // Make comment required for Returned/Rejected (optional but professional)
    function updateCommentRequirement() {
        var d = $("#gp-decision").val();
        var require = (d === "returned" || d === "rejected");
        $("#gp-comment").prop("required", require);
        $("#gp-comment-hint").text(require ? "<?php echo app_lang('comment_required_for_this_action'); ?>" : "<?php echo app_lang('comment_optional'); ?>");
    }
    $("#gp-decision").on("change", updateCommentRequirement);
    updateCommentRequirement();

    $("#gp-portal-approval-form").appForm({
        onSuccess: function () {
            window.location.reload();
        }
    });
    <?php endif; ?>

    <?php if ($show_qr_section): ?>
    window.addEventListener("beforeprint", function () {
        $.ajax({
            url: "<?php echo get_uri('gate_pass_portal/record_qr_print'); ?>",
            type: "POST",
            dataType: "json",
            data: {
                gate_pass_request_id: "<?php echo (int)$request->id; ?>",
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            }
        });
    });
    <?php endif; ?>

    if (typeof feather !== "undefined") feather.replace();
});
</script>
