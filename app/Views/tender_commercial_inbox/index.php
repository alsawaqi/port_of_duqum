<div class="card">
    <div class="card-header">
        <h4><?php echo app_lang("tender_commercial_inbox"); ?></h4>
    </div>

    <div class="card-body">
        <table id="tender-commercial-inbox-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-commercial-inbox-table").appTable({
        source: '<?php echo_uri("tender_commercial_inbox/list_data"); ?>',
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