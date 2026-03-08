<div class="card">
  <div class="card-header">
    <h4><?php echo app_lang("tender_procurement_inbox"); ?></h4>
  </div>

  <div class="card-body">
    <table id="tender-procurement-inbox-table" class="display" cellspacing="0" width="100%"></table>
  </div>
</div>

<script>
$(document).ready(function () {
  $("#tender-procurement-inbox-table").appTable({
    source: '<?php echo_uri("tender_procurement_inbox/list_data"); ?>',
    columns: [
      {title: "Reference"},
      {title: "Subject"},
      {title: "Company"},
      {title: "Department"},
      {title: "Type"},
      {title: "Req Status"},
      {title: "Tender Status"},
      {title: "Closing At"},
      {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
    ]
  });

  $(document).on("click", ".publish", function () {
    var $el = $(this);
    var requestId = $el.attr("data-request-id");

    appLoader.show();
    $.post($el.attr("data-action-url"), {tender_request_id: requestId}, function (res) {
      appLoader.hide();
      var r = JSON.parse(res);
      if (r.success) {
        $("#tender-procurement-inbox-table").appTable({newData: true});
        appAlert.success(r.message, {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    });
  });
});
</script>