<?php echo form_open(get_uri("ptw_reasons/save"), ["id" => "ptw-reason-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Title</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "title",
                        "value" => $model_info->title ?? "",
                        "class" => "form-control",
                        "maxlength" => 255,
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required"),
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Stage</label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "stage",
                        [
                            "any" => "Any",
                            "hsse" => "HSSE",
                            "hmo" => "HMO",
                            "terminal" => "Terminal",
                        ],
                        $model_info->stage ?? "any",
                        "class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Reason Type</label>
                <div class="col-md-9">
                    <?php
                    echo form_dropdown(
                        "reason_type",
                        [
                            "revise" => "Revise",
                            "reject" => "Reject",
                        ],
                        $model_info->reason_type ?? "revise",
                        "class='form-control select2' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("sort_order"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "sort_order",
                        "type" => "number",
                        "min" => 0,
                        "value" => isset($model_info->sort_order) ? (int)$model_info->sort_order : 0,
                        "class" => "form-control",
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("status"); ?></label>
                <div class="col-md-9 pt-2">
                    <?php echo form_checkbox("is_active", "1", isset($model_info->is_active) ? (bool)$model_info->is_active : true); ?>
                    <span class="ms-1"><?php echo app_lang("active"); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#ptw-reason-form").appForm({
        onSuccess: function (result) {
            $("#ptw-reasons-table").appTable({newData: result.data, dataId: result.id});
        }
    });

    $("#ptw-reason-form .select2").select2();
});
</script>