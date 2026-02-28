<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('legal_types'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_legal_types)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("legal_types/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_legal_type'),
                        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_legal_type'))
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="legal-types-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#legal-types-table").appTable({
            source: '<?php echo_uri("legal_types/list_data") ?>',
            columns: [{
                    title: '<?php echo app_lang("name") ?>',
                    "class": "w30p"
                },
                {
                    title: '<?php echo app_lang("code") ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("status") ?>',
                    "class": "w20p"
                }
                <?php if (!empty($can_update_legal_types) || !empty($can_delete_legal_types)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>