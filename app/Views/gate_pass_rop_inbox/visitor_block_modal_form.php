<?php echo form_open(get_uri("gate_pass_rop_inbox/save_visitor_block"), ["id" => "gp-rop-visitor-block-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body gp-pro-modal-body">
    <input type="hidden" name="request_id" value="<?php echo (int)$request->id; ?>" />

    <div class="mb-3">
        <label class="form-label">Visitor <span class="text-danger">*</span></label>
        <select name="visitor_id" class="form-control" required>
            <option value="">- Select visitor -</option>
            <?php foreach (($visitors ?? []) as $v): ?>
                <option value="<?php echo (int)$v->id; ?>">
                    <?php echo esc($v->full_name ?: ("Visitor #" . (int)$v->id)); ?>
                    <?php if ((int)($v->is_blocked ?? 0) === 1): ?> (Blocked)<?php endif; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label">Action <span class="text-danger">*</span></label>
        <select name="block_action" id="rop-block-action" class="form-control" required>
            <option value="block">Block</option>
            <option value="unblock">Unblock</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label"><?php echo app_lang("reason"); ?> (required for block)</label>
        <textarea name="block_reason" id="rop-block-reason" class="form-control" rows="3" placeholder="Enter block reason..."></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    function syncReasonRequired() {
        var action = $("#rop-block-action").val();
        $("#rop-block-reason").prop("required", action === "block");
        if (action === "unblock") {
            $("#rop-block-reason").val("");
        }
    }

    $("#rop-block-action").on("change", syncReasonRequired);
    syncReasonRequired();

    $("#gp-rop-visitor-block-form").appForm({
        onSuccess: function () {
            $("#gate-pass-rop-inbox-table").DataTable().ajax.reload(null, false);
        }
    });
});
</script>
