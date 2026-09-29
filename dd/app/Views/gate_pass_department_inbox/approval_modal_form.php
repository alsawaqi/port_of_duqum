<?php echo form_open(get_uri("gate_pass_department_requests/save_approval"), [
    "id" => "gp-approval-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

    <div class="form-group">
        <label><?php echo app_lang("decision"); ?></label>
        <select name="decision" class="form-control" required>
            <option value="approved"><?php echo app_lang("approve"); ?></option>
            <option value="returned"><?php echo app_lang("return"); ?></option>
            <option value="rejected"><?php echo app_lang("reject"); ?></option>
        </select>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("comment"); ?> <span class="text-off">(required for Return/Reject)</span></label>
        <textarea name="comment" class="form-control" rows="4" placeholder="Write comment..."></textarea>
    </div>

    <!-- OPTIONAL: Fee waive fields (if you want department to waive here) -->
    <hr class="mt15 mb15">
    <div class="form-group">
        <label>
            <input type="checkbox" name="fee_is_waived" value="1" id="fee_is_waived">
            <?php echo app_lang("waived"); ?>
        </label>
        <div class="text-off font-12">If checked, requester will not be asked to pay.</div>
    </div>

    <div class="form-group" id="waive_reason_wrap" style="display:none;">
        <label><?php echo app_lang("reason"); ?></label>
        <textarea name="fee_waived_reason" class="form-control" rows="3" placeholder="Reason for waiving fee..."></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#fee_is_waived").on("change", function(){
        $("#waive_reason_wrap").toggle(this.checked);
    });

    $("#gp-approval-form").appForm({
        onSuccess: function () {
            window.location.href = "<?php echo get_uri('gate_pass_department_inbox'); ?>";
        }
    });
});
</script>
