<?php
$docs = $docs ?? [];
$bid = $bid ?? null;
$required_sections = $required_sections ?? [];
$documents_map = $documents_map ?? [];
$bid_item_price_map = $bid_item_price_map ?? [];
$bid_item_price_rows = $bid_item_price_rows ?? [];
$clarifications = $clarifications ?? [];
$clarification_open = $clarification_open ?? false;
$clarification_attachments = $clarification_attachments ?? [];
$clarification_scope_options = $clarification_scope_options ?? ["general" => "General", "tender" => "Tender / Procurement", "technical" => "Technical Team", "commercial" => "Commercial Team", "vendor" => "Vendor Specific"];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];
$procurement_approved_for_submission = !empty($procurement_approved_for_submission);
$procurement_approval_status = $procurement_approval_status ?? "required";
$tender_fee_required = isset($tender_fee_required) ? (bool) $tender_fee_required : ((float) ($tender->tender_fee ?? 0) > 0);
$tender_fee_paid = isset($tender_fee_paid) ? (bool) $tender_fee_paid : !$tender_fee_required;
$tender_fee_payment_status = $tender_fee_payment_status ?? ($tender_fee_required ? ($tender_fee_paid ? "paid" : "unpaid") : "not_required");

$submission_period_open = (($tender->status ?? "") === "published") && (($tender->workflow_stage ?? "bidding") === "bidding");
if ($submission_period_open && !empty($tender->closing_at) && strtotime($tender->closing_at) <= time()) {
    $submission_period_open = false;
}
$submission_open = $submission_period_open && $procurement_approved_for_submission && $tender_fee_paid;

$section_labels = [
    "technical" => "Technical Proposal",
    "commercial_priced" => "Commercial Proposal (With Price)",
    "commercial_unpriced" => "Commercial Proposal (Without Price)",
    "bank_guarantee" => "Bank Guarantee Documents",
];

$section_fields = [
    "technical" => "technical_file",
    "commercial_priced" => "commercial_priced_file",
    "commercial_unpriced" => "commercial_unpriced_file",
    "bank_guarantee" => "bank_guarantee_file",
];

$status = strtolower((string) ($tender->status ?? "draft"));
$workflow_stage = strtolower((string) ($tender->workflow_stage ?? "bidding"));
$target = $tender->vendor_category_name ?: "-";
if (!empty($tender->vendor_sub_category_name)) {
    $target .= " / " . $tender->vendor_sub_category_name;
}

$date_value = function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$stage_label = function ($stage) {
    $labels = [
        "bidding" => "Bid Submission",
        "technical_3key" => "Bid Opening",
        "technical" => "Technical Evaluation",
        "commercial" => "Commercial Evaluation",
        "award_decision" => "Award Decision",
    ];
    return $labels[$stage] ?? ucwords(str_replace("_", " ", $stage ?: "-"));
};

$status_badge = function ($value) {
    $value = strtolower((string) $value);
    $class = [
        "draft" => "secondary",
        "published" => "primary",
        "closed" => "dark",
        "awarded" => "success",
        "cancelled" => "danger",
    ][$value] ?? "secondary";
    return "<span class='badge bg-" . $class . "'>" . esc(ucfirst($value ?: "-")) . "</span>";
};

$participation_status_label = function (string $status) {
    $labels = [
        "approved" => "Approved",
        "pending_approval" => "Pending approval",
        "required" => "Approval required",
        "rejected" => "Rejected",
        "declined" => "Declined",
    ];

    return $labels[$status] ?? ucwords(str_replace("_", " ", $status ?: "Approval required"));
};

$fee_payment_status_label = function (string $status) {
    $labels = [
        "paid" => "Paid",
        "unpaid" => "Payment required",
        "not_required" => "Not required",
    ];

    return $labels[$status] ?? ucwords(str_replace("_", " ", $status ?: "-"));
};

$saved_item_total = 0.0;
foreach ($bid_item_price_rows as $price_row) {
    if ($price_row->line_total !== null && $price_row->line_total !== "") {
        $saved_item_total += (float) $price_row->line_total;
    }
}

$vendor_tender_back_url = get_uri("vendor_portal") . "#vp-tenders";
$vendor_tender_header_actions = '<a href="' . esc($vendor_tender_back_url, "attr") . '" class="btn btn-default gp-pro-btn gp-pro-btn-icon">'
    . '<i data-feather="arrow-left" class="icon-16"></i> Back to Vendor Portal'
    . '</a>';
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page vendor-tender-detail-page">
    <style>
        .vendor-tender-detail-page {
            --vtd-border: rgba(15, 23, 42, .08);
            --vtd-muted: #64748b;
            --vtd-title: #0f172a;
            --vtd-shadow: 0 14px 40px rgba(15, 23, 42, .09);
            color: #1f2937;
        }

        .vtd-back-row {
            margin-bottom: 12px;
        }

        .vtd-hero {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: var(--vtd-shadow);
            padding: 20px;
            margin-bottom: 14px;
        }

        .vtd-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #0ea5e9;
            pointer-events: none;
        }

        .vtd-hero-inner {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: start;
        }

        .vtd-eyebrow {
            font-size: 12px;
            color: var(--vtd-muted);
            margin-bottom: 4px;
        }

        .vtd-title {
            margin: 0;
            color: var(--vtd-title);
            font-size: 24px;
            font-weight: 800;
        }

        .vtd-reference {
            margin-top: 6px;
            color: var(--vtd-muted);
            font-size: 13px;
        }

        .vtd-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .vtd-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid var(--vtd-border);
            background: rgba(255, 255, 255, .86);
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .vtd-stats {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
            margin-top: 18px;
        }

        .vtd-stat {
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            padding: 12px;
            background: rgba(255, 255, 255, .88);
        }

        .vtd-stat span {
            display: block;
            font-size: 11px;
            color: var(--vtd-muted);
            margin-bottom: 4px;
        }

        .vtd-stat strong {
            display: block;
            color: var(--vtd-title);
            font-size: 13px;
            line-height: 1.3;
        }

        .vtd-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.45fr) minmax(340px, .75fr);
            gap: 14px;
            align-items: start;
        }

        .vtd-section {
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
            margin-bottom: 14px;
            overflow: hidden;
        }

        .vtd-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            padding: 15px 16px;
            border-bottom: 1px solid var(--vtd-border);
            background: rgba(248, 250, 252, .72);
        }

        .vtd-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vtd-section-title h4 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: var(--vtd-title);
        }

        .vtd-section-body {
            padding: 16px;
        }

        .vtd-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .vtd-info {
            border: 1px solid rgba(15, 23, 42, .06);
            border-radius: 8px;
            padding: 11px 12px;
            background: #fbfdff;
            min-height: 70px;
        }

        .vtd-info span {
            display: block;
            font-size: 11px;
            color: var(--vtd-muted);
            margin-bottom: 5px;
        }

        .vtd-info strong,
        .vtd-info div {
            color: #1f2937;
            font-size: 13px;
        }

        .vtd-requirements {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .vtd-table {
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            overflow: hidden;
        }

        .vtd-table .table {
            margin-bottom: 0;
        }

        .vtd-table th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
        }

        .vtd-total-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(37, 99, 235, .18);
            border-radius: 8px;
            background: rgba(37, 99, 235, .06);
            padding: 12px;
        }

        .vtd-total-strip span {
            color: var(--vtd-muted);
            font-size: 12px;
            font-weight: 700;
        }

        .vtd-total-strip strong {
            color: var(--vtd-title);
            font-size: 16px;
        }

        .vtd-document-list {
            display: grid;
            gap: 10px;
        }

        .vtd-document-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: center;
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            padding: 12px;
            background: #fff;
        }

        .vtd-document-title {
            font-weight: 800;
            color: var(--vtd-title);
            margin-bottom: 3px;
        }

        .vtd-document-meta {
            color: var(--vtd-muted);
            font-size: 12px;
        }

        .vtd-side {
            position: sticky;
            top: 76px;
        }

        .vtd-bid-status {
            border-radius: 8px;
            padding: 12px;
            background: rgba(37, 99, 235, .06);
            border: 1px solid rgba(37, 99, 235, .18);
            margin-bottom: 12px;
        }

        .vtd-approval-card {
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px;
            margin-bottom: 14px;
            background: #eff6ff;
            color: #1e3a8a;
        }

        .vtd-approval-card.is-pending {
            border-color: #fde68a;
            background: #fffbeb;
            color: #92400e;
        }

        .vtd-approval-card.is-rejected {
            border-color: #fecaca;
            background: #fef2f2;
            color: #991b1b;
        }

        .vtd-upload-row {
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            background: #fff;
        }

        .vtd-upload-title {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 8px;
            font-weight: 800;
            color: var(--vtd-title);
        }

        .vtd-chat {
            background: #f8fafc;
            border: 1px solid var(--vtd-border);
            border-radius: 8px;
            padding: 12px;
            max-height: 420px;
            overflow-y: auto;
        }

        .vtd-chat-bubble {
            max-width: 78%;
            padding: 12px 14px;
            border-radius: 8px;
            box-shadow: 0 6px 12px rgba(20, 31, 56, .06);
        }

        .vtd-chat-self {
            background: #103d73;
            color: #fff;
        }

        .vtd-chat-other {
            background: #fff;
            border: 1px solid #dfe6f1;
            color: #223047;
        }

        @media (max-width: 991px) {
            .vtd-hero-inner,
            .vtd-layout {
                grid-template-columns: 1fr;
            }

            .vtd-badges {
                justify-content: flex-start;
            }

            .vtd-side {
                position: static;
            }
        }

        @media (max-width: 767px) {
            .vtd-stats,
            .vtd-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <?php
    echo view("includes/tender_page_header", [
        "title" => $tender->title ?: "Tender Details",
        "subtitle" => ($tender->reference ?: "Tender") . " - Review documents, submit bids, and manage clarifications.",
        "icon" => "briefcase",
        "breadcrumbs" => [
            ["label" => "Vendor Portal", "url" => get_uri("vendor_portal")],
            ["label" => "Tender Details"]
        ],
        "actions" => $vendor_tender_header_actions
    ]);
    ?>

    <div class="vtd-hero">
        <div class="vtd-hero-inner">
            <div>
                <div class="vtd-eyebrow">Tender Details</div>
                <h1 class="vtd-title"><?php echo esc($tender->title ?: "-"); ?></h1>
                <div class="vtd-reference"><?php echo esc($tender->reference ?: "-"); ?> / <?php echo esc($target); ?></div>
            </div>
            <div class="vtd-badges">
                <?php echo $status_badge($status); ?>
                <span class="vtd-pill"><i data-feather="activity" class="icon-14"></i> <?php echo esc($stage_label($workflow_stage)); ?></span>
                <span class="vtd-pill"><i data-feather="mail" class="icon-14"></i> <?php echo esc($participation_status_label($procurement_approval_status)); ?></span>
            </div>
        </div>

        <div class="vtd-stats">
            <div class="vtd-stat"><span>Release</span><strong><?php echo $date_value($tender->release_at ?: ($tender->published_at ?? null)); ?></strong></div>
            <div class="vtd-stat"><span>Clarification</span><strong><?php echo $date_value($tender->clarification_deadline ?? null); ?></strong></div>
            <div class="vtd-stat"><span>Submission Deadline</span><strong><?php echo $date_value($tender->closing_at ?? null); ?></strong></div>
            <div class="vtd-stat"><span>Your Bid</span><strong><?php echo !empty($bid) ? esc(ucfirst($bid->status ?? "submitted")) : "Not submitted"; ?></strong></div>
        </div>
    </div>

    <div class="vtd-layout">
        <main>
            <section class="vtd-section">
                <div class="vtd-section-header">
                    <div class="vtd-section-title">
                        <i data-feather="file-text" class="icon-16"></i>
                        <h4>Overview</h4>
                    </div>
                    <span class="badge bg-<?php echo (($tender->tender_type ?? "open") === "close") ? "warning text-dark" : "success"; ?>">
                        <?php echo strtoupper(esc($tender->tender_type ?? "open")); ?>
                    </span>
                </div>
                <div class="vtd-section-body">
                    <?php if (!empty($tender->brief_description)) { ?>
                        <div class="alert alert-light mb15"><?php echo nl2br(esc($tender->brief_description)); ?></div>
                    <?php } ?>

                    <div class="vtd-info-grid">
                        <div class="vtd-info"><span>Target Specialty</span><strong><?php echo esc($target); ?></strong></div>
                        <div class="vtd-info"><span>Tender Fees</span><strong><?php echo $money_value($tender->tender_fee ?? null); ?></strong></div>
                        <div class="vtd-info"><span>Document Purchase Deadline</span><strong><?php echo $date_value($tender->document_purchase_deadline ?? null); ?></strong></div>
                        <div class="vtd-info"><span>Site Visit Date / Deadline</span><strong><?php echo $date_value($tender->site_visit_at ?? null); ?></strong></div>
                        <div class="vtd-info"><span>Site Visit Attendance</span><div><?php echo !empty($tender->site_visit_mandatory) ? "<span class='badge bg-warning text-dark'>Mandatory</span>" : "<span class='badge bg-light text-dark'>Optional</span>"; ?></div></div>
                        <div class="vtd-info"><span>Site Visit Location</span><strong><?php echo esc($tender->site_visit_location ?? "-"); ?></strong></div>
                        <div class="vtd-info"><span>Bid Opening Date</span><strong><?php echo $date_value($tender->bid_opening_at ?? null); ?></strong></div>
                    </div>

                    <?php if (!empty($tender->site_visit_instructions)) { ?>
                        <div class="vtd-info mt15">
                            <span>Site Visit Instructions</span>
                            <div><?php echo nl2br(esc($tender->site_visit_instructions)); ?></div>
                        </div>
                    <?php } ?>

                    <div class="mt15">
                        <div class="text-muted mb5">Required Submission Documents</div>
                        <div class="vtd-requirements">
                            <?php if ($required_sections) { ?>
                                <?php foreach ($required_sections as $section) { ?>
                                    <span class="badge bg-info text-dark"><?php echo esc($section_labels[$section] ?? $section); ?></span>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-muted">No required document rules configured.</span>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="vtd-section">
                <div class="vtd-section-header">
                    <div class="vtd-section-title">
                        <i data-feather="clipboard" class="icon-16"></i>
                        <h4>Tender Details</h4>
                    </div>
                </div>
                <div class="vtd-section-body">
                    <div class="vtd-info-grid mb15">
                        <div class="vtd-info"><span>PR No (Optional)</span><strong><?php echo esc($rfq_detail->pr_no ?? "-"); ?></strong></div>
                        <div class="vtd-info"><span>Delivery Location</span><strong><?php echo esc($rfq_detail->delivery_location ?? "-"); ?></strong></div>
                        <div class="vtd-info"><span>INCOTERM</span><strong><?php echo esc($rfq_detail->incoterm ?? "-"); ?></strong></div>
                        <div class="vtd-info"><span>Estimated Material/Service Required On</span><strong><?php echo !empty($rfq_detail->material_required_on) ? format_to_date($rfq_detail->material_required_on, false) : "-"; ?></strong></div>
                    </div>

                    <?php if (!empty($rfq_detail->terms_reference)) { ?>
                        <div class="vtd-info mb15">
                            <span>Terms & Conditions</span>
                            <div><?php echo esc($rfq_detail->terms_reference); ?></div>
                        </div>
                    <?php } ?>

                    <?php if ($rfq_items) { ?>
                        <div class="vtd-table table-responsive">
                            <table class="table table-bordered table-striped vtd-rfq-pricing-table">
                                <thead>
                                    <tr>
                                        <th>Sr No</th>
                                        <th>Description</th>
                                        <th>UOM</th>
                                        <th>Qty</th>
                                        <th>Part No (Optional)</th>
                                        <th style="width: 155px;">Your Unit Price</th>
                                        <th style="width: 155px;">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rfq_items as $item) { ?>
                                        <?php
                                        $item_id = (int) ($item->id ?? 0);
                                        $price_row = $bid_item_price_map[$item_id] ?? null;
                                        $saved_unit_price = $price_row->unit_price ?? "";
                                        $saved_line_total = $price_row->line_total ?? "";
                                        ?>
                                        <tr>
                                            <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                                            <td><?php echo esc($item->description ?? "-"); ?></td>
                                            <td><?php echo esc($item->uom ?? "-"); ?></td>
                                            <td><?php echo $item->qty !== null ? number_format((float) $item->qty, 3) : "-"; ?></td>
                                            <td><?php echo esc($item->part_no ?? ($item->brand ?? "-")); ?></td>
                                            <td>
                                                <?php if ($submission_open) { ?>
                                                    <input
                                                        type="number"
                                                        step="0.001"
                                                        min="0"
                                                        name="rfq_item_unit_price[<?php echo $item_id; ?>]"
                                                        form="vendor-bid-page-form"
                                                        class="form-control form-control-sm vtd-item-unit-price"
                                                        data-rfq-qty="<?php echo esc($item->qty ?? ""); ?>"
                                                        value="<?php echo esc($saved_unit_price); ?>"
                                                        required />
                                                <?php } else { ?>
                                                    <?php echo $saved_unit_price !== "" ? number_format((float) $saved_unit_price, 3) : "-"; ?>
                                                <?php } ?>
                                            </td>
                                            <td class="text-end">
                                                <span class="vtd-item-line-total" data-line-total>
                                                    <?php echo $saved_line_total !== "" ? number_format((float) $saved_line_total, 3) : "-"; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="vtd-total-strip mt10">
                            <span>Calculated Bid Total</span>
                            <strong class="bid-item-pricing-total" data-bid-total-display>
                                <?php
                                $display_total = $saved_item_total > 0 ? $saved_item_total : (float) ($bid->total_amount ?? 0);
                                echo number_format($display_total, 3) . " " . esc($bid->currency ?? "OMR");
                                ?>
                            </strong>
                        </div>
                    <?php } else { ?>
                        <div class="text-muted">No tender item lines have been published for this tender.</div>
                    <?php } ?>
                </div>
            </section>

            <section class="vtd-section">
                <div class="vtd-section-header">
                    <div class="vtd-section-title">
                        <i data-feather="folder" class="icon-16"></i>
                        <h4>Tender Documents</h4>
                    </div>
                </div>
                <div class="vtd-section-body">
                    <?php if (count($docs)) { ?>
                        <div class="vtd-document-list">
                            <?php foreach ($docs as $doc) { ?>
                                <div class="vtd-document-row">
                                    <div>
                                        <div class="vtd-document-title"><?php echo esc($doc->title ?: ($doc->original_name ?: basename($doc->path))); ?></div>
                                        <div class="vtd-document-meta">
                                            <?php echo esc($doc->doc_type ?: "-"); ?> / <?php echo esc($doc->original_name ?: basename($doc->path)); ?>
                                            <?php if ((int) ($doc->time_limited ?? 0) === 1) { ?>
                                                / Time-limited<?php echo !empty($doc->expires_in_hours) ? " (" . (int) $doc->expires_in_hours . "h)" : ""; ?>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php echo js_anchor(
                                            "<i data-feather='eye' class='icon-14'></i> Preview",
                                            [
                                                "title" => "Preview Document",
                                                "class" => "btn btn-primary btn-sm",
                                                "data-toggle" => "app-modal",
                                                "data-sidebar" => "0",
                                                "data-url" => get_uri("vendor_portal/preview_tender_document/" . (int) $doc->id),
                                            ]
                                        ); ?>
                                        <a href="<?php echo get_uri('vendor_portal/download_tender_document/' . (int) $doc->id); ?>" class="btn btn-default btn-sm">
                                            <i data-feather="download" class="icon-14"></i> Download
                                        </a>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="text-muted">No tender documents have been uploaded yet.</div>
                    <?php } ?>
                </div>
            </section>

            <section class="vtd-section">
                <div class="vtd-section-header">
                    <div class="vtd-section-title">
                        <i data-feather="message-square" class="icon-16"></i>
                        <h4>Clarifications & Replies</h4>
                    </div>
                </div>
                <div class="vtd-section-body">
                    <?php if ($clarification_open) { ?>
                        <?php echo form_open_multipart(get_uri("vendor_portal/save_clarification"), [
                            "id" => "vendor-clarification-form",
                            "class" => "general-form mb15",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                            <div class="form-group">
                                <label>Clarification Type</label>
                                <?php echo form_dropdown(
                                    "clarification_scope",
                                    $clarification_scope_options,
                                    "general",
                                    "class='form-control select2'"
                                ); ?>
                            </div>
                            <div class="form-group">
                                <label>Send Clarification</label>
                                <textarea name="message" class="form-control" rows="3" required placeholder="Write your clarification question here"></textarea>
                            </div>
                            <div class="form-group">
                                <label>Attach Files</label>
                                <input type="file" name="clarification_files[]" class="form-control" multiple>
                            </div>
                            <button type="submit" class="btn btn-default">
                                <i data-feather="send" class="icon-16"></i> Submit Clarification
                            </button>
                        <?php echo form_close(); ?>
                    <?php } else { ?>
                        <div class="alert alert-light">Clarification submissions are closed for this tender.</div>
                    <?php } ?>

                    <?php if ($clarifications) { ?>
                        <div class="vtd-chat">
                            <?php foreach ($clarifications as $item) { ?>
                                <?php
                                $is_vendor_message = strtolower((string) ($item->type ?? "")) === "clarification"
                                    && (empty($item->parent_id) || (int) $item->parent_id === 0);
                                $sender_name = $is_vendor_message
                                    ? "You"
                                    : (trim((string) ($item->created_by_name ?? "")) ?: "Procurement");
                                $message_time = $item->published_at ?: $item->created_at;
                                $scope_label = \App\Models\Tender_communications_model::clarification_scope_label($item->clarification_scope ?? "general");
                                $item_attachments = $clarification_attachments[(int) $item->id] ?? [];
                                ?>
                                <div class="d-flex mb10 <?php echo $is_vendor_message ? "justify-content-end" : "justify-content-start"; ?>">
                                    <div class="vtd-chat-bubble <?php echo $is_vendor_message ? "vtd-chat-self" : "vtd-chat-other"; ?>">
                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 small mb5 opacity-75">
                                            <div class="fw-semibold"><?php echo esc($sender_name); ?></div>
                                            <div><?php echo !empty($message_time) ? format_to_datetime($message_time) : "-"; ?></div>
                                        </div>
                                        <div class="mb5"><span class="badge bg-light text-dark"><?php echo esc($scope_label); ?></span></div>
                                        <?php if (!empty($item->subject)) { ?>
                                            <div class="fw-semibold mb5"><?php echo esc($item->subject); ?></div>
                                        <?php } ?>
                                        <div><?php echo nl2br(esc($item->message ?? "")); ?></div>
                                        <?php if (!empty($item_attachments)) { ?>
                                            <div class="clarification-attachments mt10">
                                                <?php foreach ($item_attachments as $attachment) { ?>
                                                    <a href="<?php echo get_uri("vendor_portal/download_clarification_attachment/" . (int) $attachment->id); ?>" class="badge bg-light text-dark me-1 mb5">
                                                        <i data-feather="paperclip" class="icon-14"></i>
                                                        <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="text-muted">No clarifications submitted yet.</div>
                    <?php } ?>
                </div>
            </section>
        </main>

        <aside class="vtd-side">
            <section class="vtd-section">
                <div class="vtd-section-header">
                    <div class="vtd-section-title">
                        <i data-feather="upload-cloud" class="icon-16"></i>
                        <h4>Bid Submission</h4>
                    </div>
                </div>
                <div class="vtd-section-body">
                    <?php if (($tender->status ?? "") === "awarded" && !empty($bid)) { ?>
                        <?php if (!empty($is_awarded_to_vendor)) { ?>
                            <div class="alert alert-success">
                                <strong>Congratulations.</strong> Your bid has been awarded for this tender.
                                <?php if (isset($latest_commercial_evaluation->total_score)) { ?>
                                    <div class="mt5">Commercial Score: <strong><?php echo number_format((float) $latest_commercial_evaluation->total_score, 3); ?></strong></div>
                                <?php } ?>
                            </div>
                        <?php } elseif (!empty($is_regretted_vendor)) { ?>
                            <div class="alert alert-danger">This tender has been awarded to another vendor.</div>
                        <?php } ?>
                    <?php } elseif (($tender->workflow_stage ?? "") === "award_decision" && !empty($bid) && strtolower((string) ($latest_commercial_evaluation->decision ?? "")) === "accepted") { ?>
                        <div class="alert alert-info">Your bid is currently commercially accepted and awaiting final award confirmation.</div>
                    <?php } ?>

                    <div class="vtd-bid-status">
                        <div class="text-muted mb5">Current Bid Status</div>
                        <?php if ($bid) { ?>
                            <strong><?php echo esc(ucfirst($bid->status)); ?></strong>
                            <?php if (!empty($bid->submitted_at)) { ?>
                                <div class="small text-muted mt5">Submitted At: <?php echo format_to_datetime($bid->submitted_at); ?></div>
                            <?php } ?>
                        <?php } else { ?>
                            <strong>Not submitted</strong>
                            <div class="small text-muted mt5">Submit all required documents before the deadline.</div>
                        <?php } ?>
                    </div>

                    <?php if ($submission_period_open && $tender_fee_required) { ?>
                        <?php if ($tender_fee_paid) { ?>
                            <div class="vtd-approval-card">
                                <strong>Tender fee payment is marked as paid.</strong>
                                <div class="small mt5">
                                    <?php echo $money_value($tender->tender_fee ?? null); ?>
                                    <?php if (!empty($tender->fee_paid_at)) { ?>
                                        · Paid At: <?php echo format_to_datetime($tender->fee_paid_at); ?>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="vtd-approval-card is-pending">
                                <strong>Tender fee payment is required before applying.</strong>
                                <div class="small mt5">You will be redirected to the secure payment provider. Payment is confirmed only after the provider notification is verified.</div>
                                <?php echo form_open(get_uri("vendor_portal/pay_tender_fee"), [
                                    "id" => "vendor-tender-fee-payment-form",
                                    "class" => "general-form mt10",
                                    "role" => "form"
                                ]); ?>
                                    <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i data-feather="credit-card" class="icon-14"></i> Pay Tender Fee
                                    </button>
                                <?php echo form_close(); ?>
                            </div>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($submission_period_open && $tender_fee_paid && !$procurement_approved_for_submission) { ?>
                        <?php if ($procurement_approval_status === "pending_approval") { ?>
                            <div class="vtd-approval-card is-pending">
                                <strong>Participation request is pending procurement approval.</strong>
                                <div class="small mt5">You can submit the bid after procurement approves this tender participation request.</div>
                            </div>
                        <?php } elseif (in_array($procurement_approval_status, ["rejected", "declined"], true)) { ?>
                            <div class="vtd-approval-card is-rejected">
                                <strong>Procurement did not approve this participation request.</strong>
                                <div class="small mt5">Please contact procurement if you need this request reviewed again.</div>
                                <?php echo form_open(get_uri("vendor_portal/request_tender_approval"), [
                                    "id" => "vendor-tender-approval-form",
                                    "class" => "general-form mt10",
                                    "role" => "form"
                                ]); ?>
                                    <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i data-feather="send" class="icon-14"></i> Request Procurement Approval
                                    </button>
                                <?php echo form_close(); ?>
                            </div>
                        <?php } else { ?>
                            <div class="vtd-approval-card">
                                <strong>Procurement approval is required before submitting a bid.</strong>
                                <div class="small mt5">Request participation approval for this active tender. Once approved, the bid upload form will be available here.</div>
                                <?php echo form_open(get_uri("vendor_portal/request_tender_approval"), [
                                    "id" => "vendor-tender-approval-form",
                                    "class" => "general-form mt10",
                                    "role" => "form"
                                ]); ?>
                                    <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i data-feather="send" class="icon-14"></i> Request Procurement Approval
                                    </button>
                                <?php echo form_close(); ?>
                            </div>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($submission_open) { ?>
                        <?php echo form_open_multipart(get_uri("vendor_portal/save_bid"), [
                            "id" => "vendor-bid-page-form",
                            "class" => "general-form",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />

                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label>Total Amount</label>
                                        <input
                                            type="number"
                                            step="0.001"
                                            name="total_amount"
                                            class="form-control"
                                            value="<?php echo esc($bid->total_amount ?? ($saved_item_total > 0 ? number_format($saved_item_total, 3, ".", "") : "")); ?>"
                                            <?php echo $rfq_items ? "readonly" : ""; ?>>
                                        <?php if ($rfq_items) { ?>
                                            <small class="text-muted">Calculated from your tender item prices.</small>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label>Currency</label>
                                        <input type="text" name="currency" class="form-control" value="<?php echo esc($bid->currency ?? "OMR"); ?>" maxlength="3">
                                    </div>
                                </div>
                            </div>

                            <?php foreach ($section_labels as $section => $label) { ?>
                                <?php
                                $field_name = $section_fields[$section];
                                $current_doc = $documents_map[$section] ?? null;
                                $is_required = in_array($section, $required_sections, true);
                                ?>
                                <div class="vtd-upload-row">
                                    <div class="vtd-upload-title">
                                        <span><?php echo esc($label); ?></span>
                                        <?php if ($is_required) { ?>
                                            <span class="badge bg-danger">Required</span>
                                        <?php } else { ?>
                                            <span class="badge bg-secondary">Optional</span>
                                        <?php } ?>
                                    </div>
                                    <input type="file" name="<?php echo esc($field_name); ?>" class="form-control" <?php echo ($is_required && empty($current_doc)) ? "required" : ""; ?>>
                                    <?php if (!empty($current_doc)) { ?>
                                        <div class="small text-muted mt5">Current: <?php echo esc($current_doc->original_name ?? "-"); ?></div>
                                        <a href="<?php echo get_uri('vendor_portal/download_bid_document/' . (int) $current_doc->id); ?>" class="btn btn-default btn-sm mt5">
                                            <i data-feather="download" class="icon-14"></i> Download Current File
                                        </a>
                                    <?php } elseif (!$is_required) { ?>
                                        <div class="small text-muted mt5">No file uploaded.</div>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <button type="submit" class="btn btn-primary w-100">
                                <i data-feather="check-circle" class="icon-16"></i>
                                <?php echo !empty($bid) ? "Update Bid" : "Submit Bid"; ?>
                            </button>
                        <?php echo form_close(); ?>
                    <?php } elseif (!$submission_period_open) { ?>
                        <div class="alert alert-warning mb0">
                            Bid submission is closed for this tender.
                            <?php if (($tender->status ?? "") === "closed") { ?>
                                <strong>The tender is now officially closed.</strong>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </section>
        </aside>
    </div>
</div>

<script>
$(document).ready(function () {
    function updateBidItemTotals() {
        var total = 0;

        $(".vtd-item-unit-price").each(function () {
            var $input = $(this);
            var unitPrice = parseFloat($input.val());
            var qty = parseFloat($input.data("rfq-qty"));
            var $lineTotal = $input.closest("tr").find("[data-line-total]");

            if (!isNaN(unitPrice) && !isNaN(qty)) {
                var lineTotal = unitPrice * qty;
                total += lineTotal;
                $lineTotal.text(lineTotal.toFixed(3));
            } else {
                $lineTotal.text("-");
            }
        });

        if ($(".vtd-item-unit-price").length) {
            var currency = $.trim($("[name='currency']").val() || "OMR") || "OMR";
            $("[name='total_amount']").val(total.toFixed(3));
            $("[data-bid-total-display]").text(total.toFixed(3) + " " + currency);
        }
    }

    $(document).on("input", ".vtd-item-unit-price, [name='currency']", updateBidItemTotals);
    updateBidItemTotals();

    var bidForm = $("#vendor-bid-page-form");
    if (bidForm.length) {
        bidForm.appForm({
            onSuccess: function (result) {
                appAlert.success((result && result.message) || "Bid saved successfully.", {duration: 2200});
                setTimeout(function () {
                    window.location.reload();
                }, 500);
            }
        });
    }

    var approvalForm = $("#vendor-tender-approval-form");
    if (approvalForm.length) {
        approvalForm.appForm({
            onSuccess: function (result) {
                appAlert.success((result && result.message) || "Participation request sent.", {duration: 2200});
                setTimeout(function () {
                    window.location.reload();
                }, 500);
            }
        });
    }

    var feePaymentForm = $("#vendor-tender-fee-payment-form");
    if (feePaymentForm.length) {
        feePaymentForm.appForm({
            onSuccess: function (result) {
                if (result && result.checkout_url) {
                    window.location.assign(result.checkout_url);
                    return;
                }

                appAlert.success((result && result.message) || "Payment status refreshed.", {duration: 2200});
                window.location.reload();
            }
        });
    }

    var clarificationForm = $("#vendor-clarification-form");
    if (clarificationForm.length) {
        clarificationForm.appForm({
            onSuccess: function (result) {
                appAlert.success((result && result.message) || "Clarification submitted successfully.", {duration: 2200});
                setTimeout(function () {
                    window.location.reload();
                }, 500);
            }
        });
    }

    var clarificationChat = document.querySelector(".vtd-chat");
    if (clarificationChat) {
        clarificationChat.scrollTop = clarificationChat.scrollHeight;
    }

    if (window.feather) {
        feather.replace();
    }
});
</script>
