<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("ptw_hsse_inbox"); ?></h1>
        </div>

        <div class="table-responsive">
            <table id="ptw-hsse-inbox-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#ptw-hsse-inbox-table").appTable({
        source: "<?php echo_uri('ptw_hsse_inbox/list_data'); ?>",
        columns: [
            { title: "<?php echo app_lang('reference'); ?>" },
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('applicant_name'); ?>" },
            { title: "<?php echo app_lang('work_location'); ?>" },
            { title: "<?php echo app_lang('starting_date_time'); ?>" },
            { title: "<?php echo app_lang('completion_date_time'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w150" }
        ],
        order: [[0, "desc"]],
        onDrawCallback: function () {
            if (window.feather) feather.replace();
        }
    });
});
</script>