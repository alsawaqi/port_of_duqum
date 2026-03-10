<div class="page-title clearfix">
    <h4>3-Key Commercial Bid Opening</h4>
</div>

<div class="table-responsive">
    <table id="tender-committee-opening-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script>
$(document).ready(function () {
    $("#tender-committee-opening-table").appTable({
        source: '<?php echo_uri("tender_committee_opening_inbox/list_data"); ?>',
        columns: [
            {title: "Reference"},
            {title: "Title"},
            {title: "Bids"},
            {title: "Opening Status"},
            {title: "Committee Ends At"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>