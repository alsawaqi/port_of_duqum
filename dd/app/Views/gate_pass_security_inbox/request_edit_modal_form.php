<?php
$visit_from_val = !empty($model_info->visit_from) ? substr((string)$model_info->visit_from, 0, 10) : "";
$visit_to_val = !empty($model_info->visit_to) ? substr((string)$model_info->visit_to, 0, 10) : "";
?>

<?php echo form_open(get_uri("gate_pass_security_inbox/save_request_patch"), [
    "id" => "gp-sec-request-edit-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="request_id" value="<?php echo (int)$model_info->id; ?>" />

    <div class="form-group">
        <label>Visit From <span class="text-danger">*</span></label>
        <input type="date" name="visit_from" class="form-control" required value="<?php echo esc($visit_from_val); ?>">
    </div>

    <div class="form-group">
        <label>Visit To <span class="text-danger">*</span></label>
        <input type="date" name="visit_to" class="form-control" required value="<?php echo esc($visit_to_val); ?>">
    </div>

    <div class="form-group">
        <label>Notes</label>
        <textarea name="purpose_notes" rows="4" class="form-control"><?php echo esc($model_info->purpose_notes ?? ""); ?></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-sec-request-edit-form").appForm({
        onSuccess: function () {
            if ($("#btn_lookup").length) {
                $("#btn_lookup").trigger("click");
            } else {
                window.location.reload();
            }
        }
    });
});
</script>
