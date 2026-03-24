<div id="page-content" class="page-wrapper clearfix gp-pro-page">
  <div class="card gp-pro-card">
    <div class="page-title clearfix">
      <h1><?php echo app_lang("tender_department_manager_inbox"); ?></h1>
    </div>

    <div class="table-responsive gp-pro-table-shell">
      <table id="tender-department-manager-inbox-table" class="display" cellspacing="0" width="100%"></table>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="departmentManagerRejectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="reject_id" />
        <div class="form-group">
          <label>Comment (required)</label>
          <textarea id="reject_comment" class="form-control" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
        <button class="btn btn-danger" id="doRejectBtn">Reject</button>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function () {
  function tryParseResponse(res) {
    if (typeof res === "object") {
      return res;
    }
    try {
      return JSON.parse(res);
    } catch (e) {
      return {success: false, message: "Unexpected server response."};
    }
  }

  function reloadTable() {
    $("#tender-department-manager-inbox-table").appTable({newData: true});
  }

  $("#tender-department-manager-inbox-table").appTable({
    source: '<?php echo_uri("tender_department_manager_inbox/list_data"); ?>',
    columns: [
      {title: "Reference"},
      {title: "Subject"},
      {title: "Budget (OMR)"},
      {title: "Fee (OMR)"},
      {title: "Status"},
      {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
    ]
  });

  $(document).on("click", ".approve", function () {
    var $el = $(this);
    appLoader.show();
    $.post($el.attr("data-action-url"), {id: $el.attr("data-id")}, function (res) {
      appLoader.hide();
      var r = tryParseResponse(res);
      if (r.success) {
        reloadTable();
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    }).fail(function () {
      appLoader.hide();
      appAlert.error("Request failed. Please try again.", {duration: 3000});
    });
  });

  $(document).on("click", ".reject", function () {
    $("#reject_id").val($(this).attr("data-id"));
    $("#reject_comment").val("");
    $("#departmentManagerRejectModal").modal("show");
  });

  $("#doRejectBtn").on("click", function () {
    var id = $("#reject_id").val();
    var comment = $("#reject_comment").val().trim();
    if (!comment) {
      appAlert.error("Comment is required", {duration: 3000});
      return;
    }
    appLoader.show();
    $.post('<?php echo_uri("tender_department_manager_inbox/reject"); ?>', {id: id, comment: comment}, function (res) {
      appLoader.hide();
      $("#departmentManagerRejectModal").modal("hide");
      var r = tryParseResponse(res);
      if (r.success) {
          reloadTable();
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    }).fail(function () {
      appLoader.hide();
      appAlert.error("Request failed. Please try again.", {duration: 3000});
    });
  });
});
</script>