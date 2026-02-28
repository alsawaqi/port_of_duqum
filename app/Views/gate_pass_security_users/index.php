<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_security_users"); ?></h1>
            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_security_users/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_security_user"),
                    ["class" => "btn btn-default", "title" => app_lang("add_security_user")]
                ); ?>
            </div>
        </div>
        <div class="table-responsive">
            <table id="gate-pass-security-users-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-security-users-table").appTable({
        source: '<?php echo_uri("gate_pass_security_users/list_data"); ?>',
        columns: [
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('name'); ?>" },
            { title: "<?php echo app_lang('email'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<?php echo app_lang('actions'); ?>", class: "text-center option w100" }
        ],
        order: [[0, "asc"]]
    });
});
</script>
