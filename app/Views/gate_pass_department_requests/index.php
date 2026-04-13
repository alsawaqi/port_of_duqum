<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="p15 pb0">
        <?php $kpis = $kpis ?? []; ?>
        <?php echo view("gate_pass_includes/dashboard_kpis_widget", ["kpis" => $kpis]); ?>
        <div class="mb15">
            <a class="btn btn-default" href="<?php echo get_uri("gate_pass_department_requests/export_list_csv"); ?>">
                <i data-feather="download" class="icon-16"></i> <?php echo app_lang("gate_pass_export_csv"); ?>
            </a>
        </div>
    </div>
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_department_requests"); ?></h1>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="gate-pass-dept-requests-table" class="display" cellspacing="0" width="100%">
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function () {

    if (typeof feather !== "undefined") feather.replace();

    // IMPORTANT: prevent "Cannot reinitialise DataTable"
    var $table = $("#gate-pass-dept-requests-table");
    if ($.fn.DataTable.isDataTable($table)) {
        $table.DataTable().destroy();
        $table.empty();
    }

    $table.appTable({
        source: '<?php echo_uri("gate_pass_department_requests/list_data"); ?>',
        columns: [
            {title: '<?php echo app_lang("reference"); ?>'},
            {title: '<?php echo app_lang("created_at"); ?>'},
            {title: '<?php echo app_lang("company"); ?>'},
            {title: '<?php echo app_lang("department"); ?>'},
            {title: '<?php echo app_lang("requester"); ?>'},
            {title: '<?php echo app_lang("phone"); ?>'},
            {title: '<?php echo app_lang("purpose"); ?>'},
            {title: '<?php echo app_lang("visit_from"); ?>'},
            {title: '<?php echo app_lang("visit_to"); ?>'},
            {title: '<?php echo app_lang("status"); ?>'},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ],
        order: [[1, "desc"]]
    });
});
</script>
