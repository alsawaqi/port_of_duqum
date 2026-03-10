<?php echo form_open(get_uri('tender_commercial_inbox/save_bid_evaluation'), ['id' => 'tender-commercial-bid-form', 'class' => 'general-form']); ?>
<input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
<input type="hidden" name="bid_id" value="<?php echo (int) ($bid->id ?? 0); ?>" />

<?php
$current_decision = strtolower(trim((string) ($active_evaluation->decision ?? $latest_evaluation->decision ?? '')));
if (!in_array($current_decision, ['accepted', 'rejected'], true)) {
    $current_decision = '';
}

$locked_by_other = !$editable;
$owner_name = trim((string) ($latest_evaluation->evaluator_name ?? ''));
if ($owner_name === '') {
    $owner_name = $latest_evaluation->evaluator_email ?? 'another evaluator';
}
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
        <div class="col-md-4 mb10">
            <strong>Commercial Proposal:</strong><br>
            <?php if (!empty($bid->commercial_doc_id)) { ?>
                <a href="<?php echo get_uri('tender_commercial_inbox/download_bid_document/' . $bid->commercial_doc_id); ?>" class="btn btn-default btn-sm mt5">
                    <i data-feather="download" class="icon-14"></i>
                    Download
                </a>
            <?php } else { ?>
                <span class="text-off">No commercial file</span>
            <?php } ?>
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
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <?php if ($editable) { ?>
        <button type="submit" class="btn btn-primary">Save Evaluation</button>
    <?php } ?>
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
    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>