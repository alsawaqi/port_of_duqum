<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix gp-pro-title">
            <h1>PTW Reasons</h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("ptw_reasons/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add"),
                    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon"]
                ); ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="ptw-reasons-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#ptw-reasons-table").appTable({
        source: '<?php echo_uri("ptw_reasons/list_data"); ?>',
        columns: [
            {title: "Reason"},
            {title: "Stage", class: "w10p"},
            {title: "Type", class: "w10p"},
            {title: "<?php echo app_lang("sort_order"); ?>", class: "w10p"},
            {title: "<?php echo app_lang("status"); ?>", class: "w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>