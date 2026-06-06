<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => app_lang("tender_procurement_inbox"),
        "subtitle" => "Create, publish, cancel, retender, and monitor procurement-led tenders.",
        "icon" => "briefcase"
    ]);
    ?>
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_procurement_inbox"); ?></h1>
            <div class="title-button-group">
                <?php
                echo anchor(
                    get_uri("tender_clarifications"),
                    "<i data-feather='message-square' class='icon-16'></i> Clarifications",
                    ["class" => "btn btn-default"]
                );
                echo anchor(
                    get_uri("tender_reports"),
                    "<i data-feather='bar-chart-2' class='icon-16'></i> Tender Register",
                    ["class" => "btn btn-default"]
                );
                ?>
                <?php
                echo anchor(
                    get_uri("tender_procurement_inbox/form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> New Tender",
                    ["class" => "btn btn-default", "title" => "Create Tender"]
                );
                ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-procurement-inbox-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    function reloadTable() {
        $("#tender-procurement-inbox-table").appTable({reload: true});
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

    $("#tender-procurement-inbox-table").appTable({
        source: '<?php echo_uri("tender_procurement_inbox/list_data"); ?>',
        order: [],
        stateSave: false,
        columns: [
            {title: "Reference"},
            {title: "<?php echo app_lang('created_date'); ?>"},
            {title: "Subject"},
            {title: "Company"},
            {title: "Department"},
            {title: "Type"},
            {title: "Request Source"},
            {title: "Tender Status"},
            {title: "Manager Approval"},
            {title: "Workflow Stage"},
            {title: "Submission Deadline"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w150"}
        ]
    });

    $(document).on("click", ".publish", function () {
        postAction($(this), {tender_id: $(this).attr("data-tender-id")});
    });

    $(document).on("click", ".award", function () {
        if (confirm("Finalize this tender as awarded?")) {
            postAction($(this), {tender_id: $(this).attr("data-tender-id")});
        }
    });

    $(document).on("click", ".cancel-tender", function () {
        if (confirm("Submit this tender cancellation to the procurement manager for approval?")) {
            postAction($(this), {tender_id: $(this).attr("data-tender-id")});
        }
    });

    $(document).on("click", ".retender", function () {
        if (confirm("Create a new retender draft from this tender?")) {
            postAction($(this), {tender_id: $(this).attr("data-tender-id")});
        }
    });
});
</script>
