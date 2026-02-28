<?php echo form_open(get_uri("gate_pass_fee_rules/save"), ["id" => "gate-pass-fee-rule-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
  <div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo esc($model_info->id ?? ""); ?>" />

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Rate Type</label>
        <div class="col-md-9">
          <?php echo form_dropdown("rate_type", $rate_type_dropdown, $model_info->rate_type ?? "flat", "class='form-control select2' required"); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Min Days</label>
        <div class="col-md-9">
          <?php echo form_input([
            "name" => "min_days",
            "type" => "number",
            "min" => "1",
            "value" => $model_info->min_days ?? 1,
            "class" => "form-control",
            "data-rule-required" => true,
            "data-msg-required" => app_lang("field_required"),
          ]); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Max Days</label>
        <div class="col-md-9">
          <?php echo form_input([
            "name" => "max_days",
            "type" => "number",
            "min" => "1",
            "value" => $model_info->max_days ?? "",
            "class" => "form-control",
            "placeholder" => "Leave empty for open ended (e.g. 15+ days)"
          ]); ?>
        </div>
      </div>
    </div>

    <hr/>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3"><?php echo app_lang("currency"); ?></label>
        <div class="col-md-9">
          <?php echo form_dropdown("currency", $currency_options, $model_info->currency ?? "OMR", "class='form-control select2' required"); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3"><?php echo app_lang("amount"); ?></label>
        <div class="col-md-9">
          <?php echo form_input([
            "name" => "amount",
            "type" => "number",
            "step" => "0.001",
            "min" => "0",
            "value" => $model_info->amount ?? 0,
            "class" => "form-control",
            "data-rule-required" => true,
            "data-msg-required" => app_lang("field_required"),
          ]); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Waivable</label>
        <div class="col-md-9 pt-2">
          <?php echo form_checkbox("is_waivable", "1", ($model_info->is_waivable ?? 1) ? true : false); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3"><?php echo app_lang("status"); ?></label>
        <div class="col-md-9 pt-2">
          <?php echo form_checkbox("is_active", "1", ($model_info->is_active ?? 1) ? true : false); ?>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
  <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
  $("#gate-pass-fee-rule-form").appForm({
    onSuccess: function (result) {
      $("#gate-pass-fee-rules-table").appTable({newData: result.data, dataId: result.id});
    }
  });
  $(".select2").select2();
});
</script>
