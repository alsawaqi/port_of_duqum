<?php
$bid = $bid ?? null;
$required_sections = $required_sections ?? [];
$documents_map = $documents_map ?? [];
$rfq_items = $rfq_items ?? [];
$bid_item_price_map = $bid_item_price_map ?? [];

$saved_item_total = 0.0;
foreach ($bid_item_price_map as $price_row) {
    if ($price_row->line_total !== null && $price_row->line_total !== "") {
        $saved_item_total += (float) $price_row->line_total;
    }
}

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
                    value="<?php echo esc($bid->total_amount ?? ($saved_item_total > 0 ? number_format($saved_item_total, 3, ".", "") : "")); ?>"
                    <?php echo $rfq_items ? "readonly" : ""; ?> />
                <?php if ($rfq_items) { ?>
                    <small class="text-muted">Calculated from tender item prices.</small>
                <?php } ?>
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

    <?php if ($rfq_items) { ?>
        <hr>
        <h5 class="mb10">Tender Item Pricing</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-striped mb10">
                <thead>
                    <tr>
                        <th>Sr No</th>
                        <th>Description</th>
                        <th>UOM</th>
                        <th>Qty</th>
                        <th>Part No (Optional)</th>
                        <th style="width: 150px;">Unit Price</th>
                        <th style="width: 150px;">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rfq_items as $item) { ?>
                        <?php
                        $item_id = (int) ($item->id ?? 0);
                        $price_row = $bid_item_price_map[$item_id] ?? null;
                        $saved_unit_price = $price_row->unit_price ?? "";
                        $saved_line_total = $price_row->line_total ?? "";
                        ?>
                        <tr>
                            <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                            <td><?php echo esc($item->description ?? "-"); ?></td>
                            <td><?php echo esc($item->uom ?? "-"); ?></td>
                            <td><?php echo $item->qty !== null ? number_format((float) $item->qty, 3) : "-"; ?></td>
                            <td><?php echo esc($item->part_no ?? ($item->brand ?? "-")); ?></td>
                            <td>
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0"
                                    name="rfq_item_unit_price[<?php echo $item_id; ?>]"
                                    class="form-control form-control-sm bid-modal-item-unit-price"
                                    data-rfq-qty="<?php echo esc($item->qty ?? ""); ?>"
                                    value="<?php echo esc($saved_unit_price); ?>"
                                    required />
                            </td>
                            <td class="text-end"><span data-bid-modal-line-total><?php echo $saved_line_total !== "" ? number_format((float) $saved_line_total, 3) : "-"; ?></span></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="alert alert-info mb15">
            Calculated Bid Total:
            <strong data-bid-modal-total>
                <?php
                $display_total = $saved_item_total > 0 ? $saved_item_total : (float) ($bid->total_amount ?? 0);
                echo number_format($display_total, 3) . " " . esc($bid->currency ?? "OMR");
                ?>
            </strong>
        </div>
    <?php } ?>

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
    function updateBidModalTotals() {
        var total = 0;

        $(".bid-modal-item-unit-price").each(function () {
            var $input = $(this);
            var unitPrice = parseFloat($input.val());
            var qty = parseFloat($input.data("rfq-qty"));
            var $lineTotal = $input.closest("tr").find("[data-bid-modal-line-total]");

            if (!isNaN(unitPrice) && !isNaN(qty)) {
                var lineTotal = unitPrice * qty;
                total += lineTotal;
                $lineTotal.text(lineTotal.toFixed(3));
            } else {
                $lineTotal.text("-");
            }
        });

        if ($(".bid-modal-item-unit-price").length) {
            var currency = $.trim($("[name='currency']").val() || "OMR") || "OMR";
            $("[name='total_amount']").val(total.toFixed(3));
            $("[data-bid-modal-total]").text(total.toFixed(3) + " " + currency);
        }
    }

    $(document).on("input", ".bid-modal-item-unit-price, [name='currency']", updateBidModalTotals);
    updateBidModalTotals();

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
