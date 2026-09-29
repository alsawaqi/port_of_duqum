<?php echo form_open(get_uri("vendors/block"), ["id" => "vendor-block-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int)($vendor->id ?? 0); ?>" />

        <div class="alert alert-warning mb15">
            <?php echo app_lang("vendor_block_warning"); ?>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("vendor"); ?></label>
                <div class="col-md-9">
                    <div class="form-control-plaintext">
                        <strong><?php echo esc($vendor->vendor_name ?? "-"); ?></strong>
                        <span class="text-muted"><?php echo esc($vendor->email ?? ""); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3"><?php echo app_lang("reason"); ?></label>
                <div class="col-md-9">
                    <?php echo form_textarea([
                        "name" => "reason",
                        "class" => "form-control",
                        "placeholder" => app_lang("vendor_block_reason_placeholder"),
                        "data-rule-required" => true,
                        "data-msg-required" => app_lang("field_required")
                    ]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("cancel"); ?></button>
    <button type="submit" class="btn btn-danger"><?php echo app_lang("block_vendor"); ?></button>
</div>

<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-block-form").appForm({
            onSuccess: function(result) {
                appAlert.success(result.message || "<?php echo app_lang("record_saved"); ?>", {
                    duration: 2000
                });
                $("#vendors-table").appTable({
                    reload: true
                });
            }
        });
    });
</script>
