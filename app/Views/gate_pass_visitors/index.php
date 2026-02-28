<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_visitors"); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_visitors/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_visitor"),
                    ["class" => "btn btn-default", "title" => app_lang("add_visitor")]
                ); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="gate-pass-visitors-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-visitors-table").appTable({
        source: '<?php echo_uri("gate_pass_visitors/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('username'); ?>", class:"w15p"},
            {title: "<?php echo app_lang('name'); ?>", class:"w20p"},
            {title: "<?php echo app_lang('email'); ?>", class:"w20p"},
            {title: "<?php echo app_lang('phone'); ?>", class:"w10p"},
            {title: "<?php echo app_lang('alternative_phone'); ?>", class:"w10p"},
            {title: "<?php echo app_lang('otp_channel'); ?>", class:"w10p"},
            {title: "<?php echo app_lang('status'); ?>", class:"w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class:"text-center option w120"}
        ]
    });
});
</script>
