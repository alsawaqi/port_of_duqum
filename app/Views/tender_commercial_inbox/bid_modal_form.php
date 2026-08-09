<?php echo form_open_multipart(get_uri('tender_commercial_inbox/save_bid_evaluation'), ['id' => 'tender-commercial-bid-form', 'class' => 'general-form']); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
<input type="hidden" name="bid_id" value="<?php echo (int) ($bid->id ?? 0); ?>" />

<?php
$bid_item_prices = $bid_item_prices ?? [];
$finding_attachments = $finding_attachments ?? [];
$internal_messages = $internal_messages ?? [];
$internal_attachments = $internal_attachments ?? [];

$current_decision = strtolower(trim((string) ($active_evaluation->decision ?? $latest_evaluation->decision ?? '')));
if (!in_array($current_decision, ['accepted', 'rejected'], true)) {
    $current_decision = '';
}

$locked_by_other = !$editable;
$owner_name = trim((string) ($latest_evaluation->evaluator_name ?? ''));
if ($owner_name === '') {
    $owner_name = $latest_evaluation->evaluator_email ?? 'another evaluator';
}

$doc_button = function ($doc_id, string $label) {
    if (empty($doc_id)) {
        return "<span class='badge bg-light text-dark mb5'>" . esc($label) . ": missing</span>";
    }

    $preview = js_anchor(
        "<i data-feather='eye' class='icon-14'></i> " . esc($label),
        [
            "title" => "Preview " . $label,
            "class" => "btn btn-primary btn-sm mb5 me-1",
            "data-toggle" => "app-modal",
            "data-sidebar" => "0",
            "data-url" => get_uri("tender_commercial_inbox/preview_bid_document/" . (int) $doc_id),
        ]
    );

    $download = anchor(
        get_uri("tender_commercial_inbox/download_bid_document/" . (int) $doc_id),
        "<i data-feather='download' class='icon-14'></i>",
        ["class" => "btn btn-default btn-sm mb5 me-1", "title" => "Download " . $label]
    );

    return "<span class='d-inline-flex flex-wrap align-items-center gap-1 me-1'>" . $preview . $download . "</span>";
};
?>

<div class="modal-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
        <div>
            <h4 class="mb5">Commercial Review - <?php echo esc($bid->vendor_name ?? '-'); ?></h4>
            <div class="text-off">Tender: <?php echo esc($tender->reference ?? '-'); ?> / <?php echo esc($tender->title ?? '-'); ?></div>
        </div>
        <div>
            <?php if ($current_decision === '') { ?>
                <span class="badge bg-warning text-dark">Pending</span>
            <?php } elseif ($current_decision === 'accepted') { ?>
                <span class="badge bg-success">Approved</span>
            <?php } else { ?>
                <span class="badge bg-danger">Rejected</span>
            <?php } ?>
        </div>
    </div>

    <div class="row mb15">
    <div class="col-md-4 mb10"><strong>Submitted At:</strong><br><?php echo !empty($bid->submitted_at) ? format_to_datetime($bid->submitted_at) : '-'; ?></div>
    <div class="col-md-4 mb10"><strong>Current Score:</strong><br><?php echo isset($active_evaluation->total_score) ? number_format((float) ($active_evaluation->total_score ?? 0), 3) : '0.000'; ?> / 100.000</div>
    <div class="col-md-4 mb10">
        <strong>Quoted Amount:</strong><br>
            <?php
            if ($bid->total_amount !== null && $bid->total_amount !== "") {
                echo number_format((float) $bid->total_amount, 3) . " " . esc($bid->currency ?? "OMR");
            } else {
                echo "-";
            }
            ?>
        </div>
    </div>

    <div class="mb15">
        <h5 class="mb10">Item Price Breakdown</h5>
        <?php if ($bid_item_prices) { ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Description</th>
                            <th>UOM</th>
                            <th>Qty</th>
                            <th>Part No (Optional)</th>
                            <th>Vendor Unit Price</th>
                            <th>Line Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bid_item_prices as $item) { ?>
                            <tr>
                                <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                                <td><?php echo esc($item->description ?? "-"); ?></td>
                                <td><?php echo esc($item->uom ?? "-"); ?></td>
                                <td><?php echo $item->qty !== null ? number_format((float) $item->qty, 3) : "-"; ?></td>
                                <td><?php echo esc($item->part_no ?? ($item->brand ?? "-")); ?></td>
                                <td>
                                    <?php echo $item->vendor_unit_price !== null && $item->vendor_unit_price !== "" ? number_format((float) $item->vendor_unit_price, 3) : "-"; ?>
                                </td>
                                <td>
                                    <?php echo $item->line_total !== null && $item->line_total !== "" ? number_format((float) $item->line_total, 3) : "-"; ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <div class="alert alert-light mb0">No tender item prices were submitted for this bid.</div>
        <?php } ?>
    </div>

    <div class="row mb15">
        <div class="col-md-12">
            <strong>Accessible Bid Documents:</strong><br>
            <div class="mt5">
                <?php echo $doc_button($bid->technical_doc_id ?? 0, "Technical Proposal"); ?>
                <?php echo $doc_button($bid->commercial_unpriced_doc_id ?? 0, "Commercial Without Price"); ?>
                <?php echo $doc_button($bid->commercial_doc_id ?? 0, "Commercial With Price"); ?>
                <?php echo $doc_button($bid->bank_guarantee_doc_id ?? 0, "Bank Guarantee"); ?>
            </div>
        </div>
    </div>

    <?php if ($locked_by_other && $current_decision !== '') { ?>
        <div class="alert alert-warning">
            This bid has already been finalized by <strong><?php echo esc($owner_name); ?></strong>. You can only view it here.
        </div>
    <?php } elseif ($editable && $current_decision !== '') { ?>
        <div class="alert alert-info">
            You finalized this bid earlier. You can still edit your own commercial evaluation.
        </div>
    <?php } else { ?>
        <div class="alert alert-info">
            Review the commercial amount and proposal, then mark this bid as commercially approved or rejected. Only one bid should be approved in the tender.
        </div>
    <?php } ?>

    <div class="form-group mb15">
        <label><strong>Commercial Decision</strong></label>
        <select name="decision" class="form-control" <?php echo $editable ? '' : 'disabled'; ?>>
            <option value="">- Select -</option>
            <option value="accepted" <?php echo $current_decision === 'accepted' ? 'selected' : ''; ?>>Commercial Approved</option>
            <option value="rejected" <?php echo $current_decision === 'rejected' ? 'selected' : ''; ?>>Commercial Rejected</option>
        </select>
        <?php if (!$editable) { ?>
            <input type="hidden" name="decision" value="<?php echo esc($current_decision); ?>" />
        <?php } ?>
    </div>

    <div class="form-group mb15">
    <label><strong>Commercial Score</strong></label>
    <input
        type="number"
        name="commercial_score"
        class="form-control"
        step="0.001"
        min="0"
        max="100"
        value="<?php echo esc(isset($active_evaluation->total_score) ? (string) ($active_evaluation->total_score ?? '') : ''); ?>"
        placeholder="0.000"
        <?php echo $editable ? '' : 'disabled'; ?> />
    <?php if (!$editable) { ?>
        <input type="hidden" name="commercial_score" value="<?php echo esc(isset($active_evaluation->total_score) ? (string) ($active_evaluation->total_score ?? '') : ''); ?>" />
    <?php } ?>
    <small class="text-muted">Enter the commercial score for this bid.</small>
</div>

    <div class="form-group mb0">
        <label><strong>Overall Commercial Comments</strong></label>
        <textarea name="evaluation_comment" class="form-control" rows="4" placeholder="Commercial remarks" <?php echo $editable ? '' : 'disabled'; ?>><?php echo esc((string) ($active_evaluation->comments ?? '')); ?></textarea>
        <?php if (!$editable) { ?>
            <input type="hidden" name="evaluation_comment" value="<?php echo esc((string) ($active_evaluation->comments ?? '')); ?>" />
        <?php } ?>
    </div>

    <div class="form-group mt15 mb0">
        <label><strong>Commercial Findings Documents</strong></label>
        <?php if (!empty($finding_attachments)) { ?>
            <div class="d-flex flex-wrap gap-1 mb10">
                <?php foreach ($finding_attachments as $attachment) { ?>
                    <a href="<?php echo get_uri("tender_commercial_inbox/download_finding_document/" . (int) $attachment->id); ?>" class="btn btn-default btn-sm">
                        <i data-feather="paperclip" class="icon-14"></i>
                        <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="text-off mb10">No commercial findings documents uploaded yet.</div>
        <?php } ?>

        <?php if ($editable) { ?>
            <input type="file" name="commercial_finding_files[]" class="form-control" multiple>
            <small class="text-off">Upload pricing analysis, commercial findings, comparison sheets, or other supporting evidence for this score.</small>
        <?php } ?>
    </div>

    <div class="mt15 internal-clarification-thread">
        <h5 class="mb10">Internal Procurement Clarifications</h5>
        <?php if (empty($internal_messages)) { ?>
            <div class="alert alert-light mb0">No internal clarification messages have been recorded for this bid.</div>
        <?php } else { ?>
            <div class="commercial-internal-thread">
                <?php foreach ($internal_messages as $message) {
                    $message_attachments = $internal_attachments[(int) $message->id] ?? [];
                    $is_procurement_reply = strtolower((string) ($message->type ?? "")) === "commercial_clarification_response";
                ?>
                    <div class="internal-thread-item <?php echo $is_procurement_reply ? "procurement-reply" : "evaluator-request"; ?>">
                        <div class="d-flex justify-content-between gap-2 flex-wrap mb5">
                            <strong><?php echo esc($is_procurement_reply ? (trim((string) ($message->created_by_name ?? "")) ?: "Procurement") : "Commercial Team"); ?></strong>
                            <span class="text-off"><?php echo !empty($message->published_at ?: $message->created_at) ? format_to_datetime($message->published_at ?: $message->created_at) : "-"; ?></span>
                        </div>
                        <?php if (!empty($message->subject)) { ?>
                            <div class="fw-semibold mb5"><?php echo esc($message->subject); ?></div>
                        <?php } ?>
                        <div><?php echo nl2br(esc($message->message ?? "")); ?></div>
                        <?php if ($message_attachments) { ?>
                            <div class="d-flex flex-wrap gap-1 mt10">
                                <?php foreach ($message_attachments as $attachment) { ?>
                                    <a href="<?php echo get_uri("tender_commercial_inbox/download_clarification_attachment/" . (int) $attachment->id); ?>" class="btn btn-default btn-sm">
                                        <i data-feather="paperclip" class="icon-14"></i>
                                        <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                                    </a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <?php if ($editable) { ?>
        <button type="submit" class="btn btn-primary">Save Evaluation</button>
    <?php } ?>
</div>

<?php echo form_close(); ?>

<?php echo form_open_multipart(get_uri('tender_commercial_inbox/request_clarification'), ['id' => 'commercial-clarification-request-form', 'class' => 'general-form']); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
<input type="hidden" name="bid_id" value="<?php echo (int) ($bid->id ?? 0); ?>" />

<div class="modal-body border-top">
    <div class="mb10">
        <h5 class="mb5">Ask Procurement For Clarification</h5>
        <div class="text-off">Use this when the vendor's commercial submission, price, guarantee, or commercial documents need more information.</div>
    </div>
    <div class="form-group">
        <label>Subject</label>
        <input type="text" name="subject" class="form-control" maxlength="255" value="Commercial clarification request - <?php echo esc($bid->vendor_name ?? 'Vendor'); ?>">
    </div>
    <div class="form-group">
        <label>Clarification Required</label>
        <textarea name="message" class="form-control" rows="3" required placeholder="Describe what procurement should clarify with this vendor"></textarea>
    </div>
    <div class="form-group mb0">
        <label>Attach Supporting Files</label>
        <input type="file" name="clarification_files[]" class="form-control" multiple>
    </div>
</div>

<div class="modal-footer">
    <button type="submit" class="btn btn-default">
        <i data-feather="message-square" class="icon-16"></i> Send Clarification Request
    </button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#tender-commercial-bid-form").appForm({
    onSuccess: function (response) {
        appAlert.success(response.message || "Saved successfully.", {duration: 2000});
        setTimeout(function () {
            if (response.redirect_url) {
                window.location.href = response.redirect_url;
            } else {
                window.location.reload();
            }
        }, 500);
    }
});
    $("#commercial-clarification-request-form").appForm({
        onSuccess: function (response) {
            appAlert.success(response.message || "Clarification request sent.", {duration: 2000});
            setTimeout(function () {
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    window.location.reload();
                }
            }, 500);
        }
    });
    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>

<style>
.commercial-internal-thread {
    display: grid;
    gap: 10px;
    max-height: 260px;
    overflow-y: auto;
    background: #f8fafc;
    border: 1px solid #e5e9f2;
    border-radius: 8px;
    padding: 12px;
}
.internal-thread-item {
    border: 1px solid #dfe6f1;
    border-radius: 8px;
    background: #fff;
    padding: 12px;
}
.internal-thread-item.procurement-reply {
    border-color: #b9d7ff;
    background: #f2f7ff;
}
</style>
