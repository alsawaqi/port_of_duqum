 
<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_department_users"); ?></h1>
            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("tender_department_users/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_tender_department_user"),
                    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_tender_department_user")]
                ); ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
                <table id="tender-department-users-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-department-users-table").appTable({
        source: '<?php echo_uri("tender_department_users/list_data"); ?>',
        columns: [
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('department'); ?>" },
            { title: "<?php echo app_lang('name'); ?>" },
            { title: "<?php echo app_lang('email'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<?php echo app_lang('actions'); ?>", class: "text-center option w100" }
        ]
    });
});
</script>