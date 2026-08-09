<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-ptw-page">
    <?php echo view("includes/ptw_page_header", [
        "title" => app_lang("ptw_applicant_users"),
        "subtitle" => "Authorize PTW applicants for specific companies.",
        "icon" => "user-check",
        "breadcrumbs" => [["label" => "PTW"], ["label" => app_lang("ptw_applicant_users")]]
    ]); ?>
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("ptw_applicant_users"); ?></h1>
            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("ptw_applicant_users/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_ptw_applicant_user"),
                    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_ptw_applicant_user")]
                ); ?>
            </div>
        </div>
        <div class="table-responsive gp-pro-table-shell">
            <table id="ptw-applicant-users-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>
<script>
$(document).ready(function () {
    $("#ptw-applicant-users-table").appTable({
        source: '<?php echo_uri("ptw_applicant_users/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('company'); ?>"},
            {title: "<?php echo app_lang('name'); ?>"},
            {title: "<?php echo app_lang('email'); ?>"},
            {title: "<?php echo app_lang('phone'); ?>"},
            {title: "<?php echo app_lang('status'); ?>"},
            {title: "<?php echo app_lang('actions'); ?>", class: "text-center option w100"}
        ],
        order: [[0, "asc"]]
    });
});
</script>
