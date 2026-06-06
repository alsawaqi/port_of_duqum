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

$stage_chart_labels = [];
$stage_chart_data = [];
$stage_total_count = 0;
$slowest_stage = "-";
$slowest_sec = 0;
foreach ($labels as $key => $label) {
    $row = $stage_avg[$key] ?? ["avg_sec" => 0, "count" => 0];
    $sec = (int)($row["avg_sec"] ?? 0);
    $cnt = (int)($row["count"] ?? 0);
    $stage_chart_labels[] = $label;
    $stage_chart_data[] = round($sec / 60, 2);
    $stage_total_count += $cnt;
    if ($sec > $slowest_sec) {
        $slowest_sec = $sec;
        $slowest_stage = $label;
    }
}

$widget_uid = "pod-ptw-time-" . uniqid();
$stage_chart_id = $widget_uid . "-stage";
$chart_json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$stage_total = array_sum($stage_chart_data);

$stage_chart = [
    "type" => "bar",
    "data" => [
        "labels" => $stage_chart_labels,
        "datasets" => [[
            "label" => "Avg minutes",
            "data" => $stage_chart_data,
            "backgroundColor" => ["#12b76a", "#465fff", "#06aed5"],
            "borderWidth" => 0,
            "barThickness" => 24,
            "maxBarThickness" => 30
        ]]
    ],
    "options" => [
        "legend" => ["display" => false],
        "layout" => ["padding" => ["top" => 8, "right" => 8, "bottom" => 0, "left" => 0]],
        "scales" => [
            "xAxes" => [["gridLines" => ["display" => false], "ticks" => ["fontColor" => "#667085", "fontSize" => 11]]],
            "yAxes" => [["gridLines" => ["color" => "rgba(228, 231, 236, .8)", "drawBorder" => false], "ticks" => ["beginAtZero" => true, "fontColor" => "#667085", "fontSize" => 11]]]
        ]
    ]
];
?>

<div class="card bg-white pod-dashboard-card pod-processing-widget pod-ptw-widget">
    <div class="card-header pod-dashboard-card-header d-flex justify-content-between align-items-center">
        <div class="pod-dashboard-title">
            <span class="pod-dashboard-icon"><i data-feather="clock" class="icon-16"></i></span>
            <span>PTW Processing Times</span>
        </div>
        <span class="pod-dashboard-chip"><?php echo "Last " . $days . " days"; ?></span>
    </div>

    <div class="card-body">
        <div class="pod-dashboard-meta-grid">
            <div class="pod-dashboard-meta">
                <strong><?php echo esc($fmt($slowest_sec)); ?></strong>
                <span>Slowest average: <?php echo esc($slowest_stage); ?></span>
            </div>
            <div class="pod-dashboard-meta">
                <strong><?php echo (int)$stage_total_count; ?></strong>
                <span>Completed reviews</span>
            </div>
        </div>

        <div class="pod-dashboard-chart-grid pod-dashboard-chart-grid-single">
            <div class="pod-chart-panel">
                <div class="pod-section-heading">Average processing time by stage <span>minutes</span></div>
                <div class="pod-chart-wrap pod-chart-wrap-sm">
                    <canvas id="<?php echo esc($stage_chart_id); ?>" data-pod-chart-config="<?php echo esc($stage_chart_id); ?>-config"></canvas>
                    <?php if (!$stage_total) { ?><div class="pod-chart-empty">No processing-time data yet</div><?php } ?>
                </div>
                <script type="application/json" id="<?php echo esc($stage_chart_id); ?>-config"><?php echo json_encode($stage_chart, $chart_json_flags); ?></script>
            </div>
        </div>

        <div class="pod-dashboard-table-block">
            <div class="pod-section-heading">Average processing time (by stage)</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th class="text-end">Avg time</th>
                            <th class="text-end">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($labels as $key => $label) {
                        $row = $stage_avg[$key] ?? ["avg_sec" => 0, "count" => 0];
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
</div>
