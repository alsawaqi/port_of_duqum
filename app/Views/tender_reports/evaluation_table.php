<?php
$rows = $rows ?? [];
$status_badge = $status_badge ?? function ($status) {
    return esc($status ?: "-");
};
$date_value = $date_value ?? function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
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
                <th>Submitted At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows) { ?>
                <tr>
                    <td colspan="7" class="text-center text-off p20">No evaluation records found.</td>
                </tr>
            <?php } ?>

            <?php foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo esc($row->vendor_name ?? "-"); ?></td>
                    <td><?php echo esc(trim((string) ($row->evaluator_name ?? "")) ?: "-"); ?></td>
                    <td><?php echo $status_badge($row->decision ?? $row->status ?? "-"); ?></td>
                    <td><?php echo number_format((float) ($row->total_score ?? 0), 3); ?></td>
                    <td><?php echo esc($row->score_breakdown ?? "-"); ?></td>
                    <td><?php echo nl2br(esc($row->comments ?? "-")); ?></td>
                    <td><?php echo $date_value($row->submitted_at ?? null); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
