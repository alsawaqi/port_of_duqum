<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('vendor_group_fees'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_vendor_group_fees)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_group_fees/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_group_fee'),
                        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_vendor_group_fee'))
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="vendor-group-fees-table" class="display" cellspacing="0" width="100%"></table>
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