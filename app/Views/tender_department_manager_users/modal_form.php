 
<?php echo form_open(get_uri("tender_department_manager_users/save"), ["id" => "tender-department-manager-user-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("company"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "company_id",
                        $company_dropdown ?? ["0" => "- " . app_lang("select_company") . " -"],
                        $model_info->company_id ?? "0",
                        "class='form-control select2' id='tdu_company_id' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("department"); ?></label>
                <div class="col-md-9">
                    <?php echo form_dropdown(
                        "department_id",
                        $department_dropdown ?? ["0" => "- " . app_lang("select_department") . " -"],
                        $model_info->department_id ?? "0",
                        "class='form-control select2' id='tdu_department_id' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    ); ?>
                </div>
            </div>
        </div>

        <hr />

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("first_name"); ?></label>
            <div class="col-md-9">
                <?php echo form_input(["name"=>"first_name","value"=>$model_info->first_name ?? "","class"=>"form-control","data-rule-required"=>true,"data-msg-required"=>app_lang("field_required")]); ?>
            </div>
        </div></div>

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("last_name"); ?></label>
            <div class="col-md-9">
                <?php echo form_input(["name"=>"last_name","value"=>$model_info->last_name ?? "","class"=>"form-control","data-rule-required"=>true,"data-msg-required"=>app_lang("field_required")]); ?>
            </div>
        </div></div>

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("email"); ?></label>
            <div class="col-md-9">
                <?php echo form_input(["name"=>"email","type"=>"email","value"=>$model_info->email ?? "","class"=>"form-control","data-rule-required"=>true,"data-rule-email"=>true,"data-msg-required"=>app_lang("field_required")]); ?>
            </div>
        </div></div>

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("phone"); ?></label>
            <div class="col-md-9">
                <?php echo form_input(["name"=>"phone","value"=>$model_info->phone ?? "","class"=>"form-control"]); ?>
            </div>
        </div></div>

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("password"); ?></label>
            <div class="col-md-9">
                <?php echo form_password(["name"=>"password","class"=>"form-control","placeholder"=>($model_info ? app_lang("leave_blank_to_keep") : "")]); ?>
            </div>
        </div></div>

        <div class="form-group"><div class="row">
            <label class="col-md-3"><?php echo app_lang("status"); ?></label>
            <div class="col-md-9">
                <?php echo form_dropdown("status", ["active"=>app_lang("active"),"inactive"=>app_lang("inactive")], $model_info->status ?? "active", "class='form-control select2'"); ?>
            </div>
        </div></div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    function loadDepartments(companyId, selectedId) {
        $("#tdu_department_id").empty();
        $("#tdu_department_id").append(new Option("- <?php echo app_lang("select_department"); ?> -", "0", false, false));

        if (!companyId || companyId === "0") {
            $("#tdu_department_id").trigger("change");
            return;
        }

        $.getJSON("<?php echo_uri('tender_department_manager_users/departments_by_company'); ?>", {company_id: companyId})
            .done(function (resp) {
                // controller returns {"results":[{id,text}]} in our implementation
                var rows = (resp && resp.results) ? resp.results : (resp || []);
                rows.forEach(function (r) {
                    var opt = new Option(r.text, r.id, false, false);
                    $("#tdu_department_id").append(opt);
                });

                if (selectedId && selectedId !== "0") {
                    $("#tdu_department_id").val(String(selectedId)).trigger("change");
                } else {
                    $("#tdu_department_id").trigger("change");
                }
            });
    }

    $(".select2").select2();

    // edit mode preload
    var initialCompany = $("#tdu_company_id").val();
    var initialDept = "<?php echo esc($model_info->department_id ?? "0"); ?>";
    if (initialCompany && initialCompany !== "0") {
        loadDepartments(initialCompany, initialDept);
    }

    $("#tdu_company_id").on("change", function () {
        loadDepartments($(this).val(), "0");
    });

    $("#tender-department-manager-user-form").appForm({
        onSuccess: function () {
            $("#tender-department-manager-users-table").appTable({reload: true});
        }
    });
});
</script>