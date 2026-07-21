<?php
$master_data_actions = "";
if (!empty($can_create_countries)) {
    $master_data_actions = modal_anchor(
        get_uri("country/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_country"),
        ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_country")]
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("countries"),
        "subtitle" => "Maintain country records used for vendor locations, addresses, and geographic filtering.",
        "icon" => "globe",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("countries")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang('countries'); ?></h1>
                <p class="pod-master-card-subtitle">Keep country names, codes, and active status ready for operational use.</p>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="country-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#country-table").appTable({
            source: '<?php echo_uri("country/list_data") ?>',
            columns: [
                {title: '<?php echo app_lang("name"); ?>', "class": "w40p"},
                {title: '<?php echo app_lang("code"); ?>', "class": "w20p"},
                {title: '<?php echo app_lang("status"); ?>', "class": "w20p"},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w120"}
            ]
        });
    });
</script>
