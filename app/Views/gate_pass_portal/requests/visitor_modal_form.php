<?php
$visitor_row_id = (int) (!empty($model_info) && !empty($model_info->id) ? $model_info->id : 0);
$is_new_visitor = $visitor_row_id < 1;
$gp_show_driving_license_initial = (!empty($model_info) && trim((string) ($model_info->role ?? "")) === "driver");

$gp_attachment_ext = static function (?string $path): string {
    $path = (string) $path;
    if ($path === "") {
        return "";
    }

    return strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: "");
};

$gp_is_image_ext = static function (string $ext): bool {
    return in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "bmp"], true);
};

$gp_render_existing_visitor_preview = static function (
    int $visitor_id,
    string $field,
    ?string $path,
    callable $extFn,
    callable $isImgFn
) {
    if ($visitor_id < 1 || !$path || trim((string) $path) === "") {
        return;
    }
    $ext = $extFn($path);
    $url = get_uri("gate_pass_portal/visitor_attachment_download/" . $visitor_id . "/" . $field);
    echo '<div class="gp-attach-existing mt8">';
    echo '<span class="gp-attach-existing-label text-muted small">' . esc(app_lang("current")) . '</span>';
    echo '<div class="gp-attach-thumb-row mt4">';
    if ($isImgFn($ext)) {
        echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="gp-attach-thumb-link">';
        echo '<img src="' . esc($url) . '" class="gp-attach-thumb" alt=""></a>';
    } elseif ($ext === "pdf") {
        echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="btn btn-default btn-xs gp-attach-pdf-btn">';
        echo '<i data-feather="file-text" class="icon-14"></i> ' . esc(app_lang("gate_pass_pdf_preview")) . '</a>';
    } else {
        echo '<a href="' . esc($url) . '" target="_blank" rel="noopener" class="btn btn-default btn-xs">' . esc(app_lang("view")) . '</a>';
    }
    echo "</div></div>";
};
?>
<style>
.gp-attach-wrap .gp-attach-thumb-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.gp-attach-wrap .gp-attach-thumb {
    max-height: 88px; max-width: 120px; width: auto; height: auto;
    object-fit: contain; border-radius: 8px; border: 1px solid rgba(15,23,42,.12);
    background: #f8fafc;
}
.gp-attach-wrap .gp-attach-thumb-link { display: inline-block; line-height: 0; }
.gp-attach-wrap .gp-attach-preview-new { min-height: 0; }
.gp-attach-wrap .gp-attach-preview-new .gp-attach-thumb { margin-top: 4px; }
.gp-attach-wrap .gp-attach-pdf-new {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 6px;
    padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(15,23,42,.12);
    background: rgba(239,68,68,.06); font-size: 12px; font-weight: 600; color: #b91c1c;
}
.gp-attach-existing-label { display: block; }
</style>

<?php echo form_open(get_uri("gate_pass_portal/save_visitor"), ["id" => "gp-visitor-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data"]); ?>

<div class="modal-body clearfix gp-pro-modal-body">
  <input type="hidden" name="id" value="<?php echo esc($model_info?->id ?? ''); ?>"/>
  <input type="hidden" name="gate_pass_request_id" value="<?php echo esc($gate_pass_request_id); ?>"/>

  <div class="form-group">
    <label><?php echo app_lang("full_name"); ?> <span class="text-danger">*</span></label>
    <input name="full_name" class="form-control" required value="<?php echo esc($model_info?->full_name ?? ''); ?>">
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
  $current_id_type = empty($model_info) ? "" : trim((string) ($model_info->id_type ?? ""));
  $current_nationality = empty($model_info) ? "" : trim((string) ($model_info->nationality ?? ""));
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
      <select name="id_type" class="form-control" required
              data-rule-required="true"
              data-msg-required="<?php echo esc(app_lang("field_required")); ?>">
        <?php foreach ($id_type_options as $val => $label): ?>
          <option value="<?php echo esc($val); ?>" <?php echo ($current_id_type === $val) ? "selected" : ""; ?>><?php echo esc($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label><?php echo app_lang("id_number"); ?></label>
      <input name="id_number" class="form-control" value="<?php echo esc($model_info?->id_number ?? ''); ?>" required>
    </div>
  </div>

  <div class="form-group gp-attach-wrap mt-2">
    <label>ID Attachment</label>
    <input type="file" name="id_attachment_path" class="form-control gp-attach-input" accept="image/*,.pdf" data-allow-pdf="1">
    <div class="gp-attach-preview-zone">
      <?php $gp_render_existing_visitor_preview($visitor_row_id, "id_attachment_path", $model_info?->id_attachment_path ?? null, $gp_attachment_ext, $gp_is_image_ext); ?>
      <div class="gp-attach-preview-new"></div>
    </div>
  </div>

  <div class="form-group gp-attach-wrap">
    <label>Visa Attachment</label>
    <input type="file" name="visa_attachment_path" class="form-control gp-attach-input" accept="image/*,.pdf" data-allow-pdf="1">
    <div class="gp-attach-preview-zone">
      <?php $gp_render_existing_visitor_preview($visitor_row_id, "visa_attachment_path", $model_info?->visa_attachment_path ?? null, $gp_attachment_ext, $gp_is_image_ext); ?>
      <div class="gp-attach-preview-new"></div>
    </div>
  </div>

  <div class="form-group gp-attach-wrap">
    <label>Photo Attachment</label>
    <input type="file" name="photo_attachment_path" class="form-control gp-attach-input" accept="image/*" data-allow-pdf="0">
    <div class="gp-attach-preview-zone">
      <?php $gp_render_existing_visitor_preview($visitor_row_id, "photo_attachment_path", $model_info?->photo_attachment_path ?? null, $gp_attachment_ext, $gp_is_image_ext); ?>
      <div class="gp-attach-preview-new"></div>
    </div>
  </div>

  <div class="row mt-2">
    <div class="col-md-6">
      <label><?php echo app_lang("nationality"); ?> <span class="text-danger">*</span></label>
      <select name="nationality" id="gp-visitor-nationality" class="form-control" required
              data-rule-required="true"
              data-msg-required="<?php echo esc(app_lang("field_required")); ?>">
        <?php foreach ($nationality_options as $val => $label): ?>
          <option value="<?php echo esc($val); ?>" <?php echo ($current_nationality === $val) ? "selected" : ""; ?>><?php echo esc($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label><?php echo app_lang("phone"); ?> <span class="text-danger">*</span></label>
      <input name="phone" id="gp-visitor-phone" type="text" inputmode="numeric" autocomplete="tel" class="form-control" required
             data-rule-required="true"
             data-msg-required="<?php echo esc(app_lang("field_required")); ?>"
             data-rule-digits="true"
             data-rule-minlength="6"
             data-rule-maxlength="20"
             data-msg-digits="<?php echo esc(app_lang("gate_pass_phone_digits_only")); ?>"
             data-msg-minlength="<?php echo esc(app_lang("gate_pass_phone_digits_only")); ?>"
             data-msg-maxlength="<?php echo esc(app_lang("gate_pass_phone_digits_only")); ?>"
             value="<?php echo esc($model_info?->phone ?? ''); ?>">
    </div>
  </div>

  <div class="form-group mt-2">
    <label><?php echo app_lang("gate_pass_visitor_company"); ?> <span class="text-danger">*</span></label>
    <input name="visitor_company" id="gp-visitor-company" class="form-control" required
           data-rule-required="true"
           data-msg-required="<?php echo esc(app_lang("field_required")); ?>"
           value="<?php echo esc($model_info?->visitor_company ?? ''); ?>">
  </div>

  <div class="row">
    <div class="col-md-6">
      <label><?php echo app_lang("role"); ?> <span class="text-danger">*</span></label>
      <select name="role" id="gp-visitor-role" class="form-control" required
              data-rule-required="true"
              data-msg-required="<?php echo esc(app_lang("field_required")); ?>">
        <?php if ($is_new_visitor): ?>
        <option value="" selected><?php echo app_lang("select"); ?></option>
        <?php endif; ?>
        <option value="visitor" <?php echo (($model_info?->role ?? '') === "visitor") ? "selected" : ""; ?>><?php echo app_lang("gate_pass_role_visitor"); ?></option>
        <option value="driver" <?php echo (($model_info?->role ?? '') === "driver") ? "selected" : ""; ?>><?php echo app_lang("gate_pass_role_driver"); ?></option>
        <option value="passenger" <?php echo (($model_info?->role ?? '') === "passenger") ? "selected" : ""; ?>><?php echo app_lang("gate_pass_role_passenger"); ?></option>
      </select>
    </div>
    <div class="col-md-6 pt-4">
      <label>
        <input type="checkbox" name="is_primary" value="1" <?php echo (($model_info?->is_primary ?? 0) == 1) ? "checked" : ""; ?>>
        <?php echo app_lang("gate_pass_primary_visitor"); ?>
      </label>
    </div>
  </div>

  <div class="form-group gp-attach-wrap mt-2" id="gp-dl-wrap" style="<?php echo $gp_show_driving_license_initial ? "" : "display:none;"; ?>">
    <label>
      <?php echo app_lang("driving_license_attachment"); ?>
      <span class="text-danger gp-dl-required-star" style="<?php echo $gp_show_driving_license_initial ? "" : "display:none;"; ?>">*</span>
    </label>
    <p class="text-muted small mb8 mt0"><?php echo app_lang("gate_pass_driving_license_driver_only_hint"); ?></p>
    <input type="file" name="driving_license_attachment_path" id="gp-dl-file" class="form-control gp-attach-input" accept="image/*,.pdf" data-allow-pdf="1"<?php echo $gp_show_driving_license_initial ? "" : " disabled"; ?>>
    <div class="gp-attach-preview-zone">
      <?php $gp_render_existing_visitor_preview($visitor_row_id, "driving_license_attachment_path", $model_info?->driving_license_attachment_path ?? null, $gp_attachment_ext, $gp_is_image_ext); ?>
      <div class="gp-attach-preview-new"></div>
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
  function gpClearBlobUrl($input) {
    var prev = $input.data("gpBlobUrl");
    if (prev) {
      try { URL.revokeObjectURL(prev); } catch (e) {}
      $input.removeData("gpBlobUrl");
    }
  }

  function gpBindVisitorAttachmentPreviews($form) {
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

  $("#gp-visitor-form").closest(".modal").on("hidden.bs.modal", function () {
    $("#gp-visitor-form input[type=file].gp-attach-input").each(function () {
      gpClearBlobUrl($(this));
    });
  });

  gpBindVisitorAttachmentPreviews($("#gp-visitor-form"));

  function gpSyncDrivingLicenseSection() {
    var $role = $("#gp-visitor-role");
    var $wrap = $("#gp-dl-wrap");
    var $file = $("#gp-dl-file");
    var isDriver = $role.val() === "driver";
    if (isDriver) {
      $wrap.show();
      $file.prop("disabled", false);
      $wrap.find(".gp-dl-required-star").show();
    } else {
      $wrap.hide();
      $wrap.find(".gp-dl-required-star").hide();
      gpClearBlobUrl($file);
      $file.val("").prop("disabled", true);
      $wrap.find(".gp-attach-preview-new").empty();
    }
  }
  $("#gp-visitor-role").on("change", gpSyncDrivingLicenseSection);
  gpSyncDrivingLicenseSection();

  $("#gp-visitor-form").appForm({
    onSubmit: function () {
      $("#gp-dl-file").prop("disabled", false);
    },
    onSuccess: function (result) {
      $("#gp-visitors-table").appTable({ newData: result.data, dataId: result.id });
    }
  });

  if (typeof feather !== "undefined") {
    feather.replace();
  }
});
</script>
