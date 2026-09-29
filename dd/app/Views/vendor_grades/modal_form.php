<?php echo form_open(get_uri("vendor_grades/save"), ["id" => "vendor-grades-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("code"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "code",
                        "value" => $model_info->code ?? "",
                        "class" => "form-control",
                        "placeholder" => "A, B2, AC",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "name",
                        "value" => $model_info->name ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang("vendor_grade"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
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
                        "placeholder" => app_lang("vendor_grade_description_placeholder")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("sort"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "sort",
                        "value" => $model_info->sort ?? "",
                        "class" => "form-control",
                        "type" => "number",
                        "min" => 0
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                            <?php echo (!empty($model_info->id) ? (!empty($model_info->is_active) ? "checked" : "") : "checked"); ?>>
                        <label class="form-check-label" for="is_active"><?php echo app_lang("active"); ?></label>
                    </div>
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

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-grades-form").appForm({
            onSuccess: function(result) {
                $("#vendor-grades-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });
    });
</script>
