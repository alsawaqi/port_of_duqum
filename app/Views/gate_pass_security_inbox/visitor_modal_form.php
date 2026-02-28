<?php
$id_type_options = [
    "" => "- " . app_lang("id_type") . " -",
    "Passport" => "Passport",
    "National ID" => "National ID",
    "Driving License" => "Driving License",
    "Residence Permit" => "Residence Permit",
    "Visa" => "Visa",
    "Other" => "Other",
];

$nationality_options = [
    "" => "- " . app_lang("nationality") . " -",
    "Omani" => "Omani",
    "Indian" => "Indian",
    "Pakistani" => "Pakistani",
    "Egyptian" => "Egyptian",
    "Philippine" => "Philippine",
    "Bangladeshi" => "Bangladeshi",
    "Sri Lankan" => "Sri Lankan",
    "Indonesian" => "Indonesian",
    "Jordanian" => "Jordanian",
    "Saudi" => "Saudi",
    "Emirati" => "Emirati",
    "Yemeni" => "Yemeni",
    "British" => "British",
    "American" => "American",
    "Canadian" => "Canadian",
    "Australian" => "Australian",
    "Other" => "Other",
];

$current_id_type = trim((string)($model_info->id_type ?? ""));
$current_nationality = trim((string)($model_info->nationality ?? ""));
if ($current_id_type !== "" && !isset($id_type_options[$current_id_type])) {
    $id_type_options[$current_id_type] = $current_id_type;
}
if ($current_nationality !== "" && !isset($nationality_options[$current_nationality])) {
    $nationality_options[$current_nationality] = $current_nationality;
}
?>

<?php echo form_open(get_uri("gate_pass_security_inbox/save_visitor"), [
    "id" => "gp-sec-visitor-form",
    "class" => "general-form",
    "role" => "form"
]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
    <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />
    <input type="hidden" name="gate_pass_request_id" value="<?php echo (int)$gate_pass_request_id; ?>" />

    <div class="form-group">
        <label>Full Name <span class="text-danger">*</span></label>
        <input name="full_name" class="form-control" required value="<?php echo esc($model_info->full_name ?? ""); ?>">
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("id_type"); ?></label>
                <select name="id_type" class="form-control">
                    <?php foreach ($id_type_options as $val => $label): ?>
                        <option value="<?php echo esc($val); ?>" <?php echo ($current_id_type === $val) ? "selected" : ""; ?>>
                            <?php echo esc($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>ID Number</label>
                <input name="id_number" class="form-control" value="<?php echo esc($model_info->id_number ?? ""); ?>">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("nationality"); ?></label>
                <select name="nationality" class="form-control">
                    <?php foreach ($nationality_options as $val => $label): ?>
                        <option value="<?php echo esc($val); ?>" <?php echo ($current_nationality === $val) ? "selected" : ""; ?>>
                            <?php echo esc($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Phone</label>
                <input name="phone" class="form-control" value="<?php echo esc($model_info->phone ?? ""); ?>">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Visitor Company</label>
        <input name="visitor_company" class="form-control" value="<?php echo esc($model_info->visitor_company ?? ""); ?>">
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang("role"); ?></label>
                <select name="role" class="form-control">
                    <?php $current_role = trim((string)($model_info->role ?? "visitor")); ?>
                    <option value="visitor" <?php echo $current_role === "visitor" ? "selected" : ""; ?>>Visitor</option>
                    <option value="driver" <?php echo $current_role === "driver" ? "selected" : ""; ?>>Driver</option>
                    <option value="passenger" <?php echo $current_role === "passenger" ? "selected" : ""; ?>>Passenger</option>
                </select>
            </div>
        </div>
        <div class="col-md-6 pt25">
            <label>
                <input type="checkbox" name="is_primary" value="1" <?php echo ((int)($model_info->is_primary ?? 0) === 1) ? "checked" : ""; ?>>
                Primary Visitor
            </label>
        </div>
    </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $("#gp-sec-visitor-form").appForm({
        onSuccess: function (result) {
            $("#gp-scan-visitors-table").appTable({
                newData: result.data,
                dataId: result.id
            });

            if (typeof feather !== "undefined") feather.replace();
        }
    });
});
</script>
