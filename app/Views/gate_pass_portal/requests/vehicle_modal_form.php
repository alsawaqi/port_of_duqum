<?php echo form_open(get_uri("gate_pass_portal/save_vehicle"), ["id" => "gp-vehicle-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
  <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ''); ?>"/>
  <input type="hidden" name="gate_pass_request_id" value="<?php echo esc($gate_pass_request_id); ?>"/>

  <div class="form-group">
    <label>Plate No <span class="text-danger">*</span></label>
    <input name="plate_no" class="form-control" required value="<?php echo esc($model_info->plate_no ?? ''); ?>">
  </div>

  <div class="row">
    <div class="col-md-6">
      <div class="form-group">
        <label>Make</label>
        <input name="make" class="form-control" value="<?php echo esc($model_info->make ?? ''); ?>">
      </div>
    </div>
    <div class="col-md-6">
      <div class="form-group">
        <label>Model</label>
        <input name="model" class="form-control" value="<?php echo esc($model_info->model ?? ''); ?>">
      </div>
    </div>
  </div>

  <div class="form-group">
    <label>Color</label>
    <input name="color" class="form-control" value="<?php echo esc($model_info->color ?? ''); ?>">
  </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
  <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
  <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function(){
  $("#gp-vehicle-form").appForm({
    onSuccess: function(result){
      $("#gp-vehicles-table").appTable({newData: result.data, dataId: result.id});
    }
  });
});
</script>

