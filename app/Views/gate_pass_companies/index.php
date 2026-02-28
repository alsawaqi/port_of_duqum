<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_companies"); ?></h1>

            <div class="title-button-group">
                <?php echo modal_anchor(
                    get_uri("gate_pass_companies/modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_company"),
                    ["class" => "btn btn-default", "title" => app_lang("add_company")]
                ); ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="gate-pass-companies-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#gate-pass-companies-table").appTable({
        source: '<?php echo_uri("gate_pass_companies/list_data"); ?>',
        columns: [
            {title: "<?php echo app_lang('name'); ?>", class: "w40p"},
            {title: "<?php echo app_lang('code'); ?>", class: "w20p"},
            {title: "<?php echo app_lang('status'); ?>", class: "w20p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w120"}
        ]
    });
});
</script>
