<?php
$tender_id = $tender->id ?? "";
?>

<?php echo form_open(get_uri("tender_procurement_inbox/save"), ["id" => "tender-procurement-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
  <div class="container-fluid">

    <input type="hidden" name="tender_request_id" value="<?php echo esc($request->id); ?>" />

    <div class="row">
      <div class="col-md-6">
        <div><strong>Request Ref:</strong> <?php echo esc($request->reference); ?></div>
        <div><strong>Company:</strong> <?php echo esc($request->company_name ?? "-"); ?></div>
        <div><strong>Department:</strong> <?php echo esc($request->department_name ?? "-"); ?></div>
        <div><strong>Requester:</strong> <?php echo esc($request->requester_name ?? "-"); ?></div>
      </div>
      <div class="col-md-6">
        <div><strong>Budget (OMR):</strong> <?php echo esc($request->budget_omr); ?></div>
        <div><strong>Fee (OMR):</strong> <?php echo esc($request->tender_fee); ?></div>
        <div><strong>Type:</strong> <?php echo esc($request->tender_type); ?></div>
        <div><strong>Status:</strong> <span class="badge bg-secondary"><?php echo esc($request->status); ?></span></div>
      </div>
    </div>

    <?php if (($request->tender_type ?? "open") === "close") { ?>
  <hr>
  <h6>Close Tender Vendors from Request</h6>

  <?php if (!empty($request_selected_vendors)) { ?>
    <ul class="mb-2">
      <?php foreach ($request_selected_vendors as $rv) { ?>
        <li><?php echo esc($rv->vendor_name); ?></li>
      <?php } ?>
    </ul>
    <div class="text-muted small">
      These vendors were selected in the Tender Request. On save, Procurement will invite these vendors directly.
      Category/Subcategory targeting becomes optional fallback.
    </div>
  <?php } else { ?>
    <div class="text-muted small">
      No vendors were selected in the Tender Request. Use category/subcategory targeting below if needed.
    </div>
  <?php } ?>
<?php } ?>



    <hr>




<h5 class="mb-2">Target Vendors (Specialty)</h5>

<div class="row">
  <div class="col-md-6">
    <label>Vendor Category</label>
    <?php echo form_dropdown(
        "vendor_category_id",
        $vendor_categories_dropdown ?? ["" => "- " . app_lang("select") . " -"],
        !empty($target_cat) ? (int)$target_cat->id : "",
        "class='form-control select2' id='vendor_category_id' data-rule-required='true' data-msg-required='" . app_lang("field_required") . "'"
    ); ?>
  </div>

  <div class="col-md-6">
    <label>Vendor Subcategory</label>
    <select name="vendor_sub_category_id" id="vendor_sub_category_id" class="form-control select2">
      <option value=""><?php echo "- " . app_lang("select") . " -"; ?></option>
    </select>
  </div>
</div>

<div class="mt-2">
  <label class="form-check">
    <input type="checkbox" class="form-check-input" name="publish_now" value="1" checked>
    <span class="form-check-label">Publish after Save</span>
  </label>
</div>

<script>
  window.__target_sub_id = "<?php echo !empty($target_sub) ? (int)$target_sub->id : ""; ?>";
</script>

 

<?php
// preselect (controller passes $target_cat / $target_sub)
if (!empty($target_cat)) { ?>
  <script>
    window.__target_cat = <?php echo (int)$target_cat->id; ?>;
    window.__target_cat_text = "<?php echo esc($target_cat->name); ?>";
  </script>
<?php } ?>

<?php if (!empty($target_sub)) { ?>
  <script>
    window.__target_sub = <?php echo (int)$target_sub->id; ?>;
    window.__target_sub_text = "<?php echo esc($target_sub->name); ?>";
  </script>
<?php } ?>
 

<?php if (!empty($invited_vendors)) { ?>
  <hr>
  <h6>Invited Vendors</h6>
  <div class="table-responsive">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>Vendor</th>
          <th>Email</th>
          <th>Status</th>
          <th>Invited At</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invited_vendors as $iv) { ?>
          <tr>
            <td><?php echo esc($iv->vendor_name ?? "-"); ?></td>
            <td><?php echo esc($iv->email ?? "-"); ?></td>
            <td><span class="badge bg-secondary"><?php echo esc($iv->invite_status ?? "sent"); ?></span></td>
            <td><?php echo esc($iv->invited_at ?? "-"); ?></td>
          </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
<?php } else { ?>
  <div class="text-muted mt-2">No invited vendors yet.</div>
<?php } ?>

    <hr>

    

    <div class="form-group">
      <label>Reference (Tender)</label>
      <?php echo form_input([
        "name" => "reference",
        "value" => esc($tender->reference ?? $request->reference),
        "class" => "form-control",
        "data-rule-required" => true,
        "data-msg-required" => app_lang("field_required")
      ]); ?>
    </div>

    <div class="form-group">
      <label>Title</label>
      <?php echo form_input([
        "name" => "title",
        "value" => esc($tender->title ?? $request->subject),
        "class" => "form-control",
        "data-rule-required" => true,
        "data-msg-required" => app_lang("field_required")
      ]); ?>
    </div>

    <div class="form-group">
      <label>Closing Date/Time</label>
      <?php
        $closing_val = "";
        if (!empty($tender->closing_at)) {
          $closing_val = date("Y-m-d\\TH:i", strtotime($tender->closing_at));
        }
      ?>
      <input type="datetime-local" name="closing_at" class="form-control" value="<?php echo esc($closing_val); ?>" required />
    </div>

    <hr>
    <h5 class="mb-2">Tender Documents</h5>

    <div class="row">
      <div class="col-md-4">
        <label>Doc Type</label>
        <?php echo form_dropdown("doc_type", [
          "RFP" => "RFP",
          "BOQ" => "BOQ",
          "DRAWING" => "DRAWING",
          "OTHER" => "OTHER",
        ], "RFP", "class='form-control select2'"); ?>
      </div>

    

      <div class="col-md-4">
        <label class="d-block">&nbsp;</label>
        <label class="form-check">
          <input type="checkbox" class="form-check-input" name="time_limited" value="1">
          <span class="form-check-label">Time-limited download</span>
        </label>
      </div>

      <div class="col-md-4">
        <label>Expires (hours)</label>
        <?php echo form_input(["name"=>"expires_in_hours","value"=>"72","class"=>"form-control"]); ?>
      </div>
    </div>

    <div class="mt-3">
      <?php echo view("includes/multi_file_uploader", [
        "max_files" => 10,
        "description_placeholder" => "Document title (optional)"
      ]); ?>
    </div>

    <?php if (!empty($docs)) { ?>
      <hr>
      <h6>Existing Documents</h6>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead>
            <tr>
              <th>Type</th>
              <th>Title</th>
              <th>File</th>
              <th>Size</th>
              <th>Limited</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tender-docs-body">
            <?php foreach ($docs as $d) { ?>
              <tr id="doc-row-<?php echo (int)$d->id; ?>">
                <td><?php echo esc($d->doc_type); ?></td>
                <td><?php echo esc($d->title ?? "-"); ?></td>
                <td><?php echo esc($d->original_name ?? "-"); ?></td>
                <td><?php echo esc($d->size_bytes ? convert_file_size($d->size_bytes) : "-"); ?></td>
                <td><?php echo ((int)$d->time_limited) ? ("Yes (" . (int)$d->expires_in_hours . "h)") : "No"; ?></td>
                <td class="text-end">
                  <a href="javascript:void(0)" class="delete-doc text-danger" data-id="<?php echo (int)$d->id; ?>">
                    <i data-feather="trash-2" class="icon-16"></i>
                  </a>
                </td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>

  </div>
</div>

<div class="modal-footer">
  <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
  <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>

<?php echo form_close(); ?>
 


<script>
$(document).ready(function () {

  // init Select2 in modal (once)
  $("#tender-procurement-form .select2").select2();

  function loadSubCategories(catId, selectedSubId) {
    $("#vendor_sub_category_id").html("<option value=''>- <?php echo app_lang('select'); ?> -</option>");

    if (!catId) {
      $("#vendor_sub_category_id").trigger("change");
      return;
    }

    $.get("<?php echo_uri('tender_procurement_inbox/get_vendor_sub_categories_dropdown'); ?>",
      { vendor_category_id: catId }
    ).done(function (html) {
      $("#vendor_sub_category_id").html(html);

      if (selectedSubId) {
        $("#vendor_sub_category_id").val(String(selectedSubId)).trigger("change");
      } else {
        $("#vendor_sub_category_id").trigger("change");
      }
    });
  }

  // change category -> reload subcategories
  $("#vendor_category_id").on("change", function () {
    loadSubCategories($(this).val(), "");
  });

  // preload on edit
  var initialCat = $("#vendor_category_id").val();
  if (initialCat) {
    loadSubCategories(initialCat, window.__target_sub_id || "");
  }

  $("#tender-procurement-form").appForm({
    onSuccess: function (result) {
      $("#tender-procurement-inbox-table").appTable({newData: true});
      appAlert.success(result.message || "Saved", {duration: 3000});
    }
  });

  $(document).on("click", ".delete-doc", function () {
    var id = $(this).attr("data-id");
    appLoader.show();
    $.post("<?php echo_uri('tender_procurement_inbox/delete_document'); ?>", {id: id}, function (res) {
      appLoader.hide();
      var r = JSON.parse(res);
      if (r.success) {
        $("#doc-row-" + id).remove();
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    });
  });

});
</script>