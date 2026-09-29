<?php
$fee_amount = (float)($request->fee_amount ?? 0);
$currency   = $request->currency ?? "OMR";

$is_waived  = (int)($request->fee_is_waived ?? 0) === 1;
$waive_reason = $request->fee_waived_reason ?? "";
?>

<?php echo form_open(get_uri("gate_pass_department_requests/save_approval"), ["id" => "gp-dept-approval-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

    <!-- Fee summary -->
    <div class="alert alert-info mb-3">
        <strong>Fee:</strong>
        <?php echo esc($currency); ?> <?php echo number_format($fee_amount, 3); ?>
        <?php if ($fee_amount <= 0): ?>
            <span class="text-muted">(No fee)</span>
        <?php endif; ?>

        <?php if ($fee_amount > 0): ?>
            <div class="mt-2">
                <span class="badge <?php echo $is_waived ? "bg-success" : "bg-secondary"; ?>">
                    <?php echo $is_waived ? "Currently Waived" : "Not Waived"; ?>
                </span>
            </div>
        <?php endif; ?>

        <small class="text-muted d-block mt-2">
            <?php echo app_lang("gate_pass_dept_approval_fee_info"); ?>
        </small>
    </div>

    <div class="mb-3">
        <label class="form-label"><?php echo app_lang("decision"); ?></label>
        <select name="decision" id="gp-dept-decision" class="form-control" required>
            <option value="approved"><?php echo app_lang("approve"); ?></option>
            <option value="returned"><?php echo app_lang("return_for_review"); ?></option>
            <option value="rejected"><?php echo app_lang("reject"); ?></option>
        </select>
    </div>

    <?php if ($fee_amount > 0): ?>
        <!-- Waive controls (only shown when decision = approved) -->
        <div class="mb-3" id="gp-waive-wrap" style="display:none;">
            <!-- hidden default (so unchecked still posts 0) -->
            <input type="hidden" name="fee_is_waived" value="0">

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="gp-waive-fee"
                       name="fee_is_waived" value="1"
                       <?php echo $is_waived ? "checked" : ""; ?>>
                <label class="form-check-label" for="gp-waive-fee">
                    <?php echo app_lang("gate_pass_dept_request_fee_waiver"); ?>
                </label>
            </div>

            <div class="mt-2" id="gp-waive-reason-wrap" style="display:none;">
                <label class="form-label"><?php echo app_lang("gate_pass_dept_fee_waiver_reason_label"); ?> <span class="text-danger">*</span></label>
                <textarea name="fee_waived_reason" id="gp-waive-reason" class="form-control" rows="3"
                          placeholder="<?php echo app_lang("gate_pass_dept_fee_waiver_reason_label"); ?>"><?php echo esc($waive_reason); ?></textarea>
                <small class="text-muted"><?php echo app_lang("gate_pass_dept_fee_waiver_reason_hint"); ?></small>
            </div>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">
            <?php echo app_lang("comment"); ?>
            <span class="text-danger">(<?php echo app_lang("required_for_return_reject"); ?>)</span>
        </label>
        <textarea name="comment" id="gp-dept-comment" class="form-control" rows="4" placeholder="<?php echo app_lang("write_remark"); ?>"></textarea>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {

    function syncWaiveUI() {
        const decision = $("#gp-dept-decision").val();
        const showWaiveBlock = (decision === "approved");

        $("#gp-waive-wrap").toggle(showWaiveBlock);

        const isWaived = $("#gp-waive-fee").is(":checked");
        $("#gp-waive-reason-wrap").toggle(showWaiveBlock && isWaived);

        // if not waived, clear reason so we don't send stale text
        if (!isWaived) {
            $("#gp-waive-reason").val("");
        }
    }

    $("#gp-dept-decision").on("change", syncWaiveUI);
    $("#gp-waive-fee").on("change", syncWaiveUI);
    syncWaiveUI();

    $("#gp-dept-approval-form").appForm({
        onSubmit: function () {
            const decision = $("#gp-dept-decision").val();
            const waiveVisible = $("#gp-waive-wrap").is(":visible");
            const waiveChecked = waiveVisible && $("#gp-waive-fee").is(":checked");

            if (decision === "approved" && waiveChecked) {
                const r = ($("#gp-waive-reason").val() || "").trim();
                if (!r) {
                    appAlert.error("<?php echo app_lang('field_required'); ?>");
                    return false;
                }
            }
            return true;
        },
        onSuccess: function () {
            window.location.href = "<?php echo get_uri('gate_pass_department_requests'); ?>";
        }
    });

    if (typeof feather !== "undefined") feather.replace();
});
</script>
