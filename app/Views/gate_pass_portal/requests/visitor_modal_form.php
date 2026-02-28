<?php echo form_open(get_uri("gate_pass_portal/save_visitor"), ["id"=>"gp-visitor-form", "class"=>"general-form", "role"=>"form", "enctype"=>"multipart/form-data"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
  <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>"/>
  <input type="hidden" name="gate_pass_request_id" value="<?php echo esc($gate_pass_request_id); ?>"/>

  <div class="form-group">
    <label>Full Name</label>
    <input name="full_name" class="form-control" required value="<?php echo esc($model_info->full_name ?? ''); ?>">
  </div>

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
  $current_id_type = trim($model_info->id_type ?? "");
  $current_nationality = trim($model_info->nationality ?? "");
  if ($current_id_type !== "" && !isset($id_type_options[$current_id_type])) {
    $id_type_options[$current_id_type] = $current_id_type;
  }
  if ($current_nationality !== "" && !isset($nationality_options[$current_nationality])) {
    $nationality_options[$current_nationality] = $current_nationality;
  }
  ?>
  <div class="row">
    <div class="col-md-6">
      <label><?php echo app_lang("id_type"); ?></label>
      <select name="id_type" class="form-control">
        <?php foreach ($id_type_options as $val => $label): ?>
          <option value="<?php echo esc($val); ?>" <?php echo ($current_id_type === $val) ? "selected" : ""; ?>><?php echo esc($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label>ID Number</label>
      <input name="id_number" class="form-control" value="<?php echo esc($model_info->id_number ?? ''); ?>">
    </div>
  </div>

  <div class="form-group mt-2">
    <label>ID Attachment</label>
    <input type="file" name="id_attachment_path" class="form-control" accept="image/*,.pdf">
    <?php if (!empty($model_info->id_attachment_path)) { ?>
      <small class="text-muted d-block mt-1"><?php echo app_lang("current"); ?>: <?php echo esc(basename($model_info->id_attachment_path)); ?></small>
    <?php } ?>
  </div>

  <div class="form-group">
    <label>Visa Attachment</label>
    <input type="file" name="visa_attachment_path" class="form-control" accept="image/*,.pdf">
    <?php if (!empty($model_info->visa_attachment_path)) { ?>
      <small class="text-muted d-block mt-1"><?php echo app_lang("current"); ?>: <?php echo esc(basename($model_info->visa_attachment_path)); ?></small>
    <?php } ?>
  </div>

  <div class="form-group">
    <label>Photo Attachment</label>
    <input type="file" name="photo_attachment_path" class="form-control" accept="image/*">
    <?php if (!empty($model_info->photo_attachment_path)) { ?>
      <small class="text-muted d-block mt-1"><?php echo app_lang("current"); ?>: <?php echo esc(basename($model_info->photo_attachment_path)); ?></small>
    <?php } ?>
  </div>

  <div class="form-group">
    <label>Driving License Attachment</label>
    <input type="file" name="driving_license_attachment_path" class="form-control" accept="image/*,.pdf">
    <?php if (!empty($model_info->driving_license_attachment_path)) { ?>
      <small class="text-muted d-block mt-1"><?php echo app_lang("current"); ?>: <?php echo esc(basename($model_info->driving_license_attachment_path)); ?></small>
    <?php } ?>
  </div>

  <div class="row mt-2">
    <div class="col-md-6">
      <label><?php echo app_lang("nationality"); ?></label>
      <select name="nationality" class="form-control">
        <?php foreach ($nationality_options as $val => $label): ?>
          <option value="<?php echo esc($val); ?>" <?php echo ($current_nationality === $val) ? "selected" : ""; ?>><?php echo esc($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label>Phone</label>
      <input name="phone" class="form-control" value="<?php echo esc($model_info->phone ?? ''); ?>">
    </div>
  </div>

  <div class="form-group mt-2">
    <label>Visitor Company</label>
    <input name="visitor_company" class="form-control" value="<?php echo esc($model_info->visitor_company ?? ''); ?>">
  </div>

  <div class="row">
    <div class="col-md-6">
      <label>Role</label>
      <select name="role" class="form-control">
        <option value="visitor" <?php echo (($model_info->role ?? '')=="visitor")?"selected":""; ?>>Visitor</option>
        <option value="driver" <?php echo (($model_info->role ?? '')=="driver")?"selected":""; ?>>Driver</option>
        <option value="passenger" <?php echo (($model_info->role ?? '')=="passenger")?"selected":""; ?>>Passenger</option>
      </select>
    </div>
    <div class="col-md-6 pt-4">
      <label>
        <input type="checkbox" name="is_primary" value="1" <?php echo (($model_info->is_primary ?? 0)==1)?"checked":""; ?>>
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
$(document).ready(function(){
  $("#gp-visitor-form").appForm({
    onSuccess: function(result){
      $("#gp-visitors-table").appTable({newData: result.data, dataId: result.id});
    }
  });
});
</script>
