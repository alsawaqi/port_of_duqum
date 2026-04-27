<?php
$stage = strtolower((string) ($stage ?? "commercial"));
$vendors = $vendors ?? [];
$teams = $teams ?? [];
$opening_audit = $opening_audit ?? [];

$date_value = static function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = static function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$team_rows = function (string $role) use ($teams) {
    $rows = $teams[$role] ?? [];
    if (!$rows) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row->full_name ?? ""));
        $out[] = [
            "name" => $name !== "" ? $name : ($row->email ?? "-"),
            "role" => ucwords(str_replace("_", " ", $role)),
        ];
    }
    return $out;
};

$attendance = array_merge(
    $team_rows("chairman"),
    $team_rows("itc_member"),
    $team_rows("secretary")
);

$stage_label = $stage === "technical" ? "Technical Bid Opening Form" : "Commercial Bid Opening Form";
$opening_time = $stage === "technical"
    ? ($tender->technical_start_at ?? $tender->bid_opening_at ?? null)
    : ($tender->commercial_unlocked_at ?? $tender->bid_opening_at ?? null);
?>

<div id="page-content" class="page-wrapper clearfix tender-opening-print">
    <div class="mb15 no-print">
        <a href="<?php echo get_uri("tender_reports/details/" . (int) $tender->id); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i> Back to Tender Report
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print();">
            <i data-feather="printer" class="icon-16"></i> Print
        </button>
    </div>

    <div class="card gp-pro-card opening-form-card">
        <div class="card-body">
            <div class="opening-form-header">
                <div>
                    <div class="text-off">Port of Duqm</div>
                    <h2><?php echo esc($stage_label); ?></h2>
                </div>
                <div class="opening-form-ref">
                    <strong><?php echo esc($tender->reference ?? "-"); ?></strong>
                    <span><?php echo esc($tender->title ?? "-"); ?></span>
                </div>
            </div>

            <div class="table-responsive mt20">
                <table class="table table-bordered">
                    <tbody>
                        <tr>
                            <th style="width:22%;">Tender Number</th>
                            <td><?php echo esc($tender->reference ?? "-"); ?></td>
                            <th style="width:22%;">Tender Title</th>
                            <td><?php echo esc($tender->title ?? "-"); ?></td>
                        </tr>
                        <tr>
                            <th>Tender Budget</th>
                            <td><?php echo $money_value($tender->budget_omr ?? null); ?></td>
                            <th>Opening Stage</th>
                            <td><?php echo esc(ucfirst($stage)); ?></td>
                        </tr>
                        <tr>
                            <th>Submission Date</th>
                            <td><?php echo $date_value($tender->closing_at ?? null); ?></td>
                            <th>Opening Date</th>
                            <td><?php echo $date_value($opening_time); ?></td>
                        </tr>
                        <tr>
                            <th>Time & Place</th>
                            <td colspan="3"><?php echo esc($tender->site_visit_location ?? $tender->company_name ?? "PODC Tender Committee Meeting"); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4 class="mt20 mb10">Supplier Bids</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width:55px;">No.</th>
                            <th>Supplier Name</th>
                            <th>Total</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$vendors) { ?>
                            <tr><td colspan="4" class="text-center text-off p20">No supplier bids recorded.</td></tr>
                        <?php } ?>
                        <?php foreach ($vendors as $index => $vendor) { ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo esc($vendor->vendor_name ?? "-"); ?></td>
                                <td><?php echo $stage === "commercial" ? $money_value($vendor->total_amount ?? null, $vendor->currency ?? "OMR") : "Sealed until commercial opening"; ?></td>
                                <td>
                                    <?php
                                    $comment = $stage === "technical"
                                        ? ($vendor->technical_comments ?? "")
                                        : ($vendor->commercial_comments ?? "");
                                    echo nl2br(esc($comment ?: "-"));
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <h4 class="mt20 mb10">ITC Attendance & Signatures</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>Name</th><th>Role</th><th style="width:34%;">Signature</th></tr></thead>
                    <tbody>
                        <?php if (!$attendance) { ?>
                            <tr><td colspan="3" class="text-center text-off p20">No ITC members assigned.</td></tr>
                        <?php } ?>
                        <?php foreach ($attendance as $row) { ?>
                            <tr>
                                <td><?php echo esc($row["name"]); ?></td>
                                <td><?php echo esc($row["role"]); ?></td>
                                <td class="signature-cell"></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <h4 class="mt20 mb10">3-Key System Audit</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Session</th><th>Status</th><th>Generated</th><th>Unlocked</th><th>Member</th><th>Valid</th></tr></thead>
                    <tbody>
                        <?php
                        $has_audit = false;
                        foreach ($opening_audit as $audit) {
                            if (strtolower((string) ($audit->opening_stage ?? "")) !== $stage) {
                                continue;
                            }
                            $has_audit = true;
                            ?>
                            <tr>
                                <td>#<?php echo (int) ($audit->opening_id ?? 0); ?></td>
                                <td><?php echo esc(ucwords(str_replace("_", " ", $audit->opening_status ?? "-"))); ?></td>
                                <td><?php echo $date_value($audit->generated_at ?? null); ?></td>
                                <td><?php echo $date_value($audit->unlocked_at ?? null); ?></td>
                                <td><?php echo esc(trim((string) ($audit->member_name ?? "")) ?: ($audit->member_email ?? "-")); ?></td>
                                <td><?php echo !empty($audit->is_valid) ? "Yes" : "No"; ?></td>
                            </tr>
                        <?php } ?>
                        <?php if (!$has_audit) { ?>
                            <tr><td colspan="6" class="text-center text-off p20">No opening audit entries for this stage.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
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
.tender-opening-print .opening-form-card {
    max-width: 1100px;
    margin: 0 auto;
}
.opening-form-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    border-bottom: 2px solid #1f2a44;
    padding-bottom: 14px;
}
.opening-form-header h2 {
    margin: 3px 0 0;
    color: #1f2a44;
}
.opening-form-ref {
    text-align: right;
}
.opening-form-ref strong,
.opening-form-ref span {
    display: block;
}
.signature-cell {
    height: 54px;
}
@media print {
    body {
        background: #fff !important;
    }
    .no-print,
    #left-menu,
    #sidebar,
    #page-container > header,
    .navbar,
    .sidebar {
        display: none !important;
    }
    .page-wrapper,
    #page-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        border: 0 !important;
        box-shadow: none !important;
    }
}
</style>
