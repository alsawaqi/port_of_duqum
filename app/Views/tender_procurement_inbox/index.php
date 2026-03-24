<div id="page-content" class="page-wrapper clearfix gp-pro-page">
  <div class="card gp-pro-card">
    <div class="page-title clearfix">
      <h1><?php echo app_lang("tender_procurement_inbox"); ?></h1>
    </div>

    <div class="table-responsive gp-pro-table-shell">
      <table id="tender-procurement-inbox-table" class="display" cellspacing="0" width="100%"></table>
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
    $("#tender-procurement-inbox-table").appTable({newData: true});
  }

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

  function postTenderAction($el, successReloadSelector) {
    var tenderId = $el.attr("data-tender-id");
    appLoader.show();
    $.post($el.attr("data-action-url"), {tender_id: tenderId}, function (res) {
      appLoader.hide();
      var r = tryParseResponse(res);
      if (r.success) {
        $(successReloadSelector).appTable({newData: true});
        appAlert.success(r.message || "Done", {duration: 3000});
      } else {
        appAlert.error(r.message || "Error", {duration: 3000});
      }
    }).fail(function () {
      appLoader.hide();
      appAlert.error("Request failed. Please try again.", {duration: 3000});
    });
  }

  $(document).on("click", ".award", function () {
    if (!confirm("Finalize this tender as awarded?")) {
      return;
    }
    postTenderAction($(this), "#tender-procurement-inbox-table");
  });

  $(document).on("click", ".cancel-tender", function () {
    if (!confirm("Cancel this tender?")) {
      return;
    }
    postTenderAction($(this), "#tender-procurement-inbox-table");
  });

  $(document).on("click", ".retender", function () {
    if (!confirm("Create a new retender draft from this tender?")) {
      return;
    }
    postTenderAction($(this), "#tender-procurement-inbox-table");
  });
});
</script>