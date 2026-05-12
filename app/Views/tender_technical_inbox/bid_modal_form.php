<?php echo form_open_multipart(get_uri('tender_technical_inbox/save_bid_evaluation'), ['id' => 'tender-technical-bid-form', 'class' => 'general-form']); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
<input type="hidden" name="bid_id" value="<?php echo (int) ($bid->id ?? 0); ?>" />

<?php
$finding_attachments = $finding_attachments ?? [];
$internal_messages = $internal_messages ?? [];
$internal_attachments = $internal_attachments ?? [];
$status = strtolower((string) ($bid->status ?? 'submitted'));
$current_decision = $status === 'accepted' || $status === 'rejected' ? $status : '';
$locked_by_other = !$editable;
$owner_name = trim((string) ($latest_evaluation->evaluator_name ?? ''));
if ($owner_name === '') {
    $owner_name = $latest_evaluation->evaluator_email ?? 'another evaluator';
}

$technical_bid_document_actions = function ($doc_id) {
    $doc_id = (int) $doc_id;
    if (!$doc_id) {
        return "<span class='text-off'>No technical file</span>";
    }

    $preview = js_anchor(
        "<i data-feather='eye' class='icon-14'></i> Preview",
        [
            "title" => "Preview Technical Proposal",
            "class" => "btn btn-primary btn-sm mt5 me-1",
            "data-toggle" => "app-modal",
            "data-sidebar" => "0",
            "data-url" => get_uri("tender_technical_inbox/preview_bid_document/" . $doc_id),
        ]
    );

    $download = anchor(
        get_uri("tender_technical_inbox/download_bid_document/" . $doc_id),
        "<i data-feather='download' class='icon-14'></i> Download",
        ["class" => "btn btn-default btn-sm mt5"]
    );

    return "<div class='d-flex flex-wrap gap-1'>" . $preview . $download . "</div>";
};
?>

<div class="modal-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
        <div>
            <h4 class="mb5">Technical Review - <?php echo esc($bid->vendor_name ?? '-'); ?></h4>
            <div class="text-off">Tender: <?php echo esc($tender->reference ?? '-'); ?> / <?php echo esc($tender->title ?? '-'); ?></div>
        </div>
        <div>
            <?php if ($status === 'submitted') { ?>
                <span class="badge bg-warning text-dark">Pending</span>
            <?php } elseif ($status === 'accepted') { ?>
                <span class="badge bg-success">Accepted</span>
            <?php } else { ?>
                <span class="badge bg-danger">Rejected</span>
            <?php } ?>
        </div>
    </div>

    <div class="row mb15">
        <div class="col-md-4 mb10"><strong>Submitted At:</strong><br><?php echo !empty($bid->submitted_at) ? format_to_datetime($bid->submitted_at) : '-'; ?></div>
        <div class="col-md-4 mb10"><strong>Current Score:</strong><br><?php echo !empty($active_evaluation->id) ? number_format((float) ($active_evaluation->total_score ?? 0), 3) : '0.000'; ?> / <?php echo number_format((float) $stage_max_score, 3); ?></div>
        <div class="col-md-4 mb10">
            <strong>Technical Proposal:</strong><br>
            <?php echo $technical_bid_document_actions($bid->technical_doc_id ?? 0); ?>
        </div>
    </div>

    <?php if ($locked_by_other && in_array($status, ['accepted', 'rejected'], true)) { ?>
        <div class="alert alert-warning">
            This bid has already been finalized by <strong><?php echo esc($owner_name); ?></strong>. You can only view it here.
        </div>
    <?php } elseif ($editable && in_array($status, ['accepted', 'rejected'], true)) { ?>
        <div class="alert alert-info">
            You finalized this bid earlier. You can still edit your own technical evaluation.
        </div>
    <?php } else { ?>
        <div class="alert alert-info">
            Score this bidder against the technical criteria, then mark the bid as technically accepted or rejected.
        </div>
    <?php } ?>

    <div class="form-group mb15">
        <label><strong>Technical Decision</strong></label>
        <select name="decision" class="form-control" <?php echo $editable ? '' : 'disabled'; ?>>
            <option value="">- Select -</option>
            <option value="accepted" <?php echo $current_decision === 'accepted' ? 'selected' : ''; ?>>Technical Accepted</option>
            <option value="rejected" <?php echo $current_decision === 'rejected' ? 'selected' : ''; ?>>Technical Rejected</option>
        </select>
        <?php if (!$editable) { ?>
            <input type="hidden" name="decision" value="<?php echo esc($current_decision); ?>" />
        <?php } ?>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle mb0">
            <thead>
                <tr>
                    <th style="width: 35%;">Criterion</th>
                    <th style="width: 12%;">Weight</th>
                    <th style="width: 18%;">Score</th>
                    <th style="width: 35%;">Comment</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($criteria as $criterion) {
                    $criterion_id = (int) $criterion->id;
                    $saved_score_row = $scores_by_criterion[$criterion_id] ?? null;
                    $saved_score = $saved_score_row ? $saved_score_row->score : '';
                    $saved_comment = $saved_score_row ? $saved_score_row->comment : '';
                ?>
                    <tr>
                        <td><?php echo esc($criterion->name ?? '-'); ?></td>
                        <td><?php echo number_format((float) ($criterion->weight ?? 0), 3); ?></td>
                        <td>
                            <input
                                type="number"
                                name="scores[<?php echo $criterion_id; ?>]"
                                class="form-control criterion-score"
                                step="0.001"
                                min="0"
                                max="<?php echo esc((string) ($criterion->weight ?? 0)); ?>"
                                value="<?php echo esc((string) $saved_score); ?>"
                                placeholder="0.000"
                                <?php echo $editable ? '' : 'disabled'; ?> />
                            <?php if (!$editable) { ?>
                                <input type="hidden" name="scores[<?php echo $criterion_id; ?>]" value="<?php echo esc((string) $saved_score); ?>" />
                            <?php } ?>
                        </td>
                        <td>
                            <textarea
                                name="criterion_comments[<?php echo $criterion_id; ?>]"
                                class="form-control"
                                rows="2"
                                placeholder="Optional comment"
                                <?php echo $editable ? '' : 'disabled'; ?>><?php echo esc((string) $saved_comment); ?></textarea>
                            <?php if (!$editable) { ?>
                                <input type="hidden" name="criterion_comments[<?php echo $criterion_id; ?>]" value="<?php echo esc((string) $saved_comment); ?>" />
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="text-end mt10 mb15">
        <strong>Total Score:</strong>
        <span id="technical-bid-total"><?php echo !empty($active_evaluation->id) ? number_format((float) ($active_evaluation->total_score ?? 0), 3) : '0.000'; ?></span>
        / <?php echo number_format((float) $stage_max_score, 3); ?>
    </div>

    <div class="form-group mb0">
        <label><strong>Overall Evaluation Comments</strong></label>
        <textarea name="evaluation_comment" class="form-control" rows="4" placeholder="Overall technical remarks" <?php echo $editable ? '' : 'disabled'; ?>><?php echo esc((string) ($active_evaluation->comments ?? '')); ?></textarea>
        <?php if (!$editable) { ?>
            <input type="hidden" name="evaluation_comment" value="<?php echo esc((string) ($active_evaluation->comments ?? '')); ?>" />
        <?php } ?>
    </div>

    <div class="form-group mt15 mb0">
        <label><strong>Technical Findings Documents</strong></label>
        <?php if (!empty($finding_attachments)) { ?>
            <div class="d-flex flex-wrap gap-1 mb10">
                <?php foreach ($finding_attachments as $attachment) { ?>
                    <a href="<?php echo get_uri("tender_technical_inbox/download_finding_document/" . (int) $attachment->id); ?>" class="btn btn-default btn-sm">
                        <i data-feather="paperclip" class="icon-14"></i>
                        <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="text-off mb10">No findings documents uploaded yet.</div>
        <?php } ?>

        <?php if ($editable) { ?>
            <input type="file" name="technical_finding_files[]" class="form-control" multiple>
            <small class="text-off">Upload the technical team's findings, notes, comparison sheets, or supporting evidence for this score.</small>
        <?php } ?>
    </div>

    <div class="mt15 internal-clarification-thread">
        <h5 class="mb10">Internal Procurement Clarifications</h5>
        <?php if (empty($internal_messages)) { ?>
            <div class="alert alert-light mb0">No internal clarification messages have been recorded for this bid.</div>
        <?php } else { ?>
            <div class="technical-internal-thread">
                <?php foreach ($internal_messages as $message) {
                    $message_attachments = $internal_attachments[(int) $message->id] ?? [];
                    $is_procurement_reply = strtolower((string) ($message->type ?? "")) === "technical_clarification_response";
                ?>
                    <div class="internal-thread-item <?php echo $is_procurement_reply ? "procurement-reply" : "evaluator-request"; ?>">
                        <div class="d-flex justify-content-between gap-2 flex-wrap mb5">
                            <strong><?php echo esc($is_procurement_reply ? (trim((string) ($message->created_by_name ?? "")) ?: "Procurement") : "Technical Team"); ?></strong>
                            <span class="text-off"><?php echo !empty($message->published_at ?: $message->created_at) ? format_to_datetime($message->published_at ?: $message->created_at) : "-"; ?></span>
                        </div>
                        <?php if (!empty($message->subject)) { ?>
                            <div class="fw-semibold mb5"><?php echo esc($message->subject); ?></div>
                        <?php } ?>
                        <div><?php echo nl2br(esc($message->message ?? "")); ?></div>
                        <?php if ($message_attachments) { ?>
                            <div class="d-flex flex-wrap gap-1 mt10">
                                <?php foreach ($message_attachments as $attachment) { ?>
                                    <a href="<?php echo get_uri("tender_technical_inbox/download_clarification_attachment/" . (int) $attachment->id); ?>" class="btn btn-default btn-sm">
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

<?php echo form_open_multipart(get_uri('tender_technical_inbox/request_clarification'), ['id' => 'technical-clarification-request-form', 'class' => 'general-form']); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
<input type="hidden" name="bid_id" value="<?php echo (int) ($bid->id ?? 0); ?>" />

<div class="modal-body border-top">
    <div class="mb10">
        <h5 class="mb5">Ask Procurement For Clarification</h5>
        <div class="text-off">Use this when the vendor's technical submission needs more information. Procurement will contact the vendor and forward the response back to the technical team.</div>
    </div>
    <div class="form-group">
        <label>Subject</label>
        <input type="text" name="subject" class="form-control" maxlength="255" value="Technical clarification request - <?php echo esc($bid->vendor_name ?? 'Vendor'); ?>">
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
    function updateBidTotal() {
        var total = 0;
        $(".criterion-score").each(function () {
            var value = parseFloat($(this).val());
            if (!isNaN(value)) {
                total += value;
            }
        });
        $("#technical-bid-total").text(total.toFixed(3));
    }

    $(".criterion-score").on("input", function () {
        updateBidTotal();
    });

    updateBidTotal();

    $("#tender-technical-bid-form").appForm({
        onSuccess: function (response) {
            appAlert.success(response.message || "Saved successfully.", {duration: 2000});
            setTimeout(function () {
                window.location.reload();
            }, 500);
        }
    });

    $("#technical-clarification-request-form").appForm({
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
.technical-internal-thread {
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
