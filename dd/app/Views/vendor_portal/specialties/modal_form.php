<?php echo form_open(get_uri("vendor_portal/save_specialty"), ["id" => "specialty-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <input type="hidden" name="id" value="<?php echo $model_info->id ?? ""; ?>" />


    <div class="form-group">
        <label for="specialty_type" class=" col-md-3"><?php echo app_lang("type"); ?></label>
        <div class=" col-md-9">
            <?php
            echo form_dropdown(
                "specialty_type",
                [
                    "" => "- " . app_lang("select") . " -",
                    "product" => "Product",
                    "service" => "Service",
                    "other" => "Other"
                ],
                $model_info->specialty_type ?? "",
                "class='select2 validate-hidden' id='specialty_type' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
            );
            ?>
        </div>
    </div>

    <div class="form-group">
        <label for="vendor_category_id" class="col-md-3"><?php echo app_lang("category"); ?></label>
        <div class="col-md-9">
            <?php
            echo form_dropdown(
                "vendor_category_id",
                $categories_dropdown ?? ["" => "- " . app_lang("select") . " -"],
                $model_info->vendor_category_id ?? "",
                "class='select2 validate-hidden' id='vendor_category_id'
             data-rule-required='true'
             data-msg-required='" . app_lang("field_required") . "'"
            );
            ?>
        </div>
    </div>


    <div class="form-group">
        <label for="vendor_sub_category_id" class="col-md-3">
            <?php echo app_lang("sub_category"); ?>
        </label>
        <div class="col-md-9">
            <?php
            echo form_dropdown(
                "vendor_sub_category_id",
                ["" => "- " . app_lang("select") . " -"], // will be filled via AJAX
                $model_info->vendor_sub_category_id ?? "",
                "class='select2 validate-hidden' id='vendor_sub_category_id'
             data-rule-required='true'
             data-msg-required='" . app_lang("field_required") . "'"
            );
            ?>
        </div>
    </div>


    <div class="form-group">
        <label for="specialty_name" class=" col-md-3"><?php echo app_lang("name"); ?></label>
        <div class=" col-md-9">
            <?php
            echo form_input([
                "id" => "specialty_name",
                "name" => "specialty_name",
                "value" => $model_info->specialty_name ?? "",
                "class" => "form-control",
                "placeholder" => app_lang("name"),
                "data-rule-required" => true,
                "data-msg-required" => app_lang("field_required")
            ]);
            ?>
        </div>
    </div>

    <div class="form-group">
        <label for="specialty_description" class=" col-md-3"><?php echo app_lang("description"); ?></label>
        <div class=" col-md-9">
            <?php
            echo form_textarea([
                "id" => "specialty_description",
                "name" => "specialty_description",
                "value" => $model_info->specialty_description ?? "",
                "class" => "form-control",
                "placeholder" => app_lang("description"),
                "rows" => 4
            ]);
            ?>
        </div>
    </div>

</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <i data-feather="x" class="icon-16"></i> <?php echo app_lang("close"); ?>
    </button>
    <button type="submit" class="btn btn-primary">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("save"); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
    $(document).ready(function() {

        // existing appForm
        $("#specialty-form").appForm({
            onSuccess: function(result) {
                $("#vendor-specialties-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });

        $("#specialty_type").select2();
        $("#vendor_category_id").select2();
        $("#vendor_sub_category_id").select2(); // 👈 NEW

        function loadSubCategories(categoryId, selectedSubId) {
            $("#vendor_sub_category_id").html(
                "<option value=''>- <?php echo app_lang('select'); ?> -</option>"
            ).trigger("change");

            if (!categoryId) {
                return;
            }

            $.get(
                "<?php echo get_uri('vendor_portal/get_vendor_sub_categories_dropdown'); ?>", {
                    vendor_category_id: categoryId
                },
                function(html) {
                    $("#vendor_sub_category_id").html(html);

                    if (selectedSubId) {
                        $("#vendor_sub_category_id")
                            .val(selectedSubId)
                            .trigger("change");
                    }
                }
            );
        }

        // When category changes, refresh sub-categories
        $("#vendor_category_id").on("change", function() {
            var catId = $(this).val();
            loadSubCategories(catId, null);
        });

        // If editing, pre-load sub-categories for existing values
        var initialCategoryId = $("#vendor_category_id").val();
        var initialSubCategoryId = "<?php echo $model_info->vendor_sub_category_id ?? ""; ?>";
        if (initialCategoryId) {
            loadSubCategories(initialCategoryId, initialSubCategoryId);
        }
    });
</script>