<?php
$days = (int)($stats["days"] ?? 90);
$rows = $stats["rows"] ?? [];
?>

<div class="card bg-white">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i data-feather="x-circle" class="icon-16"></i>&nbsp;Gate Pass Top Rejection Reasons</div>
        <small class="text-muted"><?php echo "Last " . $days . " days"; ?></small>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Reason</th>
                        <th class="text-end">Count</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)) { ?>
                    <?php foreach ($rows as $r) { ?>
                        <tr>
                            <td class="text-muted"><?php echo esc($r["reason"] ?? "-"); ?></td>
                            <td class="text-end fw-semibold"><?php echo (int)($r["count"] ?? 0); ?></td>
                        </tr>
                    <?php } ?>
                <?php } else { ?>
                    <tr>
                        <td class="text-muted">No data</td>
                        <td class="text-end fw-semibold">0</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>