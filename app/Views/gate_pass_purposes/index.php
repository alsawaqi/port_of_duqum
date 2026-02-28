<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_purposes"); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_purposes/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_purpose"),
                    ["class" => "btn btn-default"]
                ); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="gate-pass-purposes-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-purposes-table").appTable({
        source: '<?php echo_uri("gate_pass_purposes/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('name'); ?>"},
            {title: "<?php echo app_lang('status'); ?>", class:"w15p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class:"text-center option w120"}
        ]
    });
});
</script>
