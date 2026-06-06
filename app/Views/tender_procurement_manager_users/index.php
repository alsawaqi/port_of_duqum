<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => app_lang("tender_procurement_manager_users"),
        "subtitle" => "Maintain procurement manager users for tender governance.",
        "icon" => "user-check"
    ]);
    ?>
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_procurement_manager_users"); ?></h1>
            <div class="title-button-group">
                <?php if (!empty($can_create_tender_user)) { ?>
                    <?php echo modal_anchor(
                        get_uri("tender_procurement_manager_users/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_tender_procurement_manager_user"),
                        ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_tender_procurement_manager_user")]
                    ); ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="tender-procurement-manager-users-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-procurement-manager-users-table").appTable({
        source: '<?php echo_uri("tender_procurement_manager_users/list_data"); ?>',
        columns: [
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('name'); ?>" },
            { title: "<?php echo app_lang('email'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<?php echo app_lang('actions'); ?>", class: "text-center option w100" }
        ]
    });
});
</script>
