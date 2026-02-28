<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('vendor_groups'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_vendor_groups)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_groups/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_group'),
                        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_vendor_group'))
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="vendor-groups-table" class="display" cellspacing="0" width="100%"></table>
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