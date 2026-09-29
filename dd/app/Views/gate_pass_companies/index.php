<?php
$master_data_actions = modal_anchor(
    get_uri("gate_pass_companies/modal_form"),
    "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_company"),
    ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_company")]
);
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("gate_pass_companies"),
        "subtitle" => "Maintain company records used across gate pass, vendor, and operational workflows.",
        "icon" => "briefcase",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("gate_pass_companies")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang("gate_pass_companies"); ?></h1>
                <p class="pod-master-card-subtitle">Review company codes, active status, and master identifiers.</p>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
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
