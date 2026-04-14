<?php
$docs = $docs ?? [];
$bid = $bid ?? null;
$submission_open = (($tender->status ?? "") === "published") && (($tender->workflow_stage ?? "bidding") === "bidding");

if ($submission_open && !empty($tender->closing_at) && strtotime($tender->closing_at) <= time()) {
    $submission_open = false;
}
?>

<div class="modal-body">
    <div class="mb15">
        <h4 class="mb-1"><?php echo esc($tender->title ?: "-"); ?></h4>
        <div class="text-muted"><?php echo esc($tender->reference ?: "-"); ?></div>
    </div>

    <div class="row">
        <div class="col-md-6 mb15">
            <div class="text-muted">Tender Type</div>
            <div>
                <?php if (($tender->tender_type ?? "open") === "close") { ?>
                    <span class="badge bg-warning">CLOSE</span>
                <?php } else { ?>
                    <span class="badge bg-success">OPEN</span>
                <?php } ?>
            </div>
        </div>

        <div class="col-md-6 mb15">
    <div class="text-muted">Tender Status</div>
    <div>
        <?php
        $status = strtolower((string)($tender->status ?? "draft"));
        $status_classes = [
            "draft" => "secondary",
            "published" => "primary",
            "closed" => "dark",
            "awarded" => "success",
            "cancelled" => "danger",
        ];
        $status_class = $status_classes[$status] ?? "secondary";
        ?>
        <span class="badge bg-<?php echo $status_class; ?>">
            <?php echo esc(ucfirst($status)); ?>
        </span>
    </div>
</div>

<div class="col-md-6 mb15">
    <div class="text-muted">Invite Status</div>
    <div><?php echo esc(ucfirst($tender->invite_status ?? "sent")); ?></div>
</div>

        <div class="col-md-6 mb15">
            <div class="text-muted">Published At</div>
            <div><?php echo !empty($tender->published_at) ? format_to_datetime($tender->published_at) : "-"; ?></div>
        </div>

        <div class="col-md-6 mb15">
            <div class="text-muted">Closing At</div>
            <div><?php echo !empty($tender->closing_at) ? format_to_datetime($tender->closing_at) : "-"; ?></div>
        </div>

        <div class="col-md-12 mb15">
            <div class="text-muted">Target Specialty</div>
            <div>
                <?php
                $target = $tender->vendor_category_name ?: "-";
                if (!empty($tender->vendor_sub_category_name)) {
                    $target .= " / " . $tender->vendor_sub_category_name;
                }
                echo esc($target);
                ?>
            </div>
        </div>
    </div>

    <hr>

    <h5 class="mb10">Your Bid Status</h5>

    <?php if (($tender->status ?? "") === "awarded" && !empty($bid)) { ?>
    <?php if (!empty($is_awarded_to_vendor)) { ?>
        <div class="alert alert-success mb15">
            <strong>Congratulations.</strong> Your bid has been awarded for this tender.
            <?php if (isset($latest_commercial_evaluation->total_score)) { ?>
                <div class="mt5">Commercial Score: <strong><?php echo number_format((float) $latest_commercial_evaluation->total_score, 3); ?></strong></div>
            <?php } ?>
        </div>
    <?php } elseif (!empty($is_regretted_vendor)) { ?>
        <div class="alert alert-danger mb15">
            This tender has been awarded to another vendor.
        </div>
    <?php } ?>
<?php } elseif (($tender->workflow_stage ?? "") === "award_decision" && !empty($bid) && strtolower((string) ($latest_commercial_evaluation->decision ?? "")) === "accepted") { ?>
    <div class="alert alert-info mb15">
        Your bid is currently the commercially accepted bid and is awaiting final award confirmation.
    </div>
<?php } ?>

    

    <?php if ($bid) { ?>
        <div class="mb15">
            <span class="badge bg-success"><?php echo esc(ucfirst($bid->status)); ?></span>
            <?php if (!empty($bid->submitted_at)) { ?>
                <div class="text-muted mt5">Submitted At: <?php echo format_to_datetime($bid->submitted_at); ?></div>
            <?php } ?>
        </div>
    <?php } else { ?>
        <div class="text-muted mb15">You have not submitted a bid yet.</div>
    <?php } ?>

    <?php if ($submission_open) { ?>
        <div class="mb20">
            <?php
            echo modal_anchor(
                get_uri("vendor_portal/bid_modal"),
                "<i data-feather='upload' class='icon-16'></i> " . (!empty($bid) ? "Update Bid" : "Submit Bid"),
                [
                    "class" => "btn btn-primary",
                    "title" => !empty($bid) ? "Update Bid" : "Submit Bid",
                    "data-post-tender_id" => $tender->id
                ]
            );
            ?>
        </div>
        <?php } else { ?>
    <div class="alert alert-warning">
        Bid submission is closed for this tender.
        <?php if (($tender->status ?? "") === "closed") { ?>
            <strong>The tender is now officially closed.</strong>
        <?php } ?>
    </div>
<?php } ?>

    <hr>
    <h5 class="mb15">Tender Documents</h5>

    <?php if (count($docs)) { ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Title</th>
                        <th>File Name</th>
                        <th>Access</th>
                        <th class="text-center" style="width: 110px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($docs as $doc) { ?>
                        <tr>
                            <td><?php echo esc($doc->doc_type ?: "-"); ?></td>
                            <td><?php echo esc($doc->title ?: "-"); ?></td>
                            <td><?php echo esc($doc->original_name ?: basename($doc->path)); ?></td>
                            <td>
                                <?php if ((int)($doc->time_limited ?? 0) === 1) { ?>
                                    <span class="badge bg-warning">
                                        Time-limited
                                        <?php if (!empty($doc->expires_in_hours)) { ?>
                                            (<?php echo (int) $doc->expires_in_hours; ?>h)
                                        <?php } ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-success">Standard</span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <a href="<?php echo get_uri('vendor_portal/download_tender_document/' . $doc->id); ?>" class="btn btn-default btn-sm">
                                    <i data-feather="download" class="icon-14"></i> Download
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } else { ?>
        <div class="text-muted">No documents uploaded yet.</div>
    <?php } ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">Close</button>
</div>

<script>
if (typeof feather !== "undefined") {
    feather.replace();
}
</script>