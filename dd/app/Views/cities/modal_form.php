<?php echo form_open(get_uri("cities/save"), array("id" => "cities-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <!-- Country -->
        <div class="form-group">
            <div class="row">
                <label for="country_id" class="col-md-3"><?php echo app_lang('countries'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "country_id",
                        $countries_dropdown,
                        "", // we will set by JS when editing
                        "id='country_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <!-- Region -->
        <div class="form-group">
            <div class="row">
                <label for="regions_id" class="col-md-3"><?php echo app_lang('regions'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "regions_id",
                        $regions_dropdown,
                        $model_info->regions_id,
                        "id='regions_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <!-- City Name -->
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

        <!-- Code -->
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

        $("#cities-form").appForm({
            onSuccess: function(result) {
                $("#cities-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

        $("#country_id").select2();
        $("#regions_id").select2();

        // ✅ Country change triggers Regions update
        $("#country_id").on("change", function() {
            var country_id = $(this).val();

            $("#regions_id").html("<option value=''><?php echo app_lang('loading'); ?></option>").trigger("change");

            $.ajax({
                url: "<?php echo get_uri('cities/get_regions_dropdown_by_country'); ?>/" + country_id,
                success: function(response) {
                    $("#regions_id").html(response).trigger("change");
                }
            });
        });

    });
</script>