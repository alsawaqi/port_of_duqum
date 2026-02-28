 <div id="page-content" class="page-wrapper clearfix">
     <div class="card">
         <div class="page-title clearfix">
             <h1><?php echo app_lang('vendor_categories'); ?></h1>

             <div class="title-button-group">
                <?php if (!empty($can_create_vendor_categories)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_categories/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor_category'),
                        array("class" => "btn btn-default", "title" => app_lang('add_vendor_category'))
                    );
                    ?>
                <?php } ?>
             </div>
         </div>

         <div class="table-responsive">
             <table id="vendor-categories-table" class="display" cellspacing="0" width="100%"></table>
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