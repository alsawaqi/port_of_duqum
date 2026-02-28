<?php echo form_open(get_uri("ptw_requirement_definitions/save"), ["id" => "ptw-requirement-definition-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />
        <input type="hidden" name="category" value="<?php echo esc($category); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Category</label>
                <div class="col-md-9 pt-2">
                    <span class="badge bg-info"><?php echo esc($category_label); ?></span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Label</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "label",
                        "value" => $model_info->label ?? "",
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
                <label class="col-md-3">Code</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "code",
                        "value" => $model_info->code ?? "",
                        "class" => "form-control",
                        "maxlength" => 255,
                        "placeholder" => "e.g. risk_assessment"
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Group Key</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "group_key",
                        "value" => $model_info->group_key ?? "",
                        "class" => "form-control",
                        "maxlength" => 100,
                        "placeholder" => "Optional grouping key"
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Help Text</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "help_text",
                        "value" => $model_info->help_text ?? "",
                        "class" => "form-control",
                        "maxlength" => 255,
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Text Input Label</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "text_label",
                        "value" => $model_info->text_label ?? "",
                        "class" => "form-control",
                        "maxlength" => 255,
                        "placeholder" => "Shown when Text Input is enabled"
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Allowed Extensions</label>
                <div class="col-md-9">
                    <?php echo form_input([
                        "name" => "allowed_extensions",
                        "value" => $model_info->allowed_extensions ?? "",
                        "class" => "form-control",
                        "maxlength" => 255,
                        "placeholder" => "pdf,doc,docx,jpg,jpeg,png"
                    ]); ?>
                    <small class="text-muted">Comma separated</small>
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
                <label class="col-md-3">Options</label>
                <div class="col-md-9 pt-2">
                    <div class="mb-2">
                        <?php echo form_checkbox("is_mandatory", "1", ($model_info->is_mandatory ?? 0) ? true : false); ?>
                        <span class="ms-1">Mandatory</span>
                    </div>
                    <div class="mb-2">
                        <?php echo form_checkbox("requires_attachment", "1", ($model_info->requires_attachment ?? 0) ? true : false); ?>
                        <span class="ms-1">Requires Attachment</span>
                    </div>
                    <div class="mb-2">
                        <?php echo form_checkbox("has_text_input", "1", ($model_info->has_text_input ?? 0) ? true : false); ?>
                        <span class="ms-1">Has Text Input</span>
                    </div>
                    <div>
                        <?php echo form_checkbox("is_active", "1", isset($model_info->is_active) ? (bool)$model_info->is_active : true); ?>
                        <span class="ms-1"><?php echo app_lang("active"); ?></span>
                    </div>
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
    $("#ptw-requirement-definition-form").appForm({
        onSuccess: function (result) {
            $("#ptw-requirement-definitions-table").appTable({newData: result.data, dataId: result.id});
        }
    });
});
</script>