<?php
$status = $request->status ?? "";
$stage  = $request->stage ?? "";
$waiver_pending = gate_pass_fee_waiver_pending($request);
$wst = strtolower(trim((string)($request->fee_waiver_commercial_status ?? "")));
$waiver_rejected_recorded = !$waiver_pending && $wst === "rejected" && empty($request->fee_is_waived);

if (!isset($status_label)) {
    $status_label = gate_pass_request_status_display($request);
}

$status_class = "badge-soft-secondary";
if ($status === "submitted") {
    $status_class = "badge-soft-warning";
}
if ($status === "returned") {
    $status_class = "badge-soft-danger";
}
if ($status === "department_approved" || $status === "commercial_approved") {
    $status_class = "badge-soft-success";
}
if ($status === "rejected") {
    $status_class = "badge-soft-danger";
}

$stage_class = "badge-soft-secondary";
if ($stage === "department") {
    $stage_class = "badge-soft-primary";
}
if ($stage === "commercial") {
    $stage_class = "badge-soft-info";
}
if ($stage === "security") {
    $stage_class = "badge-soft-warning";
}
if ($stage === "rop") {
    $stage_class = "badge-soft-danger";
}
if ($stage === "issued") {
    $stage_class = "badge-soft-success";
}

$show_set_fee_btn = ($status === "department_approved" && $stage === "commercial");
$show_commercial_return_btn = $show_set_fee_btn && !$waiver_pending;

$visit_from_disp = !empty($request->visit_from) ? format_to_date($request->visit_from) : "-";
$visit_to_disp = !empty($request->visit_to) ? format_to_date($request->visit_to) : "-";

$currency_disp = trim((string)($request->currency ?? "OMR"));
$fee_raw = $request->fee_amount ?? null;
$fee_num = is_numeric($fee_raw) ? (float)$fee_raw : null;
$fee_amount_disp = $fee_num !== null ? number_format($fee_num, 3) : (($fee_raw !== null && $fee_raw !== "") ? (string)$fee_raw : "—");

$step_labels = [
    "department" => app_lang("gate_pass_stage_department"),
    "commercial" => app_lang("gate_pass_stage_commercial"),
    "security" => app_lang("gate_pass_stage_security"),
    "rop" => app_lang("gate_pass_stage_rop"),
    "issued" => app_lang("gate_pass_stage_issued"),
];
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-detail-page gp-commercial-detail">

    <nav class="gp-commercial-breadcrumb" aria-label="<?php echo app_lang('gate_pass_commercial_inbox'); ?>">
        <a href="<?php echo get_uri('gate_pass_commercial_inbox'); ?>" class="gp-commercial-breadcrumb-link">
            <i data-feather="arrow-left" class="icon-16"></i>
            <span><?php echo app_lang('back'); ?> — <?php echo app_lang('gate_pass_commercial_inbox'); ?></span>
        </a>
    </nav>

    <div class="card gp-card gp-detail-hero mb15 gp-commercial-hero">
        <div class="gp-detail-hero-top">
            <div class="gp-detail-title-block">
                <p class="gp-commercial-kicker"><?php echo app_lang('gate_pass_commercial_inbox'); ?></p>
                <h1 class="gp-detail-h1">
                    <?php echo app_lang('gate_pass_request_details'); ?>
                    <span class="gp-detail-ref">#<?php echo esc($request->reference); ?></span>
                </h1>
                <p class="gp-commercial-lead"><?php echo app_lang('gate_pass_commercial_detail_hint'); ?></p>
            </div>
            <div class="gp-detail-hero-aside">
                <div class="gp-detail-badge-row gp-detail-badge-row-hero">
                    <?php if (!empty($status)): ?>
                        <span class="badge <?php echo $status_class; ?> gp-detail-badge">
                            <i data-feather="info" class="icon-14"></i>
                            <?php echo esc($status_label); ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($stage)): ?>
                        <span class="badge <?php echo $stage_class; ?> gp-detail-badge">
                            <i data-feather="layers" class="icon-14"></i>
                            <?php echo esc($stage); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="gp-commercial-actions">
                    <?php if ($show_set_fee_btn): ?>
                        <?php echo modal_anchor(
                            get_uri("gate_pass_commercial_inbox/fee_modal_form"),
                            "<span class='gp-set-fee-omr'>OMR</span> " . app_lang("set_fee"),
                            ["class" => "btn btn-primary gp-btn-primary", "title" => app_lang("set_fee"), "data-post-id" => $request->id]
                        ); ?>
                    <?php endif; ?>
                    <?php if (!empty($show_commercial_return_btn) && $show_commercial_return_btn): ?>
                        <?php echo modal_anchor(
                            get_uri("gate_pass_commercial_inbox/return_request_modal"),
                            "<i data-feather='corner-up-left' class='icon-16'></i> " . app_lang("gate_pass_return_to_requester"),
                            ["class" => "btn btn-warning gp-btn", "title" => app_lang("gate_pass_return_to_requester"), "data-post-id" => $request->id]
                        ); ?>
                    <?php endif; ?>
                    <?php echo modal_anchor(
                        get_uri("gate_pass_commercial_inbox/approval_history_modal"),
                        "<i data-feather='list' class='icon-16'></i> " . app_lang("approval_history"),
                        ["class" => "btn btn-default gp-btn", "title" => app_lang("approval_history"), "data-post-id" => $request->id]
                    ); ?>
                </div>
            </div>
        </div>

        <div class="gp-detail-meta">
            <?php if (gate_pass_request_created_at_pick($request)): ?>
                <span class="gp-chip">
                    <i data-feather="clock" class="icon-14"></i>
                    <?php echo app_lang("created_at"); ?>: <?php echo gate_pass_request_created_display($request); ?>
                </span>
            <?php endif; ?>
            <span class="gp-chip"><i data-feather="briefcase" class="icon-14"></i><?php echo esc($request->company_name ?? "-"); ?></span>
            <span class="gp-chip"><i data-feather="grid" class="icon-14"></i><?php echo esc($request->department_name ?? "-"); ?></span>
            <span class="gp-chip"><i data-feather="clipboard" class="icon-14"></i><?php echo esc($request->purpose_name ?? "-"); ?></span>
        </div>

        <div class="gp-detail-summary">
            <div class="gp-detail-summary-item">
                <span class="gp-detail-summary-label"><?php echo app_lang("visit_from"); ?></span>
                <span class="gp-detail-summary-value"><?php echo esc($visit_from_disp); ?></span>
            </div>
            <div class="gp-detail-summary-item">
                <span class="gp-detail-summary-label"><?php echo app_lang("visit_to"); ?></span>
                <span class="gp-detail-summary-value"><?php echo esc($visit_to_disp); ?></span>
            </div>
            <div class="gp-detail-summary-item gp-detail-summary-item--fee">
                <span class="gp-detail-summary-label"><?php echo app_lang("fee_amount"); ?></span>
                <span class="gp-detail-summary-value">
                    <?php echo esc($currency_disp); ?> <?php echo esc($fee_amount_disp); ?>
                    <?php if (!empty($request->fee_is_waived)): ?>
                        <span class="badge badge-soft-success ms-1"><?php echo app_lang("waived"); ?></span>
                    <?php elseif ($waiver_pending): ?>
                        <span class="badge badge-soft-warning ms-1"><?php echo app_lang("gate_pass_fee_waiver_pending"); ?></span>
                    <?php elseif ($waiver_rejected_recorded): ?>
                        <span class="badge badge-soft-secondary ms-1"><?php echo app_lang("gate_pass_fee_waiver_rejected_badge"); ?></span>
                    <?php endif; ?>
                </span>
            </div>
            <div class="gp-detail-summary-item gp-detail-summary-item--wide">
                <span class="gp-detail-summary-label"><?php echo app_lang("gate_pass_commercial_fee_next_steps"); ?></span>
                <span class="gp-detail-summary-value gp-detail-summary-value--muted">
                    <?php if ($waiver_pending): ?>
                        <?php echo app_lang("gate_pass_fee_waiver_pending_commercial_banner"); ?>
                    <?php elseif ($waiver_rejected_recorded): ?>
                        <?php echo app_lang("gate_pass_fee_waiver_rejected_may_waive_later"); ?>
                    <?php elseif (!empty($request->fee_is_waived)): ?>
                        <?php echo app_lang("gate_pass_fee_waived_summary"); ?>
                    <?php elseif ($fee_num !== null && $fee_num > 0): ?>
                        <?php echo app_lang("gate_pass_commercial_note_requester_pays"); ?>
                    <?php else: ?>
                        <?php echo app_lang("gate_pass_zero_fee_summary"); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="gp-commercial-workflow">
            <div class="gp-commercial-workflow-head">
                <span class="gp-commercial-workflow-icon"><i data-feather="git-branch" class="icon-18"></i></span>
                <div>
                    <div class="gp-commercial-workflow-title"><?php echo app_lang("gate_pass_workflow"); ?></div>
                    <div class="gp-commercial-workflow-sub text-off"><?php echo app_lang("stage"); ?></div>
                </div>
            </div>
            <div class="gp-stepper gp-commercial-stepper">
                <?php
                $steps = ["department", "commercial", "security", "rop", "issued"];
                $current_index = array_search(strtolower((string)$stage), $steps, true);
                if ($current_index === false) {
                    $current_index = -1;
                }
                foreach ($steps as $i => $s):
                    $is_done = ($current_index >= $i);
                    $is_active = ($current_index === $i);
                    $cls = $is_active ? "active" : ($is_done ? "done" : "");
                    ?>
                    <div class="gp-step <?php echo $cls; ?>">
                        <div class="gp-step-dot">
                            <?php if ($is_done): ?>
                                <i data-feather="check" class="icon-14"></i>
                            <?php else: ?>
                                <span><?php echo $i + 1; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="gp-step-label"><?php echo esc($step_labels[$s] ?? ucfirst($s)); ?></div>
                        <?php if ($i < count($steps) - 1): ?>
                            <div class="gp-step-line"></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php echo view("gate_pass_includes/request_information_card", ["request" => $request]); ?>

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
    <div class="card gp-card gp-dt-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="paperclip" class="icon-18"></i></span>
                <div>
                    <h2 class="gp-dt-card-title mt0 mb0"><?php echo app_lang("gate_pass_documents_on_file_title"); ?></h2>
                    <div class="text-off font-13"><?php echo app_lang("gate_pass_documents_on_file_reviewer_hint"); ?></div>
                </div>
            </div>
        </div>
        <div class="gp-dt-panel p15 p-sm-12">
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
                                        $base = get_uri("gate_pass_commercial_inbox/visitor_attachment_download/" . (int)$vrow->id . "/" . $field);
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
                <?php if ($mp === "") {
                    continue;
                } ?>
                <div class="mb0">
                    <div class="fw-semibold mb8"><?php echo app_lang("vehicles"); ?>: <?php echo esc($veh->plate_no ?? "-"); ?></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb0">
                            <thead><tr><th><?php echo app_lang("attachment"); ?></th><th class="text-center w180"><?php echo app_lang("actions"); ?></th></tr></thead>
                            <tbody>
                                <tr>
                                    <td><?php echo app_lang("gate_pass_mulkiyah_attachment"); ?></td>
                                    <td class="text-center">
                                        <?php $vb = get_uri("gate_pass_commercial_inbox/vehicle_attachment_download/" . (int)$veh->id . "/mulkiyah_attachment_path"); ?>
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

    <div id="gp-commercial-visitors-card" class="card gp-card gp-dt-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="users" class="icon-18"></i></span>
                <div>
                    <h2 class="gp-dt-card-title mt0 mb0"><?php echo app_lang("visitors"); ?></h2>
                    <div class="text-off font-13"><?php echo app_lang("list"); ?></div>
                </div>
            </div>
        </div>
        <div class="gp-dt-panel table-responsive p15 p-sm-12 gp-table-wrap">
            <table id="gp-commercial-visitors-table" class="display gp-dt-table" width="100%" cellspacing="0"></table>
        </div>
    </div>

    <div id="gp-commercial-vehicles-card" class="card gp-card gp-dt-card mb15">
        <div class="gp-section-title gp-section-title-row">
            <div class="d-flex align-items-center">
                <span class="gp-section-icon"><i data-feather="truck" class="icon-18"></i></span>
                <div>
                    <h2 class="gp-dt-card-title mt0 mb0"><?php echo app_lang("vehicles"); ?></h2>
                    <div class="text-off font-13"><?php echo app_lang("list"); ?></div>
                </div>
            </div>
        </div>
        <div class="gp-dt-panel table-responsive p15 p-sm-12 gp-table-wrap">
            <table id="gp-commercial-vehicles-table" class="display gp-dt-table" width="100%" cellspacing="0"></table>
        </div>
    </div>
</div>

<style>
/* Commercial request detail — aligned with portal premium UI */
.gp-commercial-detail.gp-detail-page { --gp-d-ink: #0f172a; --gp-d-muted: #64748b; --gp-d-line: rgba(15,23,42,.08); --gp-d-radius: 14px; }

#page-content.gp-commercial-detail.gp-detail-page.gp-pro-page {
    animation: none !important;
    transform: none !important;
    opacity: 1 !important;
}

.gp-commercial-detail#page-content {
    overflow-y: auto !important;
    overflow-x: hidden;
    height: auto !important;
    min-height: 100%;
    padding-bottom: 48px;
}

.gp-commercial-breadcrumb { margin-bottom: 12px; }
.gp-commercial-breadcrumb-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--gp-d-muted, #64748b);
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 10px;
    border: 1px solid rgba(15,23,42,.08);
    background: rgba(255,255,255,.8);
    transition: color .15s ease, background .15s ease, border-color .15s ease;
}
.gp-commercial-breadcrumb-link:hover {
    color: #1d4ed8;
    background: rgba(37, 99, 235, .06);
    border-color: rgba(37, 99, 235, .2);
}
.gp-commercial-breadcrumb-link i { flex-shrink: 0; }

.gp-set-fee-omr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.08em;
    line-height: 1;
    padding: 3px 7px;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.55);
    margin-inline-end: 6px;
    vertical-align: middle;
    color: inherit;
}

.gp-card { border-radius: var(--gp-d-radius, 14px); border: 1px solid rgba(0,0,0,.06); overflow: hidden; }

.gp-commercial-hero.gp-detail-hero {
    border: 1px solid var(--gp-d-line);
    box-shadow: 0 10px 40px rgba(15, 23, 42, .07);
    border-left: 4px solid rgba(37, 99, 235, .55);
}
.gp-detail-hero-top {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; flex-wrap: wrap;
    padding: 22px 24px 16px;
    background: linear-gradient(135deg, rgba(248,250,252,.98) 0%, #fff 45%, rgba(239,246,255,.65) 100%);
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-commercial-kicker {
    margin: 0 0 6px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #2563eb;
}
.gp-detail-h1 {
    margin: 0 0 10px;
    font-size: 1.45rem;
    font-weight: 800;
    letter-spacing: -.02em;
    color: var(--gp-d-ink);
    line-height: 1.2;
}
.gp-detail-ref { font-weight: 700; color: var(--gp-d-muted); margin-inline-start: 6px; }
.gp-commercial-lead {
    margin: 0;
    max-width: 640px;
    font-size: 14px;
    line-height: 1.55;
    color: var(--gp-d-muted);
}
.gp-detail-hero-aside {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 14px;
    max-width: 100%;
}
.gp-detail-badge-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.gp-detail-badge-row-hero { justify-content: flex-end; }
.gp-detail-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-weight: 650; font-size: 12px; }
.gp-commercial-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; }
.gp-btn { border-radius: 10px; font-weight: 600; }
.gp-btn-primary { border-radius: 11px; padding: 10px 16px; font-weight: 650; box-shadow: 0 8px 22px rgba(37, 99, 235, .22); }

.gp-detail-meta {
    display: flex; flex-wrap: wrap; gap: 8px;
    padding: 14px 24px 16px;
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-chip {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 7px 12px; border-radius: 999px;
    background: rgba(15,23,42,.04);
    border: 1px solid var(--gp-d-line);
    font-size: 13px;
    color: var(--gp-d-ink);
}

.gp-detail-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    padding: 18px 24px 20px;
    background: linear-gradient(180deg, rgba(248,250,252,.9), #fff);
    border-bottom: 1px solid var(--gp-d-line);
}
.gp-detail-summary-item--fee { grid-column: span 1; }
.gp-detail-summary-item--wide { grid-column: 1 / -1; }
@media (min-width: 992px) {
    .gp-detail-summary-item--wide { grid-column: span 2; }
}
.gp-detail-summary-item {
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid var(--gp-d-line);
    background: #fff;
    box-shadow: 0 1px 2px rgba(15,23,42,.04);
}
.gp-detail-summary-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: var(--gp-d-muted);
    margin-bottom: 6px;
}
.gp-detail-summary-value { font-size: 15px; font-weight: 750; color: var(--gp-d-ink); line-height: 1.35; }
.gp-detail-summary-value--muted { font-size: 13px; font-weight: 600; color: var(--gp-d-muted); }

.gp-commercial-workflow {
    padding: 18px 24px 22px;
    background: #fff;
}
.gp-commercial-workflow-head {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}
.gp-commercial-workflow-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(37, 99, 235, .08);
    border: 1px solid rgba(37, 99, 235, .15);
    color: #1d4ed8;
}
.gp-commercial-workflow-title { font-size: 14px; font-weight: 800; color: var(--gp-d-ink); }
.gp-commercial-workflow-sub { font-size: 12px; margin-top: 2px; }

.gp-commercial-stepper.gp-stepper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px 0;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid var(--gp-d-line);
    background: rgba(248,250,252,.5);
}
.gp-step {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    padding: 6px 8px;
}
.gp-step-dot {
    width: 30px;
    height: 30px;
    border-radius: 10px;
    border: 1px solid rgba(15,23,42,.12);
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    color: var(--gp-d-muted);
}
.gp-step-line { width: 24px; height: 2px; background: rgba(15,23,42,.1); }
.gp-step-label { font-size: 12px; font-weight: 700; color: var(--gp-d-muted); max-width: 100px; }
.gp-step.done .gp-step-dot {
    background: rgba(25, 135, 84, .12);
    border-color: rgba(25, 135, 84, .35);
    color: #0f5132;
}
.gp-step.active .gp-step-dot {
    background: rgba(37, 99, 235, .14);
    border-color: rgba(37, 99, 235, .45);
    color: #1e40af;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}
.gp-step.done .gp-step-line { background: rgba(25, 135, 84, .35); }
.gp-step.active .gp-step-line { background: rgba(37, 99, 235, .35); }

.gp-dt-card-title { font-size: 1rem; font-weight: 800; letter-spacing: -.01em; }
.gp-section-title { padding: 16px 20px; border-bottom: 1px solid rgba(0,0,0,.06); background: linear-gradient(180deg, #fff, rgba(248,250,252,.85)); }
.gp-section-title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.gp-section-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(37, 99, 235, .08);
    border: 1px solid rgba(37, 99, 235, .12);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: #1d4ed8;
}

.gp-dt-panel { background: linear-gradient(180deg, rgba(248,250,252,.92) 0%, #fff 14px); border-top: 1px solid rgba(15,23,42,.06); }
.gp-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; max-width: 100%; }
.gp-table-wrap .dataTables_wrapper { overflow: visible !important; }
.gp-table-wrap .dataTables_scrollBody { overflow-x: auto !important; overflow-y: visible !important; max-height: none !important; height: auto !important; }

.gp-commercial-detail .gp-dt-table.dataTable thead th {
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--gp-d-muted);
    border-bottom: 1px solid rgba(15,23,42,.1) !important;
    background: rgba(248,250,252,.98) !important;
    padding: 11px 12px !important;
}
.gp-commercial-detail .gp-dt-table.dataTable tbody td {
    padding: 10px 12px !important;
    vertical-align: middle;
    border-color: rgba(15,23,42,.06) !important;
    font-size: 13px;
}
.gp-commercial-detail .gp-dt-table.dataTable tbody tr:nth-child(even) td { background: rgba(248,250,252,.4); }
.gp-commercial-detail .gp-dt-table.dataTable tbody tr:hover td { background: rgba(59, 130, 246, .06) !important; }

.badge-soft-secondary { background: rgba(108,117,125,.15); color:#2f343a; border:1px solid rgba(108,117,125,.25); }
.badge-soft-primary { background: rgba(13,110,253,.15); color:#084298; border:1px solid rgba(13,110,253,.25); }
.badge-soft-success { background: rgba(25,135,84,.15); color:#0f5132; border:1px solid rgba(25,135,84,.25); }
.badge-soft-warning { background: rgba(255,193,7,.20); color:#664d03; border:1px solid rgba(255,193,7,.35); }
.badge-soft-danger { background: rgba(220,53,69,.15); color:#842029; border:1px solid rgba(220,53,69,.25); }
.badge-soft-info { background: rgba(13,202,240,.15); color:#055160; border:1px solid rgba(13,202,240,.25); }

@media (max-width: 576px) {
    .gp-detail-hero-top { padding: 18px 16px 14px; }
    .gp-detail-meta, .gp-detail-summary, .gp-commercial-workflow { padding-left: 16px; padding-right: 16px; }
    .gp-detail-hero-aside { width: 100%; align-items: stretch; }
    .gp-detail-badge-row-hero { justify-content: flex-start; }
    .gp-commercial-actions { flex-direction: column; }
    .gp-commercial-actions .btn { width: 100%; justify-content: center; }
}
</style>

<script>
$(document).ready(function () {
    var requestId = <?php echo (int)($request->id ?? 0); ?>;

    $("#gp-commercial-visitors-table").appTable({
        source: "<?php echo_uri('gate_pass_commercial_inbox/visitors_list_data/'); ?>" + requestId,
        scrollY: false,
        scrollCollapse: false,
        displayLength: 10,
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('full_name'); ?>", data: 0 },
            { title: "<?php echo app_lang('id_type'); ?>", data: 1 },
            { title: "<?php echo app_lang('id_number'); ?>", data: 2 },
            { title: "<?php echo app_lang('nationality'); ?>", data: 3 },
            { title: "<?php echo app_lang('phone'); ?>", data: 4 },
            { title: "<?php echo app_lang('role'); ?>", data: 5 },
            { title: "<?php echo app_lang('blocked'); ?>", data: 6, class: "text-center" },
            { title: "<?php echo app_lang('reason'); ?>", data: 7 },
            { title: "<?php echo app_lang('primary'); ?>", data: 8, class: "text-center" }
        ],
        order: [[0, "asc"]]
    });

    $("#gp-commercial-vehicles-table").appTable({
        source: "<?php echo_uri('gate_pass_commercial_inbox/vehicles_list_data/'); ?>" + requestId,
        scrollY: false,
        scrollCollapse: false,
        displayLength: 10,
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('plate_no'); ?>", data: 0 },
            { title: "<?php echo app_lang('gate_pass_mulkiyah_attachment'); ?>", data: 1, class: "text-center" }
        ],
        order: [[0, "asc"]]
    });

    setTimeout(function () {
        $(".gp-table-wrap .dataTables_scrollBody").css({ "overflow-y": "visible", "max-height": "none", "height": "auto" });
    }, 100);

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
