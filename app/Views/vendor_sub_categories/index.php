<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('vendor_sub_categories'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_vendor_sub_categories)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_sub_categories/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_sub_category'),
                        ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_vendor_sub_category')]
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="vendor-sub-categories-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-sub-categories-table").appTable({
            source: '<?php echo_uri("vendor_sub_categories/list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("vendor_categories"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w15p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_sub_categories) || !empty($can_delete_vendor_sub_categories)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w15p"
                }
                <?php } ?>
            ],
            onDrawCallback: function() {
                if (window.feather) feather.replace();
            }
        });
    });
</script>