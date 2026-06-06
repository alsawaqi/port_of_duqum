<?php
$vendor_page_actions = "";

if (!empty($can_create_vendor_groups)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendor_groups/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_group'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon pod-action-btn", "title" => app_lang('add_vendor_group'))
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-vendor-page pod-vendor-master-page">
    <?php
    echo view("includes/pod_page_header", array(
        "title" => app_lang("vendor_groups"),
        "subtitle" => "Organize vendors into commercial groups, validity bands, and procurement categories.",
        "icon" => "briefcase",
        "breadcrumbs" => array(
            array("label" => app_lang("vendors_master")),
            array("label" => app_lang("vendor_groups"))
        ),
        "actions" => $vendor_page_actions
    ));
    ?>

    <div class="card gp-pro-card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendor_groups'); ?></h2>
                <p class="pod-vendor-card-subtitle">Maintain vendor group rules and status in one clean workspace.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
            <div class="table-responsive gp-pro-table-shell">
                <table id="vendor-groups-table" class="display" cellspacing="0" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-groups-table").appTable({
            source: '<?php echo_uri("vendor_groups/list_data") ?>',
            columns: [{
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w10p"
                },
                {
                    title: 'Riyada',
                    "class": "w10p"
                },
                {
                    title: 'Validity (days)',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_groups) || !empty($can_delete_vendor_groups)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>
