<?php echo form_open(get_uri("vendor_groups/save"), array("id" => "vendor-groups-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('name'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "name" => "name",
                        "value" => $model_info->name,
                        "class" => "form-control",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('code'); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "name" => "code",
                        "value" => $model_info->code,
                        "class" => "form-control",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Default validity days</label>
                <div class="col-md-9">
                    <?php echo form_input(array(
                        "name" => "default_validity_days",
                        "value" => $model_info->default_validity_days,
                        "class" => "form-control",
                        "type" => "number",
                        "min" => 1
                    )); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Requires Riyada</label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" id="requires_riyada" name="requires_riyada" value="1"
                            <?php echo ($model_info->requires_riyada ? "checked" : ""); ?>>
                        <label class="form-check-label" for="requires_riyada">Yes</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('status'); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" value="1"
                            <?php echo ($model_info->id ? ($model_info->is_active ? "checked" : "") : "checked"); ?>>
                        <label class="form-check-label" for="is_active"><?php echo app_lang("active"); ?></label>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang('save'); ?></button>
</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-groups-form").appForm({
            onSuccess: function(result) {
                $("#vendor-groups-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });
    });
</script>