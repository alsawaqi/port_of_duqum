<?php
$master_data_actions = "";
if (!empty($can_create_cities)) {
    $master_data_actions = modal_anchor(
        get_uri("cities/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_city'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_city'))
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("cities"),
        "subtitle" => "Maintain city records linked to countries and regions for clean location selection.",
        "icon" => "map-pin",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("cities")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang('cities'); ?></h1>
                <p class="pod-master-card-subtitle">Manage city names, codes, status, and their region hierarchy.</p>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="cities-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#cities-table").appTable({
            source: '<?php echo_uri("cities/list_data") ?>',
            columns: [{
                    title: '<?php echo app_lang("countries"); ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("regions"); ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_cities) || !empty($can_delete_cities)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>
