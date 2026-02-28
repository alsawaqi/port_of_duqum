 <div class="vp-documents p15">
     <style>
         /* =========================
           Vendor Portal – Documents
        ========================== */
         .vp-documents {
             --vpd-radius: 16px;
             --vpd-border: rgba(15, 23, 42, .08);
             --vpd-shadow: 0 14px 40px rgba(15, 23, 42, .10);
             --vpd-muted: #64748b;
             --vpd-title: #0f172a;
         }

         .vpd-shell {
             border-radius: var(--vpd-radius);
             border: 1px solid var(--vpd-border);
             background: #ffffff;
             box-shadow: var(--vpd-shadow);
             padding: 18px 18px 14px;
             position: relative;
             overflow: hidden;

             opacity: 0;
             transform: translateY(10px);
             transition: opacity .35s ease, transform .35s ease;
         }

         .vp-documents-ready .vpd-shell {
             opacity: 1;
             transform: translateY(0);
         }

         .vpd-shell::before {
             content: "";
             position: absolute;
             inset: -40%;
             background:
                 radial-gradient(720px 220px at 0% 0%, rgba(59, 130, 246, .05), transparent 55%),
                 radial-gradient(520px 200px at 100% 0%, rgba(45, 212, 191, .05), transparent 55%);
             opacity: 0.9;
             pointer-events: none;
         }

         .vpd-inner {
             position: relative;
             z-index: 2;
         }

         .vpd-header {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             margin-bottom: 10px;
         }

         .vpd-header-title {
             display: flex;
             align-items: center;
             gap: 8px;
         }

         .vpd-header-title h4 {
             margin: 0;
             font-size: 16px;
             font-weight: 800;
             color: var(--vpd-title);
             letter-spacing: -.2px;
         }

         .vpd-header-sub {
             margin: 0;
             font-size: 12px;
             color: var(--vpd-muted);
         }

         .vpd-header-icon {
             width: 26px;
             height: 26px;
             border-radius: 999px;
             background: rgba(56, 189, 248, .14);
             display: flex;
             align-items: center;
             justify-content: center;
             color: #0369a1;
         }

         .vpd-add-btn .btn {
             border-radius: 12px;
             font-weight: 700;
             display: inline-flex;
             align-items: center;
             gap: 6px;
             position: relative;
             overflow: hidden;
         }

         .vpd-add-btn .btn::after {
             content: "";
             position: absolute;
             inset: 0;
             background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
             transform: translateX(-100%);
             pointer-events: none;
         }

         .vpd-add-btn .btn:hover::after {
             transform: translateX(100%);
             transition: transform .55s ease;
         }

         /* Banners */
         .vpd-banner {
             border-radius: 12px;
             padding: 10px 12px;
             display: flex;
             gap: 10px;
             margin-bottom: 8px;
             align-items: flex-start;
             border: 1px solid transparent;
             background: #f8fafc;
             color: #0f172a;
             animation: vpdSlideIn .4s ease forwards;
         }

         .vpd-banner-icon {
             width: 26px;
             height: 26px;
             border-radius: 999px;
             display: flex;
             align-items: center;
             justify-content: center;
             flex-shrink: 0;
         }

         .vpd-banner-content {
             flex: 1;
         }

         .vpd-banner-title {
             margin: 0 0 2px;
             font-size: 13px;
             font-weight: 700;
         }

         .vpd-banner-text {
             margin: 0;
             font-size: 12px;
         }

         .vpd-banner-note {
             margin-top: 6px;
             font-size: 11px;
             color: #64748b;
         }

         .vpd-banner-warning {
             border-color: rgba(245, 158, 11, .45);
             background: rgba(245, 158, 11, .06);
         }

         .vpd-banner-warning .vpd-banner-icon {
             background: rgba(245, 158, 11, .16);
             color: #b45309;
         }

         .vpd-banner-info {
             border-color: rgba(59, 130, 246, .35);
             background: rgba(59, 130, 246, .05);
         }

         .vpd-banner-info .vpd-banner-icon {
             background: rgba(59, 130, 246, .12);
             color: #1d4ed8;
         }

         @keyframes vpdSlideIn {
             from {
                 opacity: 0;
                 transform: translateY(6px);
             }

             to {
                 opacity: 1;
                 transform: translateY(0);
             }
         }

         /* Toolbar / legend */
         .vpd-toolbar {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 10px;
             flex-wrap: wrap;
             margin-bottom: 10px;
         }

         .vpd-legend {
             display: flex;
             flex-wrap: wrap;
             gap: 6px;
             font-size: 11px;
             color: var(--vpd-muted);
         }

         .vpd-pill {
             display: inline-flex;
             align-items: center;
             gap: 6px;
             padding: 4px 8px;
             border-radius: 999px;
             border: 1px solid var(--vpd-border);
             background: rgba(255, 255, 255, .8);
             backdrop-filter: blur(6px);
         }

         .vpd-pill-dot {
             width: 8px;
             height: 8px;
             border-radius: 999px;
         }

         .vpd-pill-dot.approved {
             background: #22c55e;
         }

         .vpd-pill-dot.pending {
             background: #f59e0b;
         }

         .vpd-pill-dot.rejected {
             background: #ef4444;
         }

         .vpd-hint {
             font-size: 11px;
             color: var(--vpd-muted);
         }

         /* Table polish */
         .vpd-table-wrap {
             border-radius: 14px;
             border: 1px solid var(--vpd-border);
             overflow: hidden;
             background: #ffffff;
         }

         .vpd-table-wrap table.dataTable thead th {
             background: rgba(15, 23, 42, .03);
             font-size: 12px;
             color: #0f172a;
             border-bottom: 1px solid var(--vpd-border);
         }

         .vpd-table-wrap table.dataTable tbody tr {
             transition: background-color .16s ease, transform .12s ease;
         }

         .vpd-table-wrap table.dataTable tbody tr:hover {
             background-color: rgba(15, 23, 42, .02);
             transform: translateY(-1px);
         }
     </style>

     <div class="vpd-shell">
         <div class="vpd-inner">

             <?php if (!empty($review_request) && !empty($review_request->review_comment)) { ?>
                 <div class="vpd-banner vpd-banner-info">
                     <div class="vpd-banner-icon">
                         <i data-feather="message-square" class="icon-16"></i>
                     </div>
                     <div class="vpd-banner-content">
                         <p class="vpd-banner-title">Admin review</p>
                         <p class="vpd-banner-text mb0">
                             <?php echo nl2br(esc($review_request->review_comment)); ?>
                         </p>
                         <div class="vpd-banner-note">
                             Please update your documents and save again to re-submit for approval.
                         </div>
                     </div>
                 </div>
             <?php } ?>

             <?php if (!empty($is_locked)) { ?>
                 <div class="vpd-banner vpd-banner-warning">
                     <div class="vpd-banner-icon">
                         <i data-feather="alert-triangle" class="icon-16"></i>
                     </div>
                     <div class="vpd-banner-content">
                         <p class="vpd-banner-title"><?php echo app_lang("pending_review"); ?></p>
                         <p class="vpd-banner-text mb0">
                             You have a submitted update request.
                             <strong>You can still add new documents</strong>, but edit/delete is locked until admin review.
                         </p>
                     </div>
                 </div>
             <?php } ?>

             <div class="vpd-header">
                 <div class="vpd-header-title">
                     <div class="vpd-header-icon">
                         <i data-feather="file-text" class="icon-16"></i>
                     </div>
                     <div>
                         <h4><?php echo app_lang("documents"); ?></h4>
                         <p class="vpd-header-sub">
                             Upload and maintain your official company documents and certificates.
                         </p>
                     </div>
                 </div>

                 <div class="vpd-add-btn">
                     <?php
                        echo modal_anchor(
                            get_uri("vendor_portal/document_modal_form"),
                            "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_document'),
                            ["class" => "btn btn-default", "title" => app_lang('add_document')]
                        );
                        ?>
                 </div>
             </div>

             <div class="vpd-toolbar">
                 <div class="vpd-legend">
                     <span class="vpd-pill">
                         <span class="vpd-pill-dot approved"></span>
                         <span>Approved</span>
                     </span>
                     <span class="vpd-pill">
                         <span class="vpd-pill-dot pending"></span>
                         <span>Pending</span>
                     </span>
                     <span class="vpd-pill">
                         <span class="vpd-pill-dot rejected"></span>
                         <span>Rejected</span>
                     </span>
                 </div>

                 <div class="vpd-hint">
                     Keep your documents up to date to avoid interruptions in approval.
                 </div>
             </div>

             <div class="vpd-table-wrap">
                 <div class="table-responsive mb0">
                     <table id="vendor-documents-table" class="display" cellspacing="0" width="100%"></table>
                 </div>
             </div>

         </div>
     </div>
 </div>

 <script type="text/javascript">
     $(document).ready(function() {

         // Entrance animation
         setTimeout(function() {
             $(".vp-documents").addClass("vp-documents-ready");
             if (window.feather) feather.replace();
         }, 40);

         $("#vendor-documents-table").appTable({
             source: '<?php echo_uri("vendor_portal/documents_list_data"); ?>',
             columns: [{
                     title: '<?php echo app_lang("type"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("file"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("issued_date"); ?>'
                 },
                 {
                     title: '<?php echo app_lang("expiry_date"); ?>'
                 },
                 {
                     title: 'Approval',
                     "class": "text-center w10p"
                 },
                 {
                     title: '<?php echo app_lang("size"); ?>',
                     "class": "text-center w10p"
                 },
                 {
                     title: '<?php echo app_lang("uploaded_by"); ?>',
                     "class": "text-center w10p"
                 },
                 {
                     title: '<i data-feather="menu" class="icon-16"></i>',
                     "class": "text-center option w100"
                 }
             ],
             onDrawCallback: function() {
                 // Row micro animation
                 $("#vendor-documents-table tbody tr").each(function(idx, row) {
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