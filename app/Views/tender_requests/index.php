<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_requests"); ?></h1>

            <div class="title-button-group">
                <?php
                echo modal_anchor(
                    get_uri("tender_requests/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> Add Request",
                    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => "Add Tender Request"]
                );
                ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-requests-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
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
        $("#tender-requests-table").appTable({newData: true});
    }

    $("#tender-requests-table").appTable({
        source: '<?php echo_uri("tender_requests/list_data"); ?>',
        columns: [
            {title: "Reference"},
            {title: "Subject"},
            {title: "Budget (OMR)"},
            {title: "Fee (OMR)"},
            {title: "Status"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });

    $(document).on("click", ".submit", function () {
        var $el = $(this);
        appLoader.show();
        $.post($el.attr("data-action-url"), {id: $el.attr("data-id")}, function (res) {
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
});
</script>