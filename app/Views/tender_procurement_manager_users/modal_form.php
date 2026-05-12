<?php echo form_open(get_uri("tender_procurement_manager_users/save"), ["id" => "tender-procurement-manager-user-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <?php echo view("includes/operational_user_identity_fields", ["model_info" => $model_info]); ?>

        <hr />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("company"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "company_id",
                        $company_dropdown ?? ["0" => "- " . app_lang("select_company") . " -"],
                        $model_info->company_id ?? "0",
                        "class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown("status", ["active" => app_lang("active"), "inactive" => app_lang("inactive")], $model_info->status ?? "active", "class='form-control select2'"); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#tender-procurement-manager-user-form").appForm({
        onSuccess: function () {
            $("#tender-procurement-manager-users-table").appTable({reload: true});
        }
    });
    $(".select2").select2();
});
</script>
