<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang('countries'); ?></h1>

            <div class="title-button-group">
            <?php if (!empty($can_create_countries)) { ?>
    <?php echo modal_anchor(get_uri("country/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add_country"), ["class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon"]); ?>
<?php } ?>
            </div>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="country-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#country-table").appTable({
            source: '<?php echo_uri("country/list_data") ?>',
            columns: [
                {title: '<?php echo app_lang("name"); ?>', "class": "w40p"},
                {title: '<?php echo app_lang("code"); ?>', "class": "w20p"},
                {title: '<?php echo app_lang("status"); ?>', "class": "w20p"},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w120"}
            ]
        });
    });
</script>
