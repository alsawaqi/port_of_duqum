<?php
$rows = $rows ?? [];
$evaluation_attachments = $evaluation_attachments ?? [];
$attachments_label = $attachments_label ?? "Technical Findings";
$status_badge = $status_badge ?? function ($status) {
    return esc($status ?: "-");
};
$date_value = $date_value ?? function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};
$duration_value = $duration_value ?? function ($seconds) {
    if ($seconds === null || $seconds === "" || !is_numeric($seconds)) {
        return "-";
    }

    $seconds = max(0, (int) $seconds);
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);
    $parts = [];

    if ($days > 0) {
        $parts[] = $days . "d";
    }
    if ($hours > 0) {
        $parts[] = $hours . "h";
    }
    $parts[] = $minutes . "m";

    return implode(" ", $parts);
};
?>

<div class="table-responsive mb20">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Vendor</th>
                <th>Evaluator</th>
                <th>Decision</th>
                <th>Total Score</th>
                <th>Score Breakdown</th>
                <th>Comments</th>
                <th><?php echo esc($attachments_label); ?></th>
                <th>Review Duration</th>
                <th>Late Submission</th>
                <th>Submitted At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) { ?>
                <tr>
                    <td colspan="10" class="text-center text-off p20">No evaluation records found.</td>
                </tr>
            <?php } ?>

            <?php foreach ($rows as $row) {
                $attachments = $evaluation_attachments[(int) ($row->id ?? 0)] ?? [];
                $is_late = (int) ($row->submitted_after_deadline ?? 0) === 1;
                $late_status = (string) ($row->late_review_status ?? "");
            ?>
                <tr>
                    <td><?php echo esc($row->vendor_name ?? "-"); ?></td>
                    <td><?php echo esc(trim((string) ($row->evaluator_name ?? "")) ?: "-"); ?></td>
                    <td><?php echo $status_badge($row->decision ?? $row->status ?? "-"); ?></td>
                    <td><?php echo number_format((float) ($row->total_score ?? 0), 3); ?></td>
                    <td><?php echo esc($row->score_breakdown ?? "-"); ?></td>
                    <td><?php echo nl2br(esc($row->comments ?? "-")); ?></td>
                    <td>
                        <?php if ($attachments) { ?>
                            <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($attachments as $attachment) { ?>
                                    <a href="<?php echo get_uri("tender_reports/download_evaluation_attachment/" . (int) $attachment->id); ?>" class="btn btn-default btn-sm">
                                        <i data-feather="paperclip" class="icon-14"></i>
                                        <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <span class="text-off">-</span>
                        <?php } ?>
                    </td>
                    <td><?php echo esc($duration_value($row->review_duration_seconds ?? null)); ?></td>
                    <td>
                        <?php if ($is_late) { ?>
                            <div class="mb5">
                                <span class="badge <?php echo $late_status === 'accepted' ? 'bg-success' : ($late_status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                    Late Submission: <?php echo esc(ucwords(str_replace("_", " ", $late_status ?: "pending"))); ?>
                                </span>
                            </div>
                            <?php if ($late_status === "" || $late_status === "pending") { ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <button type="button"
                                        class="btn btn-success btn-sm late-evaluation-review-action"
                                        data-action-url="<?php echo get_uri("tender_reports/approve_late_evaluation"); ?>"
                                        data-evaluation-id="<?php echo (int) ($row->id ?? 0); ?>">
                                        Approve Late
                                    </button>
                                    <button type="button"
                                        class="btn btn-danger btn-sm late-evaluation-review-action"
                                        data-action-url="<?php echo get_uri("tender_reports/reject_late_evaluation"); ?>"
                                        data-evaluation-id="<?php echo (int) ($row->id ?? 0); ?>">
                                        Reject Late
                                    </button>
                                </div>
                            <?php } ?>
                            <?php if (!empty($row->deadline_at)) { ?>
                                <div class="text-off mt5">Deadline: <?php echo format_to_datetime($row->deadline_at); ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <span class="badge bg-light text-dark">On time</span>
                        <?php } ?>
                    </td>
                    <td><?php echo $date_value($row->submitted_at ?? null); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script>
$(document).off("click", ".late-evaluation-review-action").on("click", ".late-evaluation-review-action", function () {
    var $button = $(this);
    var isReject = ($button.data("action-url") || "").indexOf("reject_late_evaluation") !== -1;
    if (!confirm((isReject ? "Reject" : "Approve") + " this late evaluation?")) {
        return;
    }

    appLoader.show();
    $.post($button.data("action-url"), {evaluation_id: $button.data("evaluation-id")}, function (res) {
        appLoader.hide();
        if (res && res.success) {
            appAlert.success(res.message || "Done", {duration: 3000});
            window.location.reload();
        } else {
            appAlert.error((res && res.message) || "Request failed.", {duration: 3000});
        }
    }, "json").fail(function () {
        appLoader.hide();
        appAlert.error("Request failed. Please try again.", {duration: 3000});
    });
});
</script>
