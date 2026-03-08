<div class="card">
  <div class="card-header">
    <h4><?php echo app_lang("tender_finance_inbox"); ?></h4>
  </div>

  <div class="card-body">
    <table id="tender-finance-inbox-table" class="display" cellspacing="0" width="100%"></table>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="financeRejectModal" tabindex="-1" role="dialog">
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
  $("#tender-finance-inbox-table").appTable({
    source: '<?php echo_uri("tender_finance_inbox/list_data"); ?>',
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
      var r = JSON.parse(res);
      if (r.success) {
        $("#tender-finance-inbox-table").appTable({newData: true});
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    });
  });

  $(document).on("click", ".reject", function () {
    $("#reject_id").val($(this).attr("data-id"));
    $("#reject_comment").val("");
    $("#financeRejectModal").modal("show");
  });

  $("#doRejectBtn").on("click", function () {
    var id = $("#reject_id").val();
    var comment = $("#reject_comment").val().trim();
    if (!comment) {
      appAlert.error("Comment is required", {duration: 3000});
      return;
    }
    appLoader.show();
    $.post('<?php echo_uri("tender_finance_inbox/reject"); ?>', {id: id, comment: comment}, function (res) {
      appLoader.hide();
      $("#financeRejectModal").modal("hide");
      var r = JSON.parse(res);
      if (r.success) {
        $("#tender-finance-inbox-table").appTable({newData: true});
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    });
  });
});
</script>