<?php
$m = $model_info ?? null;
$vehicle_row_id = $m ? (int) $m->id : 0;
$mul_path = gate_pass_vehicle_mulkiyah_path_value($m);
$plate_split = gate_pass_plate_split_prefix_digits((string) ($m?->plate_no ?? ""));
$prefix_options = gate_pass_oman_plate_prefix_options_for_ui();
$sel_prefix = $plate_split["prefix"] ?? "";
if ($sel_prefix !== "" && !array_key_exists($sel_prefix, $prefix_options)) {
    $prefix_options[$sel_prefix] = $sel_prefix;
}
?>
<style>
.gp-attach-wrap .gp-attach-thumb-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.gp-attach-wrap .gp-attach-thumb {
    max-height: 88px; max-width: 120px; width: auto; height: auto;
    object-fit: contain; border-radius: 8px; border: 1px solid rgba(15,23,42,.12);
    background: #f8fafc;
}
.gp-attach-wrap .gp-attach-thumb-link { display: inline-block; line-height: 0; }
.gp-attach-existing-label { display: block; }
.gp-attach-wrap .gp-attach-pdf-new {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 6px;
    padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(15,23,42,.12);
    background: rgba(239,68,68,.06); font-size: 12px; font-weight: 600; color: #b91c1c;
}
.gp-plate-pair { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px 12px; }
.gp-plate-pair .gp-plate-prefix-wrap { flex: 0 1 220px; min-width: 140px; }
.gp-plate-pair .gp-plate-digits-wrap { flex: 1 1 160px; min-width: 120px; }
.gp-plate-pair label.gp-plate-field-label { display: block; font-size: 12px; color: #64748b; margin-bottom: 4px; font-weight: 600; }
</style>

<?php echo form_open(get_uri("gate_pass_portal/save_vehicle"), ["id" => "gp-vehicle-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
  <input type="hidden" name="id" value="<?php echo esc($m ? ($m->id ?? '') : ''); ?>"/>
  <input type="hidden" name="gate_pass_request_id" value="<?php echo esc($gate_pass_request_id); ?>"/>

  <div class="form-group">
    <label class="mb8"><?php echo app_lang("plate_no"); ?> <span class="text-danger">*</span></label>
    <div class="gp-plate-pair">
      <div class="gp-plate-prefix-wrap">
        <label class="gp-plate-field-label" for="gp-plate-prefix"><?php echo app_lang("gate_pass_plate_prefix"); ?></label>
        <select name="plate_prefix" id="gp-plate-prefix" class="form-control" required>
            <?php foreach ($prefix_options as $val => $lab): ?>
              <option value="<?php echo esc($val); ?>" <?php echo $sel_prefix === $val ? "selected" : ""; ?>><?php echo esc($lab); ?></option>
            <?php endforeach; ?>
        </select>
      </div>
      <div class="gp-plate-digits-wrap">
        <label class="gp-plate-field-label" for="gp-plate-digits"><?php echo app_lang("gate_pass_plate_numbers"); ?></label>
        <input type="text" name="plate_digits" id="gp-plate-digits" class="form-control" inputmode="numeric" pattern="[0-9]*" autocomplete="off" maxlength="6" required value="<?php echo esc($plate_split["digits"] ?? ""); ?>">
      </div>
    </div>
    <small class="text-muted"><?php echo app_lang("gate_pass_plate_format_hint"); ?></small>
  </div>

  <div class="form-group gp-attach-wrap">
    <label><?php echo app_lang("gate_pass_mulkiyah_attachment"); ?></label>
    <input type="file" name="mulkiyah_attachment_path" class="form-control gp-attach-input" accept="image/*,.pdf" data-allow-pdf="1">
    <div class="gp-attach-preview-zone">
      <?php
      if ($vehicle_row_id > 0 && $mul_path !== "") {
          $ext = strtolower(pathinfo($mul_path, PATHINFO_EXTENSION) ?: "");
          $url = get_uri("gate_pass_portal/vehicle_attachment_download/" . $vehicle_row_id . "/mulkiyah_attachment_path");
          $imgExt = ["jpg", "jpeg", "png", "gif", "webp", "bmp"];
          echo '<div class="gp-attach-existing mt8">';
          echo '<span class="gp-attach-existing-label text-muted small">' . esc(app_lang("current")) . '</span>';
          echo '<div class="gp-attach-thumb-row mt4">';
          if (in_array($ext, $imgExt, true)) {
              echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="gp-attach-thumb-link">';
              echo '<img src="' . esc($url) . '" class="gp-attach-thumb" alt=""></a>';
          } elseif ($ext === "pdf") {
              echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="btn btn-default btn-xs gp-attach-pdf-btn">';
              echo '<i data-feather="file-text" class="icon-14"></i> ' . esc(app_lang("gate_pass_pdf_preview")) . '</a>';
          } else {
              echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="btn btn-default btn-xs">' . esc(app_lang("view")) . '</a>';
          }
          echo "</div></div>";
      }
      ?>
      <div class="gp-attach-preview-new"></div>
    </div>
    <small class="text-muted"><?php echo app_lang("gate_pass_mulkiyah_attachment_hint"); ?></small>
  </div>
</div>

<div class="modal-footer gp-pro-modal-footer">
  <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
  <button type="submit" class="btn btn-primary gp-pro-btn"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
  function gpDigitsOnly($el) {
    $el.on("input", function () {
      this.value = (this.value || "").replace(/\D/g, "").slice(0, 6);
    });
  }
  gpDigitsOnly($("#gp-plate-digits"));

  function gpClearBlobUrl($input) {
    var prev = $input.data("gpBlobUrl");
    if (prev) {
      try { URL.revokeObjectURL(prev); } catch (e) {}
      $input.removeData("gpBlobUrl");
    }
  }

  function gpBindVehicleAttachmentPreviews($form) {
    $form.find("input[type=file].gp-attach-input").off("change.gpAtt").on("change.gpAtt", function () {
      var $input = $(this);
      var $new = $input.closest(".gp-attach-wrap").find(".gp-attach-preview-new");
      gpClearBlobUrl($input);
      $new.empty();
      var file = this.files && this.files[0];
      if (!file) {
        return;
      }
      var allowPdf = $input.data("allow-pdf") == 1;
      if (file.type.indexOf("image/") === 0) {
        var reader = new FileReader();
        reader.onload = function (ev) {
          $new.html("<img class='gp-attach-thumb' alt='' src='" + ev.target.result + "' />");
        };
        reader.readAsDataURL(file);
      } else if (allowPdf && file.type === "application/pdf") {
        var url = URL.createObjectURL(file);
        $input.data("gpBlobUrl", url);
        $new.html(
          "<div class='gp-attach-pdf-new'><a href='" + url + "' target='_blank' rel='noopener'>" +
          <?php echo json_encode(app_lang("gate_pass_pdf_open_new_tab")); ?> +
          "</a> · " + $("<div/>").text(file.name).html() + "</div>"
        );
      } else {
        $new.html("<div class='text-muted small mt4'>" + $("<div/>").text(file.name).html() + "</div>");
      }
    });
  }

  $("#gp-vehicle-form").closest(".modal").on("hidden.bs.modal", function () {
    $("#gp-vehicle-form input[type=file].gp-attach-input").each(function () {
      gpClearBlobUrl($(this));
    });
  });

  gpBindVehicleAttachmentPreviews($("#gp-vehicle-form"));

  $("#gp-vehicle-form").appForm({
    onSuccess: function (result) {
      $("#gp-vehicles-table").appTable({ newData: result.data, dataId: result.id });
    }
  });

  setTimeout(function () {
    if (!$("#gp-plate-prefix").val()) {
      $("#gp-plate-prefix").trigger("focus");
    } else {
      $("#gp-plate-digits").trigger("focus");
    }
  }, 200);

  if (typeof feather !== "undefined") {
    feather.replace();
  }
});
</script>
