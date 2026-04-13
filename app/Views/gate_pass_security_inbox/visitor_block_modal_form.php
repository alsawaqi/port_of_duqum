<?php echo form_open(get_uri("gate_pass_security_inbox/save_visitor_block"), ["id" => "gp-sec-visitor-block-form", "class" => "general-form", "role" => "form"]); ?>

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
        <select name="block_action" id="sec-block-action" class="form-control" required>
            <option value="block">Block</option>
            <option value="unblock">Unblock</option>
        </select>
    </div>

    <div class="mb-3">
        <label class="form-label"><?php echo app_lang("reason"); ?> (required for block)</label>
        <textarea name="block_reason" id="sec-block-reason" class="form-control" rows="3" placeholder="Enter block reason..."></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    var prefillVid = <?php echo (int)($prefill_visitor_id ?? 0); ?>;
    var prefillAct = <?php echo json_encode((string)($prefill_action ?? "")); ?>;

    function syncReasonRequired() {
        var action = $("#sec-block-action").val();
        $("#sec-block-reason").prop("required", action === "block");
        if (action === "unblock") {
            $("#sec-block-reason").val("");
        }
    }

    $("#sec-block-action").on("change", syncReasonRequired);
    syncReasonRequired();

    if (prefillVid > 0) {
        $("select[name=visitor_id]").val(String(prefillVid));
    }
    if (prefillAct === "block" || prefillAct === "unblock") {
        $("#sec-block-action").val(prefillAct);
        syncReasonRequired();
    }

    $("#gp-sec-visitor-block-form").appForm({
        onSuccess: function () {
            if ($.fn.DataTable && $.fn.DataTable.isDataTable($("#gate-pass-security-inbox-table"))) {
                $("#gate-pass-security-inbox-table").DataTable().ajax.reload(null, false);
            }
            $(document).trigger("gp-security-visitor-block-saved");
            if (typeof window.location !== "undefined" && window.location.pathname.indexOf("details") !== -1) {
                window.location.reload();
            }
        }
    });
});
</script>
