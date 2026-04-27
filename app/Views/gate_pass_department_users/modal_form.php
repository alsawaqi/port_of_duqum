<?php echo form_open(get_uri("gate_pass_department_users/save"), ["id" => "gate-pass-department-user-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">

        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <?php echo view("includes/operational_user_identity_fields", ["model_info" => $model_info]); ?>

        <hr />

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
