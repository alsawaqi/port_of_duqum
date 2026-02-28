<?php echo form_open(get_uri("vendor_document_types/save"), ["id" => "vendor-document-types-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <!-- Vendor Group (optional) -->
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('vendor_group'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "vendor_group_id",
                        $vendor_groups_dropdown,
                        $model_info->vendor_group_id,
                        "id='vendor_group_id' class='form-control select2'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <!-- Name -->
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('name'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "name",
                        "name" => "name",
                        "value" => $model_info->name,
                        "class" => "form-control",
                        "placeholder" => app_lang("name"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <!-- Code -->
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('code'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "code",
                        "name" => "code",
                        "value" => $model_info->code,
                        "class" => "form-control",
                        "placeholder" => app_lang("code"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <!-- Required -->
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang('required'); ?></label>
                <div class="col-md-9">
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="is_required" name="is_required" value="1"
                            <?php echo ($model_info->is_required ? "checked" : ""); ?>>
                        <label class="form-check-label" for="is_required"><?php echo app_lang("is_required"); ?></label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status -->
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

        $("#vendor-document-types-form").appForm({
            onSuccess: function(result) {
                $("#vendor-document-types-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

        $("#vendor_group_id").select2();
    });
</script>