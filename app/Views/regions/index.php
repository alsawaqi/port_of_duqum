<?php
$master_data_actions = "";
if (!empty($can_create_regions)) {
    $master_data_actions = modal_anchor(
        get_uri("regions/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_region'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_region'))
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("regions"),
        "subtitle" => "Maintain regional location records under each country for accurate filtering and registration.",
        "icon" => "map",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("regions")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang('regions'); ?></h1>
                <p class="pod-master-card-subtitle">Connect region names and codes to their parent countries.</p>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="regions-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {

    let columns = [
        {title: '<?php echo app_lang("countries"); ?>', "class": "w20p"},
        {title: '<?php echo app_lang("name"); ?>', "class": "w30p"},
        {title: '<?php echo app_lang("code"); ?>', "class": "w15p"},
        {title: '<?php echo app_lang("status"); ?>', "class": "w15p"}
    ];

    <?php if (!empty($can_update_regions) || !empty($can_delete_regions)) { ?>
        columns.push({title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"});
    <?php } ?>

    $("#regions-table").appTable({
        source: '<?php echo_uri("regions/list_data") ?>',
        columns: columns
    });
});
</script>


