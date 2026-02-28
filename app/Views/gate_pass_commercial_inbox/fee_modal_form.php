<?php
$is_waived = !empty($request->fee_is_waived);
$is_zero_fee = isset($request->fee_amount) && (float)$request->fee_amount <= 0;
$waived_by_name = $waived_by_name ?? "";
?>

<?php echo form_open(get_uri("gate_pass_commercial_inbox/save_fee"), ["id" => "gp-commercial-fee-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

    <p class="mb15"><strong><?php echo app_lang("reference"); ?>:</strong> <?php echo esc($request->reference ?? "-"); ?></p>
    <p class="mb15"><strong><?php echo app_lang("company"); ?>:</strong> <?php echo esc($request->company_name ?? "-"); ?></p>

    <?php if ($is_waived): ?>
        <div class="alert alert-success mb15">
            <div class="mb5"><strong>Fee:</strong> <?php echo app_lang("waived"); ?></div>
            <?php if (!empty($request->fee_waived_reason)): ?>
                <div class="mb5"><strong>Reason:</strong> <?php echo esc($request->fee_waived_reason); ?></div>
            <?php endif; ?>
            <?php if (!empty($waived_by_name)): ?>
                <div class="mb5"><strong><?php echo app_lang("waived_by"); ?>:</strong> <?php echo esc($waived_by_name); ?></div>
            <?php endif; ?>
            <?php if (!empty($request->fee_waived_at)): ?>
                <div class="mb0"><strong>Date:</strong> <?php echo format_to_datetime($request->fee_waived_at); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$is_waived && !$is_zero_fee): ?>
        <div class="alert alert-info mb15">
            <strong>Note:</strong> Requester must complete payment in the Portal after department approval.
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label><?php echo app_lang("currency"); ?></label>
        <?php echo form_dropdown(
            "currency",
            $currency_options ?? ["OMR" => "OMR", "USD" => "USD", "EUR" => "EUR", "GBP" => "GBP"],
            $request->currency ?? "OMR",
            "class='form-control select2' required"
        ); ?>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("fee_amount"); ?></label>
        <?php echo form_input([
            "id" => "gp-fee-amount",
            "name" => "fee_amount",
            "type" => "number",
            "step" => "0.001",
            "min" => "0",
            "value" => isset($request->fee_amount) && $request->fee_amount !== "" ? $request->fee_amount : "0",
            "class" => "form-control",
            "required" => true,
        ]); ?>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("waived"); ?>?</label>
        <select name="fee_is_waived" id="gp-fee-is-waived" class="form-control">
            <option value="0" <?php echo !$is_waived ? "selected" : ""; ?>>No</option>
            <option value="1" <?php echo $is_waived ? "selected" : ""; ?>>Yes</option>
        </select>
    </div>

    <div class="form-group" id="gp-waived-reason-wrap">
        <label><?php echo app_lang("reason"); ?></label>
        <textarea name="fee_waived_reason" id="gp-fee-waived-reason" class="form-control" rows="3" placeholder="Optional reason for fee waiver"><?php echo esc($request->fee_waived_reason ?? ""); ?></textarea>
    </div>

    <hr />

    <div class="form-group">
        <label><?php echo app_lang("reject_reason"); ?></label>
        <?php echo form_dropdown(
            "reason_id",
            $reason_options ?? ["0" => "- " . app_lang("select") . " -"],
            "0",
            "id='gp-reject-reason' class='form-control select2'"
        ); ?>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("comment"); ?> (optional)</label>
        <textarea name="reject_comment" id="gp-reject-comment" class="form-control" rows="3" placeholder="<?php echo app_lang("comment"); ?>..."></textarea>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
    <button type="button" id="gp-approve-waiver-btn" class="btn btn-success gp-pro-btn-success">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("approve"); ?>
    </button>
    <button type="button" id="gp-reject-request-btn" class="btn btn-danger gp-pro-btn-danger">
        <i data-feather="x-circle" class="icon-16"></i> <?php echo app_lang("reject"); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-commercial-fee-form").appForm({
        onSuccess: function () {
            $("#gate-pass-commercial-inbox-table").appTable({ newData: true });
        }
    });

    $(".select2").select2();

    function syncFeeUI() {
        var waived = $("#gp-fee-is-waived").val() === "1";
        var feeAmount = parseFloat($("#gp-fee-amount").val() || "0");
        if (isNaN(feeAmount)) {
            feeAmount = 0;
        }

        $("#gp-waived-reason-wrap").toggle(waived);
        $("#gp-approve-waiver-btn").toggle(waived || feeAmount <= 0);
    }

    $("#gp-fee-is-waived").on("change", syncFeeUI);
    $("#gp-fee-amount").on("input change", syncFeeUI);
    syncFeeUI();

    $("#gp-approve-waiver-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);

        $.ajax({
            url: "<?php echo get_uri('gate_pass_commercial_inbox/approve_waiver'); ?>",
            type: "POST",
            dataType: "json",
            data: {
                gate_pass_request_id: "<?php echo (int)$request->id; ?>",
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function (res) {
                if (res && res.success) {
                    appAlert.success(res.message || "<?php echo app_lang('record_saved'); ?>");
                    $("#gate-pass-commercial-inbox-table").appTable({ newData: true });
                    $(".modal").modal("hide");
                } else {
                    appAlert.error((res && res.message) ? res.message : "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });

    $("#gp-reject-request-btn").on("click", function () {
        var $btn = $(this);
        var reasonId = parseInt($("#gp-reject-reason").val() || "0", 10);
        var comment = $("#gp-reject-comment").val() || "";

        if (!reasonId) {
            appAlert.error("<?php echo app_lang('reject_reason_required'); ?>");
            return;
        }

        $btn.prop("disabled", true);

        $.ajax({
            url: "<?php echo get_uri('gate_pass_commercial_inbox/reject_request'); ?>",
            type: "POST",
            dataType: "json",
            data: {
                gate_pass_request_id: "<?php echo (int)$request->id; ?>",
                reason_id: reasonId,
                comment: comment,
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function (res) {
                if (res && res.success) {
                    appAlert.success(res.message || "<?php echo app_lang('record_saved'); ?>");
                    $("#gate-pass-commercial-inbox-table").appTable({ newData: true });
                    $(".modal").modal("hide");
                } else {
                    appAlert.error((res && res.message) ? res.message : "<?php echo app_lang('error_occurred'); ?>");
                    $btn.prop("disabled", false);
                }
            },
            error: function () {
                appAlert.error("<?php echo app_lang('error_occurred'); ?>");
                $btn.prop("disabled", false);
            }
        });
    });

    if (typeof feather !== "undefined") feather.replace();
});
</script>
