<?php echo form_open(get_uri("gate_pass_companies/save"), ["id" => "company-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "name",
                        "value" => $model_info->name ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang("name"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("code"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "code",
                        "value" => $model_info->code ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang("code"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9 pt-2">
                    <?php echo form_checkbox("is_active", "1", ($model_info->is_active ?? 1) ? true : false); ?>
                    <?php echo app_lang("active"); ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#company-form").appForm({
        onSuccess: function (result) {
            $("#gate-pass-companies-table").appTable({newData: result.data, dataId: result.id});
        }
    });
});
</script>
