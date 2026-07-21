<?php
$master_data_actions = modal_anchor(
    get_uri("gate_pass_departments/modal_form"),
    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_department"),
    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_department")]
);
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("gate_pass_departments"),
        "subtitle" => "Maintain department records and map them cleanly to operating companies.",
        "icon" => "layers",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("gate_pass_departments")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang("gate_pass_departments"); ?></h1>
                <p class="pod-master-card-subtitle">Keep department ownership, codes, and availability easy to audit.</p>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
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
