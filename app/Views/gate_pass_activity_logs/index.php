<div id="page-content" class="page-wrapper clearfix">
    <div class="p15">
        <div class="card">
            <div class="page-title clearfix">
                <h1><?php echo app_lang("gate_pass_admin_activity_logs"); ?></h1>
                <div class="title-button-group"></div>
            </div>
            <div class="p15 pt0 text-off">
                <?php echo app_lang("gate_pass_admin_activity_logs_hint"); ?>
            </div>
            <div class="table-responsive">
                <table id="gp-admin-audit-table" class="display" width="100%" cellspacing="0"></table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function () {
    if (typeof feather !== "undefined") feather.replace();
    var $t = $("#gp-admin-audit-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    $t.appTable({
        source: "<?php echo_uri('gate_pass_activity_logs/list_data'); ?>",
        columnShowHideOption: false,
        displayLength: 25,
        columns: [
            { title: "<?php echo app_lang('date'); ?>" },
            { title: "<?php echo app_lang('reference'); ?>" },
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('user'); ?>" },
            { title: "<?php echo app_lang('action'); ?>" },
            { title: "<?php echo app_lang('details'); ?>" }
        ],
        order: [[0, "desc"]],
        onDrawCallback: function () {
            if (window.feather) feather.replace();
        }
    });
});
</script>
