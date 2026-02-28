<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix gp-pro-title">
            <h1><?php echo app_lang("gate_pass_reasons"); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_reasons/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_reason"),
                    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon"]
                ); ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="gate-pass-reasons-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-reasons-table").appTable({
        source: '<?php echo_uri("gate_pass_reasons/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('reason'); ?>"},
            {title: "<?php echo app_lang('description'); ?>"},
            {title: "<?php echo app_lang('sort_order'); ?>", class: "w10p"},
            {title: "<?php echo app_lang('status'); ?>", class: "w15p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>

