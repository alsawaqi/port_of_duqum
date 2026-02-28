<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('vendor_document_types'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_vendor_document_types)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_document_types/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_document_type'),
                        ["class" => "btn btn-default", "title" => app_lang('add_vendor_document_type')]
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="vendor-document-types-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#vendor-document-types-table").appTable({
            source: '<?php echo_uri("vendor_document_types/list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("vendor_group"); ?>',
                    "class": "w20p"
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
                    title: '<?php echo app_lang("required"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_vendor_document_types) || !empty($can_delete_vendor_document_types)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>