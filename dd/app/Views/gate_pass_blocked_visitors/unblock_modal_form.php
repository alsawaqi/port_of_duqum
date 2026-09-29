<?php echo form_open(get_uri("gate_pass_blocked_visitors/unblock"), ["id" => "gp-unblock-visitor-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body gp-pro-modal-body">
    <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>" />

    <div class="alert alert-info">
        <div class="fw-bold mb5"><?php echo app_lang("gate_pass_unblock_visitor"); ?></div>
        <div>
            <?php echo esc($model_info->visitor_name ?: app_lang("gate_pass_unnamed_visitor")); ?>
            &middot;
            <?php echo esc($model_info->id_number ?? "-"); ?>
        </div>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("gate_pass_unblock_reason"); ?></label>
        <textarea name="unblock_reason" class="form-control" rows="4"
                  placeholder="<?php echo esc(app_lang("gate_pass_unblock_reason_placeholder")); ?>"></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-success gp-pro-btn"><?php echo app_lang("gate_pass_unblock"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-unblock-visitor-form").appForm({
        onSuccess: function (result) {
            $("#gate-pass-blocked-visitors-table").appTable({ newData: result.data, dataId: result.id });
        }
    });
});
</script>
