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

$status_sorted = $status;
if (is_array($status_sorted)) {
    arsort($status_sorted);
}
?>

<div class="card bg-white">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i data-feather="clipboard" class="icon-16"></i>&nbsp;PTW KPIs
        </div>
        <small class="text-muted"><?php echo esc($scope_label ?? ""); ?></small>
    </div>

    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="text-muted small">In progress</div>
                <div class="fs-3 fw-bold"><?php echo $in_progress; ?></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="fw-semibold mb-2">Applications by stage</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php if (!empty($stage)) { ?>
                            <?php foreach ($stage_labels as $key => $label) { ?>
                                <tr>
                                    <td class="text-muted"><?php echo esc($label); ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)($stage[$key] ?? 0); ?></td>
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

            <div class="col-md-6">
                <div class="fw-semibold mb-2">Status breakdown</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php if (!empty($status_sorted)) { ?>
                            <?php
                            $i = 0;
                            foreach ($status_sorted as $s => $cnt) {
                                $i++;
                                if ($i > 8) { break; }
                            ?>
                                <tr>
                                    <td class="text-muted"><?php echo esc(ucwords(str_replace("_", " ", (string)$s))); ?></td>
                                    <td class="text-end fw-semibold"><?php echo (int)$cnt; ?></td>
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