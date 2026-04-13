<?php echo form_open(get_uri("gate_pass_security_users/save"), ["id" => "gate-pass-security-user-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("company"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "company_id",
                        $company_dropdown,
                        $model_info->company_id ?? "0",
                        "class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    ); ?>
                </div>
            </div>
        </div>

        <hr />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("first_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["name" => "first_name", "value" => $model_info->first_name ?? "", "class" => "form-control", "data-rule-required" => true]); ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("last_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["name" => "last_name", "value" => $model_info->last_name ?? "", "class" => "form-control", "data-rule-required" => true]); ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["name" => "email", "type" => "email", "value" => $model_info->email ?? "", "class" => "form-control", "data-rule-required" => true, "data-rule-email" => true]); ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("phone"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["name" => "phone", "value" => $model_info->phone ?? "", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>
        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("password"); ?></label>
                <div class="col-md-9">
                    <?php echo form_password(["name" => "password", "class" => "form-control", "placeholder" => $model_info ? app_lang("leave_blank_to_keep") : ""]); ?>
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
    $("#gate-pass-security-user-form").appForm({
        onSuccess: function () {
            $("#gate-pass-security-users-table").appTable({ reload: true });
        }
    });
    $(".select2").select2();
});
</script>
