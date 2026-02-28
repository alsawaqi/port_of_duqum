 <div class="vp-contacts p15">
     <style>
         /* =========================
           Vendor Portal – Contacts
        ========================== */
         .vp-contacts {
             --vp-radius: 16px;
             --vp-border: rgba(15, 23, 42, .08);
             --vp-shadow: 0 14px 40px rgba(15, 23, 42, .10);
             --vp-muted: #64748b;
             --vp-title: #0f172a;
         }

         .vp-contacts-shell {
             border-radius: var(--vp-radius);
             border: 1px solid var(--vp-border);
             background: #ffffff;
             box-shadow: var(--vp-shadow);
             padding: 18px 18px 14px;
             position: relative;
             overflow: hidden;

             opacity: 0;
             transform: translateY(10px);
             transition: opacity .35s ease, transform .35s ease;
         }

         .vp-contacts-ready .vp-contacts-shell {
             opacity: 1;
             transform: translateY(0);
         }

         .vp-contacts-shell::before {
             content: "";
             position: absolute;
             inset: -40%;
             background:
                 radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                 radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
             opacity: 0.85;
             pointer-events: none;
         }

         .vp-inner {
             position: relative;
             z-index: 2;
         }

         .vp-header {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             margin-bottom: 10px;
         }

         .vp-header-title {
             display: flex;
             align-items: center;
             gap: 8px;
         }

         .vp-header-title h4 {
             margin: 0;
             font-size: 16px;
             font-weight: 800;
             color: var(--vp-title);
             letter-spacing: -.2px;
         }

         .vp-header-sub {
             margin: 0;
             font-size: 12px;
             color: var(--vp-muted);
         }

         .vp-header-icon {
             width: 26px;
             height: 26px;
             border-radius: 999px;
             background: rgba(59, 130, 246, .12);
             display: flex;
             align-items: center;
             justify-content: center;
             color: #1d4ed8;
         }

         .vp-toolbar {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             flex-wrap: wrap;
             margin-bottom: 10px;
         }

         .vp-legend {
             display: flex;
             flex-wrap: wrap;
             gap: 6px;
             font-size: 11px;
             color: var(--vp-muted);
         }

         .vp-pill {
             display: inline-flex;
             align-items: center;
             gap: 6px;
             padding: 4px 8px;
             border-radius: 999px;
             border: 1px solid var(--vp-border);
             background: rgba(255, 255, 255, .8);
             backdrop-filter: blur(6px);
         }

         .vp-pill-dot {
             width: 8px;
             height: 8px;
             border-radius: 999px;
         }

         .vp-pill-dot.pending {
             background: #f59e0b;
         }

         .vp-pill-dot.approved {
             background: #22c55e;
         }

         .vp-pill-dot.inactive {
             background: #94a3b8;
         }

         .vp-add-btn .btn {
             border-radius: 12px;
             font-weight: 700;
             display: inline-flex;
             align-items: center;
             gap: 6px;
             position: relative;
             overflow: hidden;
         }

         .vp-add-btn .btn::after {
             content: "";
             position: absolute;
             inset: 0;
             background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
             transform: translateX(-100%);
             pointer-events: none;
         }

         .vp-add-btn .btn:hover::after {
             transform: translateX(100%);
             transition: transform .55s ease;
         }

         /* Review & lock banners */
         .vp-banner {
             border-radius: 12px;
             padding: 10px 12px;
             display: flex;
             gap: 10px;
             margin-bottom: 8px;
             align-items: flex-start;
             border: 1px solid transparent;
             background: #f8fafc;
             color: #0f172a;
             animation: vpSlideIn .4s ease forwards;
         }

         .vp-banner-icon {
             width: 26px;
             height: 26px;
             border-radius: 999px;
             display: flex;
             align-items: center;
             justify-content: center;
             flex-shrink: 0;
         }

         .vp-banner-content {
             flex: 1;
         }

         .vp-banner-title {
             margin: 0 0 2px;
             font-size: 13px;
             font-weight: 700;
         }

         .vp-banner-text {
             margin: 0;
             font-size: 12px;
         }

         .vp-banner-note {
             margin-top: 6px;
             font-size: 11px;
             color: #64748b;
         }

         .vp-banner-info {
             border-color: rgba(59, 130, 246, .35);
             background: rgba(59, 130, 246, .05);
         }

         .vp-banner-info .vp-banner-icon {
             background: rgba(59, 130, 246, .12);
             color: #1d4ed8;
         }

         .vp-banner-warning {
             border-color: rgba(245, 158, 11, .45);
             background: rgba(245, 158, 11, .06);
         }

         .vp-banner-warning .vp-banner-icon {
             background: rgba(245, 158, 11, .16);
             color: #b45309;
         }

         @keyframes vpSlideIn {
             from {
                 opacity: 0;
                 transform: translateY(6px);
             }

             to {
                 opacity: 1;
                 transform: translateY(0);
             }
         }

         /* Table polish */
         .vp-table-wrap {
             border-radius: 14px;
             border: 1px solid var(--vp-border);
             overflow: hidden;
             background: #ffffff;
         }

         .vp-table-wrap table.dataTable thead th {
             background: rgba(15, 23, 42, .03);
             font-size: 12px;
             color: #0f172a;
             border-bottom: 1px solid var(--vp-border);
         }

         .vp-table-wrap table.dataTable tbody tr {
             transition: background-color .16s ease, transform .12s ease;
         }

         .vp-table-wrap table.dataTable tbody tr:hover {
             background-color: rgba(15, 23, 42, .02);
             transform: translateY(-1px);
         }
     </style>

     <div class="vp-contacts-shell">
         <div class="vp-inner">

             <?php if (!empty($review_request) && !empty($review_request->review_comment)) { ?>
                 <div class="vp-banner vp-banner-info">
                     <div class="vp-banner-icon">
                         <i data-feather="message-square" class="icon-16"></i>
                     </div>
                     <div class="vp-banner-content">
                         <p class="vp-banner-title">Admin review</p>
                         <p class="vp-banner-text mb0">
                             <?php echo nl2br(esc($review_request->review_comment)); ?>
                         </p>
                         <div class="vp-banner-note">
                             Please update your contacts and save again to re-submit for approval.
                         </div>
                     </div>
                 </div>
             <?php } ?>

             <?php if (!empty($is_locked)) { ?>
                 <div class="vp-banner vp-banner-warning">
                     <div class="vp-banner-icon">
                         <i data-feather="lock" class="icon-16"></i>
                     </div>
                     <div class="vp-banner-content">
                         <p class="vp-banner-title"><?php echo app_lang("pending_review"); ?></p>
                         <p class="vp-banner-text mb0">
                             You can still <strong>add new contacts</strong>, but
                             <strong>edit/delete</strong> are disabled until approval.
                         </p>
                     </div>
                 </div>
             <?php } ?>

             <div class="vp-header">
                 <div>
                     <div class="vp-header-title">
                         <div class="vp-header-icon">
                             <i data-feather="users" class="icon-16"></i>
                         </div>
                         <div>
                             <h4><?php echo app_lang("contacts"); ?></h4>
                             <p class="vp-header-sub">Manage the primary and secondary contacts for this vendor.</p>
                         </div>
                     </div>
                 </div>

                 <div class="vp-add-btn">
                     <?php echo modal_anchor(
                            get_uri("vendor_portal/contact_modal_form"),
                            "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_contact'),
                            ["class" => "btn btn-default"]
                        ); ?>
                 </div>
             </div>

             <div class="vp-toolbar">
                 <div class="vp-legend">
                     <span class="vp-pill">
                         <span class="vp-pill-dot approved"></span>
                         <span>Approved contact</span>
                     </span>
                     <span class="vp-pill">
                         <span class="vp-pill-dot pending"></span>
                         <span>Pending update</span>
                     </span>
                     <span class="vp-pill">
                         <span class="vp-pill-dot inactive"></span>
                         <span>Inactive / non-primary</span>
                     </span>
                 </div>
             </div>

             <div class="vp-table-wrap">
                 <div class="table-responsive mb0">
                     <table id="vendor-contacts-table" class="display" cellspacing="0" width="100%"></table>
                 </div>
             </div>

         </div>
     </div>
 </div>

 <script type="text/javascript">
     $(document).ready(function() {

         // Entrance animation
         setTimeout(function() {
             $(".vp-contacts").addClass("vp-contacts-ready");
             if (window.feather) feather.replace();
         }, 40);

         $("#vendor-contacts-table").appTable({
             source: '<?php echo_uri("vendor_portal/contacts_list_data"); ?>',
             columns: [{
                     title: '<?php echo app_lang("name"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("designation"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("email"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("mobile"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("primary"); ?>',
                     "class": "text-center w10p"
                 },
                 {
                     title: 'Approval',
                     "class": "text-center w10p"
                 },
                 {
                     title: 'Active',
                     "class": "text-center w10p"
                 },
                 {
                     title: '<i data-feather="menu" class="icon-16"></i>',
                     "class": "text-center option w100"
                 }
             ],
             onDrawCallback: function() {
                 // row micro-animation + refresh icons
                 $("#vendor-contacts-table tbody tr").each(function(idx, row) {
                     $(row).css({
                         opacity: 0,
                         transform: "translateY(4px)"
                     });
                     setTimeout(function() {
                         $(row).css({
                             opacity: 1,
                             transform: "translateY(0)",
                             transition: "opacity .18s ease, transform .18s ease"
                         });
                     }, 30 * idx);
                 });

                 if (window.feather) {
                     feather.replace();
                 }
             }
         });

         if (window.feather) {
             feather.replace();
         }
     });
 </script>