<div class="modal-body">
    <h5 class="mb-3"><?php echo esc($opening_title ?? "Bid Opening"); ?></h5>

    <div><strong>Reference:</strong> <?php echo esc($tender->reference ?? "-"); ?></div>
    <div><strong>Title:</strong> <?php echo esc($tender->title ?? "-"); ?></div>
    <div><strong>Opening Stage:</strong> Bid Opening</div>
    <div><strong>Your Role:</strong> <?php echo esc(ucwords(str_replace("_", " ", $my_role ?? "-"))); ?></div>

    <hr>

    <?php if (!$session) { ?>
        <div class="alert alert-warning">No active opening session exists yet.</div>
        <button type="button" class="btn btn-primary" id="generate-3key-btn" data-tender-id="<?php echo (int) $tender->id; ?>" data-opening-stage="<?php echo esc($opening_stage ?? "technical"); ?>">
            Generate 3-Key Codes
        </button>
    <?php } else { ?>
        <div class="alert alert-info">
            <?php if (($session->status ?? "") === "codes_generated") { ?>
                Codes expire at: <strong><?php echo esc($session->expires_at); ?></strong>
            <?php } elseif (($session->status ?? "") === "signed") { ?>
                All committee signatures are complete. Procurement can download the bid opening form and start technical review.
            <?php } else { ?>
                Bids are unlocked. Review the technical and commercial bid package below, then sign digitally.
            <?php } ?>
        </div>

        <?php if (($session->status ?? "") === "codes_generated") { ?>
            <div class="mb-3">
                <div>Chairman confirmed: <?php echo ($confirm_map["chairman"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
                <div>Secretary confirmed: <?php echo ($confirm_map["secretary"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
                <div>Member confirmed: <?php echo ($confirm_map["itc_member"] ?? 0) >= 1 ? "Yes" : "No"; ?></div>
            </div>

            <?php if (($confirm_map[$my_role ?? ""] ?? 0) >= 1) { ?>
                <div class="alert alert-success">Your assigned role code has already been confirmed.</div>
            <?php } elseif (!empty($code_unavailable) || empty($my_code)) { ?>
                <div class="alert alert-danger">
                    Your assigned role code cannot be retrieved securely. Ask an authorized committee member to regenerate the opening codes or contact the system administrator.
                </div>
            <?php } else { ?>
                <div class="mb-3">
                    <strong>Your assigned role code:</strong>
                    <span class="badge bg-dark font-monospace" aria-label="Your assigned role code"><?php echo esc($my_code); ?></span>
                </div>

                <?php echo form_open(get_uri("tender_committee_opening_inbox/confirm_codes"), ["id" => "tender-3key-confirm-form", "class" => "general-form"]); ?>
                <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                <input type="hidden" name="opening_stage" value="<?php echo esc($opening_stage ?? "technical"); ?>" />

                <div class="form-group">
                    <label for="opening-code-input">Confirm your assigned role code</label>
                    <input
                        id="opening-code-input"
                        type="password"
                        name="opening_code"
                        class="form-control"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        autocomplete="one-time-code"
                        spellcheck="false"
                        required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
                    <button type="submit" class="btn btn-primary">Confirm My Code</button>
                </div>

                <?php echo form_close(); ?>
            <?php } ?>
        <?php } else { ?>
            <h5 class="mb10">Bid Package Summary</h5>
            <div class="table-responsive mb15">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Status</th>
                            <th>Technical</th>
                            <th>Commercial Without Price</th>
                            <th>Commercial With Price</th>
                            <th>Bank Guarantee</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bid_summary)) { ?>
                            <tr><td colspan="7" class="text-center text-off p20">No submitted bids found.</td></tr>
                        <?php } ?>
                        <?php foreach (($bid_summary ?? []) as $bid) { ?>
                            <?php
                            $doc_link = static function ($doc_id, $label) {
                                if (empty($doc_id)) {
                                    return esc($label ?: "-");
                                }
                                return anchor(
                                    get_uri("tender_committee_opening_inbox/download_bid_document/" . (int) $doc_id),
                                    esc($label ?: "Download"),
                                    ["class" => "btn btn-default btn-xs", "target" => "_blank"]
                                );
                            };
                            ?>
                            <tr>
                                <td><?php echo esc($bid->vendor_name ?? "-"); ?></td>
                                <td><?php echo esc(ucwords(str_replace("_", " ", $bid->bid_status ?? "-"))); ?></td>
                                <td><?php echo $doc_link($bid->technical_doc_id ?? 0, $bid->technical_doc_name ?? "-"); ?></td>
                                <td><?php echo $doc_link($bid->commercial_unpriced_doc_id ?? 0, $bid->commercial_unpriced_doc_name ?? "-"); ?></td>
                                <td><?php echo $doc_link($bid->commercial_priced_doc_id ?? 0, $bid->commercial_priced_doc_name ?? "-"); ?></td>
                                <td><?php echo $doc_link($bid->bank_guarantee_doc_id ?? 0, $bid->bank_guarantee_doc_name ?? "-"); ?></td>
                                <td><?php echo $bid->total_amount !== null ? number_format((float) $bid->total_amount, 3) . " " . esc($bid->currency ?? "OMR") : "-"; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <h5 class="mb10">Committee Digital Signatures</h5>
            <div class="mb15">
                <span class="badge bg-light text-dark me-1">Chairman: <?php echo ($signature_map["chairman"] ?? 0) >= 1 ? "Signed" : "Pending"; ?></span>
                <span class="badge bg-light text-dark me-1">Secretary: <?php echo ($signature_map["secretary"] ?? 0) >= 1 ? "Signed" : "Pending"; ?></span>
                <span class="badge bg-light text-dark me-1">Member: <?php echo ($signature_map["itc_member"] ?? 0) >= 1 ? "Signed" : "Pending"; ?></span>
            </div>

            <?php if (($signature_map[$my_role ?? ""] ?? 0) >= 1) { ?>
                <div class="alert alert-success">Your digital signature has been saved.</div>
            <?php } elseif (($session->status ?? "") === "unlocked") { ?>
                <?php echo form_open(get_uri("tender_committee_opening_inbox/sign_opening"), ["id" => "tender-3key-sign-form", "class" => "general-form"]); ?>
                    <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                    <input type="hidden" name="opening_stage" value="<?php echo esc($opening_stage ?? "technical"); ?>" />

                    <div class="form-group">
                        <label>Signature Name</label>
                        <input type="text" name="signature_name" class="form-control" value="<?php echo esc(trim((string) (($this->login_user->first_name ?? "") . " " . ($this->login_user->last_name ?? "")))); ?>">
                    </div>
                    <div class="form-group">
                        <label>Digital Signature Statement</label>
                        <textarea name="committee_signature_statement" class="form-control" rows="3" required>I confirm that I reviewed the opened technical and commercial bid package for this tender and digitally sign the bid opening form.</textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
                        <button type="submit" class="btn btn-primary">Sign Bid Opening</button>
                    </div>
                <?php echo form_close(); ?>
            <?php } ?>
        <?php } ?>
    <?php } ?>
</div>

<script>
$(document).ready(function () {
    function tryParseResponse(res) {
        if (typeof res === "object") {
            return res;
        }
        try {
            return JSON.parse(res);
        } catch (e) {
            return {success: false, message: "Unexpected server response."};
        }
    }

    $("#generate-3key-btn").on("click", function () {
        appLoader.show();
        $.post('<?php echo_uri("tender_committee_opening_inbox/generate_codes"); ?>', {
            tender_id: $(this).data("tender-id"),
            opening_stage: $(this).data("opening-stage")
        }, function (res) {
            appLoader.hide();
            var r = tryParseResponse(res);
            if (r.success) {
                appAlert.success(r.message || "Codes generated.", {duration: 3000});
                $(".modal").modal("hide");
                $("#tender-committee-opening-table").appTable({reload: true});
            } else {
                appAlert.error(r.message || "Error", {duration: 3000});
            }
        }).fail(function (xhr) {
            appLoader.hide();
            var r = tryParseResponse(xhr.responseText || "");
            appAlert.error(r.message || "Request failed. Please try again.", {duration: 3000});
        });
    });

    $("#tender-3key-confirm-form").appForm({
        onSuccess: function (response) {
            if (response.redirect_url) {
                window.location.href = response.redirect_url;
                return;
            }
            $("#tender-committee-opening-table").appTable({reload: true});
        }
    });

    $("#tender-3key-sign-form").appForm({
        onSuccess: function (response) {
            if (response.redirect_url) {
                window.location.href = response.redirect_url;
                return;
            }
            $("#tender-committee-opening-table").appTable({reload: true});
            if (response.reload) {
                $(".modal").modal("hide");
            }
        }
    });
});
</script>
