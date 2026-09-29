<?php
$reviews = $reviews ?? [];
$stage_label = $stage_label ?? "";

$safeDate = static function ($value): string {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$reviewerName = static function ($row): string {
    $name = trim((string)(($row->first_name ?? "") . " " . ($row->last_name ?? "")));
    return $name !== "" ? $name : (string)($row->email ?? "-");
};

$decisionClass = static function ($decision): string {
    $decision = strtolower(trim((string)$decision));
    if ($decision === "approved") return "is-approved";
    if ($decision === "rejected") return "is-rejected";
    if ($decision === "revise") return "is-revise";
    return "is-pending";
};
?>

<style>
.ptw-history-wrap { color: #1f2937; }
.ptw-history-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.ptw-history-kpi {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 12px;
}
.ptw-history-kpi span {
    display: block;
    color: #64748b;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .03em;
}
.ptw-history-kpi strong {
    display: block;
    color: #111827;
    font-size: 15px;
    margin-top: 4px;
}
.ptw-history-timeline { position: relative; padding-left: 18px; }
.ptw-history-timeline::before {
    content: "";
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 5px;
    width: 2px;
    background: #dbeafe;
}
.ptw-history-item {
    position: relative;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    padding: 12px 14px;
    margin-bottom: 12px;
}
.ptw-history-item::before {
    content: "";
    position: absolute;
    left: -18px;
    top: 17px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #2563eb;
    border: 3px solid #eff6ff;
}
.ptw-history-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}
.ptw-history-title strong { color: #111827; font-size: 14px; }
.ptw-history-meta { display: flex; flex-wrap: wrap; gap: 8px 14px; color: #64748b; font-size: 12px; }
.ptw-history-pill {
    display: inline-flex;
    align-items: center;
    min-height: 24px;
    padding: 3px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid transparent;
}
.ptw-history-pill.is-approved { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.ptw-history-pill.is-revise { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.ptw-history-pill.is-rejected { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
.ptw-history-pill.is-pending { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.ptw-history-note { border-left: 3px solid #2563eb; background: #f8fafc; border-radius: 6px; padding: 9px 10px; margin-top: 8px; }
.ptw-history-note strong { display: block; color: #334155; font-size: 12px; margin-bottom: 3px; }
.ptw-history-empty { color: #94a3b8; text-align: center; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 8px; }
@media (max-width: 640px) {
    .ptw-history-summary { grid-template-columns: 1fr; }
    .ptw-history-title { display: block; }
    .ptw-history-title .ptw-history-pill { margin-top: 8px; }
}
</style>

<div class="ptw-history-wrap">
    <?php
    $review_count = count($reviews);
    $revision_count = 0;
    $total_seconds = 0;
    foreach ($reviews as $review) {
        if (strtolower((string)($review->decision ?? "")) === "revise") {
            $revision_count++;
        }
        if (!empty($review->received_at) && !empty($review->completed_at)) {
            $start = strtotime((string)$review->received_at);
            $end = strtotime((string)$review->completed_at);
            if ($start && $end && $end >= $start) {
                $total_seconds += ($end - $start);
            }
        }
    }
    ?>
    <div class="ptw-history-summary">
        <div class="ptw-history-kpi"><span>Stage</span><strong><?php echo esc($stage_label !== "" ? $stage_label : "-"); ?></strong></div>
        <div class="ptw-history-kpi"><span>Review actions</span><strong><?php echo (int)$review_count; ?></strong></div>
        <div class="ptw-history-kpi"><span>Revisions requested</span><strong><?php echo (int)$revision_count; ?></strong></div>
        <div class="ptw-history-kpi"><span>Total completed time</span><strong><?php echo esc(ptw_duration_from_seconds($total_seconds)); ?></strong></div>
    </div>

    <?php if ($reviews): ?>
        <div class="ptw-history-timeline">
            <?php foreach ($reviews as $review): ?>
                <?php $decision = strtolower((string)($review->decision ?? "")); ?>
                <div class="ptw-history-item">
                    <div class="ptw-history-title">
                        <strong>Revision <?php echo (int)($review->revision_no ?? 0); ?></strong>
                        <span class="ptw-history-pill <?php echo $decisionClass($decision); ?>"><?php echo esc(ptw_decision_display_label($decision)); ?></span>
                    </div>
                    <div class="ptw-history-meta">
                        <span><i data-feather="user" class="icon-13"></i> <?php echo esc($reviewerName($review)); ?></span>
                        <span><i data-feather="clock" class="icon-13"></i> <?php echo esc(ptw_duration_between($review->received_at ?? null, $review->completed_at ?? null)); ?></span>
                        <span>Received: <?php echo esc($safeDate($review->received_at ?? null)); ?></span>
                        <span>Completed: <?php echo esc($safeDate($review->completed_at ?? null)); ?></span>
                    </div>
                    <?php if (!empty($review->status_change_reason)): ?>
                        <div class="ptw-history-note"><strong>Reason</strong><?php echo nl2br(esc((string)$review->status_change_reason)); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($review->remarks)): ?>
                        <div class="ptw-history-note"><strong>Comments</strong><?php echo nl2br(esc((string)$review->remarks)); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="ptw-history-empty"><?php echo app_lang("no_records_found"); ?></div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function () {
    if (window.feather) feather.replace();
});
</script>
