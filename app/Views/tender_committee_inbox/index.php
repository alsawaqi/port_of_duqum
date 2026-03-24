<div id="page-content" class="page-wrapper clearfix gp-pro-page">
  <div class="card gp-pro-card">
    <div class="page-title clearfix">
      <h1><?php echo app_lang("tender_committee_inbox"); ?></h1>
    </div>

    <div class="table-responsive gp-pro-table-shell">
      <table id="tender-committee-inbox-table" class="display" cellspacing="0" width="100%"></table>
    </div>
  </div>
</div>

<div class="modal fade" id="committeeRejectModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Request</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="committee_reject_id" />
        <div class="form-group">
          <label>Comment (required)</label>
          <textarea id="committee_reject_comment" class="form-control" rows="4"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
        <button class="btn btn-danger" id="doCommitteeRejectBtn">Reject</button>
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
    $("#tender-committee-inbox-table").appTable({newData: true});
  }

  $("#tender-committee-inbox-table").appTable({
    source: '<?php echo_uri("tender_committee_inbox/list_data"); ?>',
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
    $("#committee_reject_id").val($(this).attr("data-id"));
    $("#committee_reject_comment").val("");
    $("#committeeRejectModal").modal("show");
  });

  $("#doCommitteeRejectBtn").on("click", function () {
    var id = $("#committee_reject_id").val();
    var comment = $("#committee_reject_comment").val().trim();
    if (!comment) {
      appAlert.error("Comment is required", {duration: 3000});
      return;
    }
    appLoader.show();
    $.post('<?php echo_uri("tender_committee_inbox/reject"); ?>', {id: id, comment: comment}, function (res) {
      appLoader.hide();
      $("#committeeRejectModal").modal("hide");
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