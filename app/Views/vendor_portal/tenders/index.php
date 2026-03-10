<div class="card">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1">Available Tenders</h4>
                <div class="text-muted">Only tenders issued to your approved specialties are shown here.</div>
            </div>
        </div>
    </div>

    <div class="card-body">
        <table id="vendor-tenders-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#vendor-tenders-table").appTable({
        source: '<?php echo_uri("vendor_portal/tenders_list_data"); ?>',
        columns: [
            {title: "Reference"},
            {title: "Title"},
            {title: "Type", class: "w10p"},
            {title: "Tender Status", class: "w10p"},
            {title: "Target Specialty"},
            {title: "Published At", class: "w15p"},
            {title: "Closing At", class: "w15p"},
            {title: "Invite Status", class: "w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w100"}
        ]
    });
});
</script>