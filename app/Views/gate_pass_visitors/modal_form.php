<?php echo form_open(get_uri("gate_pass_visitors/save"), ["id" => "visitor-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("username"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "username",
                        "value" => $model_info->username ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang("username"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                    <small class="text-muted">Only letters & numbers (no spaces/special chars).</small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("first_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "first_name",
                        "value" => $model_info->first_name ?? "",
                        "class" => "form-control",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("last_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "last_name",
                        "value" => $model_info->last_name ?? "",
                        "class" => "form-control",
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "email",
                        "type" => "email",
                        "value" => $model_info->email ?? "",
                        "class" => "form-control",
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("phone"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "phone",
                        "value" => $model_info->phone ?? "",
                        "class" => "form-control"
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("alternative_phone"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "emergency_number",
                        "value" => $model_info->alternative_phone ?? "",
                        "class" => "form-control",
                        "placeholder" => app_lang("alternative_phone")
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("otp_channel"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "otp_channel",
                        ["phone" => "Phone", "email" => "Email"],
                        $model_info->otp_channel ?? "phone",
                        "class='form-control select2' data-rule-required='true'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "portal_status",
                        ["active" => "Active", "invited" => "Invited", "suspended" => "Suspended"],
                        $model_info->status ?? "active",
                        "class='form-control select2' data-rule-required='true'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("password"); ?></label>
                <div class="col-md-9">
                    <?php echo form_password([
                        "name" => "password",
                        "class" => "form-control",
                        "placeholder" => ($model_info ? "Leave blank to keep existing password" : "Required")
                    ]); ?>
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

<script>
$(document).ready(function () {
    $("#visitor-form").appForm({
        onSuccess: function (result) {
            $("#gate-pass-visitors-table").appTable({newData: result.data, dataId: result.id});
        }
    });

    $(".select2").select2();
});
</script>
