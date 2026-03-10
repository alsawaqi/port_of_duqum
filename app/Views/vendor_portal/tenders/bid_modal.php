<?php echo form_open_multipart(get_uri("vendor_portal/save_bid"), [
    "id" => "vendor-bid-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />

<div class="modal-body clearfix">
    <div class="mb15">
        <h4 class="mb-1"><?php echo esc($tender->title ?: "-"); ?></h4>
        <div class="text-muted"><?php echo esc($tender->reference ?: "-"); ?></div>
    </div>

    <div class="alert alert-info">
        Submit both files before the tender closing date.
        You can update your submission until the tender closes.
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Total Amount</label>
                <input type="number" step="0.001" name="total_amount" class="form-control"
                    value="<?php echo esc($bid->total_amount ?? ""); ?>" />
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label>Currency</label>
                <input type="text" name="currency" class="form-control"
                    value="<?php echo esc($bid->currency ?? "OMR"); ?>" maxlength="3" />
            </div>
        </div>
    </div>

    <hr>
    <h5>Technical Proposal</h5>

    <div class="form-group">
        <label>Technical Proposal File</label>
        <input type="file" name="technical_file" class="form-control" <?php echo empty($technical_doc) ? "required" : ""; ?> />
        <?php if (!empty($technical_doc)) { ?>
            <small class="text-muted d-block mt5">
                Current: <?php echo esc($technical_doc->original_name); ?>
            </small>
            <a href="<?php echo get_uri('vendor_portal/download_bid_document/' . $technical_doc->id); ?>" class="btn btn-default btn-sm mt5">
                <i data-feather="download" class="icon-14"></i> Download Current Technical File
            </a>
        <?php } ?>
    </div>

    <hr>
    <h5>Commercial Proposal</h5>

    <div class="form-group">
        <label>Commercial Proposal File</label>
        <input type="file" name="commercial_file" class="form-control" <?php echo empty($commercial_doc) ? "required" : ""; ?> />
        <?php if (!empty($commercial_doc)) { ?>
            <small class="text-muted d-block mt5">
                Current: <?php echo esc($commercial_doc->original_name); ?>
            </small>
            <a href="<?php echo get_uri('vendor_portal/download_bid_document/' . $commercial_doc->id); ?>" class="btn btn-default btn-sm mt5">
                <i data-feather="download" class="icon-14"></i> Download Current Commercial File
            </a>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
        <i data-feather="x" class="icon-16"></i> Close
    </button>
    <button type="submit" class="btn btn-primary">
        <i data-feather="check-circle" class="icon-16"></i>
        <?php echo !empty($bid) ? "Update Bid" : "Submit Bid"; ?>
    </button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#vendor-bid-form").appForm({
        onSuccess: function (result) {
            $("#vendor-tenders-table").appTable({reload: true});
        }
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>