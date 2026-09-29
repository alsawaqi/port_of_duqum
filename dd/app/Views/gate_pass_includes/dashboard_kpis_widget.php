<?php
$k = $kpis ?? [];
$status = $k["status"] ?? [];
$stage = $k["stage"] ?? [];
$in_progress = (int)($k["in_progress"] ?? 0);
$issued_valid = (int)($k["issued_valid"] ?? 0);
$status_sorted = is_array($status) ? $status : [];
arsort($status_sorted);
$stage_labels = [
    "visitor" => "Visitor",
    "department" => "Department",
    "commercial" => "Commercial",
    "security" => "Security",
    "rop" => "ROP",
    "issued" => "Issued",
];
?>
<div class="card mb15">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i data-feather="bar-chart-2" class="icon-16"></i> <?php echo app_lang("gate_pass_portal_dashboard"); ?></span>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6">
                <div class="text-muted small"><?php echo app_lang("in_progress"); ?></div>
                <div class="fs-3 fw-bold"><?php echo $in_progress; ?></div>
            </div>
            <div class="col-6">
                <div class="text-muted small"><?php echo app_lang("gate_pass_issued_valid"); ?></div>
                <div class="fs-3 fw-bold"><?php echo $issued_valid; ?></div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="fw-semibold mb-2"><?php echo app_lang("gate_pass_by_stage"); ?></div>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php foreach ($stage_labels as $key => $label): ?>
                        <tr>
                            <td class="text-muted"><?php echo esc($label); ?></td>
                            <td class="text-end fw-semibold"><?php echo (int)($stage[$key] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-6">
                <div class="fw-semibold mb-2"><?php echo app_lang("gate_pass_by_status"); ?></div>
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php
                    $i = 0;
                    foreach ($status_sorted as $s => $cnt):
                        $i++;
                        if ($i > 10) {
                            break;
                        }
                    ?>
                        <tr>
                            <td class="text-muted"><?php echo esc(ucwords(str_replace("_", " ", (string)$s))); ?></td>
                            <td class="text-end fw-semibold"><?php echo (int)$cnt; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$status_sorted): ?>
                        <tr><td colspan="2" class="text-muted">—</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>if (typeof feather !== "undefined") feather.replace();</script>
