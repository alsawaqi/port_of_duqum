<?php echo form_open(get_uri("ptw_hmo_inbox/save_review"), ["id" => "ptw-hmo-review-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <input type="hidden" name="ptw_application_id" value="<?php echo (int)$application->id; ?>" />

    <div class="form-group">
        <label><?php echo app_lang("decision"); ?></label>
        <select name="decision" id="ptw-hmo-decision" class="form-control" required>
            <option value="approved"><?php echo app_lang("approve"); ?></option>
            <option value="revise"><?php echo app_lang("revise"); ?></option>
            <option value="rejected"><?php echo app_lang("reject"); ?></option>
        </select>
    </div>

    <div class="form-group" id="ptw-hmo-reason-select-wrap" style="display:none;">
        <label><?php echo app_lang("reason"); ?> <span class="text-off">(<?php echo app_lang("optional"); ?>)</span></label>
        <select name="reason_id" id="ptw-hmo-reason-id" class="form-control">
            <option value="0">— <?php echo app_lang("select"); ?> —</option>
            <?php foreach ($reason_options ?? [] as $val => $label):
                if ($val === "0") continue; ?>
                <option value="<?php echo esc((string)$val); ?>"><?php echo esc((string)$label); ?></option>
            <?php endforeach; ?>
        </select>
        <small class="text-off"><?php echo app_lang("selecting_a_reason_auto_fills_below"); ?></small>
    </div>

    <div class="form-group" id="ptw-hmo-status-reason-wrap" style="display:none;">
        <label><?php echo app_lang("status_change_reason"); ?> <span class="text-danger">*</span></label>
        <textarea name="status_change_reason" id="ptw-hmo-status-reason" class="form-control" rows="3"></textarea>
        <small class="text-off"><?php echo app_lang("required_for_reject_or_revise"); ?></small>
    </div>

    <div class="form-group mb0">
        <label><?php echo app_lang("remarks"); ?></label>
        <textarea name="remarks" class="form-control" rows="4"></textarea>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    function syncDecisionUi() {
        var decision = $("#ptw-hmo-decision").val();
        var needReason = (decision === "revise" || decision === "rejected");

        $("#ptw-hmo-reason-select-wrap").toggle(needReason);
        $("#ptw-hmo-status-reason-wrap").toggle(needReason);
        $("#ptw-hmo-status-reason").prop("required", needReason);

        if (!needReason) {
            $("#ptw-hmo-reason-id").val("0");
            $("#ptw-hmo-status-reason").val("");
        }
    }

    $("#ptw-hmo-decision").on("change", syncDecisionUi);
    syncDecisionUi();

    // Auto-fill reason textarea when a reason is picked from the dropdown
    $("#ptw-hmo-reason-id").on("change", function () {
        var selected = $(this).find("option:selected").text().trim();
        var current  = $("#ptw-hmo-status-reason").val().trim();
        if ($(this).val() !== "0" && current === "") {
            $("#ptw-hmo-status-reason").val(selected);
        }
        if ($(this).val() === "0") {
            $("#ptw-hmo-status-reason").val("");
        }
    });

    $("#ptw-hmo-review-form").appForm({
    onSuccess: function () {
        // Always return reviewer to main inbox after action
        window.location.href = "<?php echo get_uri('ptw_hmo_inbox'); ?>";
    }
});
});
</script>