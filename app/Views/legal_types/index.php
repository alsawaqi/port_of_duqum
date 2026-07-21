<?php
$master_data_actions = "";
if (!empty($can_create_legal_types)) {
    $master_data_actions = modal_anchor(
        get_uri("legal_types/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_legal_type'),
        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_legal_type'))
    );
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-master-page">
    <?php echo view("includes/master_data_page_header", [
        "title" => app_lang("legal_types"),
        "subtitle" => "Maintain legal entity classifications used during vendor and company registration.",
        "icon" => "file-text",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("legal_types")]
        ],
        "actions" => $master_data_actions
    ]); ?>

    <div class="card gp-pro-card pod-master-card">
        <div class="page-title clearfix">
            <div>
                <h1><?php echo app_lang('legal_types'); ?></h1>
                <p class="pod-master-card-subtitle">Standardize legal type names, codes, and active availability.</p>
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
