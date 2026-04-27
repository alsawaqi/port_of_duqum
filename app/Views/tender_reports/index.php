<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-report-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_register"); ?></h1>
            <div class="title-button-group">
                <a href="<?php echo get_uri("tender_procurement_inbox"); ?>" class="btn btn-default">
                    <i data-feather="briefcase" class="icon-16"></i> Procurement Inbox
                </a>
            </div>
        </div>

        <div class="card-body">
            <div class="alert alert-light mb15">
                Tender register for procurement/admin review. Use the filters to open a tender and view its timeline, participants, evaluations, document access, and audit history.
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-register-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-register-table").appTable({
        source: '<?php echo_uri("tender_reports/list_data"); ?>',
        filterDropdown: [
            {name: "status", class: "w160", options: [
                {id: "", text: "- Status -"},
                {id: "draft", text: "Draft"},
                {id: "published", text: "Published"},
                {id: "closed", text: "Closed"},
                {id: "awarded", text: "Awarded"},
                {id: "cancelled", text: "Cancelled"}
            ]},
            {name: "tender_type", class: "w160", options: [
                {id: "", text: "- Type -"},
                {id: "open", text: "Open"},
                {id: "close", text: "Close"}
            ]},
            {name: "workflow_stage", class: "w180", options: [
                {id: "", text: "- Stage -"},
                {id: "bidding", text: "Bidding"},
                {id: "technical_3key", text: "3-Key Technical Opening"},
                {id: "technical", text: "Technical"},
                {id: "committee_3key", text: "3-Key Commercial Opening"},
                {id: "commercial", text: "Commercial"},
                {id: "award_decision", text: "Award Decision"}
            ]}
        ],
        columns: [
            {title: "Reference"},
            {title: "Tender"},
            {title: "Company"},
            {title: "Department"},
            {title: "Type", class: "text-center"},
            {title: "Status", class: "text-center"},
            {title: "Stage", class: "text-center"},
            {title: "Invited", class: "text-center"},
            {title: "Bids", class: "text-center"},
            {title: "Submission Deadline"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w100"}
        ]
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
