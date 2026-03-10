<div class="modal-body">
    <h5 class="mb-3">3-Key Commercial Opening</h5>

    <div><strong>Reference:</strong> <?php echo esc($tender->reference ?? "-"); ?></div>
    <div><strong>Title:</strong> <?php echo esc($tender->title ?? "-"); ?></div>
    <div><strong>Your Role:</strong> <?php echo esc(ucwords(str_replace("_", " ", $my_role ?? "-"))); ?></div>

    <hr>

    <?php if (!$session) { ?>
        <div class="alert alert-warning">No active opening session exists yet.</div>
        <button type="button" class="btn btn-primary" id="generate-3key-btn" data-tender-id="<?php echo (int) $tender->id; ?>">
            Generate 3-Key Codes
        </button>
    <?php } else { ?>
        <div class="alert alert-info">
            Codes expire at: <strong><?php echo esc($session->expires_at); ?></strong>
        </div>

        <div class="mb-3">
            <strong>Your code:</strong>
            <?php if (($my_role ?? "") === "chairman") { ?>
                <span class="badge bg-dark"><?php echo esc($session->chairman_code); ?></span>
            <?php } elseif (($my_role ?? "") === "secretary") { ?>
                <span class="badge bg-dark"><?php echo esc($session->secretary_code); ?></span>
            <?php } elseif (($my_role ?? "") === "itc_member") { ?>
                <span class="badge bg-dark"><?php echo esc($session->member_code); ?></span>
            <?php } ?>
        </div>

        <div class="mb-3">
            <div>Chairman confirmed: <?php echo ($confirm_map["chairman"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
            <div>Secretary confirmed: <?php echo ($confirm_map["secretary"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
            <div>Member confirmed: <?php echo ($confirm_map["itc_member"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
        </div>

        <?php echo form_open(get_uri("tender_committee_opening_inbox/confirm_codes"), ["id" => "tender-3key-confirm-form", "class" => "general-form"]); ?>
        <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />

        <div class="form-group">
            <label>Chairman Code</label>
            <input type="text" name="chairman_code" class="form-control" required>
        </div>

        <div class="form-group">
            <label>Secretary Code</label>
            <input type="text" name="secretary_code" class="form-control" required>
        </div>

        <div class="form-group">
            <label>Member Code</label>
            <input type="text" name="member_code" class="form-control" required>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
            <button type="submit" class="btn btn-primary">Confirm Codes</button>
        </div>

        <?php echo form_close(); ?>
    <?php } ?>
</div>

<script>
$(document).ready(function () {
    $("#generate-3key-btn").on("click", function () {
        $.post('<?php echo_uri("tender_committee_opening_inbox/generate_codes"); ?>', {
            tender_id: $(this).data("tender-id")
        }, function (res) {
            appAlert.success(res.message || "Codes generated.", {duration: 3000});
            $(".modal").modal("hide");
            $("#tender-committee-opening-table").appTable({reload: true});
        });
    });

    $("#tender-3key-confirm-form").appForm({
        onSuccess: function (response) {
            $("#tender-committee-opening-table").appTable({reload: true});
        }
    });
});
</script>