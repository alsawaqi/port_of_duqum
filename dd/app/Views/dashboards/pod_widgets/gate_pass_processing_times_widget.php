<?php
$days = (int)($stats["days"] ?? 30);
$stage_avg = $stats["stage_avg"] ?? [];
$dept_avg = $stats["department_avg"] ?? [];

$labels = [
    "department" => "Department",
    "commercial" => "Commercial",
    "security" => "Security",
    "rop" => "ROP",
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

$dept_chart_rows = array_slice($dept_avg, 0, 6);
$dept_chart_labels = [];
$dept_chart_data = [];
foreach ($dept_chart_rows as $r) {
    $dept_chart_labels[] = (string)($r["department_name"] ?? "-");
    $dept_chart_data[] = round(((int)($r["avg_sec"] ?? 0)) / 60, 2);
}

$widget_uid = "pod-gp-time-" . uniqid();
$stage_chart_id = $widget_uid . "-stage";
$dept_chart_id = $widget_uid . "-dept";
$chart_json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$stage_total = array_sum($stage_chart_data);
$dept_total = array_sum($dept_chart_data);

$stage_chart = [
    "type" => "bar",
    "data" => [
        "labels" => $stage_chart_labels,
        "datasets" => [[
            "label" => "Avg minutes",
            "data" => $stage_chart_data,
            "backgroundColor" => ["#465fff", "#f79009", "#12b76a", "#7a5af8"],
            "borderWidth" => 0,
            "barThickness" => 20,
            "maxBarThickness" => 24
        ]]
    ],
    "options" => [
        "legend" => ["display" => false],
        "scales" => [
            "xAxes" => [["gridLines" => ["display" => false], "ticks" => ["fontColor" => "#667085", "fontSize" => 11]]],
            "yAxes" => [["gridLines" => ["color" => "rgba(228, 231, 236, .8)", "drawBorder" => false], "ticks" => ["beginAtZero" => true, "fontColor" => "#667085", "fontSize" => 11]]]
        ]
    ]
];

$dept_chart = [
    "type" => "horizontalBar",
    "data" => [
        "labels" => $dept_chart_labels,
        "datasets" => [[
            "label" => "Avg minutes",
            "data" => $dept_chart_data,
            "backgroundColor" => "#06aed5",
            "borderWidth" => 0,
            "barThickness" => 14,
            "maxBarThickness" => 18
        ]]
    ],
    "options" => [
        "legend" => ["display" => false],
        "layout" => ["padding" => ["top" => 4, "right" => 8, "bottom" => 0, "left" => 0]],
        "scales" => [
            "xAxes" => [["gridLines" => ["color" => "rgba(228, 231, 236, .8)", "drawBorder" => false], "ticks" => ["beginAtZero" => true, "fontColor" => "#667085", "fontSize" => 11]]],
            "yAxes" => [["gridLines" => ["display" => false], "ticks" => ["fontColor" => "#667085", "fontSize" => 11]]]
        ]
    ]
];
?>

<div class="card bg-white pod-dashboard-card pod-processing-widget pod-gate-pass-widget">
    <div class="card-header pod-dashboard-card-header d-flex justify-content-between align-items-center">
        <div class="pod-dashboard-title">
            <span class="pod-dashboard-icon"><i data-feather="clock" class="icon-16"></i></span>
            <span>Gate Pass Processing Times</span>
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
                <span>Completed stage decisions</span>
            </div>
        </div>

        <div class="pod-dashboard-chart-grid">
            <div class="pod-chart-panel">
                <div class="pod-section-heading">Average by stage <span>minutes</span></div>
                <div class="pod-chart-wrap pod-chart-wrap-sm">
                    <canvas id="<?php echo esc($stage_chart_id); ?>" data-pod-chart-config="<?php echo esc($stage_chart_id); ?>-config"></canvas>
                    <?php if (!$stage_total) { ?><div class="pod-chart-empty">No stage timing yet</div><?php } ?>
                </div>
                <script type="application/json" id="<?php echo esc($stage_chart_id); ?>-config"><?php echo json_encode($stage_chart, $chart_json_flags); ?></script>
            </div>

            <div class="pod-chart-panel">
                <div class="pod-section-heading">Slowest departments <span>top <?php echo count($dept_chart_rows); ?></span></div>
                <div class="pod-chart-wrap pod-chart-wrap-sm">
                    <canvas id="<?php echo esc($dept_chart_id); ?>" data-pod-chart-config="<?php echo esc($dept_chart_id); ?>-config"></canvas>
                    <?php if (!$dept_total) { ?><div class="pod-chart-empty">No department timing yet</div><?php } ?>
                </div>
                <script type="application/json" id="<?php echo esc($dept_chart_id); ?>-config"><?php echo json_encode($dept_chart, $chart_json_flags); ?></script>
            </div>
        </div>

        <div class="pod-dashboard-table-grid">
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

            <div class="pod-dashboard-table-block">
                <div class="pod-section-heading">Average processing time (by department)</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th class="text-end">Avg time</th>
                                <th class="text-end">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($dept_avg)) { ?>
                            <?php foreach ($dept_avg as $r) { ?>
                                <tr>
                                    <td class="text-muted"><?php echo esc($r["department_name"] ?? "-"); ?></td>
                                    <td class="text-end fw-semibold"><?php echo esc($fmt($r["avg_sec"] ?? 0)); ?></td>
                                    <td class="text-end"><?php echo (int)($r["count"] ?? 0); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td class="text-muted">No data</td>
                                <td class="text-end fw-semibold">00:00:00</td>
                                <td class="text-end">0</td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
