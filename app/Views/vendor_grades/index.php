<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("vendor_grades"); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_vendor_grades)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_grades/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_vendor_grade"),
                        ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang("add_vendor_grade")]
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="vendor-grades-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-grades-table").appTable({
            source: '<?php echo_uri("vendor_grades/list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("description"); ?>',
                    "class": "w35p"
                },
                {
                    title: '<?php echo app_lang("sort"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_grades) || !empty($can_delete_vendor_grades)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>
