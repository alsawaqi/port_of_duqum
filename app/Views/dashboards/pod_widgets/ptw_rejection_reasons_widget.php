<?php
$days = (int)($stats["days"] ?? 90);
$rows = $stats["rows"] ?? [];

$chart_labels = [];
$chart_data = [];
$total_rejections = 0;
$top_reason = "-";
foreach ($rows as $index => $r) {
    $reason = (string)($r["reason"] ?? "-");
    $count = (int)($r["count"] ?? 0);
    $chart_labels[] = $reason;
    $chart_data[] = $count;
    $total_rejections += $count;
    if ($index === 0) {
        $top_reason = $reason;
    }
}

$widget_uid = "pod-ptw-reject-" . uniqid();
$chart_id = $widget_uid . "-chart";
$chart_json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$chart_total = array_sum($chart_data);

$chart = [
    "type" => "horizontalBar",
    "data" => [
        "labels" => $chart_labels,
        "datasets" => [[
            "label" => "Rejections",
            "data" => $chart_data,
            "backgroundColor" => ["#f04438", "#f79009", "#465fff", "#06aed5", "#12b76a"],
            "borderWidth" => 0,
            "barThickness" => 14,
            "maxBarThickness" => 18
        ]]
    ],
    "options" => [
        "legend" => ["display" => false],
        "layout" => ["padding" => ["top" => 4, "right" => 8, "bottom" => 0, "left" => 0]],
        "scales" => [
            "xAxes" => [["gridLines" => ["color" => "rgba(228, 231, 236, .8)", "drawBorder" => false], "ticks" => ["beginAtZero" => true, "precision" => 0, "fontColor" => "#667085", "fontSize" => 11]]],
            "yAxes" => [["gridLines" => ["display" => false], "ticks" => ["fontColor" => "#667085", "fontSize" => 11]]]
        ]
    ]
];
?>

<div class="card bg-white pod-dashboard-card pod-rejection-widget pod-ptw-widget">
    <div class="card-header pod-dashboard-card-header d-flex justify-content-between align-items-center">
        <div class="pod-dashboard-title">
            <span class="pod-dashboard-icon"><i data-feather="x-circle" class="icon-16"></i></span>
            <span>PTW Top Rejection Reasons</span>
        </div>
        <span class="pod-dashboard-chip"><?php echo "Last " . $days . " days"; ?></span>
    </div>

    <div class="card-body">
        <div class="pod-dashboard-meta-grid">
            <div class="pod-dashboard-meta">
                <strong><?php echo (int)$total_rejections; ?></strong>
                <span>Total rejected decisions</span>
            </div>
            <div class="pod-dashboard-meta">
                <strong><?php echo esc($top_reason); ?></strong>
                <span>Top reason</span>
            </div>
        </div>

        <div class="pod-chart-panel">
            <div class="pod-section-heading">Ranked reasons <span><?php echo count($rows); ?> shown</span></div>
            <div class="pod-chart-wrap pod-chart-wrap-xs">
                <canvas id="<?php echo esc($chart_id); ?>" data-pod-chart-config="<?php echo esc($chart_id); ?>-config"></canvas>
                <?php if (!$chart_total) { ?><div class="pod-chart-empty">No rejection reasons yet</div><?php } ?>
            </div>
            <script type="application/json" id="<?php echo esc($chart_id); ?>-config"><?php echo json_encode($chart, $chart_json_flags); ?></script>
        </div>

        <div class="pod-dashboard-table-block mt15">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
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
</div>
