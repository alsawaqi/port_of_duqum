<?php echo form_open(get_uri("vendor_portal/save_credential"), array("id" => "vendor-credential-form", "class" => "general-form", "role" => "form")); ?>
<div class="modal-body clearfix">

    <?php echo form_hidden("id", $model_info->id); ?>

    <div class="form-group">
        <div class="row">
            <label for="type" class=" col-md-3"><?php echo app_lang('type'); ?></label>
            <div class=" col-md-9">
                <?php
                echo form_dropdown(
                    "type",
                    $type_dropdown,
                    $model_info->type ? $model_info->type : "cr",
                    "class='select2 validate-hidden' id='type' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                );
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="number" class=" col-md-3"><?php echo app_lang('number'); ?></label>
            <div class=" col-md-9">
                <?php
                echo form_input(array(
                    "id" => "number",
                    "name" => "number",
                    "value" => $model_info->number,
                    "class" => "form-control",
                    "placeholder" => app_lang("number"),
                    "data-rule-required" => true,
                    "data-msg-required" => app_lang("field_required"),
                ));
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="issue_date" class=" col-md-3"><?php echo app_lang('issue_date'); ?></label>
            <div class=" col-md-9">
                <?php
                echo form_input(array(
                    "id" => "issue_date",
                    "name" => "issue_date",
                    "value" => $model_info->issue_date,
                    "class" => "form-control",
                    "placeholder" => app_lang("issue_date"),
                ));
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="expiry_date" class=" col-md-3"><?php echo app_lang('expiry_date'); ?></label>
            <div class=" col-md-9">
                <?php
                echo form_input(array(
                    "id" => "expiry_date",
                    "name" => "expiry_date",
                    "value" => $model_info->expiry_date,
                    "class" => "form-control",
                    "placeholder" => app_lang("expiry_date"),
                ));
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="notes" class=" col-md-3"><?php echo app_lang('notes'); ?></label>
            <div class=" col-md-9">
                <?php
                echo form_textarea(array(
                    "id" => "notes",
                    "name" => "notes",
                    "value" => $model_info->notes,
                    "class" => "form-control",
                    "placeholder" => app_lang("notes"),
                    "rows" => 4
                ));
                ?>
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
    $(document).ready(function () {

        $("#vendor-credential-form").appForm({
            onSuccess: function (result) {
                $("#vendor-credentials-table").appTable({newData: result.data, dataId: result.id});
            }
        });

        $("#vendor-credential-form .select2").appDropdown();

        setDatePicker("#issue_date");
        setDatePicker("#expiry_date");
    });
</script>
