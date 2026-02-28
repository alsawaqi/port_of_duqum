<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_departments"); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_departments/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_department"),
                    ["class" => "btn btn-default", "title" => app_lang("add_department")]
                ); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="gate-pass-departments-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-departments-table").appTable({
        source: '<?php echo_uri("gate_pass_departments/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('company'); ?>", class: "w30p"},
            {title: "<?php echo app_lang('name'); ?>", class: "w30p"},
            {title: "<?php echo app_lang('code'); ?>", class: "w15p"},
            {title: "<?php echo app_lang('status'); ?>", class: "w15p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>
