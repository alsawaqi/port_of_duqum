 
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("tender_commercial_users"); ?></h1>
            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("tender_commercial_users/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_tender_commercial_user"),
                    ["class" => "btn btn-primary", "title" => app_lang("add_tender_commercial_user")]
                ); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="tender-commercial-users-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-commercial-users-table").appTable({
        source: '<?php echo_uri("tender_commercial_users/list_data"); ?>',
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