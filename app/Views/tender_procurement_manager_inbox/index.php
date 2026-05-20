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
});
</script>
