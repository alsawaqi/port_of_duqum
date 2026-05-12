<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_procurement_manager_inbox"); ?></h1>
            <div class="title-button-group"></div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-procurement-manager-inbox-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    function reloadTable() {
        $("#tender-procurement-manager-inbox-table").appTable({reload: true});
    }

    function postAction($el, payload) {
        appLoader.show();
        $.post($el.attr("data-action-url"), payload, function (res) {
            appLoader.hide();
            if (res && res.success) {
                reloadTable();
                appAlert.success(res.message || "Done", {duration: 3000});
            } else {
                appAlert.error((res && res.message) || "Request failed.", {duration: 3000});
            }
        }, "json").fail(function () {
            appLoader.hide();
            appAlert.error("Request failed. Please try again.", {duration: 3000});
        });
    }

    $("#tender-procurement-manager-inbox-table").appTable({
        source: '<?php echo_uri("tender_procurement_manager_inbox/list_data"); ?>',
        columns: [
            {title: "Reference"},
            {title: "Title"},
            {title: "Company"},
            {title: "Department"},
            {title: "Approval Status"},
            {title: "Approval Action"},
            {title: "Pending Change"},
            {title: "Submitted At"},
            {title: "Submitted By"},
            {title: "Reviewed At"},
            {title: "Reviewed By"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w150"}
        ]
    });

    $(document).on("click", ".approve-tender", function () {
        if (confirm("Approve this procurement manager request?")) {
            postAction($(this), {tender_id: $(this).attr("data-tender-id")});
        }
    });
});
</script>
