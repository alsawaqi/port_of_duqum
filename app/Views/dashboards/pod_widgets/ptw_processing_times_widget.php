<?php
$days = (int)($stats["days"] ?? 30);
$stage_avg = $stats["stage_avg"] ?? [];

$labels = [
    "hsse" => "HSSE",
    "hmo" => "HMO",
    "terminal" => "Terminal",
];

$fmt = function ($sec) {
    $sec = (int)$sec;
    if ($sec <= 0) { return "00:00:00"; }
    return convert_seconds_to_time_format($sec);
};
?>

<div class="card bg-white">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><i data-feather="clock" class="icon-16"></i>&nbsp;PTW Processing Times</div>
        <small class="text-muted"><?php echo "Last " . $days . " days"; ?></small>
    </div>

    <div class="card-body">
        <div class="fw-semibold mb-2">Average processing time (by stage)</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr class="text-muted small">
                        <th>Stage</th>
                        <th class="text-end">Avg time</th>
                        <th class="text-end">Count</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($labels as $key => $label) {
                    $row = $stage_avg[$key] ?? ["avg_sec"=>0,"count"=>0];
                ?>
                    <tr>
                        <td class="text-muted"><?php echo esc($label); ?></td>
                        <td class="text-end fw-semibold"><?php echo esc($fmt($row["avg_sec"] ?? 0)); ?></td>
                        <td class="text-end"><?php echo (int)($row["count"] ?? 0); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>