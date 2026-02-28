<?php echo form_open(get_uri("gate_pass_security_inbox/save_approval"), ["id" => "gp-security-approval-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body gp-pro-modal-body">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

    <div class="mb-3">
        <label class="form-label"><?php echo app_lang("decision"); ?></label>
        <select name="decision" id="gp-security-decision" class="form-control" required>
            <option value="approved"><?php echo app_lang("approve"); ?></option>
            <option value="returned"><?php echo app_lang("return_for_review"); ?></option>
            <option value="rejected"><?php echo app_lang("reject"); ?></option>
        </select>
    </div>

    <div class="mb-3" id="gp-security-reason-wrap" style="display:none;">
        <label class="form-label"><?php echo app_lang("reject_reason"); ?></label>
        <?php echo form_dropdown(
            "reason_id",
            $reason_options ?? ["0" => "- " . app_lang("select") . " -"],
            "0",
            "id='gp-security-reason' class='form-control'"
        ); ?>
    </div>

    <div class="mb-3">
        <label class="form-label"><?php echo app_lang("comment"); ?></label>
        <textarea name="comment" id="gp-security-comment" class="form-control" rows="4" placeholder="<?php echo app_lang("comment"); ?>..."></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    function syncSecurityApprovalUi() {
        var decision = $("#gp-security-decision").val();
        var isRejected = decision === "rejected";
        var isReturned = decision === "returned";

        $("#gp-security-reason-wrap").toggle(isRejected);
        $("#gp-security-reason").prop("required", isRejected);
        $("#gp-security-comment").prop("required", isReturned);

        if (!isRejected) {
            $("#gp-security-reason").val("0");
        }
    }

    $("#gp-security-decision").on("change", syncSecurityApprovalUi);
    syncSecurityApprovalUi();

    $("#gp-security-approval-form").appForm({
        onSuccess: function () {
            $("#gate-pass-security-inbox-table").DataTable().ajax.reload(null, false);
        }
    });
});
</script>
