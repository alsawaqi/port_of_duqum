<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('regions'); ?></h1>

            <div class="title-button-group">
            <?php if (!empty($can_create_regions)) { ?>
    <?php echo modal_anchor(get_uri("regions/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_region'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_region'))
    ); ?>
<?php } ?>

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


