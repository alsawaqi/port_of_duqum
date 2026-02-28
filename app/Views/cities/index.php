<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('cities'); ?></h1>

            <div class="title-button-group">
                <?php if (!empty($can_create_cities)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("cities/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_city'),
                        array("class" => "btn btn-default", "title" => app_lang('add_city'))
                    );
                    ?>
                <?php } ?>
            </div>
        </div>

        <div class="table-responsive">
            <table id="cities-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#cities-table").appTable({
            source: '<?php echo_uri("cities/list_data") ?>',
            columns: [{
                    title: '<?php echo app_lang("countries"); ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("regions"); ?>',
                    "class": "w20p"
                },
                {
                    title: '<?php echo app_lang("name"); ?>',
                    "class": "w25p"
                },
                {
                    title: '<?php echo app_lang("code"); ?>',
                    "class": "w10p"
                },
                {
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "w10p"
                }
                <?php if (!empty($can_update_cities) || !empty($can_delete_cities)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ]
        });
    });
</script>