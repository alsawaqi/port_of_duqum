<?php
$stage = strtolower((string) ($stage ?? "commercial"));
$vendors = $vendors ?? [];
$teams = $teams ?? [];
$opening_audit = $opening_audit ?? [];
$opening_session = $opening_session ?? null;
$signature_rows = $signature_rows ?? [];
$back_url = $back_url ?? get_uri("tender_reports/details/" . (int) ($tender->id ?? 0));
$back_label = $back_label ?? "Back to Tender Report";
$supplier_rows = array_values(array_filter($vendors, static function ($vendor) {
    return !empty($vendor->bid_id);
}));

$date_value = static function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = static function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$signature_src = static function ($path) {
    $path = trim((string) $path);
    if ($path === "") {
        return "";
    }

    $full_path = WRITEPATH . "uploads/" . ltrim($path, "/");
    if (!is_file($full_path)) {
        return "";
    }

    return "data:image/png;base64," . base64_encode(file_get_contents($full_path));
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

$stage_label = "Bid Opening Form";
$opening_time = $opening_session->signed_at ?? $opening_session->unlocked_at ?? $tender->technical_start_at ?? $tender->bid_opening_at ?? null;
$signature_by_role = [];
$signed_attendance = [];
foreach ($signature_rows as $signature) {
    if (!empty($signature->signed_at)) {
        $signature_by_role[(string) ($signature->role ?? "")] = $signature;
        $role = (string) ($signature->role ?? "");
        $signed_attendance[] = [
            "name" => $signature->signature_name ?: (trim((string) ($signature->member_name ?? "")) ?: ($signature->member_email ?? "-")),
            "role" => [
                "chairman" => "Chairman of ITC",
                "secretary" => "Secretary",
                "itc_member" => "Member",
            ][$role] ?? ucwords(str_replace("_", " ", $role)),
            "remarks" => $role === "chairman" ? "Circulation" : "Attend",
            "signed_at" => $signature->signed_at,
            "signature_image_path" => $signature->signature_image_path ?? "",
            "signature_statement" => $signature->signature_statement ?? "",
        ];
    }
}
$attendance_rows = $signed_attendance ?: array_map(static function ($row) {
    $role = strtolower(str_replace(" ", "_", (string) $row["role"]));
    return [
        "name" => $row["name"],
        "role" => [
            "chairman" => "Chairman of ITC",
            "secretary" => "Secretary",
            "itc_member" => "Member",
        ][$role] ?? $row["role"],
        "remarks" => $role === "chairman" ? "Circulation" : "Attend",
        "signed_at" => null,
        "signature_image_path" => "",
        "signature_statement" => "",
    ];
}, $attendance);

$opening_header_actions = '<a href="' . esc($back_url, "attr") . '" class="btn btn-default gp-pro-btn gp-pro-btn-icon">'
    . '<i data-feather="arrow-left" class="icon-16"></i> ' . esc($back_label)
    . '</a>'
    . '<button type="button" class="btn btn-primary gp-pro-btn gp-pro-btn-icon" onclick="window.print();">'
    . '<i data-feather="printer" class="icon-16"></i> Print'
    . '</button>';
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page tender-opening-print">
    <?php
    echo view("includes/tender_page_header", [
        "title" => $stage_label,
        "subtitle" => "Review supplier bid opening records, committee attendance, and signed audit trail.",
        "icon" => "unlock",
        "actions" => $opening_header_actions
    ]);
    ?>

    <div class="card gp-pro-card opening-form-card">
        <div class="card-body">
            <div class="opening-form-header">
                <div>
                    <div class="text-off">Port of Duqm</div>
                    <h2>Bid Opening</h2>
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
                            <th style="width:28%;">Tender Number</th>
                            <td><?php echo esc($tender->reference ?? "-"); ?></td>
                        </tr>
                        <tr>
                            <th>Tender Title</th>
                            <td><?php echo esc($tender->title ?? "-"); ?></td>
                        </tr>
                        <tr>
                            <th>Tender Budget</th>
                            <td><?php echo $money_value($tender->budget_omr ?? null); ?></td>
                        </tr>
                        <tr>
                            <th>Opening Date</th>
                            <td><?php echo $date_value($opening_time); ?></td>
                        </tr>
                        <tr>
                            <th>Submission Date</th>
                            <td><?php echo $date_value($tender->closing_at ?? null); ?></td>
                        </tr>
                        <tr>
                            <th>Time & Place</th>
                            <td><?php echo esc(($opening_time ? format_to_time($opening_time) . " / " : "") . ($tender->site_visit_location ?? $tender->company_name ?? "PODC Tender Committee Meeting")); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h4 class="mt20 mb10">Supplier Bids</h4>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th style="width:70px;">SR. NO</th>
                            <th>SUPPLIER</th>
                            <th style="width:24%;">TOTAL</th>
                            <th style="width:28%;">COMMENTS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$supplier_rows) { ?>
                            <tr><td colspan="4" class="text-center text-off p20">No supplier bids recorded.</td></tr>
                        <?php } ?>
                        <?php foreach ($supplier_rows as $index => $vendor) { ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo esc($vendor->vendor_name ?? "-"); ?></td>
                                <td><?php echo $money_value($vendor->total_amount ?? null, $vendor->currency ?? "OMR"); ?></td>
                                <td>
                                    <?php
                                    $comments = [];
                                    if (!empty($vendor->technical_doc_id)) {
                                        $comments[] = "Technical proposal uploaded";
                                    }
                                    if (!empty($vendor->commercial_unpriced_doc_id)) {
                                        $comments[] = "Commercial proposal without price uploaded";
                                    }
                                    if (!empty($vendor->commercial_priced_doc_id) || !empty($vendor->commercial_legacy_doc_id)) {
                                        $comments[] = "Commercial proposal with price uploaded";
                                    }
                                    echo nl2br(esc($comments ? implode("\n", $comments) : "-"));
                                    ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <p class="mt20 mb10">The bid was opened in the presence of the following ITC members:</p>
            <p class="mb15">By signing this Bid Opening, ITC Members hereby declare there is no conflicts of interest involved with above bidders.</p>

            <h4 class="mt20 mb10">Committee Digital Signatures</h4>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>NAME</th><th>TITLE</th><th>REMARKS</th><th style="width:28%;">SIGNATURE</th></tr></thead>
                    <tbody>
                        <?php if (!$attendance_rows) { ?>
                            <tr><td colspan="4" class="text-center text-off p20">No ITC members assigned.</td></tr>
                        <?php } ?>
                        <?php foreach ($attendance_rows as $row) { ?>
                            <?php $image_src = $signature_src($row["signature_image_path"] ?? ""); ?>
                            <tr>
                                <td><?php echo esc($row["name"]); ?></td>
                                <td><?php echo esc($row["role"]); ?></td>
                                <td><?php echo esc($row["remarks"]); ?></td>
                                <td class="signature-cell">
                                    <?php if ($image_src) { ?>
                                        <img src="<?php echo $image_src; ?>" alt="Signature">
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (($opening_session->status ?? "") === "manual_accepted") { ?>
                            <tr>
                                <td colspan="4">
                                    Manual signed opening form uploaded by procurement:
                                    <strong><?php echo esc($opening_session->manual_form_original_name ?? "-"); ?></strong>
                                    on <?php echo $date_value($opening_session->manual_form_uploaded_at ?? null); ?>.
                                </td>
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
    height: 70px;
    text-align: center;
    vertical-align: middle !important;
}
.signature-cell img {
    max-width: 190px;
    max-height: 62px;
}
@media print {
    body {
        background: #fff !important;
    }
    .no-print,
    .pod-page-header,
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
