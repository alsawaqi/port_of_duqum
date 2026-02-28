<?php
// NOTE: This file must be a VIEW. Your zip currently has controller code here by mistake.

$id = $model_info->id ?? "";
$visit_from_val = !empty($model_info->visit_from) ? substr((string)$model_info->visit_from, 0, 10) : "";
$visit_to_val   = !empty($model_info->visit_to) ? substr((string)$model_info->visit_to, 0, 10) : "";
$currency_val   = $model_info->currency ?? "OMR";
$request_type_val = $model_info->request_type ?? "both";
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
          <input type="date" name="visit_from" id="gp-visit-from" class="form-control"
                 value="<?php echo esc($visit_from_val); ?>" required>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="row">
        <label class="col-md-3">Visit To <span class="text-danger">*</span></label>
        <div class="col-md-9">
          <input type="date" name="visit_to" id="gp-visit-to" class="form-control"
                 value="<?php echo esc($visit_to_val); ?>" required>
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
            <option value="vehicle" <?php echo $request_type_val==="vehicle"?"selected":""; ?>>Vehicle Only</option>
          </select>
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
      <!-- Visitors section (inline on CREATE) -->
      <div class="mb15">
        <h4 class="mt0">Visitors</h4>
        <div class="table-responsive">
          <table class="table table-bordered" id="gp-visitors-inline">
            <thead>
              <tr>
                <th style="min-width:180px;">Full Name</th>
                <th style="min-width:120px;">ID Type</th>
                <th style="min-width:140px;">ID Number</th>
                <th style="min-width:120px;">Nationality</th>
                <th style="min-width:140px;">Phone</th>
                <th style="min-width:120px;">Role</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr class="gp-visitor-row">
                <td><input class="form-control" name="visitors[0][full_name]" placeholder="Full name"></td>
                <td>
                  <select class="form-control" name="visitors[0][id_type]">
                    <?php echo $id_type_options_html; ?>
                  </select>
                </td>
                <td><input class="form-control" name="visitors[0][id_number]" placeholder="ID number"></td>
                <td>
                  <select class="form-control" name="visitors[0][nationality]">
                    <?php echo $nationality_options_html; ?>
                  </select>
                </td>
                <td><input class="form-control" name="visitors[0][phone]" placeholder="Phone"></td>
                <td><input class="form-control" name="visitors[0][role]" placeholder="visitor"></td>
                <td class="text-center">
                  <button type="button" class="btn btn-default btn-sm gp-remove-row" title="Remove">&times;</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-default" id="gp-add-visitor">+ Add Visitor</button>
        <small class="text-muted d-block mt5">Attachments can be added later from the request details page.</small>
      </div>

      <!-- Vehicles section (inline on CREATE) -->
      <div class="mb15" id="gp-vehicles-inline-wrap">
        <h4 class="mt0">Vehicles</h4>
        <div class="table-responsive">
          <table class="table table-bordered" id="gp-vehicles-inline">
            <thead>
              <tr>
                <th style="min-width:140px;">Plate No</th>
                <th style="min-width:140px;">Type</th>
                <th style="min-width:140px;">Make</th>
                <th style="min-width:140px;">Model</th>
                <th style="min-width:120px;">Color</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody>
              <tr class="gp-vehicle-row">
                <td><input class="form-control" name="vehicles[0][plate_no]" placeholder="Plate"></td>
                <td>
                  <select class="form-control" name="vehicles[0][type]">
                    <?php echo $vehicle_row_type_options_html; ?>
                  </select>
                </td>
                <td><input class="form-control" name="vehicles[0][make]" placeholder="Make"></td>
                <td><input class="form-control" name="vehicles[0][model]" placeholder="Model"></td>
                <td><input class="form-control" name="vehicles[0][color]" placeholder="Color"></td>
                <td class="text-center">
                  <button type="button" class="btn btn-default btn-sm gp-remove-row" title="Remove">&times;</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" class="btn btn-default" id="gp-add-vehicle">+ Add Vehicle</button>
      </div>
    <?php else: ?>
      <div class="alert alert-warning gp-pro-inline-alert">
        This modal edits the request info only. To add/update visitors & vehicles, open the request details page after saving.
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
  function syncRequestTypeUI(){
    const t = $("#gp-request-type").val();
    const showVehicles = (t === "both" || t === "vehicle");
    $("#gp-vehicles-inline-wrap").toggle(showVehicles);
    $("#gp-vehicle-type-wrap").toggle(showVehicles);
    if (!showVehicles) $("#gp-vehicle-type").val("none");
  }
  $("#gp-request-type").on("change", syncRequestTypeUI);
  syncRequestTypeUI();

  // ---------- Inline dynamic rows ----------
  function nextIndex(tableId, rowClass) {
    return $("#" + tableId + " tbody tr." + rowClass).length;
  }

  const idTypeOptionsHtml = <?php echo json_encode($id_type_options_html); ?>;
  const nationalityOptionsHtml = <?php echo json_encode($nationality_options_html); ?>;
  const vehicleTypeOptionsHtml = <?php echo json_encode($vehicle_row_type_options_html); ?>;

  $("#gp-add-visitor").on("click", function(){
    const i = nextIndex("gp-visitors-inline", "gp-visitor-row");
    const tr = `
      <tr class="gp-visitor-row">
        <td><input class="form-control" name="visitors[${i}][full_name]" placeholder="Full name"></td>
        <td><select class="form-control" name="visitors[${i}][id_type]">${idTypeOptionsHtml}</select></td>
        <td><input class="form-control" name="visitors[${i}][id_number]" placeholder="ID number"></td>
        <td><select class="form-control" name="visitors[${i}][nationality]">${nationalityOptionsHtml}</select></td>
        <td><input class="form-control" name="visitors[${i}][phone]" placeholder="Phone"></td>
        <td><input class="form-control" name="visitors[${i}][role]" placeholder="visitor"></td>
        <td class="text-center"><button type="button" class="btn btn-default btn-sm gp-remove-row">&times;</button></td>
      </tr>`;
    $("#gp-visitors-inline tbody").append(tr);
  });

  $("#gp-add-vehicle").on("click", function(){
    const i = nextIndex("gp-vehicles-inline", "gp-vehicle-row");
    const tr = `
      <tr class="gp-vehicle-row">
        <td><input class="form-control" name="vehicles[${i}][plate_no]" placeholder="Plate"></td>
        <td><select class="form-control" name="vehicles[${i}][type]">${vehicleTypeOptionsHtml}</select></td>
        <td><input class="form-control" name="vehicles[${i}][make]" placeholder="Make"></td>
        <td><input class="form-control" name="vehicles[${i}][model]" placeholder="Model"></td>
        <td><input class="form-control" name="vehicles[${i}][color]" placeholder="Color"></td>
        <td class="text-center"><button type="button" class="btn btn-default btn-sm gp-remove-row">&times;</button></td>
      </tr>`;
    $("#gp-vehicles-inline tbody").append(tr);
  });

  $(document).on("click", ".gp-remove-row", function(){
    const $tr = $(this).closest("tr");
    const $tbody = $tr.closest("tbody");
    if ($tbody.find("tr").length <= 1) {
      $tr.find("input").val("");
      $tr.find("select").prop("selectedIndex", 0);
      return;
    }
    $tr.remove();
  });

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

  $("#gp-visit-from, #gp-visit-to, #gp-currency").on("change", calcFeePreview);
  calcFeePreview();

  // ---------- Submit ----------
  $("#gp-request-form").appForm({
    onSuccess: function (result) {
      $("#gp-requests-table").appTable({newData: result.data, dataId: result.id});
    }
  });

});
</script>
