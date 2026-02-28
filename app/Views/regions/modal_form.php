<?php echo form_open(get_uri("regions/save"), array("id" => "regions-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <!-- Country dropdown -->
        <div class="form-group">
            <div class="row">
                <label for="country_id" class="col-md-3"><?php echo app_lang('countries'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "country_id",
                        $countries_dropdown,
                        $model_info->country_id,
                        "id='country_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>


        <!-- Region name -->
        <div class="form-group">
            <div class="row">
                <label for="name" class="col-md-3"><?php echo app_lang('name'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "name",
                        "name" => "name",
                        "value" => $model_info->name,
                        "class" => "form-control",
                        "placeholder" => app_lang('name'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ));
                    ?>
                </div>
            </div>
        </div>

        <!-- Region code -->
        <div class="form-group">
            <div class="row">
                <label for="code" class="col-md-3"><?php echo app_lang('code'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "id" => "code",
                        "name" => "code",
                        "value" => $model_info->code,
                        "class" => "form-control",
                        "placeholder" => app_lang('code'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ));
                    ?>
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
        $("#regions-form").appForm({
            onSuccess: function(result) {
                $("#regions-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

        // ✅ Load countries into select2
        $("#country_id").select2();

        setTimeout(function() {
            $("#country_id").focus();
        }, 200);
    });
</script>