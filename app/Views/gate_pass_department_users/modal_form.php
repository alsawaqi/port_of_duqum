<?php echo form_open(get_uri("gate_pass_department_users/save"), ["id" => "gate-pass-department-user-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label for="company_id" class=" col-md-3"><?php echo app_lang("company"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "company_id",
                        $company_dropdown,
                        $model_info->company_id ?? "0",
                        "class='form-control select2' id='company_id' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="department_id" class=" col-md-3"><?php echo app_lang("department"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "department_id",
                        $department_dropdown,
                        $model_info->department_id ?? "0",
                        "class='form-control select2' id='department_id' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <hr />

        <div class="form-group">
            <div class="row">
                <label for="first_name" class=" col-md-3"><?php echo app_lang("first_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "first_name", "name" => "first_name", "value" => $model_info->first_name ?? "", "class" => "form-control", "placeholder" => app_lang("first_name"), "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="last_name" class=" col-md-3"><?php echo app_lang("last_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "last_name", "name" => "last_name", "value" => $model_info->last_name ?? "", "class" => "form-control", "placeholder" => app_lang("last_name"), "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="email" class=" col-md-3"><?php echo app_lang("email"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "email", "name" => "email", "value" => $model_info->email ?? "", "class" => "form-control", "placeholder" => app_lang("email"), "data-rule-required" => true, "data-rule-email" => true, "data-msg-required" => app_lang("field_required"), "data-msg-email" => app_lang("enter_valid_email")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="phone" class=" col-md-3"><?php echo app_lang("phone"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "phone", "name" => "phone", "value" => $model_info->phone ?? "", "class" => "form-control", "placeholder" => app_lang("phone")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="password" class=" col-md-3"><?php echo app_lang("password"); ?></label>
                <div class="col-md-9">
                    <?php echo form_password(["id" => "password", "name" => "password", "value" => "", "class" => "form-control", "placeholder" => app_lang("password")]); ?>
                    <small class="text-muted"><?php echo app_lang("leave_blank_to_keep"); ?></small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="status" class=" col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "status",
                        ["active" => app_lang("active"), "inactive" => app_lang("inactive")],
                        $model_info->status ?? "active",
                        "class='form-control select2' id='status' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <small class="text-muted"><?php echo app_lang("department_user_login_note"); ?></small>

    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">
        <i data-feather="x" class="icon-16"></i> <?php echo app_lang('close'); ?>
    </button>
    <button type="submit" class="btn btn-primary">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang('save'); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
    $(document).ready(function () {
        $("#gate-pass-department-user-form").appForm({
            onSuccess: function () {
                $("#gate-pass-department-users-table").appTable({ reload: true });
            }
        });

        $("#company_id, #department_id, #status").select2();

        $("#company_id").on("change", function () {
            var companyId = $(this).val() || 0;

            $("#department_id").empty().append(new Option("- <?php echo app_lang("select_department"); ?> -", "0", true, true)).trigger("change");

            if (parseInt(companyId) <= 0) return;

            $.ajax({
                url: "<?php echo_uri('gate_pass_department_users/departments_by_company'); ?>/" + companyId,
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (!res || !res.results) return;

                    res.results.forEach(function (item) {
                        $("#department_id").append(new Option(item.text, item.id, false, false));
                    });

                    $("#department_id").trigger("change");
                }
            });
        });
    });
</script>
