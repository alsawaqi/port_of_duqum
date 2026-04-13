<?php
$is_waived = !empty($request->fee_is_waived);
$is_zero_fee = isset($request->fee_amount) && (float)$request->fee_amount <= 0;
$waiver_pending = gate_pass_fee_waiver_pending($request);
$wst = strtolower(trim((string)($request->fee_waiver_commercial_status ?? "")));
$waiver_rejected_recorded = !$waiver_pending && $wst === "rejected" && !$is_waived;
$waived_by_name = $waived_by_name ?? "";
?>

<?php echo form_open(get_uri("gate_pass_commercial_inbox/save_fee"), ["id" => "gp-commercial-fee-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$request->id; ?>" />

    <p class="mb15"><strong><?php echo app_lang("reference"); ?>:</strong> <?php echo esc($request->reference ?? "-"); ?></p>
    <p class="mb15"><strong><?php echo app_lang("company"); ?>:</strong> <?php echo esc($request->company_name ?? "-"); ?></p>

    <?php if ($waiver_pending): ?>
        <div class="alert alert-warning mb15">
            <div class="mb5"><strong><?php echo app_lang("gate_pass_fee_waiver_pending_commercial_banner"); ?></strong></div>
            <div class="mb5 small text-muted"><?php echo app_lang("gate_pass_fee_waiver_modal_flow_hint"); ?></div>
            <?php if (!empty($request->fee_waived_reason)): ?>
                <div class="mb0"><strong><?php echo app_lang("reason"); ?>:</strong> <?php echo esc($request->fee_waived_reason); ?></div>
            <?php endif; ?>
        </div>
    <?php elseif ($waiver_rejected_recorded): ?>
        <div class="alert alert-secondary mb15">
            <div class="mb0 small"><?php echo app_lang("gate_pass_fee_waiver_rejected_may_waive_later"); ?></div>
        </div>
    <?php elseif ($is_waived): ?>
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
    <?php elseif (!$is_zero_fee): ?>
        <div class="alert alert-info mb15">
            <?php echo app_lang("gate_pass_commercial_note_requester_pays"); ?>
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

    <?php if ($waiver_pending): ?>
        <input type="hidden" name="fee_is_waived" value="0" />
        <div class="form-group">
            <label><?php echo app_lang("waived"); ?>?</label>
            <p class="form-control-static text-muted mb0"><?php echo app_lang("gate_pass_fee_waiver_pending"); ?></p>
        </div>
    <?php else: ?>
        <div class="form-group">
            <label><?php echo app_lang("waived"); ?>?</label>
            <select name="fee_is_waived" id="gp-fee-is-waived" class="form-control">
                <option value="0" <?php echo !$is_waived ? "selected" : ""; ?>>No</option>
                <option value="1" <?php echo $is_waived ? "selected" : ""; ?>>Yes</option>
            </select>
        </div>
    <?php endif; ?>

    <div class="form-group" id="gp-waived-reason-wrap" style="<?php echo $waiver_pending ? "display:none;" : ""; ?>">
        <label><?php echo app_lang("reason"); ?></label>
        <textarea name="fee_waived_reason" id="gp-fee-waived-reason" class="form-control" rows="3" placeholder="<?php echo app_lang("comment"); ?>"><?php echo esc($request->fee_waived_reason ?? ""); ?></textarea>
    </div>

    <?php if ($waiver_pending): ?>
        <div class="form-group">
            <label><?php echo app_lang("gate_pass_fee_waiver_decision_comment"); ?></label>
            <textarea id="gp-fee-waiver-decision-comment" class="form-control" rows="2" placeholder="<?php echo app_lang("comment"); ?>"></textarea>
        </div>
    <?php endif; ?>

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
    <?php if (!$is_waived && !$waiver_pending): ?>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
    <?php endif; ?>
    <?php if ($waiver_pending): ?>
    <button type="button" id="gp-approve-dept-waiver-btn" class="btn btn-success gp-pro-btn-success">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gate_pass_fee_waiver_approve"); ?>
    </button>
    <button type="button" id="gp-reject-dept-waiver-btn" class="btn btn-warning gp-pro-btn">
        <i data-feather="thumbs-down" class="icon-16"></i> <?php echo app_lang("gate_pass_fee_waiver_reject"); ?>
    </button>
    <?php else: ?>
    <button type="button" id="gp-approve-waiver-btn" class="btn btn-success gp-pro-btn-success">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("approve"); ?>
    </button>
    <?php endif; ?>
    <button type="button" id="gp-reject-request-btn" class="btn btn-danger gp-pro-btn-danger">
        <i data-feather="x-circle" class="icon-16"></i> <?php echo $waiver_pending ? app_lang("gate_pass_commercial_reject_application") : app_lang("reject"); ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    var waiverPending = <?php echo $waiver_pending ? "true" : "false"; ?>;

    $("#gp-commercial-fee-form").appForm({
        onSuccess: function () {
            window.location.href = "<?php echo get_uri('gate_pass_commercial_inbox'); ?>";
        }
    });

    $(".select2").select2();

    function syncFeeUI() {
        if (waiverPending) {
            $("#gp-waived-reason-wrap").hide();
            return;
        }
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

    function postWaiverDecision(outcome, $btn) {
        $btn.prop("disabled", true);
        $.ajax({
            url: "<?php echo get_uri('gate_pass_commercial_inbox/fee_waiver_decision'); ?>",
            type: "POST",
            dataType: "json",
            data: {
                gate_pass_request_id: "<?php echo (int)$request->id; ?>",
                outcome: outcome,
                comment: ($("#gp-fee-waiver-decision-comment").val() || "").trim(),
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function (res) {
                if (res && res.success) {
                    appAlert.success(res.message || "<?php echo app_lang('record_saved'); ?>");
                    window.location.href = "<?php echo get_uri('gate_pass_commercial_inbox'); ?>";
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
    }

    $("#gp-approve-dept-waiver-btn").on("click", function () {
        postWaiverDecision("approve", $(this));
    });

    $("#gp-reject-dept-waiver-btn").on("click", function () {
        postWaiverDecision("reject", $(this));
    });

    $("#gp-approve-waiver-btn").on("click", function () {
        var $btn = $(this);
        $btn.prop("disabled", true);

        function postApproveToSecurity() {
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
                        window.location.href = "<?php echo get_uri('gate_pass_commercial_inbox'); ?>";
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
        }

        // Approve reads the saved row. If the user chose "Waived" (or changed fee) but did not click Save yet, persist first.
        $.ajax({
            url: "<?php echo get_uri('gate_pass_commercial_inbox/save_fee'); ?>",
            type: "POST",
            dataType: "json",
            data: $("#gp-commercial-fee-form").serialize(),
            success: function (res) {
                if (res && res.success) {
                    postApproveToSecurity();
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
                    window.location.href = "<?php echo get_uri('gate_pass_commercial_inbox'); ?>";
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
