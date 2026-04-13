<?php
$m = $model_info ?? null;
$vehicle_type_options = [
    "private" => "Private",
    "commercial" => "Commercial",
    "truck" => "Truck",
    "bus" => "Bus",
    "other" => "Other",
];
$current_vehicle_type = strtolower(trim((string)($m?->type ?? "private")));
if (!isset($vehicle_type_options[$current_vehicle_type])) {
    $current_vehicle_type = "private";
}
$plate_split = gate_pass_plate_split_prefix_digits((string) ($m?->plate_no ?? ""));
$prefix_options = gate_pass_oman_plate_prefix_options_for_ui();
$sel_prefix = $plate_split["prefix"] ?? "";
if ($sel_prefix !== "" && !array_key_exists($sel_prefix, $prefix_options)) {
    $prefix_options[$sel_prefix] = $sel_prefix;
}
?>
<style>
.gp-plate-pair { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 12px; }
.gp-plate-pair .gp-plate-prefix-wrap { flex: 0 1 220px; min-width: 140px; }
.gp-plate-pair .gp-plate-digits-wrap { flex: 1 1 160px; min-width: 120px; }
.gp-plate-pair label.gp-plate-field-label { display: block; font-size: 12px; color: #64748b; margin-bottom: 4px; font-weight: 600; }
</style>

<?php echo form_open(get_uri("gate_pass_security_inbox/save_vehicle"), [
    "id" => "gp-sec-vehicle-form",
    "class" => "general-form",
    "role" => "form",
    "enctype" => "multipart/form-data",
]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="id" value="<?php echo esc($m ? ($m->id ?? "") : ""); ?>" />
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$gate_pass_request_id; ?>" />

    <div class="form-group">
        <label class="mb8"><?php echo app_lang("plate_no"); ?> <span class="text-danger">*</span></label>
        <div class="gp-plate-pair">
            <div class="gp-plate-prefix-wrap">
                <label class="gp-plate-field-label" for="gp-sec-plate-prefix"><?php echo app_lang("gate_pass_plate_prefix"); ?></label>
                <select name="plate_prefix" id="gp-sec-plate-prefix" class="form-control" required>
                    <?php foreach ($prefix_options as $val => $lab): ?>
                        <option value="<?php echo esc($val); ?>" <?php echo $sel_prefix === $val ? "selected" : ""; ?>><?php echo esc($lab); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="gp-plate-digits-wrap">
                <label class="gp-plate-field-label" for="gp-sec-plate-digits"><?php echo app_lang("gate_pass_plate_numbers"); ?></label>
                <input type="text" name="plate_digits" id="gp-sec-plate-digits" class="form-control" inputmode="numeric" pattern="[0-9]*" autocomplete="off" maxlength="6" required value="<?php echo esc($plate_split["digits"] ?? ""); ?>">
            </div>
        </div>
        <small class="text-muted"><?php echo app_lang("gate_pass_plate_format_hint"); ?></small>
    </div>

    <div class="form-group">
        <label for="type">Type</label>
        <select id="type" name="type" class="form-control">
            <?php foreach ($vehicle_type_options as $value => $label): ?>
                <option value="<?php echo esc($value); ?>" <?php echo $current_vehicle_type === $value ? "selected" : ""; ?>>
                    <?php echo esc($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label><?php echo app_lang("gate_pass_mulkiyah_attachment"); ?></label>
        <input type="file" name="mulkiyah_attachment_path" class="form-control" accept="image/*,.pdf">
        <?php if ($m && !empty($m->mulkiyah_attachment_path)) { ?>
            <small class="text-muted d-block mt-1"><?php echo app_lang("current"); ?>: <?php echo esc(basename($m->mulkiyah_attachment_path)); ?></small>
        <?php } ?>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-sec-plate-digits").on("input", function () {
        this.value = (this.value || "").replace(/\D/g, "").slice(0, 6);
    });

    $("#gp-sec-vehicle-form").appForm({
        onSuccess: function (result) {
            $("#gp-scan-vehicles-table").appTable({
                newData: result.data,
                dataId: result.id
            });

            if (typeof feather !== "undefined") feather.replace();
        }
    });

    setTimeout(function() {
        if (!$("#gp-sec-plate-prefix").val()) {
            $("#gp-sec-plate-prefix").focus();
        } else {
            $("#gp-sec-plate-digits").focus();
        }
    }, 200);
});
</script>
