<?php
$bid = $bid ?? null;
$required_sections = $required_sections ?? [];
$documents_map = $documents_map ?? [];

$section_labels = [
    "technical" => "Technical Proposal",
    "commercial_priced" => "Commercial Proposal (With Price)",
    "commercial_unpriced" => "Commercial Proposal (Without Price)",
    "bank_guarantee" => "Bank Guarantee Documents",
];

$section_fields = [
    "technical" => "technical_file",
    "commercial_priced" => "commercial_priced_file",
    "commercial_unpriced" => "commercial_unpriced_file",
    "bank_guarantee" => "bank_guarantee_file",
];
?>

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
        Upload the required bid documents before the submission deadline. You can replace your files until the tender closes.
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

    <?php foreach ($section_labels as $section => $label) {
        $field_name = $section_fields[$section];
        $current_doc = $documents_map[$section] ?? null;
        $is_required = in_array($section, $required_sections, true);
    ?>
        <hr>
        <h5 class="mb10">
            <?php echo esc($label); ?>
            <?php if ($is_required) { ?>
                <span class="badge bg-danger ms-2">Required</span>
            <?php } else { ?>
                <span class="badge bg-secondary ms-2">Optional</span>
            <?php } ?>
        </h5>

        <div class="form-group">
            <label><?php echo esc($label); ?> File</label>
            <input type="file" name="<?php echo esc($field_name); ?>" class="form-control" <?php echo ($is_required && empty($current_doc)) ? "required" : ""; ?> />
            <?php if (!empty($current_doc)) { ?>
                <small class="text-muted d-block mt5">
                    Current: <?php echo esc($current_doc->original_name ?? "-"); ?>
                </small>
                <a href="<?php echo get_uri('vendor_portal/download_bid_document/' . (int) $current_doc->id); ?>" class="btn btn-default btn-sm mt5">
                    <i data-feather="download" class="icon-14"></i> Download Current File
                </a>
            <?php } elseif (!$is_required) { ?>
                <small class="text-muted d-block mt5">No file uploaded.</small>
            <?php } ?>
        </div>
    <?php } ?>
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
        onSuccess: function () {
            $("#vendor-tenders-table").appTable({reload: true});
            setTimeout(function () {
                window.location.reload();
            }, 400);
        }
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
