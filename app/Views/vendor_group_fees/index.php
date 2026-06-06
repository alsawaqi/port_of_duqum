<?php
$vendor_page_actions = "";

if (!empty($can_create_vendor_group_fees)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendor_group_fees/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_group_fee'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon pod-action-btn", "title" => app_lang('add_vendor_group_fee'))
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-vendor-page pod-vendor-master-page">
    <?php
    echo view("includes/pod_page_header", array(
        "title" => app_lang("vendor_group_fees"),
        "subtitle" => "Manage fee schedules, active periods, and group-level commercial settings.",
        "icon" => "credit-card",
        "breadcrumbs" => array(
            array("label" => app_lang("vendors_master")),
            array("label" => app_lang("vendor_group_fees"))
        ),
        "actions" => $vendor_page_actions
    ));
    ?>

    <div class="card gp-pro-card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendor_group_fees'); ?></h2>
                <p class="pod-vendor-card-subtitle">Keep fee configuration readable, searchable, and ready for audit.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
            <div class="table-responsive gp-pro-table-shell">
                <table id="vendor-group-fees-table" class="display" cellspacing="0" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-group-fees-table").appTable({
            source: '<?php echo_uri("vendor_group_fees/list_data") ?>',
            columns: [{
                    title: '<?php echo app_lang("vendor_groups"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("fee_type"); ?>',
                    "class": "w15p"
                },
                {
                    title: '<?php echo app_lang("amount"); ?>',
                    "class": "w15p"
                },
                {
                    title: '<?php echo app_lang("active_period"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_group_fees) || !empty($can_delete_vendor_group_fees)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>
