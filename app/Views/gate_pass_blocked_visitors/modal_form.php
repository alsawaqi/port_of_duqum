<?php
$model_info = $model_info ?? null;
$is_existing = !empty($model_info) && !empty($model_info->id);
$is_unblocked = $is_existing && strtolower((string) ($model_info->status ?? "")) === "unblocked";
?>

<?php echo form_open(get_uri("gate_pass_blocked_visitors/save"), ["id" => "gp-blocked-visitor-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body gp-pro-modal-body">
    <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>" />

    <?php if ($is_unblocked): ?>
        <div class="alert alert-warning">
            <?php echo app_lang("gate_pass_block_again_hint"); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("id_number"); ?> <span class="text-danger">*</span></label>
                <input type="text" name="id_number" class="form-control" required
                       value="<?php echo esc($model_info->id_number ?? ""); ?>"
                       <?php echo $is_existing ? "readonly" : ""; ?>>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("id_type"); ?></label>
                <input type="text" name="id_type" class="form-control"
                       value="<?php echo esc($model_info->id_type ?? ""); ?>"
                       placeholder="Passport, National ID, Residence Permit">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("full_name"); ?></label>
                <input type="text" name="visitor_name" class="form-control"
                       value="<?php echo esc($model_info->visitor_name ?? ""); ?>">
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("nationality"); ?></label>
                <input type="text" name="nationality" class="form-control"
                       value="<?php echo esc($model_info->nationality ?? ""); ?>">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("gate_pass_visitor_company"); ?></label>
        <input type="text" name="visitor_company" class="form-control"
               value="<?php echo esc($model_info->visitor_company ?? ""); ?>">
    </div>

    <div class="form-group">
        <label><?php echo app_lang("gate_pass_block_reason"); ?> <span class="text-danger">*</span></label>
        <textarea name="reason" class="form-control" rows="4" required
                  placeholder="<?php echo esc(app_lang("gate_pass_block_reason_placeholder")); ?>"><?php echo esc($model_info->reason ?? ""); ?></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo $is_unblocked ? app_lang("gate_pass_block_again") : app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-blocked-visitor-form").appForm({
        onSuccess: function (result) {
            $("#gate-pass-blocked-visitors-table").appTable({ newData: result.data, dataId: result.id });
        }
    });
});
</script>
