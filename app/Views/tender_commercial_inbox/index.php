<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => app_lang("tender_commercial_inbox"),
        "subtitle" => "Evaluate commercial bid packages and prepare award-ready decisions.",
        "icon" => "bar-chart-2"
    ]);
    ?>
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_commercial_inbox"); ?></h1>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-commercial-inbox-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-commercial-inbox-table").appTable({
        source: '<?php echo_uri("tender_commercial_inbox/list_data"); ?>',
        order: [],
        stateSave: false,
        columns: [
            {title: "Reference"},
            {title: "Title"},
            {title: "Type", class: "w10p"},
            {title: "Workflow Stage", class: "w10p"},
            {title: "Commercial Ends At", class: "w15p"},
            {title: "Bids For Review", class: "w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>
