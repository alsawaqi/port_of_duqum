<?php
$app = $app ?? ($application ?? null);
$definitions_grouped = $definitions_grouped ?? [];
$responses_by_definition = $responses_by_definition ?? ($responses_index ?? []);
$attachments_by_response = $attachments_by_response ?? [];
$review_groups = $review_groups ?? [
    "hsse" => $hsse_reviews ?? [],
    "hmo" => $hmo_reviews ?? [],
    "terminal" => $terminal_reviews ?? [],
];
$audit_logs = $audit_logs ?? [];
$actions_html = $actions_html ?? "";
$back_html = $back_html ?? "";
$page_title = $page_title ?? "PTW Details";
$current_stage = strtolower((string)($current_stage ?? ($app->stage ?? "")));
$show_audit_log = $show_audit_log ?? true;
$terminal_required = ptw_terminal_approval_is_required($app);

$safeDate = static function ($value): string {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$reviewerName = static function ($row): string {
    $name = trim((string)(($row->first_name ?? "") . " " . ($row->last_name ?? "")));
    return $name !== "" ? $name : (string)($row->email ?? "-");
};

$statusClass = static function ($status): string {
    $status = strtolower(trim((string)$status));
    $map = [
        "approved" => "is-approved",
        "submitted" => "is-submitted",
        "rejected" => "is-rejected",
        "revise" => "is-revise",
        "draft" => "is-draft",
    ];
    return $map[$status] ?? "is-neutral";
};

$decisionClass = static function ($decision): string {
    $decision = strtolower(trim((string)$decision));
    if ($decision === "approved") return "is-approved";
    if ($decision === "rejected") return "is-rejected";
    if ($decision === "revise") return "is-revise";
    return "is-pending";
};

$renderAttachment = static function ($resp, $att = null): string {
    if ($att && !empty($att->id)) {
        return anchor(
            get_uri("ptw_portal/download_attachment/" . (int)$att->id),
            "<i data-feather='paperclip' class='icon-13'></i> " . esc((string)($att->file_name ?: "Download")),
            ["class" => "ptw-link"]
        );
    }

    if (!empty($resp->attachment_path ?? "") && !empty($resp->id ?? 0)) {
        return anchor(
            get_uri("ptw_portal/download_attachment/" . (int)$resp->id),
            "<i data-feather='paperclip' class='icon-13'></i> Download",
            ["class" => "ptw-link"]
        );
    }

    return "<span class='ptw-muted'>-</span>";
};

$reviewCount = 0;
$revisionCount = 0;
$totalReviewSeconds = 0;
foreach ($review_groups as $rows) {
    foreach (($rows ?? []) as $row) {
        $reviewCount++;
        if (strtolower((string)($row->decision ?? "")) === "revise") {
            $revisionCount++;
        }
        if (!empty($row->received_at) && !empty($row->completed_at)) {
            $start = strtotime((string)$row->received_at);
            $end = strtotime((string)$row->completed_at);
            if ($start && $end && $end >= $start) {
                $totalReviewSeconds += ($end - $start);
            }
        }
    }
}
$totalReviewDuration = ptw_duration_from_seconds($totalReviewSeconds);

$requirement_labels = [
    "hazard_document" => "Hazards & Attachments",
    "ppe" => "Proposed PPE",
    "preparation" => "Work Area Preparations",
];
?>

<style>
.ptw-detail-shell { color: #1f2937; }
.ptw-detail-hero {
    background: linear-gradient(135deg, #0f766e 0%, #1d4ed8 55%, #4338ca 100%);
    border-radius: 8px;
    color: #fff;
    padding: 22px 24px;
    margin-bottom: 16px;
    box-shadow: 0 14px 32px rgba(15, 23, 42, .16);
}
.ptw-detail-hero h1 { margin: 0; font-size: 22px; font-weight: 700; color: #fff; letter-spacing: 0; }
.ptw-detail-ref { margin-top: 4px; color: rgba(255,255,255,.82); font-size: 13px; }
.ptw-detail-actions { display: flex; gap: 8px; flex-wrap: wrap; justify-content: flex-end; }
.ptw-kpi-row { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.ptw-kpi {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 14px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, .05);
}
.ptw-kpi span { display: block; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: .04em; }
.ptw-kpi strong { display: block; margin-top: 6px; font-size: 17px; color: #111827; font-weight: 700; }
.ptw-section {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 16px;
    box-shadow: 0 3px 10px rgba(15, 23, 42, .04);
    overflow: hidden;
}
.ptw-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid #e5e7eb;
    background: #f8fafc;
}
.ptw-section-head h3 { margin: 0; font-size: 15px; color: #0f172a; font-weight: 700; }
.ptw-section-body { padding: 16px; }
.ptw-section-body.p0 { padding: 0; }
.ptw-info-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; }
.ptw-info-item { border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
.ptw-info-item label { display: block; margin: 0 0 3px; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: .03em; }
.ptw-info-item div { color: #111827; font-weight: 600; line-height: 1.45; }
.ptw-description { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 13px; line-height: 1.65; }
.ptw-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 24px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid transparent;
}
.ptw-pill.is-approved { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.ptw-pill.is-submitted, .ptw-pill.is-pending { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.ptw-pill.is-revise { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.ptw-pill.is-rejected { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.ptw-pill.is-draft, .ptw-pill.is-neutral { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
.ptw-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ptw-table th {
    background: #f8fafc;
    color: #475569;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    border-bottom: 1px solid #e5e7eb;
    padding: 10px 12px;
}
.ptw-table td { border-bottom: 1px solid #edf2f7; padding: 11px 12px; vertical-align: top; }
.ptw-table tr:last-child td { border-bottom: 0; }
.ptw-link { color: #1d4ed8; font-weight: 600; }
.ptw-muted { color: #94a3b8; }
.ptw-req-title { font-weight: 700; color: #111827; }
.ptw-timeline { position: relative; padding-left: 18px; }
.ptw-timeline::before { content: ""; position: absolute; top: 4px; bottom: 4px; left: 5px; width: 2px; background: #dbeafe; }
.ptw-timeline-item { position: relative; margin-bottom: 12px; padding: 13px 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
.ptw-timeline-item::before { content: ""; position: absolute; left: -18px; top: 18px; width: 12px; height: 12px; border-radius: 50%; background: #2563eb; border: 3px solid #eff6ff; }
.ptw-timeline-title { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 7px; }
.ptw-timeline-title strong { color: #111827; font-size: 14px; }
.ptw-timeline-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: #64748b; font-size: 12px; margin-bottom: 8px; }
.ptw-note-box { border-left: 3px solid #2563eb; background: #f8fafc; border-radius: 6px; padding: 9px 10px; margin-top: 7px; }
.ptw-note-box strong { display: block; color: #334155; font-size: 12px; margin-bottom: 3px; }
.ptw-meta-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.ptw-meta-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 7px; padding: 8px 10px; }
.ptw-meta-item label { display: block; color: #64748b; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 2px; }
.ptw-meta-item div { color: #111827; font-weight: 600; white-space: pre-wrap; word-break: break-word; }
.ptw-stage-strip { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.ptw-stage-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; }
.ptw-stage-card.is-current { border-color: #93c5fd; background: #eff6ff; }
.ptw-stage-card h4 { margin: 0 0 6px; color: #0f172a; font-size: 14px; font-weight: 700; }
.ptw-stage-card p { margin: 0; color: #64748b; font-size: 12px; }
@media (max-width: 900px) {
    .ptw-kpi-row, .ptw-stage-strip, .ptw-meta-list { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 640px) {
    .ptw-detail-hero .row { display: block; }
    .ptw-detail-actions { justify-content: flex-start; margin-top: 12px; }
    .ptw-kpi-row, .ptw-info-grid, .ptw-stage-strip, .ptw-meta-list { grid-template-columns: 1fr; }
}
</style>

<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-ptw-page ptw-detail-shell">
    <?php echo view("includes/ptw_page_header", [
        "title" => $page_title,
        "subtitle" => "Reference " . (string)($app->reference ?? "-") . " with current stage, review history, and submitted safety details.",
        "icon" => "file-text",
        "breadcrumbs" => [
            ["label" => "PTW"],
            ["label" => "Details"]
        ],
        "actions" => trim($back_html . " " . $actions_html)
    ]); ?>

    <div class="ptw-detail-hero">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h1>Permit snapshot</h1>
                <div class="ptw-detail-ref"><?php echo esc((string)($app->reference ?? "-")); ?></div>
            </div>
            <div class="col-md-5">
                <div class="ptw-detail-actions">
                    <span class="ptw-pill <?php echo $statusClass($app->status ?? ""); ?>"><?php echo esc(ptw_status_display_label($app->status ?? "")); ?></span>
                    <span class="ptw-pill is-submitted"><?php echo esc(ptw_stage_display_label($app->stage ?? "")); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="ptw-kpi-row">
        <div class="ptw-kpi"><span>Status</span><strong><span class="ptw-pill <?php echo $statusClass($app->status ?? ""); ?>"><?php echo esc(ptw_status_display_label($app->status ?? "")); ?></span></strong></div>
        <div class="ptw-kpi"><span>Stage</span><strong><?php echo esc(ptw_stage_display_label($app->stage ?? "")); ?></strong></div>
        <div class="ptw-kpi"><span>Terminal Approval</span><strong><?php echo $terminal_required ? "Required" : "Not required"; ?></strong></div>
        <div class="ptw-kpi"><span>Review Time</span><strong><?php echo esc($totalReviewDuration); ?></strong></div>
    </div>

    <div class="ptw-section">
        <div class="ptw-section-head">
            <h3>Application Summary</h3>
            <?php if (!empty($app->signature_file_path ?? "")): ?>
                <?php echo anchor(get_uri("ptw_portal/download_signature/" . (int)$app->id), "<i data-feather='download' class='icon-13'></i> Signature", ["class" => "ptw-link"]); ?>
            <?php endif; ?>
        </div>
        <div class="ptw-section-body">
            <div class="ptw-info-grid">
                <div class="ptw-info-item"><label>Company</label><div><?php echo esc((string)($app->company_name ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Applicant</label><div><?php echo esc((string)($app->applicant_name ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Position</label><div><?php echo esc((string)($app->applicant_position ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Contact</label><div><?php echo esc((string)($app->contact_phone ?? "-")); ?> / <?php echo esc((string)($app->contact_email ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Supervisor</label><div><?php echo esc((string)($app->work_supervisor_name ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Supervisor Contact</label><div><?php echo esc((string)($app->supervisor_contact_details ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Workers</label><div><?php echo esc((string)($app->total_workers ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Work Window</label><div><?php echo esc($safeDate($app->work_from ?? null)); ?> to <?php echo esc($safeDate($app->work_to ?? null)); ?></div></div>
                <div class="ptw-info-item"><label>Location</label><div><?php echo esc((string)($app->exact_location ?? "-")); ?></div></div>
                <div class="ptw-info-item"><label>Map / Sector</label><div><?php echo esc((string)($app->location_sector_name ?? "-")); ?> <?php echo !empty($app->location_description) ? " - " . esc((string)$app->location_description) : ""; ?></div></div>
            </div>
            <div class="mt15">
                <label class="d-block mb5" style="color:#64748b; font-size:11px; text-transform:uppercase; font-weight:700;">Work Description</label>
                <div class="ptw-description"><?php echo nl2br(esc((string)($app->work_description ?? "-"))); ?></div>
            </div>
        </div>
    </div>

    <div class="ptw-section">
        <div class="ptw-section-head"><h3>Workflow Timeline</h3></div>
        <div class="ptw-section-body">
            <div class="ptw-stage-strip">
                <?php foreach (["hsse" => "HSSE", "hmo" => "HMO", "terminal" => "Terminal"] as $stage => $label): ?>
                    <?php $rows = $review_groups[$stage] ?? []; $latest = $rows ? end($rows) : null; ?>
                    <div class="ptw-stage-card <?php echo $current_stage === $stage ? "is-current" : ""; ?>">
                        <h4><?php echo esc($label); ?></h4>
                        <p>
                            <?php if ($stage === "terminal" && !$terminal_required && !$latest): ?>
                                Terminal approval not required
                            <?php else: ?>
                                <?php echo $latest ? esc(ptw_decision_display_label($latest->decision ?? "")) . " / Revision " . (int)($latest->revision_no ?? 0) : "No activity yet"; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="ptw-timeline mt15">
                <?php $hasReviews = false; ?>
                <?php foreach (["hsse" => "HSSE", "hmo" => "HMO", "terminal" => "Terminal"] as $stage => $label): ?>
                    <?php foreach (($review_groups[$stage] ?? []) as $review): $hasReviews = true; ?>
                        <?php
                        $decision = strtolower((string)($review->decision ?? ""));
                        $duration = ptw_duration_between($review->received_at ?? null, $review->completed_at ?? null);
                        ?>
                        <div class="ptw-timeline-item">
                            <div class="ptw-timeline-title">
                                <strong><?php echo esc($label); ?> - Revision <?php echo (int)($review->revision_no ?? 0); ?></strong>
                                <span class="ptw-pill <?php echo $decisionClass($decision); ?>"><?php echo esc(ptw_decision_display_label($decision)); ?></span>
                            </div>
                            <div class="ptw-timeline-meta">
                                <span><i data-feather="user" class="icon-13"></i> <?php echo esc($reviewerName($review)); ?></span>
                                <span><i data-feather="clock" class="icon-13"></i> <?php echo esc($duration); ?></span>
                                <span>Received: <?php echo esc($safeDate($review->received_at ?? null)); ?></span>
                                <span>Completed: <?php echo esc($safeDate($review->completed_at ?? null)); ?></span>
                            </div>
                            <?php if (!empty($review->status_change_reason)): ?>
                                <div class="ptw-note-box"><strong>Reason</strong><?php echo nl2br(esc((string)$review->status_change_reason)); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($review->remarks)): ?>
                                <div class="ptw-note-box"><strong>Comments</strong><?php echo nl2br(esc((string)$review->remarks)); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (!$hasReviews): ?>
                    <div class="ptw-muted">No review activity has been recorded yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="ptw-section">
        <div class="ptw-section-head"><h3>Requirements & Attachments</h3></div>
        <div class="ptw-section-body p0">
            <?php foreach ($requirement_labels as $category => $label): ?>
                <div class="table-responsive">
                    <table class="ptw-table">
                        <thead>
                            <tr><th colspan="4"><?php echo esc($label); ?></th></tr>
                            <tr>
                                <th>Item</th>
                                <th width="110" class="text-center">Checked</th>
                                <th>Details</th>
                                <th width="250">Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rows = $definitions_grouped[$category] ?? []; ?>
                            <?php if ($rows): ?>
                                <?php foreach ($rows as $def): ?>
                                    <?php
                                    $resp = $responses_by_definition[(int)$def->id] ?? null;
                                    $att = ($resp && !empty($resp->id)) ? ($attachments_by_response[(int)$resp->id] ?? null) : null;
                                    ?>
                                    <?php if (ptw_is_other_requirement_definition($def)): ?>
                                        <?php $other_items = ptw_decode_other_requirement_items($resp); ?>
                                        <?php if (!$other_items): ?>
                                            <tr><td class="ptw-req-title"><?php echo esc((string)($def->label ?? "Other")); ?></td><td class="text-center"><span class="ptw-pill is-neutral">No</span></td><td>-</td><td>-</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($other_items as $item): ?>
                                                <tr>
                                                    <td class="ptw-req-title"><?php echo esc((string)($def->label ?? "Other")); ?></td>
                                                    <td class="text-center"><span class="ptw-pill is-approved">Yes</span></td>
                                                    <td><?php echo esc((string)($item["label"] ?? "")); ?></td>
                                                    <td>
                                                        <?php if (!empty($item["attachment_id"] ?? 0)): ?>
                                                            <?php echo anchor(get_uri("ptw_portal/download_attachment/" . (int)$item["attachment_id"]), "<i data-feather='paperclip' class='icon-13'></i> " . esc((string)($item["attachment_name"] ?: "Download")), ["class" => "ptw-link"]); ?>
                                                        <?php elseif (!empty($item["attachment_path"] ?? "") && !empty($resp->id ?? 0)): ?>
                                                            <?php echo anchor(get_uri("ptw_portal/download_attachment/" . (int)$resp->id), "<i data-feather='paperclip' class='icon-13'></i> " . esc((string)($item["attachment_name"] ?: "Download")), ["class" => "ptw-link"]); ?>
                                                        <?php else: ?>
                                                            <span class="ptw-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <tr>
                                        <td class="ptw-req-title"><?php echo esc((string)($def->label ?? "-")); ?></td>
                                        <td class="text-center"><span class="ptw-pill <?php echo (!empty($resp) && (int)($resp->is_checked ?? 0) === 1) ? "is-approved" : "is-neutral"; ?>"><?php echo (!empty($resp) && (int)($resp->is_checked ?? 0) === 1) ? "Yes" : "No"; ?></span></td>
                                        <td><?php echo nl2br(esc((string)($resp->value_text ?? "-"))); ?></td>
                                        <td><?php echo $renderAttachment($resp, $att); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center ptw-muted">No records found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($show_audit_log): ?>
        <div class="ptw-section">
            <div class="ptw-section-head"><h3>Readable Audit Log</h3></div>
            <div class="ptw-section-body">
                <div class="ptw-timeline">
                    <?php if ($audit_logs): ?>
                        <?php foreach ($audit_logs as $log): ?>
                            <div class="ptw-timeline-item">
                                <div class="ptw-timeline-title">
                                    <strong><?php echo esc(ptw_audit_action_display_label($log->action ?? "")); ?></strong>
                                    <span class="ptw-muted"><?php echo esc($safeDate($log->created_at ?? null)); ?></span>
                                </div>
                                <div class="ptw-timeline-meta">
                                    <span><i data-feather="user" class="icon-13"></i> <?php echo esc(trim((string)($log->user_name ?? "")) ?: ("User #" . (int)($log->user_id ?? 0))); ?></span>
                                </div>
                                <?php $meta_items = ptw_readable_audit_meta_items($log->meta ?? ""); ?>
                                <?php if ($meta_items): ?>
                                    <div class="ptw-meta-list">
                                        <?php foreach ($meta_items as $item): ?>
                                            <div class="ptw-meta-item"><label><?php echo esc($item["label"]); ?></label><div><?php echo esc($item["value"]); ?></div></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="ptw-muted">No audit activity has been recorded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function () {
    if (window.feather) feather.replace();
});
</script>
