<?php echo form_open(get_uri("vendor_update_requests/review"), [
    "id" => "review-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<input type="hidden" name="id" value="<?php echo $id; ?>" />

<div class="modal-body">
    <div class="form-group">
        <label><?php echo app_lang("comment"); ?></label>
        <textarea name="comment"
            class="form-control"
            required
            rows="4"
            placeholder="<?php echo app_lang('write_a_comment'); ?>"></textarea>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <?php echo app_lang("close"); ?>
    </button>
    <button type="submit" class="btn btn-info">
        <i data-feather="send" class="icon-16"></i> <?php echo app_lang("send"); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
    $("#review-form").appForm({
        onSuccess: function() {
            $("#ajaxModal").modal("hide");

            // reload whichever table is present (index page vs vendor-specific page)
            if ($("#vendor-update-requests-table").length) {
                $("#vendor-update-requests-table").appTable({
                    reload: true
                });
            }
            if ($("#vur-by-vendor-table").length) {
                $("#vur-by-vendor-table").appTable({
                    reload: true
                });
            }
        }
    });
</script>