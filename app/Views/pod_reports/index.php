<?php
$vendors = $vendors ?? [];
$tenders = $tenders ?? [];
$gate_pass = $gate_pass ?? [];
$ptw = $ptw ?? [];
$recent_activity = $recent_activity ?? [];

$label = static function ($value): string {
    return ucwords(str_replace("_", " ", (string) ($value ?: "-")));
};

$duration = static function ($seconds): string {
    $seconds = (int) $seconds;
    if ($seconds <= 0) {
        return "-";
    }
    $minutes = intdiv($seconds, 60);
    $days = intdiv($minutes, 1440);
    $hours = intdiv($minutes % 1440, 60);
    $mins = $minutes % 60;
    $parts = [];
    if ($days) {
        $parts[] = $days . "d";
    }
    if ($hours) {
        $parts[] = $hours . "h";
    }
    if (!$parts && $mins) {
        $parts[] = $mins . "m";
    }
    return $parts ? implode(" ", $parts) : "0m";
};

$module_status = static function ($status): string {
    $status = strtolower((string) $status);
    $class = [
        "approved" => "bg-success",
        "awarded" => "bg-success",
        "issued" => "bg-success",
        "published" => "bg-primary",
        "submitted" => "bg-primary",
        "closed" => "bg-dark",
        "rejected" => "bg-danger",
        "cancelled" => "bg-danger",
        "returned" => "bg-warning text-dark",
        "revise" => "bg-warning text-dark",
        "draft" => "bg-light text-dark",
    ][$status] ?? "bg-secondary";

    return "<span class='badge $class'>" . esc(ucwords(str_replace("_", " ", $status ?: "-"))) . "</span>";
};

$breakdown = function (array $items, array $labels = []) use ($label) {
    if (!$items) {
        return "<div class='opr-empty-state'>No data yet</div>";
    }

    $max = max(array_values($items)) ?: 1;
    $html = "";
    foreach ($items as $key => $count) {
        $name = $labels[$key] ?? $label($key);
        $width = max(6, (int) round(((int) $count / $max) * 100));
        $html .= "<div class='opr-bar-row'>"
            . "<div><span>" . esc($name) . "</span><strong>" . (int) $count . "</strong></div>"
            . "<i style='width:" . $width . "%'></i>"
            . "</div>";
    }
    return $html;
};

$processing_rows = function (array $rows, array $labels = []) use ($duration, $label) {
    if (!$rows) {
        return "<div class='opr-empty-state'>No processing records yet</div>";
    }

    $html = "";
    foreach ($rows as $key => $row) {
        $name = $labels[$key] ?? $label($key);
        $html .= "<div class='opr-processing-row'>"
            . "<span>" . esc($name) . "</span>"
            . "<strong>" . esc($duration($row["avg_sec"] ?? 0)) . "</strong>"
            . "<small>" . (int) ($row["count"] ?? 0) . " item(s)</small>"
            . "</div>";
    }
    return $html;
};

$chart_json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
$chart_colors = ["#465fff", "#12b76a", "#f79009", "#06aed4"];
$module_totals = [
    "Vendors" => (int) ($vendors["total"] ?? 0),
    "Tenders" => (int) ($tenders["total"] ?? 0),
    "Gate Pass" => (int) ($gate_pass["total"] ?? 0),
    "PTW" => (int) ($ptw["total"] ?? 0),
];
$work_queue = [
    "Vendor review" => (int) ($vendors["submitted"] ?? 0),
    "Tender active" => (int) ($tenders["active"] ?? 0),
    "Gate pass" => (int) ($gate_pass["in_progress"] ?? 0),
    "PTW" => (int) ($ptw["in_progress"] ?? 0),
];
$risk_mix = [
    "Vendor docs expiring" => (int) ($vendors["docs_expiring"] ?? 0),
    "Vendor docs expired" => (int) ($vendors["docs_expired"] ?? 0),
    "Gate pass returned" => (int) ($gate_pass["returned"] ?? 0),
    "PTW rejected" => (int) ($ptw["rejected"] ?? 0),
];

$module_chart_id = "opr-module-chart";
$queue_chart_id = "opr-queue-chart";
$risk_chart_id = "opr-risk-chart";
$module_chart = [
    "type" => "bar",
    "data" => [
        "labels" => array_keys($module_totals),
        "datasets" => [[
            "label" => "Records",
            "data" => array_values($module_totals),
            "backgroundColor" => $chart_colors,
            "borderRadius" => 8,
            "barPercentage" => .58,
            "categoryPercentage" => .62,
        ]],
    ],
    "options" => [
        "scales" => [
            "xAxes" => [[
                "gridLines" => ["display" => false],
                "ticks" => ["fontStyle" => "600"],
            ]],
            "yAxes" => [[
                "ticks" => ["beginAtZero" => true, "precision" => 0],
                "gridLines" => ["color" => "rgba(102, 112, 133, .16)", "drawBorder" => false],
            ]],
        ],
    ],
];
$queue_chart = [
    "type" => "horizontalBar",
    "data" => [
        "labels" => array_keys($work_queue),
        "datasets" => [[
            "label" => "Open queue",
            "data" => array_values($work_queue),
            "backgroundColor" => ["#12b76a", "#465fff", "#f79009", "#06aed4"],
            "borderRadius" => 8,
            "barPercentage" => .54,
            "categoryPercentage" => .62,
        ]],
    ],
    "options" => [
        "scales" => [
            "xAxes" => [[
                "ticks" => ["beginAtZero" => true, "precision" => 0],
                "gridLines" => ["color" => "rgba(102, 112, 133, .16)", "drawBorder" => false],
            ]],
            "yAxes" => [[
                "gridLines" => ["display" => false],
                "ticks" => ["fontStyle" => "600"],
            ]],
        ],
    ],
];
$risk_chart = [
    "type" => "doughnut",
    "data" => [
        "labels" => array_keys($risk_mix),
        "datasets" => [[
            "data" => array_values($risk_mix),
            "backgroundColor" => ["#f79009", "#f04438", "#06aed4", "#e11d48"],
            "borderWidth" => 4,
            "borderColor" => "#ffffff",
        ]],
    ],
    "options" => [
        "cutoutPercentage" => 68,
        "legend" => ["display" => true, "position" => "bottom"],
    ],
];

$reports_label = app_lang("pod_reports");
if (!$reports_label || $reports_label === "default_lang.pod_reports" || $reports_label === "pod_reports") {
    $reports_label = "Operational Reports";
}
$actions = anchor(get_uri("tender_reports"), "<i data-feather='file-text' class='icon-16'></i> Tender Register", ["class" => "btn btn-default pod-report-header-action"]);
?>

<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-reports-page pod-report-page">
    <?php echo view("includes/operational_reports_page_header", [
        "title" => $reports_label,
        "subtitle" => "Monitor vendor readiness, procurement progress, access control, and permit activity from one executive workspace.",
        "icon" => "bar-chart-2",
        "breadcrumbs" => [
            ["label" => $reports_label],
        ],
        "actions" => $actions,
    ]); ?>

    <div class="opr-command-strip">
        <div class="opr-command-copy">
            <span>Operations snapshot</span>
            <strong><?php echo number_format(array_sum($module_totals)); ?> total records across core modules</strong>
            <p>Current queues, document risk, stage velocity, and recent movement are grouped for quick review.</p>
        </div>
        <div class="opr-command-metrics">
            <div>
                <small>Open queue</small>
                <strong><?php echo number_format(array_sum($work_queue)); ?></strong>
            </div>
            <div>
                <small>Risk signals</small>
                <strong><?php echo number_format(array_sum($risk_mix)); ?></strong>
            </div>
            <div>
                <small>Recent activity</small>
                <strong><?php echo number_format(count($recent_activity)); ?></strong>
            </div>
        </div>
    </div>

    <div class="opr-module-grid">
        <section class="opr-module-card opr-module-card-vendor">
            <div class="opr-module-head">
                <span><i data-feather="briefcase" class="icon-18"></i></span>
                <div><h3>Vendor</h3><small>Onboarding and document health</small></div>
            </div>
            <div class="opr-kpi-row">
                <div><small>Total</small><strong><?php echo (int) ($vendors["total"] ?? 0); ?></strong></div>
                <div><small>Approved</small><strong><?php echo (int) ($vendors["approved"] ?? 0); ?></strong></div>
                <div><small>Submitted</small><strong><?php echo (int) ($vendors["submitted"] ?? 0); ?></strong></div>
            </div>
            <div class="opr-risk-row">
                <span class="badge bg-warning text-dark"><?php echo (int) ($vendors["docs_expiring"] ?? 0); ?> expiring</span>
                <span class="badge bg-danger"><?php echo (int) ($vendors["docs_expired"] ?? 0); ?> expired</span>
            </div>
            <div class="opr-breakdown"><?php echo $breakdown($vendors["status"] ?? []); ?></div>
        </section>

        <section class="opr-module-card opr-module-card-tender">
            <div class="opr-module-head">
                <span><i data-feather="clipboard" class="icon-18"></i></span>
                <div><h3>Tender</h3><small>Procurement lifecycle and bids</small></div>
            </div>
            <div class="opr-kpi-row">
                <div><small>Total</small><strong><?php echo (int) ($tenders["total"] ?? 0); ?></strong></div>
                <div><small>Active</small><strong><?php echo (int) ($tenders["active"] ?? 0); ?></strong></div>
                <div><small>Bids</small><strong><?php echo (int) ($tenders["bids"] ?? 0); ?></strong></div>
            </div>
            <div class="opr-breakdown"><?php echo $breakdown($tenders["stage"] ?? [], [
                "technical_3key" => "Bid Opening",
                "award_decision" => "Award Decision",
            ]); ?></div>
        </section>

        <section class="opr-module-card opr-module-card-gate">
            <div class="opr-module-head">
                <span><i data-feather="log-in" class="icon-18"></i></span>
                <div><h3>Gate Pass</h3><small>Access requests and stage queues</small></div>
            </div>
            <div class="opr-kpi-row">
                <div><small>Total</small><strong><?php echo (int) ($gate_pass["total"] ?? 0); ?></strong></div>
                <div><small>In Progress</small><strong><?php echo (int) ($gate_pass["in_progress"] ?? 0); ?></strong></div>
                <div><small>Issued</small><strong><?php echo (int) ($gate_pass["issued_valid"] ?? 0); ?></strong></div>
            </div>
            <div class="opr-breakdown"><?php echo $breakdown($gate_pass["stage"] ?? [], [
                "visitor" => "Visitor",
                "department" => "Department",
                "commercial" => "Commercial",
                "security" => "Security",
                "rop" => "ROP",
                "issued" => "Issued",
            ]); ?></div>
        </section>

        <section class="opr-module-card opr-module-card-ptw">
            <div class="opr-module-head">
                <span><i data-feather="shield" class="icon-18"></i></span>
                <div><h3>PTW</h3><small>Permit workflow and issued permits</small></div>
            </div>
            <div class="opr-kpi-row">
                <div><small>Total</small><strong><?php echo (int) ($ptw["total"] ?? 0); ?></strong></div>
                <div><small>In Progress</small><strong><?php echo (int) ($ptw["in_progress"] ?? 0); ?></strong></div>
                <div><small>Approved</small><strong><?php echo (int) ($ptw["approved"] ?? 0); ?></strong></div>
            </div>
            <div class="opr-breakdown"><?php echo $breakdown($ptw["stage"] ?? [], [
                "hsse" => "HSSE",
                "hmo" => "HMO",
                "terminal" => "Terminal",
                "completed" => "Completed",
            ]); ?></div>
        </section>
    </div>

    <div class="opr-chart-grid">
        <section class="opr-panel">
            <div class="opr-panel-head">
                <div><h3>Module Volume</h3><span>Record count by operational area</span></div>
                <strong><?php echo number_format(array_sum($module_totals)); ?> total</strong>
            </div>
            <div class="pod-chart-wrap">
                <canvas id="<?php echo esc($module_chart_id); ?>" data-pod-chart-config="<?php echo esc($module_chart_id); ?>-config"></canvas>
                <?php if (!array_sum($module_totals)) { ?><div class="pod-chart-empty">No module records yet</div><?php } ?>
            </div>
            <script type="application/json" id="<?php echo esc($module_chart_id); ?>-config"><?php echo json_encode($module_chart, $chart_json_flags); ?></script>
        </section>

        <section class="opr-panel">
            <div class="opr-panel-head">
                <div><h3>Open Queue</h3><span>Work waiting for review or movement</span></div>
                <strong><?php echo number_format(array_sum($work_queue)); ?> open</strong>
            </div>
            <div class="pod-chart-wrap">
                <canvas id="<?php echo esc($queue_chart_id); ?>" data-pod-chart-config="<?php echo esc($queue_chart_id); ?>-config"></canvas>
                <?php if (!array_sum($work_queue)) { ?><div class="pod-chart-empty">No open queue</div><?php } ?>
            </div>
            <script type="application/json" id="<?php echo esc($queue_chart_id); ?>-config"><?php echo json_encode($queue_chart, $chart_json_flags); ?></script>
        </section>

        <section class="opr-panel">
            <div class="opr-panel-head">
                <div><h3>Risk Mix</h3><span>Exceptions that may need attention</span></div>
                <strong><?php echo number_format(array_sum($risk_mix)); ?> signal(s)</strong>
            </div>
            <div class="pod-chart-wrap">
                <canvas id="<?php echo esc($risk_chart_id); ?>" data-pod-chart-config="<?php echo esc($risk_chart_id); ?>-config"></canvas>
                <?php if (!array_sum($risk_mix)) { ?><div class="pod-chart-empty">No risk signals</div><?php } ?>
            </div>
            <script type="application/json" id="<?php echo esc($risk_chart_id); ?>-config"><?php echo json_encode($risk_chart, $chart_json_flags); ?></script>
        </section>
    </div>

    <div class="opr-bottom-grid">
        <section class="opr-panel">
            <div class="opr-panel-head">
                <div><h3>Stage Duration Signals</h3><span>Recent 30-day averages where available</span></div>
                <strong>Velocity</strong>
            </div>
            <div class="opr-duration-grid">
                <div>
                    <h4>Tender</h4>
                    <?php foreach (($tenders["durations"] ?? []) as $name => $value) { ?>
                        <div class="opr-processing-row"><span><?php echo esc($name); ?></span><strong><?php echo esc($value); ?></strong><small>avg</small></div>
                    <?php } ?>
                </div>
                <div>
                    <h4>Gate Pass</h4>
                    <?php echo $processing_rows($gate_pass["processing"] ?? [], [
                        "department" => "Department",
                        "commercial" => "Commercial",
                        "security" => "Security",
                        "rop" => "ROP",
                    ]); ?>
                </div>
                <div>
                    <h4>PTW</h4>
                    <?php echo $processing_rows($ptw["processing"] ?? [], [
                        "hsse" => "HSSE",
                        "hmo" => "HMO",
                        "terminal" => "Terminal",
                    ]); ?>
                </div>
            </div>
        </section>

        <section class="opr-panel opr-activity-panel">
            <div class="opr-panel-head">
                <div><h3>Recent Activity</h3><span>Latest movement across key modules</span></div>
                <strong><?php echo number_format(count($recent_activity)); ?> rows</strong>
            </div>
            <div class="table-responsive pod-table-shell">
                <table class="table table-striped mb0 opr-activity-table">
                    <thead><tr><th>Module</th><th>Record</th><th>Status</th><th>When</th></tr></thead>
                    <tbody>
                        <?php if (!$recent_activity) { ?>
                            <tr><td colspan="4" class="text-center text-off p20">No activity yet.</td></tr>
                        <?php } ?>
                        <?php foreach ($recent_activity as $item) { ?>
                            <tr>
                                <td data-label="Module"><?php echo esc($item["module"] ?? "-"); ?></td>
                                <td data-label="Record"><?php echo esc($item["title"] ?? "-"); ?></td>
                                <td data-label="Status"><?php echo $module_status($item["status"] ?? "-"); ?></td>
                                <td data-label="When"><?php echo !empty($item["event_at"]) ? format_to_datetime($item["event_at"]) : "-"; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") {
        feather.replace();
    }
    if (window.PortalShared && typeof window.PortalShared.initDashboardCharts === "function") {
        window.PortalShared.initDashboardCharts(document);
    }
});
</script>
