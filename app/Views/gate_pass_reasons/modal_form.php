<?php echo form_open(get_uri("gate_pass_reasons/save"), ["id" => "gate-pass-reason-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("reason"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "title",
                        "value" => $model_info->title ?? "",
                        "class" => "form-control",
                        "maxlength" => 191,
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("description"); ?></label>
                <div class="col-md-9">
                    <?php echo form_textarea([
                        "name" => "description",
                        "value" => $model_info->description ?? "",
                        "class" => "form-control",
                        "rows" => 3,
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("sort_order"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "sort_order",
                        "type" => "number",
                        "min" => 0,
                        "value" => isset($model_info->sort_order) ? (int)$model_info->sort_order : 0,
                        "class" => "form-control",
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

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gate-pass-reason-form").appForm({
        onSuccess: function (result) {
            $("#gate-pass-reasons-table").appTable({newData: result.data, dataId: result.id});
        }
    });
});
</script>

