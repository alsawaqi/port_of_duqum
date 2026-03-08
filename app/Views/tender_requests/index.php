<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h4><?php echo app_lang("tender_requests"); ?></h4>

        <?php
        echo modal_anchor(
            get_uri("tender_requests/modal_form"),
            "<i data-feather='plus-circle' class='icon-16'></i> Add Request",
            ["class" => "btn btn-default", "title" => "Add Tender Request"]
        );
        ?>
    </div>

    <div class="card-body">
        <table id="tender-requests-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function () {
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
            var r = JSON.parse(res);
            if (r.success) {
                $("#tender-requests-table").appTable({newData: true});
                appAlert.success(r.message, {duration: 3000});
            } else {
                appAlert.error(r.message || "Error", {duration: 3000});
            }
        });
    });
});
</script>