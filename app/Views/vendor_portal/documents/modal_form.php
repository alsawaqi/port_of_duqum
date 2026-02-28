<?php echo form_open(get_uri("vendor_portal/save_document"), [
    "id" => "vendor-document-form",
    "class" => "general-form",
    "role" => "form",
    "enctype" => "multipart/form-data"
]); ?>

<div class="modal-body clearfix">
    <input type="hidden" name="id" value="<?php echo $model_info->id ?? ""; ?>" />

    <div class="form-group">
        <label class="control-label"><?php echo app_lang("document_type"); ?></label>
        <?php
        echo form_dropdown(
            "vendor_document_type_id",
            $types_dropdown,
            $model_info->vendor_document_type_id ?? "",
            "class='form-control select2 validate-hidden' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
        );
        ?>
    </div>

    <div class="form-group">
        <label class="control-label"><?php echo app_lang("file"); ?></label>
        <input type="file" name="file" class="form-control" <?php echo empty($model_info->id) ? "required" : ""; ?> />

        <?php if (!empty($model_info->original_name)) { ?>
            <small class="text-muted d-block mt5">
                <?php echo app_lang("current"); ?>: <?php echo esc($model_info->original_name); ?>
            </small>
        <?php } ?>
    </div>

    <div class="form-group">
        <label class="control-label"><?php echo app_lang("issued_at"); ?></label>
        <input type="text" name="issued_at" class="form-control date" value="<?php echo $model_info->issued_at ?? ""; ?>" />
    </div>

    <div class="form-group">
        <label class="control-label"><?php echo app_lang("expires_at"); ?></label>
        <input type="text" name="expires_at" class="form-control date" value="<?php echo $model_info->expires_at ?? ""; ?>" />
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <i data-feather="x" class="icon-16"></i> <?php echo app_lang("close"); ?>
    </button>
    <button type="submit" class="btn btn-primary">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("save"); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {

        $("#vendor-document-form").appForm({
            onSuccess: function(result) {
                $("#vendor-documents-table").appTable({
                    reload: true
                });
            }
        });

        $(".select2").select2();
        setDatePicker(".date");
    });
</script>