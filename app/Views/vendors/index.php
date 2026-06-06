<?php
$vendor_page_actions = "";
if (!empty($can_create_vendors)) {
    $vendor_page_actions = modal_anchor(
        get_uri("vendors/modal_form"),
        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_vendor'),
        array("class" => "btn btn-primary pod-action-btn", "title" => app_lang('add_vendor'))
    );
}
?>

 <div id="page-content" class="page-wrapper clearfix pod-page-shell pod-vendor-page pod-vendor-directory-page">
    <style>
        .pod-vendor-directory-page .pod-vendor-table-shell {
            overflow-x: auto;
        }

        .pod-vendor-directory-page #vendors-table {
            min-width: 1120px;
            table-layout: auto;
        }

        .pod-vendor-directory-page #vendors-table th,
        .pod-vendor-directory-page #vendors-table td {
            vertical-align: middle;
        }

        .pod-vendor-directory-page #vendors-table .pod-vendor-grade-col {
            min-width: 190px;
        }

        .pod-vendor-directory-page #vendors-table .pod-vendor-status-col {
            min-width: 150px;
            width: 150px;
        }

        .pod-vendor-directory-page #vendors-table .pod-vendor-actions-col {
            min-width: 82px;
            width: 82px;
        }

        .pod-vendor-directory-page .js-vendor-grade,
        .pod-vendor-directory-page .js-vendor-status {
            width: 100%;
            height: 40px;
            border-radius: 10px;
            border-color: rgba(102, 112, 133, .24);
            color: #344054;
            font-size: 12px;
            font-weight: 800;
            line-height: 18px;
            box-shadow: 0 1px 2px rgba(16, 24, 40, .05);
        }

        .pod-vendor-directory-page .js-vendor-grade {
            min-width: 190px;
        }

        .pod-vendor-directory-page .js-vendor-status {
            min-width: 142px;
            text-transform: none;
        }

        .pod-vendor-directory-page .js-vendor-grade:focus,
        .pod-vendor-directory-page .js-vendor-status:focus {
            border-color: #465fff;
            box-shadow: 0 0 0 4px rgba(70, 95, 255, .12);
        }

        .pod-vendor-directory-page .pod-vendor-status-badge {
            display: inline-flex;
            min-width: 108px;
            justify-content: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-weight: 800;
            text-transform: capitalize;
        }

        body.color-1E202D:not(.pod-auth-page) .pod-vendor-directory-page .js-vendor-grade,
        body:is(.color-1d2632, .color-2e4053, .color-404040, .color-555a61):not(.pod-auth-page) .pod-vendor-directory-page .js-vendor-grade,
        body.color-1E202D:not(.pod-auth-page) .pod-vendor-directory-page .js-vendor-status,
        body:is(.color-1d2632, .color-2e4053, .color-404040, .color-555a61):not(.pod-auth-page) .pod-vendor-directory-page .js-vendor-status {
            border-color: var(--pod-topbar-border);
            background-color: rgba(255, 255, 255, .04);
            color: #d0d5dd;
        }

        @media (max-width: 1199px) {
            .pod-vendor-directory-page #vendors-table {
                min-width: 1040px;
            }
        }

        @media (max-width: 767px) {
            .pod-vendor-directory-page .pod-vendor-table-shell {
                margin: 0 -4px;
                padding-bottom: 4px;
            }

            .pod-vendor-directory-page #vendors-table {
                min-width: 980px;
            }

            .pod-vendor-directory-page #vendors-table .pod-vendor-status-col {
                min-width: 138px;
                width: 138px;
            }

            .pod-vendor-directory-page .js-vendor-status {
                min-width: 130px;
                height: 38px;
                font-size: 11.5px;
            }
        }
    </style>

    <?php
    echo view("includes/pod_page_header", [
        "title" => app_lang("vendors"),
        "subtitle" => "Manage supplier profiles, grades, locations, and operational status from one place.",
        "icon" => "briefcase",
        "breadcrumbs" => [
            ["label" => app_lang("vendors")]
        ],
        "actions" => $vendor_page_actions
    ]);
    ?>

     <div class="card pod-vendor-card">
        <div class="pod-vendor-card-header">
            <div>
                <h2 class="pod-vendor-card-title"><?php echo app_lang('vendors'); ?></h2>
                <p class="pod-vendor-card-subtitle">Review vendor records, update status, and keep registration data current.</p>
            </div>
        </div>

        <div class="pod-vendor-card-body">
         <div class="table-responsive pod-vendor-table-shell">
             <table id="vendors-table" class="display" cellspacing="0" width="100%"></table>
         </div>
        </div>
     </div>
 </div>

 <script>
     $(document).ready(function() {
         var vendorBlockModalUrl = "<?php echo get_uri('vendors/block_modal_form'); ?>";

         var $table = $("#vendors-table").appTable({
             source: '<?php echo_uri("vendors/list_data") ?>',
             columns: [{
                     title: '<?php echo app_lang("vendor_groups"); ?>',
                     "class": "pod-vendor-group-col"
                 },
                 {
                     title: '<?php echo app_lang("vendor_grade"); ?>',
                     "class": "pod-vendor-grade-col"
                 },
                 {
                     title: '<?php echo app_lang("vendor_name"); ?>',
                     "class": "pod-vendor-name-col"
                 },
                 {
                     title: '<?php echo app_lang("email"); ?>',
                     "class": "pod-vendor-email-col"
                 },
                 {
                     title: '<?php echo app_lang("location"); ?>',
                     "class": "pod-vendor-location-col"
                 },
                 {
                     title: '<?php echo app_lang("status"); ?>',
                     "class": "pod-vendor-status-col"
                 }
                <?php if (!empty($can_view_vendors) || !empty($can_update_vendors) || !empty($can_delete_vendors)) { ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option pod-vendor-actions-col"
                }
                <?php } ?>
             ],
             // important: re-bind events after redraw
             onDrawCallback: function() {
                 feather.replace();
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

         $(document).on("change", ".js-vendor-grade", function() {
             var id = $(this).data("id");
             var gradeId = $(this).val();

             $.ajax({
                 url: "<?php echo get_uri('vendors/update_grade'); ?>",
                 type: "POST",
                 dataType: "json",
                 data: {
                     id: id,
                     vendor_grade_id: gradeId,
                     "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
                 },
                 success: function(res) {
                     if (res && res.success) {
                         appAlert.success(res.message || "Saved", {
                             duration: 2000
                         });
                         $("#vendors-table").appTable({
                             reload: true
                         });
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

         $(document).on("click", ".js-vendor-unblock", function() {
             var id = $(this).data("id");

             $.ajax({
                 url: "<?php echo get_uri('vendors/unblock'); ?>",
                 type: "POST",
                 dataType: "json",
                 data: {
                     id: id,
                     "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
                 },
                 success: function(res) {
                     if (res && res.success) {
                         appAlert.success(res.message || "Saved", {
                             duration: 2000
                         });
                         $("#vendors-table").appTable({
                             reload: true
                         });
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
