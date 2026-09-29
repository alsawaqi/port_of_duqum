<?php
$vendor_page_actions = "";
if (!empty($can_create_vendor_sub_categories)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendor_sub_categories/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_sub_category'),
        ["class" => "btn btn-primary pod-action-btn", "title" => app_lang('add_vendor_sub_category')]
    );
}
?>

<div id="page-content" class="page-wrapper clearfix pod-page-shell pod-master-page pod-vendor-page pod-vendor-master-page">
    <?php
    echo view("includes/master_data_page_header", [
        "title" => app_lang("vendor_sub_categories"),
        "subtitle" => "Organize detailed vendor sub-categories under each primary category.",
        "icon" => "folder-plus",
        "breadcrumbs" => [
            ["label" => app_lang("master_data")],
            ["label" => app_lang("vendor_sub_categories")]
        ],
        "actions" => $vendor_page_actions
    ]);
    ?>

    <div class="card pod-vendor-card pod-master-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendor_sub_categories'); ?></h2>
                <p class="pod-vendor-card-subtitle">Keep detailed vendor specialty groupings clear and easy to maintain.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
        <div class="table-responsive pod-vendor-table-shell">
            <table id="vendor-sub-categories-table" class="display" width="100%"></table>
        </div>
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
