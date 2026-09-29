<div class="modal-body">
    <h5 class="mb-3">Commercial Review</h5>

    <div><strong>Reference:</strong> <?php echo esc($tender->reference ?? "-"); ?></div>
    <div><strong>Title:</strong> <?php echo esc($tender->title ?? "-"); ?></div>
    <div><strong>Status:</strong> <span class="badge bg-success">Commercial Unlocked</span></div>

    <hr>

    <?php if (empty($bids)) { ?>
        <div class="text-muted">No technically accepted bids are available for commercial review.</div>
    <?php } else { ?>
        <?php foreach ($bids as $bid) { ?>
            <div class="card mb-3 border">
                <div class="card-body">
                    <div><strong>Vendor:</strong> <?php echo esc($bid->vendor_name ?? "-"); ?></div>
                    <div><strong>Technical Result:</strong> <span class="badge bg-success">Accepted</span></div>
                    <div><strong>Submitted At:</strong> <?php echo !empty($bid->submitted_at) ? format_to_datetime($bid->submitted_at) : "-"; ?></div>

                    <div class="mt-3">
                        <strong>Commercial Proposal:</strong><br>
                        <?php if (!empty($bid->commercial_doc_id)) { ?>
                            <a href="<?php echo get_uri("tender_commercial_inbox/download_bid_document/" . $bid->commercial_doc_id); ?>" class="btn btn-default btn-sm mt-2">
                                <i data-feather="download" class="icon-14"></i>
                                <?php echo esc($bid->commercial_doc_name ?: "Download"); ?>
                            </a>
                        <?php } else { ?>
                            <span class="text-muted">No commercial file uploaded</span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>