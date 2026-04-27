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

$breakdown = function (array $items, array $labels = []) use ($label) {
    if (!$items) {
        return "<div class='text-muted'>No data yet.</div>";
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
        return "<div class='text-muted'>No processing records yet.</div>";
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
?>

<div id="page-content" class="page-wrapper clearfix pod-report-page">
    <div class="opr-hero mb15">
        <div>
            <div class="text-off mb5">Operational Reporting</div>
            <h1>Vendor, Tender, Gate Pass & PTW Dashboard</h1>
            <p>One admin view for presentation-ready counts, stage movement, document risk, and recent activity.</p>
        </div>
        <a href="<?php echo get_uri("tender_reports"); ?>" class="btn btn-default">
            <i data-feather="bar-chart-2" class="icon-16"></i> Tender Register
        </a>
    </div>

    <div class="opr-module-grid mb15">
        <section class="opr-module-card vendor">
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

        <section class="opr-module-card tender">
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
                "technical_3key" => "3-Key Technical",
                "committee_3key" => "3-Key Commercial",
                "award_decision" => "Award Decision",
            ]); ?></div>
        </section>

        <section class="opr-module-card gate">
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

        <section class="opr-module-card ptw">
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

    <div class="row">
        <div class="col-md-7 mb15">
            <div class="opr-panel">
                <div class="opr-panel-head">
                    <h3>Stage Duration Signals</h3>
                    <span>Recent 30-day averages where available</span>
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
            </div>
        </div>
        <div class="col-md-5 mb15">
            <div class="opr-panel">
                <div class="opr-panel-head">
                    <h3>Recent Activity</h3>
                    <span>Latest movement across key modules</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb0">
                        <thead><tr><th>Module</th><th>Record</th><th>Status</th><th>When</th></tr></thead>
                        <tbody>
                            <?php if (!$recent_activity) { ?>
                                <tr><td colspan="4" class="text-center text-off p20">No activity yet.</td></tr>
                            <?php } ?>
                            <?php foreach ($recent_activity as $item) { ?>
                                <tr>
                                    <td><?php echo esc($item["module"] ?? "-"); ?></td>
                                    <td><?php echo esc($item["title"] ?? "-"); ?></td>
                                    <td><?php echo $module_status($item["status"] ?? "-"); ?></td>
                                    <td><?php echo !empty($item["event_at"]) ? format_to_datetime($item["event_at"]) : "-"; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>

<style>
.pod-report-page {
    --opr-ink: #172033;
    --opr-muted: #667085;
    --opr-line: #e4eaf3;
    --opr-blue: #2f66f2;
    --opr-green: #16856f;
    --opr-gold: #b7791f;
    --opr-red: #c2414b;
}
.opr-hero,
.opr-module-card,
.opr-panel {
    border: 1px solid var(--opr-line);
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 14px 34px rgba(23, 32, 51, .07);
}
.opr-hero {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: center;
    padding: 20px 22px;
    background: linear-gradient(135deg, #fff 0%, #f7fbff 54%, #f4faf7 100%);
}
.opr-hero h1 {
    margin: 0;
    font-size: 24px;
    color: var(--opr-ink);
}
.opr-hero p {
    margin: 6px 0 0;
    color: var(--opr-muted);
}
.opr-module-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}
.opr-module-card {
    padding: 16px;
    overflow: hidden;
    animation: gpProFadeInUp .28s ease both;
}
.opr-module-card.vendor { border-top: 4px solid var(--opr-green); }
.opr-module-card.tender { border-top: 4px solid var(--opr-blue); }
.opr-module-card.gate { border-top: 4px solid var(--opr-gold); }
.opr-module-card.ptw { border-top: 4px solid var(--opr-red); }
.opr-module-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}
.opr-module-head > span {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f2f6fb;
    color: var(--opr-blue);
}
.opr-module-head h3,
.opr-panel-head h3 {
    margin: 0;
    font-size: 16px;
    color: var(--opr-ink);
}
.opr-module-head small,
.opr-panel-head span {
    color: var(--opr-muted);
}
.opr-kpi-row {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}
.opr-kpi-row div {
    border: 1px solid var(--opr-line);
    border-radius: 10px;
    background: #fbfdff;
    padding: 10px;
}
.opr-kpi-row small {
    display: block;
    color: var(--opr-muted);
}
.opr-kpi-row strong {
    display: block;
    margin-top: 4px;
    font-size: 24px;
    color: var(--opr-ink);
}
.opr-risk-row {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 10px;
}
.opr-breakdown {
    display: grid;
    gap: 8px;
}
.opr-bar-row div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
    color: var(--opr-muted);
    margin-bottom: 4px;
}
.opr-bar-row strong {
    color: var(--opr-ink);
}
.opr-bar-row i {
    display: block;
    height: 7px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--opr-blue), var(--opr-green));
}
.opr-panel {
    padding: 16px;
}
.opr-panel-head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: flex-start;
    margin-bottom: 14px;
}
.opr-duration-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}
.opr-duration-grid h4 {
    margin: 0 0 10px;
    color: var(--opr-ink);
    font-size: 14px;
}
.opr-processing-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 6px;
    border: 1px solid var(--opr-line);
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 8px;
    background: #fbfdff;
}
.opr-processing-row span {
    color: var(--opr-muted);
}
.opr-processing-row strong {
    color: var(--opr-ink);
}
.opr-processing-row small {
    grid-column: 1 / -1;
    color: var(--opr-muted);
}
@media (max-width: 1199px) {
    .opr-module-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .opr-duration-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767px) {
    .opr-hero {
        flex-direction: column;
        align-items: stretch;
    }
    .opr-module-grid {
        grid-template-columns: 1fr;
    }
}
</style>
