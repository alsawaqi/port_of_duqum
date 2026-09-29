<?php echo form_open(get_uri("vendor_portal/save_branch"), array("id" => "vendor-branch-form", "class" => "general-form", "role" => "form")); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("name"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "name" => "name",
                        "value" => $model_info->name,
                        "class" => "form-control",
                        "placeholder" => app_lang("name"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("countries"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "country_id",
                        $countries_dropdown,
                        $model_info->country_id,
                        "id='branch_country_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("regions"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "region_id",
                        $regions_dropdown,
                        $model_info->region_id,
                        "id='branch_region_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("cities"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "city_id",
                        $cities_dropdown,
                        $model_info->city_id,
                        "id='branch_city_id' class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("address"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "name" => "address",
                        "value" => $model_info->address,
                        "class" => "form-control",
                        "placeholder" => app_lang("address")
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("phone"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "name" => "phone",
                        "value" => $model_info->phone,
                        "class" => "form-control",
                        "placeholder" => app_lang("phone")
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input(array(
                        "name" => "email",
                        "value" => $model_info->email,
                        "class" => "form-control",
                        "placeholder" => app_lang("email")
                    ));
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("primary"); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" name="is_main" value="1"
                            <?php echo ($model_info->id ? ($model_info->is_main ? "checked" : "") : ""); ?>>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <div class="form-check form-switch mt-2">
                        <input type="checkbox" class="form-check-input" name="is_active" value="1"
                            <?php echo ($model_info->id ? ($model_info->is_active ? "checked" : "") : "checked"); ?>>
                        <label class="form-check-label"><?php echo app_lang("active"); ?></label>
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
$(document).ready(function () {

    $("#vendor-branch-form").appForm({
        onSuccess: function (result) {
            $("#vendor-branches-table").appTable({newData: result.data, dataId: result.id});
        }
    });

    $("#branch_country_id").select2();
    $("#branch_region_id").select2();
    $("#branch_city_id").select2();

    $("#branch_country_id").on("change", function () {
        var country_id = $(this).val();

        $("#branch_region_id").html("<option value=''><?php echo app_lang('loading'); ?></option>").trigger("change");
        $("#branch_city_id").html("<option value=''><?php echo app_lang('select_city'); ?></option>").trigger("change");

        $.ajax({
            url: "<?php echo get_uri('vendor_portal/get_regions_dropdown_by_country'); ?>?country_id=" + country_id,
            success: function (response) {
                $("#branch_region_id").html(response).trigger("change");
            }
        });
    });

    $("#branch_region_id").on("change", function () {
        var region_id = $(this).val();

        $("#branch_city_id").html("<option value=''><?php echo app_lang('loading'); ?></option>").trigger("change");

        $.ajax({
            url: "<?php echo get_uri('vendor_portal/get_cities_dropdown_by_region'); ?>?region_id=" + region_id,
            success: function (response) {
                $("#branch_city_id").html(response).trigger("change");
            }
        });
    });

});
</script>
