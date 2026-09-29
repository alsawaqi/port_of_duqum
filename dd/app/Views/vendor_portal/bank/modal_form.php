<?php echo form_open_multipart(get_uri("vendor_portal/save_bank_account"), ["id" => "bank-account-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body">
    <input type="hidden" name="id" value="<?php echo $model_info->id ?? ""; ?>" />

    <div class="form-group">
        <label><?php echo app_lang("bank_name"); ?></label>
        <input name="bank_name" class="form-control" value="<?php echo esc($model_info->bank_name ?? ""); ?>" required />
    </div>

    <div class="form-group">
        <label><?php echo app_lang("branch"); ?></label>
        <input name="bank_branch" class="form-control" value="<?php echo esc($model_info->bank_branch ?? ""); ?>" />
    </div>

    <div class="form-group">
        <label><?php echo app_lang("account_no"); ?></label>
        <input name="bank_account_no" class="form-control" value="<?php echo esc($model_info->bank_account_no ?? ""); ?>" required />
    </div>

    <div class="form-group">
        <label><?php echo app_lang("swift_code"); ?></label>
        <input name="bank_swift_code" class="form-control" value="<?php echo esc($model_info->bank_swift_code ?? ""); ?>" />
    </div>

    <div class="form-group">
        <label><?php echo app_lang("iban"); ?></label>
        <input name="iban" class="form-control" value="<?php echo esc($model_info->iban ?? ""); ?>" />
    </div>

    <div class="form-group">
        <label><?php echo app_lang("letter_head"); ?></label>
        <input type="file" name="letter_head" class="form-control" />
        <?php if (!empty($model_info->letter_head_path)) { ?>
            <div class="mt-2">
                <?php echo anchor(
                    get_uri("vendor_portal/download_bank_letter_head/" . $model_info->id),
                    app_lang("download"),
                    ["target" => "_blank"]
                ); ?>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script>
    $(document).ready(function() {
        $("#bank-account-form").appForm({
            isAjax: true,
            isMultipart: true,
            onSuccess: function(result) {
                $("#bank-accounts-table").appTable({
                    newData: result.data,
                    dataId: result.id
                });
            }
        });
    });
</script>