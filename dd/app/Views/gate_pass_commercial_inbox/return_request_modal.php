<?php echo form_open(get_uri("gate_pass_commercial_inbox/save_return_request"), ["id" => "gp-commercial-return-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)($request->id ?? 0); ?>" />
    <p class="mb10"><strong><?php echo app_lang("reference"); ?>:</strong> <?php echo esc($request->reference ?? "-"); ?></p>
    <p class="text-muted small mb15"><?php echo app_lang("gate_pass_commercial_return_modal_hint"); ?></p>
    <div class="form-group">
        <label><?php echo app_lang("comment"); ?> <span class="text-danger">*</span></label>
        <textarea name="comment" class="form-control" rows="4" required placeholder="<?php echo app_lang("gate_pass_return_comment_placeholder"); ?>"></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-warning"><?php echo app_lang("gate_pass_return_to_requester"); ?></button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-commercial-return-form").appForm({
        onSuccess: function () {
            window.location.href = "<?php echo get_uri('gate_pass_commercial_inbox'); ?>";
        }
    });
});
</script>
