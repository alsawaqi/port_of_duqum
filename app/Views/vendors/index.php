 <div id="page-content" class="page-wrapper clearfix gp-pro-page">
     <div class="card gp-pro-card">
         <div class="page-title clearfix">
             <h1><?php echo app_lang('vendors'); ?></h1>

             <div class="title-button-group">
                <?php if (!empty($can_create_vendors)) { ?>
                    <?php
                    echo modal_anchor(
                        get_uri("vendors/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor'),
                        array("class" => "btn btn-primary gp-pro-btn gp-pro-btn-icon", "title" => app_lang('add_vendor'))
                    );
                    ?>
                <?php } ?>
             </div>
         </div>

         <div class="table-responsive gp-pro-table-shell">
             <table id="vendors-table" class="display" cellspacing="0" width="100%"></table>
         </div>
     </div>
 </div>

 <script>
     $(document).ready(function() {

         var $table = $("#vendors-table").appTable({
             source: '<?php echo_uri("vendors/list_data") ?>',
             columns: [{
                     title: '<?php echo app_lang("vendor_groups"); ?>',
                     "class": "w15p"
                 },
                 {
                     title: '<?php echo app_lang("vendor_name"); ?>',
                     "class": "w20p"
                 },
                 {
                     title: '<?php echo app_lang("email"); ?>',
                     "class": "w20p"
                 },
                 {
                     title: '<?php echo app_lang("location"); ?>',
                     "class": "w25p"
                 },
                 {
                     title: '<?php echo app_lang("status"); ?>',
                     "class": "w15p"
                 }
                <?php if (!empty($can_view_vendors) || !empty($can_update_vendors) || !empty($can_delete_vendors)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
             ],
             // important: re-bind events after redraw
             onDrawCallback: function() {
                 feather.replace();
                 bindStatusChange();
             }
         });

         $(document).on("change", ".js-vendor-status", function() {
             var id = $(this).data("id");
             var status = $(this).val();

             $.ajax({
                 url: "<?php echo get_uri('vendors/update_status'); ?>",
                 type: "POST",
                 dataType: "json",
                 data: {
                     id: id,
                     status: status,
                     "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>" // ✅ include if CSRF enabled
                 },
                 success: function(res) {
                     console.log(res);
                     if (res && res.success) {
                         appAlert.success(res.message || "Saved", {
                             duration: 2000
                         });
                         $("#vendors-table").appTable({
                             reload: true
                         }); // ✅ refresh to reflect
                     } else {
                         appAlert.error(res.message || "Error", {
                             duration: 3000
                         });
                     }
                 },
                 error: function(xhr) {
                     console.log(xhr.responseText);
                     appAlert.error("Request failed", {
                         duration: 3000
                     });
                 }
             });
         });


     });
 </script>