<?php
$vehicle_type_options = [
    "private" => "Private",
    "commercial" => "Commercial",
    "truck" => "Truck",
    "bus" => "Bus",
    "other" => "Other",
];
$current_vehicle_type = strtolower(trim((string)($model_info->type ?? "private")));
if (!isset($vehicle_type_options[$current_vehicle_type])) {
    $current_vehicle_type = "private";
}
?>

<?php echo form_open(get_uri("gate_pass_security_inbox/save_vehicle"), [
    "id" => "gp-sec-vehicle-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$gate_pass_request_id; ?>" />

    <div class="form-group">
        <label for="plate_no"><?php echo app_lang("plate_no"); ?> <span class="text-danger">*</span></label>
        <input
            id="plate_no"
            name="plate_no"
            class="form-control"
            value="<?php echo esc($model_info->plate_no ?? ""); ?>"
            required
            autocomplete="off"
        />
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

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label for="make"><?php echo app_lang("make"); ?></label>
                <input
                    id="make"
                    name="make"
                    class="form-control"
                    value="<?php echo esc($model_info->make ?? ""); ?>"
                    autocomplete="off"
                />
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label for="model"><?php echo app_lang("model"); ?></label>
                <input
                    id="model"
                    name="model"
                    class="form-control"
                    value="<?php echo esc($model_info->model ?? ""); ?>"
                    autocomplete="off"
                />
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="color"><?php echo app_lang("color"); ?></label>
        <input
            id="color"
            name="color"
            class="form-control"
            value="<?php echo esc($model_info->color ?? ""); ?>"
            autocomplete="off"
        />
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-sec-vehicle-form").appForm({
        onSuccess: function (result) {
            // ✅ IMPORTANT: this table id must match your scan page
            // In your scan.php it is: #gp-scan-vehicles-table
            $("#gp-scan-vehicles-table").appTable({
                newData: result.data,
                dataId: result.id
            });

            if (typeof feather !== "undefined") feather.replace();
        }
    });

    setTimeout(function() {
        $("#plate_no").focus();
    }, 200);
});
</script>
