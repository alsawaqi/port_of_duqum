<?php
$vendor_page_actions = "";
if (!empty($can_create_vendor_categories)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendor_categories/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_category'),
        array("class" => "btn btn-primary pod-action-btn", "title" => app_lang('add_vendor_category'))
    );
}
?>

 <div id="page-content" class="page-wrapper clearfix pod-page-shell pod-vendor-page pod-vendor-master-page">
    <?php
    echo view("includes/pod_page_header", [
        "title" => app_lang("vendor_categories"),
        "subtitle" => "Maintain the top-level vendor category structure used for registration and specialties.",
        "icon" => "folder",
        "breadcrumbs" => [
            ["label" => app_lang("vendors_master")],
            ["label" => app_lang("vendor_categories")]
        ],
        "actions" => $vendor_page_actions
    ]);
    ?>

     <div class="card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendor_categories'); ?></h2>
                <p class="pod-vendor-card-subtitle">Review and update the main category structure used across vendor profiles.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
         <div class="table-responsive pod-vendor-table-shell">
             <table id="vendor-categories-table" class="display" cellspacing="0" width="100%"></table>
         </div>
        </div>
     </div>
 </div>

 <script>
     $(document).ready(function() {
         $("#vendor-categories-table").appTable({
             source: '<?php echo_uri("vendor_categories/list_data") ?>',
             columns: [{
                     title: '<?php echo app_lang("name"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("code"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("status"); ?>'
                 }
                <?php if (!empty($can_update_vendor_categories) || !empty($can_delete_vendor_categories)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
             ],
             onDrawCallback: function() {
                 feather.replace();
             }
         });
     });
 </script>
