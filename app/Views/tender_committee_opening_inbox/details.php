<?php
load_js([
    "assets/js/signature/signature_pad.min.js",
]);

$bid_summary = $bid_summary ?? [];
$signature_rows = $signature_rows ?? [];
$signature_map = $signature_map ?? [];
$my_role = $my_role ?? "";

$date_value = static function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = static function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$doc_link = static function ($doc_id, $label) {
    if (empty($doc_id)) {
        return "<span class='text-off'>-</span>";
    }

    return anchor(
        get_uri("tender_committee_opening_inbox/download_bid_document/" . (int) $doc_id),
        "<i data-feather='download' class='icon-14'></i> " . esc($label ?: "Download"),
        ["class" => "btn btn-default btn-xs mb5", "target" => "_blank"]
    );
};

$signature_src = static function ($signature) {
    $entry_id = (int) ($signature->id ?? 0);
    if (!$entry_id || empty($signature->signature_image_path)) {
        return "";
    }
    return get_uri("tender_committee_opening_inbox/signature_image/" . $entry_id);
};

$role_label = static function ($role) {
    return [
        "chairman" => "Chairman of ITC",
        "secretary" => "Secretary",
        "itc_member" => "Member",
    ][$role] ?? ucwords(str_replace("_", " ", (string) $role));
};

$signed_roles = [];
foreach ($signature_rows as $row) {
    if (!empty($row->signed_at)) {
        $signed_roles[(string) ($row->role ?? "")] = true;
    }
}

$my_role_signed = !empty($signed_roles[(string) $my_role]);
$all_required_signed = !empty($signed_roles["chairman"]) && !empty($signed_roles["secretary"]) && !empty($signed_roles["itc_member"]);
$submitted_count = count($bid_summary);
$total_value = 0;
foreach ($bid_summary as $bid) {
    if ($bid->total_amount !== null && $bid->total_amount !== "") {
        $total_value += (float) $bid->total_amount;
    }
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-opening-review-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => "Bid Opening Review",
        "subtitle" => "Review submitted vendors, bid values, documents, signatures, and opening session status.",
        "icon" => "unlock",
        "actions" => '<a href="' . esc(get_uri("tender_committee_opening_inbox"), "attr") . '" class="btn btn-default gp-pro-btn gp-pro-btn-icon">'
            . '<i data-feather="arrow-left" class="icon-16"></i> Back to Bid Opening'
            . '</a>'
    ]);
    ?>

    <div class="opening-review-hero mb15">
        <div>
            <div class="text-off mb5">Bid Opening Review</div>
            <h2 class="mb5"><?php echo esc($tender->reference ?? "-"); ?> - <?php echo esc($tender->title ?? "-"); ?></h2>
            <div><?php echo esc($tender->company_name ?? "-"); ?> / <?php echo esc($tender->department_name ?? "-"); ?></div>
        </div>
        <div class="opening-review-status">
            <span class="badge bg-success"><?php echo esc(ucwords(str_replace("_", " ", $session->status ?? "unlocked"))); ?></span>
            <a href="<?php echo get_uri("tender_committee_opening_inbox/bid_opening_form/" . (int) $tender->id); ?>" class="btn btn-default btn-sm mt10" target="_blank">
                <i data-feather="clipboard" class="icon-14"></i> Bid Opening Form
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3 col-sm-6 mb15">
            <div class="opening-review-stat">
                <span>Submitted Vendors</span>
                <strong><?php echo (int) $submitted_count; ?></strong>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb15">
            <div class="opening-review-stat">
                <span>Total Bid Value</span>
                <strong><?php echo $money_value($total_value ?: null); ?></strong>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb15">
            <div class="opening-review-stat">
                <span>Unlocked At</span>
                <strong><?php echo $date_value($session->unlocked_at ?? null); ?></strong>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb15">
            <div class="opening-review-stat">
                <span>Your Role</span>
                <strong><?php echo esc($role_label($my_role)); ?></strong>
            </div>
        </div>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb0">Opened Bid Package</h4>
            <span class="text-off"><?php echo esc($date_value($session->unlocked_at ?? null)); ?></span>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb0 opening-review-table">
                <thead>
                    <tr>
                        <th>Vendor</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Bid Documents</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$bid_summary) { ?>
                        <tr><td colspan="5" class="text-center text-off p20">No submitted bids found.</td></tr>
                    <?php } ?>
                    <?php foreach ($bid_summary as $bid) { ?>
                        <tr>
                            <td>
                                <strong><?php echo esc($bid->vendor_name ?? "-"); ?></strong>
                                <div class="text-off">Bid #<?php echo (int) ($bid->bid_id ?? 0); ?></div>
                            </td>
                            <td><?php echo esc(ucwords(str_replace("_", " ", $bid->bid_status ?? "-"))); ?></td>
                            <td><?php echo $date_value($bid->submitted_at ?? null); ?></td>
                            <td>
                                <?php echo $doc_link($bid->technical_doc_id ?? 0, "Technical"); ?>
                                <?php echo $doc_link($bid->commercial_unpriced_doc_id ?? 0, "Commercial Without Price"); ?>
                                <?php echo $doc_link($bid->commercial_priced_doc_id ?? 0, "Commercial With Price"); ?>
                                <?php echo $doc_link($bid->bank_guarantee_doc_id ?? 0, "Bank Guarantee"); ?>
                            </td>
                            <td class="text-end fw-bold"><?php echo $money_value($bid->total_amount ?? null, $bid->currency ?? "OMR"); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card gp-pro-card">
        <div class="card-header">
            <h4 class="mb0">Committee Digital Signatures</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-7">
                    <div class="opening-signature-list">
                        <?php foreach (["chairman", "secretary", "itc_member"] as $role) { ?>
                            <?php
                            $signed_row = null;
                            foreach ($signature_rows as $row) {
                                if ((string) ($row->role ?? "") === $role && !empty($row->signed_at)) {
                                    $signed_row = $row;
                                    break;
                                }
                            }
                            $image_src = $signed_row ? $signature_src($signed_row) : "";
                            ?>
                            <div class="opening-signature-item">
                                <div>
                                    <strong><?php echo esc($role_label($role)); ?></strong>
                                    <div class="text-off"><?php echo esc($signed_row->signature_name ?? "Pending signature"); ?></div>
                                </div>
                                <div class="opening-signature-preview">
                                    <?php if ($image_src) { ?>
                                        <img src="<?php echo $image_src; ?>" alt="Signature">
                                    <?php } else { ?>
                                        <span>Pending</span>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="col-md-5">
                    <?php if ($my_role_signed) { ?>
                        <div class="alert <?php echo $all_required_signed ? "alert-success" : "alert-info"; ?> mb0">
                            <?php if ($all_required_signed) { ?>
                                All committee signatures are complete. Procurement can now download the bid opening form and start technical review.
                            <?php } else { ?>
                                Your signature was saved. Waiting for the remaining committee signatures.
                            <?php } ?>
                        </div>
                    <?php } elseif (($session->status ?? "") === "unlocked") { ?>
                        <?php echo form_open(get_uri("tender_committee_opening_inbox/sign_opening"), [
                            "id" => "tender-opening-sign-page-form",
                            "class" => "general-form",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                            <input type="hidden" name="opening_stage" value="<?php echo esc($opening_stage ?? "technical"); ?>" />
                            <input type="hidden" name="committee_signature_statement" value="I confirm that I reviewed the opened technical and commercial bid package for this tender and digitally sign the bid opening form." />

                            <div class="form-group">
                                <label>Signature Name</label>
                                <input type="text" name="signature_name" class="form-control" value="<?php echo esc(trim((string) (($this->login_user->first_name ?? "") . " " . ($this->login_user->last_name ?? "")))); ?>">
                            </div>

                            <div class="form-group">
                                <label>Digital Signature</label>
                                <div id="signature" class="opening-signature-pad">
                                    <canvas height="170"></canvas>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="d-flex align-items-start gap-2">
                                    <input type="checkbox" required>
                                    <span>I confirm the opened bid package and sign the bid opening form digitally.</span>
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i data-feather="pen-tool" class="icon-16"></i> Sign Bid Opening
                            </button>
                        <?php echo form_close(); ?>
                    <?php } else { ?>
                        <div class="alert alert-info mb0">
                            Committee signatures are complete.
                        </div>
                    <?php } ?>
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

    if ($("#signature").length) {
        initSignature("signature", {
            required: true,
            requiredMessage: "Digital signature is required."
        });
    }

    $("#tender-opening-sign-page-form").appForm({
        isModal: false,
        onSuccess: function (response) {
            appAlert.success(response.message || "Signature saved.", {duration: 2500});
            setTimeout(function () {
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                    return;
                }

                if (response.reload) {
                    window.location.reload();
                }
            }, 450);
        }
    });
});
</script>

<style>
.tender-opening-review-page .opening-review-hero {
    border: 1px solid #dce8f8;
    background: linear-gradient(135deg, #f7fbff 0%, #ffffff 60%);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    gap: 16px;
}
.opening-review-hero h2 {
    font-size: 24px;
    line-height: 1.3;
}
.opening-review-status {
    min-width: 180px;
    text-align: right;
}
.opening-review-stat {
    border: 1px solid #e1eaf6;
    background: #fff;
    border-radius: 10px;
    padding: 14px;
    min-height: 86px;
}
.opening-review-stat span {
    display: block;
    color: #697386;
    margin-bottom: 6px;
}
.opening-review-stat strong {
    color: #1f2a44;
    font-size: 18px;
}
.opening-review-table td {
    vertical-align: middle;
}
.opening-signature-list {
    display: grid;
    gap: 10px;
}
.opening-signature-item {
    border: 1px solid #e1eaf6;
    background: #fbfdff;
    border-radius: 10px;
    padding: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
}
.opening-signature-preview {
    width: 170px;
    height: 70px;
    border: 1px dashed #c9d6e8;
    background: #fff;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.opening-signature-preview img {
    max-width: 100%;
    max-height: 100%;
}
.opening-signature-preview span {
    color: #8892a6;
}
.opening-signature-pad {
    border: 1px solid #cfd9e8;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
}
.opening-signature-pad canvas {
    width: 100%;
    display: block;
}
@media (max-width: 767px) {
    .tender-opening-review-page .opening-review-hero,
    .opening-signature-item {
        display: block;
    }
    .opening-review-status {
        min-width: 0;
        text-align: left;
        margin-top: 12px;
    }
    .opening-signature-preview {
        width: 100%;
        margin-top: 10px;
    }
}
</style>
