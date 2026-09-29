<?php echo form_open(get_uri("vendor_sub_categories/save"), ["id" => "vendor-sub-category-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id); ?>" />

        <!-- Vendor Category -->
        <div class="form-group">
            <div class="row">
                <label for="vendor_category_id" class="col-md-3">
                    <?php echo app_lang('vendor_categories'); ?>
                </label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "vendor_category_id",
                        $vendor_categories_dropdown,
                        $model_info->vendor_category_id ?? "",
                        "class='form-control select2' id='vendor_category_id' data-rule-required='true' data-msg-required='" . app_lang('field_required') . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <!-- Name -->
        <div class="form-group">
            <div class="row">
                <label for="name" class="col-md-3"><?php echo app_lang('name'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_input([
                        "id" => "name",
                        "name" => "name",
                        "value" => $model_info->name,
                        "class" => "form-control",
                        "placeholder" => app_lang('name'),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang('field_required')
                    ]);
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
                    echo form_input([
                        "id" => "code",
                        "name" => "code",
                        "value" => $model_info->code,
                        "class" => "form-control",
                        "placeholder" => app_lang('code')
                    ]);
                    ?>
                </div>
            </div>
        </div>

        <!-- Status -->
        <div class="form-group">
            <div class="row">
                <label for="is_active" class="col-md-3"><?php echo app_lang('status'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "is_active",
                        [
                            "1" => app_lang("active"),
                            "0" => app_lang("inactive")
                        ],
                        ($model_info->is_active === "0" || $model_info->is_active === 0) ? "0" : "1",
                        "class='form-control select2' id='is_active'"
                    );
                    ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <?php echo app_lang('close'); ?>
    </button>
    <button type="submit" class="btn btn-primary">
        <span data-feather="check" class="icon-16"></span>
        <?php echo app_lang('save'); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {

        $("#vendor-sub-category-form .select2").select2();

        $("#vendor-sub-category-form").appForm({
            onSuccess: function(result) {
                $("#vendor-sub-categories-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });
    });
</script>