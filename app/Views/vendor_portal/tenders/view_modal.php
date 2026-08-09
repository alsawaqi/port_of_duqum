<?php
$docs = $docs ?? [];
$bid = $bid ?? null;
$required_sections = $required_sections ?? [];
$clarifications = $clarifications ?? [];
$clarification_open = $clarification_open ?? false;
$clarification_attachments = $clarification_attachments ?? [];
$clarification_scope_options = $clarification_scope_options ?? ["general" => "General", "tender" => "Tender / Procurement", "technical" => "Technical Team", "commercial" => "Commercial Team", "vendor" => "Vendor Specific"];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];
$procurement_approved_for_submission = !empty($procurement_approved_for_submission);
$procurement_approval_status = $procurement_approval_status ?? ($procurement_approved_for_submission ? "approved" : "required");
$tender_fee_required = isset($tender_fee_required) ? (bool) $tender_fee_required : ((float) ($tender->tender_fee ?? 0) > 0);
$tender_fee_paid = isset($tender_fee_paid) ? (bool) $tender_fee_paid : !$tender_fee_required;
$tender_fee_payment_status = $tender_fee_payment_status ?? ($tender_fee_required ? ($tender_fee_paid ? "paid" : "unpaid") : "not_required");

$submission_open = (($tender->status ?? "") === "published") && (($tender->workflow_stage ?? "bidding") === "bidding");
if ($submission_open && !empty($tender->closing_at) && strtotime($tender->closing_at) <= time()) {
    $submission_open = false;
}
$submission_open = $submission_open && $procurement_approved_for_submission;

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

$money_value = function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$fee_payment_status_label = function (string $status) {
    $labels = [
        "paid" => "Paid",
        "unpaid" => "Payment required",
        "not_required" => "Not required",
    ];

    return $labels[$status] ?? ucwords(str_replace("_", " ", $status ?: "-"));
};

$section_labels = [
    "technical" => "Technical Proposal",
    "commercial_priced" => "Commercial Proposal (With Price)",
    "commercial_unpriced" => "Commercial Proposal (Without Price)",
    "bank_guarantee" => "Bank Guarantee Documents",
];
?>

<div class="modal-body">
    <div class="mb15">
        <h4 class="mb-1"><?php echo esc($tender->title ?: "-"); ?></h4>
        <div class="text-muted"><?php echo esc($tender->reference ?: "-"); ?></div>
    </div>

    <?php if (!empty($tender->brief_description)) { ?>
        <div class="alert alert-light">
            <?php echo nl2br(esc($tender->brief_description)); ?>
        </div>
    <?php } ?>

    <div class="row">
        <div class="col-md-4 mb15">
            <div class="text-muted">Tender Type</div>
            <div>
                <?php if (($tender->tender_type ?? "open") === "close") { ?>
                    <span class="badge bg-warning">CLOSE</span>
                <?php } else { ?>
                    <span class="badge bg-success">OPEN</span>
                <?php } ?>
            </div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Tender Status</div>
            <div>
                <?php
                $status = strtolower((string) ($tender->status ?? "draft"));
                $status_classes = [
                    "draft" => "secondary",
                    "published" => "primary",
                    "closed" => "dark",
                    "awarded" => "success",
                    "cancelled" => "danger",
                ];
                ?>
                <span class="badge bg-<?php echo $status_classes[$status] ?? "secondary"; ?>">
                    <?php echo esc(ucfirst($status)); ?>
                </span>
            </div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Participation Approval</div>
            <div><?php echo esc($participation_status_label($procurement_approval_status)); ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Tender Fees</div>
            <div><?php echo $money_value($tender->tender_fee ?? null); ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Fee Payment Status</div>
            <div><?php echo esc($fee_payment_status_label($tender_fee_payment_status)); ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Tender Release Date</div>
            <div><?php echo !empty($tender->release_at) ? format_to_datetime($tender->release_at) : (!empty($tender->published_at) ? format_to_datetime($tender->published_at) : "-"); ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Clarification Deadline</div>
            <div><?php echo !empty($tender->clarification_deadline) ? format_to_datetime($tender->clarification_deadline) : "-"; ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Submission Deadline</div>
            <div><?php echo !empty($tender->closing_at) ? format_to_datetime($tender->closing_at) : "-"; ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Document Purchase Deadline</div>
            <div><?php echo !empty($tender->document_purchase_deadline) ? format_to_datetime($tender->document_purchase_deadline) : "-"; ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Site Visit Date / Deadline</div>
            <div><?php echo !empty($tender->site_visit_at) ? format_to_datetime($tender->site_visit_at) : "-"; ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Site Visit Location</div>
            <div><?php echo esc($tender->site_visit_location ?? "-"); ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Site Visit Attendance</div>
            <div><?php echo !empty($tender->site_visit_mandatory) ? "<span class='badge bg-warning text-dark'>Mandatory</span>" : "<span class='badge bg-light text-dark'>Optional</span>"; ?></div>
        </div>

        <div class="col-md-12 mb15">
            <div class="text-muted">Site Visit Instructions</div>
            <div><?php echo !empty($tender->site_visit_instructions) ? nl2br(esc($tender->site_visit_instructions)) : "-"; ?></div>
        </div>

        <div class="col-md-4 mb15">
            <div class="text-muted">Bid Opening Date</div>
            <div><?php echo !empty($tender->bid_opening_at) ? format_to_datetime($tender->bid_opening_at) : "-"; ?></div>
        </div>

        <div class="col-md-6 mb15">
            <div class="text-muted">Target Specialty</div>
            <div>
                <?php
                $target = $tender->vendor_category_name ?: "-";
                if (!empty($tender->vendor_sub_category_name)) {
                    $target .= " / " . $tender->vendor_sub_category_name;
                }
                echo esc($target);
                ?>
            </div>
        </div>

        <div class="col-md-6 mb15">
            <div class="text-muted">Required Submission Documents</div>
            <div>
                <?php if ($required_sections) { ?>
                    <?php foreach ($required_sections as $section) { ?>
                        <span class="badge bg-info text-dark mb5"><?php echo esc($section_labels[$section] ?? $section); ?></span>
                    <?php } ?>
                <?php } else { ?>
                    -
                <?php } ?>
            </div>
        </div>
    </div>

    <hr>
    <h5 class="mb15">Tender Details</h5>

    <div class="row">
        <div class="col-md-4 mb15"><div class="text-muted">PR No (Optional)</div><div><?php echo esc($rfq_detail->pr_no ?? "-"); ?></div></div>
        <div class="col-md-4 mb15"><div class="text-muted">Delivery Location</div><div><?php echo esc($rfq_detail->delivery_location ?? "-"); ?></div></div>
        <div class="col-md-4 mb15"><div class="text-muted">INCOTERM</div><div><?php echo esc($rfq_detail->incoterm ?? "-"); ?></div></div>
        <div class="col-md-4 mb15"><div class="text-muted">Estimated Material/Service Required On</div><div><?php echo !empty($rfq_detail->material_required_on) ? format_to_date($rfq_detail->material_required_on, false) : "-"; ?></div></div>
        <div class="col-md-12 mb15"><div class="text-muted">Terms & Conditions</div><div><?php echo esc($rfq_detail->terms_reference ?? "-"); ?></div></div>
    </div>

    <?php if ($rfq_items) { ?>
        <div class="table-responsive mb15">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Sr No</th><th>Description</th><th>UOM</th><th>Qty</th><th>Part No (Optional)</th></tr></thead>
                <tbody>
                    <?php foreach ($rfq_items as $item) { ?>
                        <tr>
                            <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                            <td><?php echo esc($item->description ?? "-"); ?></td>
                            <td><?php echo esc($item->uom ?? "-"); ?></td>
                            <td><?php echo $item->qty !== null ? number_format((float) $item->qty, 3) : "-"; ?></td>
                            <td><?php echo esc($item->part_no ?? ($item->brand ?? "-")); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>

    <hr>

    <h5 class="mb10">Your Bid Status</h5>

    <?php if (($tender->status ?? "") === "awarded" && !empty($bid)) { ?>
        <?php if (!empty($is_awarded_to_vendor)) { ?>
            <div class="alert alert-success mb15">
                <strong>Congratulations.</strong> Your bid has been awarded for this tender.
                <?php if (isset($latest_commercial_evaluation->total_score)) { ?>
                    <div class="mt5">Commercial Score: <strong><?php echo number_format((float) $latest_commercial_evaluation->total_score, 3); ?></strong></div>
                <?php } ?>
            </div>
        <?php } elseif (!empty($is_regretted_vendor)) { ?>
            <div class="alert alert-danger mb15">
                This tender has been awarded to another vendor.
            </div>
        <?php } ?>
    <?php } elseif (($tender->workflow_stage ?? "") === "award_decision" && !empty($bid) && strtolower((string) ($latest_commercial_evaluation->decision ?? "")) === "accepted") { ?>
        <div class="alert alert-info mb15">
            Your bid is currently the commercially accepted bid and is awaiting final award confirmation.
        </div>
    <?php } ?>

    <?php if ($bid) { ?>
        <div class="mb15">
            <span class="badge bg-success"><?php echo esc(ucfirst($bid->status)); ?></span>
            <?php if (!empty($bid->submitted_at)) { ?>
                <div class="text-muted mt5">Submitted At: <?php echo format_to_datetime($bid->submitted_at); ?></div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="text-muted mb15">You have not submitted a bid yet.</div>
    <?php } ?>

    <?php if ($submission_open) { ?>
        <div class="mb20">
            <?php
            echo modal_anchor(
                get_uri("vendor_portal/bid_modal"),
                "<i data-feather='upload' class='icon-16'></i> " . (!empty($bid) ? "Update Bid" : "Submit Bid"),
                [
                    "class" => "btn btn-primary",
                    "title" => !empty($bid) ? "Update Bid" : "Submit Bid",
                    "data-post-tender_id" => $tender->id
                ]
            );
            ?>
        </div>
    <?php } else { ?>
        <div class="alert alert-warning">
            Bid submission is closed for this tender.
            <?php if (($tender->status ?? "") === "closed") { ?>
                <strong>The tender is now officially closed.</strong>
            <?php } ?>
        </div>
    <?php } ?>

    <hr>
    <h5 class="mb15">Tender Documents</h5>

    <?php if (count($docs)) { ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>File Name</th>
                        <th>Access</th>
                        <th class="text-center" style="width: 110px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $doc) { ?>
                        <tr>
                            <td><?php echo esc($doc->doc_type ?: "-"); ?></td>
                            <td><?php echo esc($doc->title ?: "-"); ?></td>
                            <td><?php echo esc($doc->original_name ?: basename($doc->path)); ?></td>
                            <td>
                                <?php if ((int) ($doc->time_limited ?? 0) === 1) { ?>
                                    <span class="badge bg-warning">
                                        Time-limited
                                        <?php if (!empty($doc->expires_in_hours)) { ?>
                                            (<?php echo (int) $doc->expires_in_hours; ?>h)
                                        <?php } ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-success">Standard</span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <?php echo js_anchor(
                                    "<i data-feather='eye' class='icon-14'></i> Preview",
                                    [
                                        "title" => "Preview Document",
                                        "class" => "btn btn-primary btn-sm me-1",
                                        "data-toggle" => "app-modal",
                                        "data-sidebar" => "0",
                                        "data-url" => get_uri("vendor_portal/preview_tender_document/" . (int) $doc->id),
                                    ]
                                ); ?>
                                <a href="<?php echo get_uri('vendor_portal/download_tender_document/' . $doc->id); ?>" class="btn btn-default btn-sm">
                                    <i data-feather="download" class="icon-14"></i> Download
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="text-muted">No documents uploaded yet.</div>
    <?php } ?>

    <hr>
    <h5 class="mb15">Clarifications & Replies</h5>

    <?php if ($clarification_open) { ?>
        <?php echo form_open_multipart(get_uri("vendor_portal/save_clarification"), [
            "id" => "vendor-clarification-form",
            "class" => "general-form",
            "role" => "form"
        ]); ?>
            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
            <div class="form-group">
                <label>Clarification Type</label>
                <?php echo form_dropdown("clarification_scope", $clarification_scope_options, "general", "class='form-control'"); ?>
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
        <div class="alert alert-light">
            Clarification submissions are closed for this tender.
        </div>
    <?php } ?>

    <div class="mt15">
        <?php if ($clarifications) { ?>
            <div class="vendor-clarification-chat">
            <?php foreach ($clarifications as $item) { ?>
                <?php
                $is_vendor_message = strtolower((string) ($item->type ?? '')) === 'clarification'
                    && (empty($item->parent_id) || (int) $item->parent_id === 0);
                $sender_name = $is_vendor_message
                    ? "You"
                    : (trim((string) ($item->created_by_name ?? '')) ?: "Procurement");
                $message_time = $item->published_at ?: $item->created_at;
                $scope_label = \App\Models\Tender_communications_model::clarification_scope_label($item->clarification_scope ?? "general");
                $item_attachments = $clarification_attachments[(int) $item->id] ?? [];
                ?>
                <div class="d-flex mb10 <?php echo $is_vendor_message ? 'justify-content-end' : 'justify-content-start'; ?>">
                    <div class="vendor-chat-bubble <?php echo $is_vendor_message ? 'vendor-chat-bubble-self' : 'vendor-chat-bubble-other'; ?>">
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
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
</div>

<script>
$(document).ready(function () {
    if ($("#vendor-clarification-form").length) {
        $("#vendor-clarification-form").appForm({
            onSuccess: function (result) {
                appAlert.success(result.message || "Saved successfully.", {duration: 2000});
                setTimeout(function () {
                    window.location.reload();
                }, 400);
            }
        });
    }

    var clarificationChat = document.querySelector(".vendor-clarification-chat");
    if (clarificationChat) {
        clarificationChat.scrollTop = clarificationChat.scrollHeight;
    }

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>

<style>
.vendor-clarification-chat {
    background: #f7f9fc;
    border: 1px solid #e5e9f2;
    border-radius: 12px;
    padding: 14px;
    max-height: 340px;
    overflow-y: auto;
}

.vendor-chat-bubble {
    max-width: 78%;
    padding: 12px 14px;
    border-radius: 14px;
    box-shadow: 0 6px 12px rgba(20, 31, 56, 0.06);
}

.vendor-chat-bubble-self {
    background: #103d73;
    color: #fff;
}

.vendor-chat-bubble-other {
    background: #fff;
    border: 1px solid #dfe6f1;
    color: #223047;
}
</style>
