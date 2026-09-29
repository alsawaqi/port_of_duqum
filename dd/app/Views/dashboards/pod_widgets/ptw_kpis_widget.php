<?php
$k = $kpis ?? [];
$status = $k["status"] ?? [];
$stage = $k["stage"] ?? [];

$in_progress = (int)($k["in_progress"] ?? 0);

$stage_labels = [
    "draft" => "Draft",
    "hsse" => "HSSE",
    "hmo" => "HMO",
    "terminal" => "Terminal",
    "completed" => "Completed"
];

$status_sorted = is_array($status) ? $status : [];
arsort($status_sorted);

$stage_chart_labels = [];
$stage_chart_data = [];
foreach ($stage_labels as $key => $label) {
    $stage_chart_labels[] = $label;
    $stage_chart_data[] = (int)($stage[$key] ?? 0);
}

$status_chart_labels = [];
$status_chart_data = [];
$status_table_rows = [];
$i = 0;
foreach ($status_sorted as $s => $cnt) {
    $label = ucwords(str_replace("_", " ", (string)$s));
    $status_chart_labels[] = $label;
    $status_chart_data[] = (int)$cnt;
    if ($i < 8) {
        $status_table_rows[] = ["label" => $label, "count" => (int)$cnt];
    }
    $i++;
}

$widget_uid = "pod-ptw-kpi-" . uniqid();
$stage_chart_id = $widget_uid . "-stage";
$status_chart_id = $widget_uid . "-status";
$chart_json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$colors = ["#12b76a", "#465fff", "#06aed5", "#f79009", "#7a5af8", "#667085", "#f04438", "#16b364"];
$stage_total = array_sum($stage_chart_data);
$status_total = array_sum($status_chart_data);

$stage_chart = [
    "type" => "bar",
    "data" => [
        "labels" => $stage_chart_labels,
        "datasets" => [[
            "label" => "Applications",
            "data" => $stage_chart_data,
            "backgroundColor" => ["#98a2b3", "#12b76a", "#465fff", "#06aed5", "#667085"],
            "borderWidth" => 0,
            "barThickness" => 18,
            "maxBarThickness" => 22
        ]]
    ],
    "options" => [
        "legend" => ["display" => false],
        "layout" => ["padding" => ["top" => 8, "right" => 8, "bottom" => 0, "left" => 0]],
        "scales" => [
            "xAxes" => [[
                "gridLines" => ["display" => false],
                "ticks" => ["fontColor" => "#667085", "fontSize" => 11]
            ]],
            "yAxes" => [[
                "gridLines" => ["color" => "rgba(228, 231, 236, .8)", "drawBorder" => false],
                "ticks" => ["beginAtZero" => true, "precision" => 0, "fontColor" => "#667085", "fontSize" => 11]
            ]]
        ]
    ]
];

$status_chart = [
    "type" => "doughnut",
    "data" => [
        "labels" => $status_chart_labels,
        "datasets" => [[
            "data" => $status_chart_data,
            "backgroundColor" => array_slice($colors, 0, max(1, count($status_chart_data))),
            "borderColor" => "#ffffff",
            "borderWidth" => 3
        ]]
    ],
    "options" => [
        "cutoutPercentage" => 70,
        "legend" => ["display" => false],
        "layout" => ["padding" => ["top" => 8, "right" => 8, "bottom" => 8, "left" => 8]]
    ]
];
?>

<div class="card bg-white pod-dashboard-card pod-kpi-widget pod-ptw-widget">
    <div class="card-header pod-dashboard-card-header d-flex justify-content-between align-items-center">
        <div class="pod-dashboard-title">
            <span class="pod-dashboard-icon"><i data-feather="clipboard" class="icon-16"></i></span>
            <span>PTW KPIs</span>
        </div>
        <span class="pod-dashboard-chip"><?php echo esc($scope_label ?? ""); ?></span>
    </div>

    <div class="card-body">
        <div class="pod-kpi-summary pod-kpi-summary-single">
            <div class="pod-kpi-metric">
                <div class="pod-kpi-label">In progress</div>
                <div class="pod-kpi-value"><?php echo $in_progress; ?></div>
                <div class="pod-kpi-hint">Permits waiting for review or revision</div>
            </div>
        </div>

        <div class="pod-dashboard-chart-grid">
            <div class="pod-chart-panel">
                <div class="pod-section-heading">Applications by stage <span><?php echo $stage_total; ?> total</span></div>
                <div class="pod-chart-wrap">
                    <canvas id="<?php echo esc($stage_chart_id); ?>" data-pod-chart-config="<?php echo esc($stage_chart_id); ?>-config"></canvas>
                    <?php if (!$stage_total) { ?><div class="pod-chart-empty">No stage activity yet</div><?php } ?>
                </div>
                <script type="application/json" id="<?php echo esc($stage_chart_id); ?>-config"><?php echo json_encode($stage_chart, $chart_json_flags); ?></script>
            </div>

            <div class="pod-chart-panel">
                <div class="pod-section-heading">Status mix <span><?php echo $status_total; ?> records</span></div>
                <div class="pod-chart-wrap pod-chart-wrap-sm">
                    <canvas id="<?php echo esc($status_chart_id); ?>" data-pod-chart-config="<?php echo esc($status_chart_id); ?>-config"></canvas>
                    <?php if (!$status_total) { ?><div class="pod-chart-empty">No status data</div><?php } ?>
                </div>
                <script type="application/json" id="<?php echo esc($status_chart_id); ?>-config"><?php echo json_encode($status_chart, $chart_json_flags); ?></script>
            </div>
        </div>

        <div class="pod-dashboard-table-grid">
            <div class="pod-dashboard-table-block">
                <div class="pod-section-heading">Stage detail</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php foreach ($stage_labels as $key => $label) { ?>
                            <tr>
                                <td class="text-muted"><?php echo esc($label); ?></td>
                                <td class="text-end fw-semibold"><?php echo (int)($stage[$key] ?? 0); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pod-dashboard-table-block">
                <div class="pod-section-heading">Status breakdown</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php if (!empty($status_table_rows)) { ?>
                            <?php foreach ($status_table_rows as $row) { ?>
                                <tr>
                                    <td class="text-muted"><?php echo esc($row["label"]); ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)$row["count"]; ?></td>
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
    </div>
</div>
