<?php
// NOTE: This file must be a VIEW. Your zip currently has controller code here by mistake.

$raw_request_id = $model_info->id ?? null;
$is_new_request = ($raw_request_id === null || $raw_request_id === "" || (int) $raw_request_id === 0);
$id = $is_new_request ? "" : (string) (int) $raw_request_id;
$today_ymd = date("Y-m-d");
$visit_from_val = !empty($model_info->visit_from) ? substr((string)$model_info->visit_from, 0, 10) : "";
$visit_to_val   = !empty($model_info->visit_to) ? substr((string)$model_info->visit_to, 0, 10) : "";
if ($is_new_request && $visit_from_val === "") {
    $visit_from_val = $today_ymd;
}
$currency_val   = $model_info->currency ?? "OMR";
$request_type_val = $model_info->request_type ?? "both";
if ($request_type_val === "vehicle") {
    $request_type_val = "both";
}
$vehicle_type_val = $model_info->vehicle_type ?? "none";
$visit_type_val   = $model_info->visit_type ?? "visitor";

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

$vehicle_row_type_options = [
    "private" => "Private",
    "commercial" => "Commercial",
    "truck" => "Truck",
    "bus" => "Bus",
    "other" => "Other",
];

$render_options = static function (array $options): string {
    $html = "";
    foreach ($options as $val => $label) {
        $html .= "<option value=\"" . esc($val) . "\">" . esc($label) . "</option>";
    }
    return $html;
};

$id_type_options_html = $render_options($id_type_options);
$nationality_options_html = $render_options($nationality_options);
$vehicle_row_type_options_html = $render_options($vehicle_row_type_options);
?>

<?php echo form_open(get_uri("gate_pass_portal/save_request"), ["id" => "gp-request-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
  <div class="container-fluid">

    <input type="hidden" name="id" value="<?php echo esc($id); ?>" />

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Company <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <?php echo form_dropdown(
              "company_id",
              $company_dropdown,
              $model_info->company_id ?? "",
              "class='form-control' id='gp-company' data-rule-required='true' data-msg-required='".app_lang("field_required")."'"
          ); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Department</label>
        <div class="col-md-9">
          <select name="department_id" id="gp-department" class="form-control">
            <option value="">- <?php echo app_lang("select"); ?> -</option>
          </select>
          <small class="text-muted">Departments load after selecting company.</small>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Purpose <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <?php echo form_dropdown(
              "gate_pass_purpose_id",
              $purpose_dropdown,
              $model_info->gate_pass_purpose_id ?? "",
              "class='form-control' data-rule-required='true' data-msg-required='".app_lang("field_required")."'"
          ); ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Visit From <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <?php
          $from_attrs = "type=\"date\" name=\"visit_from\" id=\"gp-visit-from\" class=\"form-control\" required";
          if ($is_new_request) {
              $from_attrs .= " min=\"" . esc($today_ymd) . "\"";
          }
          ?>
          <input <?php echo $from_attrs; ?> value="<?php echo esc($visit_from_val); ?>">
          <?php if ($is_new_request): ?>
            <small class="text-muted"><?php echo app_lang("gate_pass_visit_from_starts_today_or_later"); ?></small>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Visit To <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <?php
          $to_disabled = $visit_from_val === "";
          $to_min = $visit_from_val !== "" ? $visit_from_val : "";
          $to_attrs = "type=\"date\" name=\"visit_to\" id=\"gp-visit-to\" class=\"form-control\"";
          if ($to_min !== "") {
              $to_attrs .= " min=\"" . esc($to_min) . "\"";
          }
          if ($to_disabled) {
              $to_attrs .= " disabled";
          } else {
              $to_attrs .= " required";
          }
          ?>
          <input <?php echo $to_attrs; ?> value="<?php echo esc($visit_to_val); ?>">
          <small class="text-muted" id="gp-visit-to-hint"><?php echo app_lang("gate_pass_visit_to_after_from_hint"); ?></small>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Visit Type</label>
        <div class="col-md-9">
          <select name="visit_type" class="form-control" id="gp-visit-type">
            <option value="visitor" <?php echo $visit_type_val==="visitor"?"selected":""; ?>>Visitor</option>
            <option value="supplier" <?php echo $visit_type_val==="supplier"?"selected":""; ?>>Supplier</option>
            <option value="agent" <?php echo $visit_type_val==="agent"?"selected":""; ?>>Agent</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Request Type</label>
        <div class="col-md-9">
          <select name="request_type" class="form-control" id="gp-request-type">
            <option value="both" <?php echo $request_type_val==="both"?"selected":""; ?>>Person + Vehicle</option>
            <option value="person" <?php echo $request_type_val==="person"?"selected":""; ?>>Person Only</option>
          </select>
          <small class="text-muted d-block mt8" id="gp-request-type-hint" role="note"></small>
        </div>
      </div>
    </div>

    <div class="form-group" id="gp-vehicle-type-wrap">
      <div class="row">
        <label class="col-md-3">Vehicle Type</label>
        <div class="col-md-9">
          <select name="vehicle_type" class="form-control" id="gp-vehicle-type">
            <option value="none" <?php echo $vehicle_type_val==="none"?"selected":""; ?>>None</option>
            <option value="private" <?php echo $vehicle_type_val==="private"?"selected":""; ?>>Private</option>
            <option value="commercial" <?php echo $vehicle_type_val==="commercial"?"selected":""; ?>>Commercial</option>
            <option value="truck" <?php echo $vehicle_type_val==="truck"?"selected":""; ?>>Truck</option>
            <option value="bus" <?php echo $vehicle_type_val==="bus"?"selected":""; ?>>Bus</option>
          </select>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Currency</label>
        <div class="col-md-9">
          <?php echo form_dropdown("currency", $currency_dropdown, $currency_val, "class='form-control' id='gp-currency'"); ?>
        </div>
      </div>
    </div>

    <div class="alert alert-info mb20 gp-pro-inline-alert">
      <strong>Calculated Fee:</strong>
      <span id="gp-fee-text">-</span>
      <span class="text-muted" id="gp-fee-meta"></span>
    </div>

    <?php if (!$id): ?>
      <div class="alert alert-info gp-pro-inline-alert">
        <?php echo app_lang("gate_pass_create_then_add_visitors"); ?>
      </div>
    <?php else: ?>
      <div class="alert alert-warning gp-pro-inline-alert">
        <?php echo app_lang("gate_pass_modal_edit_request_only"); ?>
      </div>
    <?php endif; ?>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Notes</label>
        <div class="col-md-9">
          <textarea name="purpose_notes" class="form-control" rows="3" placeholder="Notes..."><?php echo esc($model_info->purpose_notes ?? ""); ?></textarea>
        </div>
      </div>
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

  // ---------- Load departments by company ----------
  function loadDepartments(companyId, selectedId) {
    $("#gp-department").html("<option value=''>- <?php echo app_lang("select"); ?> -</option>");
    if (!companyId) return;

    $.get("<?php echo_uri('gate_pass_portal/departments_by_company'); ?>/" + companyId)
      .done(function (rows) {
        let opts = "<option value=''>- <?php echo app_lang("select"); ?> -</option>";
        (rows || []).forEach(function(r){
          const sel = (selectedId && String(selectedId) === String(r.id)) ? "selected" : "";
          opts += "<option value='"+r.id+"' "+sel+">"+r.text+"</option>";
        });
        $("#gp-department").html(opts);
      });
  }

  const initialCompany = $("#gp-company").val();
  const initialDept = "<?php echo esc($model_info->department_id ?? ""); ?>";
  if (initialCompany) loadDepartments(initialCompany, initialDept);

  $("#gp-company").on("change", function(){
    loadDepartments($(this).val(), "");
  });

  // ---------- Request type toggles ----------
  var gpRequestTypeHints = {
    both: <?php echo json_encode(app_lang("gate_pass_request_type_hint_both")); ?>,
    person: <?php echo json_encode(app_lang("gate_pass_request_type_hint_person")); ?>
  };
  function syncRequestTypeUI(){
    const t = $("#gp-request-type").val();
    const showVehicleType = (t === "both");
    $("#gp-vehicle-type-wrap").toggle(showVehicleType);
    if (!showVehicleType) $("#gp-vehicle-type").val("none");
    $("#gp-request-type-hint").text(gpRequestTypeHints[t] || gpRequestTypeHints.both);
  }
  $("#gp-request-type").on("change", syncRequestTypeUI);
  syncRequestTypeUI();

  // ---------- Fee preview ----------
  function calcFeePreview(){
    const from = $("#gp-visit-from").val();
    const to   = $("#gp-visit-to").val();
    const cur  = $("#gp-currency").val();

    $("#gp-fee-text").text("-");
    $("#gp-fee-meta").text("");

    if (!from || !to || !cur) return;

    $.get("<?php echo_uri('gate_pass_portal/calc_fee_preview'); ?>", { visit_from: from, visit_to: to, currency: cur })
      .done(function(res){
        if (!res || !res.success) {
          $("#gp-fee-text").text("No matching rule");
          $("#gp-fee-meta").text(res && res.message ? (" — " + res.message) : "");
          return;
        }
        $("#gp-fee-text").text(res.currency + " " + res.fee_amount_formatted);
        $("#gp-fee-meta").text(" — " + res.days + " day(s), " + res.rate_type);
      })
      .fail(function(){
        $("#gp-fee-text").text("Failed to calculate");
      });
  }

  // ---------- Visit dates: "to" only after "from"; to >= from ----------
  var hintToAfterFrom = <?php echo json_encode(app_lang("gate_pass_visit_to_after_from_hint")); ?>;
  var hintPickFromFirst = <?php echo json_encode(app_lang("gate_pass_visit_to_pick_from_first")); ?>;

  function syncVisitDateRange() {
    var $from = $("#gp-visit-from");
    var $to = $("#gp-visit-to");
    var $hint = $("#gp-visit-to-hint");
    var fromVal = $from.val() || "";

    if (!fromVal) {
      $to.prop("disabled", true).removeAttr("required").removeAttr("min").val("");
      if ($hint.length) $hint.text(hintPickFromFirst);
      return;
    }

    $to.prop("disabled", false).attr("required", "required").attr("min", fromVal);
    if ($hint.length) $hint.text(hintToAfterFrom);

    var toVal = $to.val() || "";
    if (toVal && toVal < fromVal) {
      $to.val(fromVal);
    }
  }

  $("#gp-visit-from").on("change input", function () {
    syncVisitDateRange();
    calcFeePreview();
  });
  $("#gp-visit-to, #gp-currency").on("change", calcFeePreview);
  syncVisitDateRange();
  calcFeePreview();

  // ---------- Submit ----------
  $("#gp-request-form").appForm({
    onSubmit: function () {
      // Disabled inputs are not posted; re-enable so visit_to is saved when set.
      $("#gp-visit-to").prop("disabled", false);
    },
    onSuccess: function (result) {
      if ($("#gp-requests-table").length) {
        $("#gp-requests-table").appTable({newData: result.data, dataId: result.id});
      } else {
        window.location.reload();
      }
    }
  });

});
</script>
