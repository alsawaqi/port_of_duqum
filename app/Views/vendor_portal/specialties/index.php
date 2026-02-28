<div class="vp-specialties p15">
    <style>
        /* =========================
           Vendor Portal – Specialties
        ========================== */
        .vp-specialties {
            --vsp-radius: 16px;
            --vsp-border: rgba(15, 23, 42, .08);
            --vsp-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --vsp-muted: #64748b;
            --vsp-title: #0f172a;
        }

        .vsp-shell {
            border-radius: var(--vsp-radius);
            border: 1px solid var(--vsp-border);
            background: #ffffff;
            box-shadow: var(--vsp-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;

            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }

        .vp-specialties-ready .vsp-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .vsp-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(720px 220px at 0% 0%, rgba(59, 130, 246, .05), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }

        .vsp-inner {
            position: relative;
            z-index: 2;
        }

        .vsp-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .vsp-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vsp-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--vsp-title);
            letter-spacing: -.2px;
        }

        .vsp-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--vsp-muted);
        }

        .vsp-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(56, 189, 248, .14);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0369a1;
        }

        .vsp-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .vsp-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }

        .vsp-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }

        /* Banners */
        .vsp-banner {
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: flex-start;
            border: 1px solid transparent;
            background: #f8fafc;
            color: #0f172a;
            animation: vspSlideIn .4s ease forwards;
        }

        .vsp-banner-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vsp-banner-content {
            flex: 1;
        }

        .vsp-banner-title {
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 700;
        }

        .vsp-banner-text {
            margin: 0;
            font-size: 12px;
        }

        .vsp-banner-note {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .vsp-banner-warning {
            border-color: rgba(245, 158, 11, .45);
            background: rgba(245, 158, 11, .06);
        }

        .vsp-banner-warning .vsp-banner-icon {
            background: rgba(245, 158, 11, .16);
            color: #b45309;
        }

        .vsp-banner-info {
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .05);
        }

        .vsp-banner-info .vsp-banner-icon {
            background: rgba(59, 130, 246, .12);
            color: #1d4ed8;
        }

        @keyframes vspSlideIn {
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
        .vsp-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .vsp-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            color: var(--vsp-muted);
        }

        .vsp-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid var(--vsp-border);
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(6px);
        }

        .vsp-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .vsp-pill-dot.approved {
            background: #22c55e;
        }

        .vsp-pill-dot.pending {
            background: #f59e0b;
        }

        .vsp-pill-dot.rejected {
            background: #ef4444;
        }

        /* Table polish */
        .vsp-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--vsp-border);
            overflow: hidden;
            background: #ffffff;
        }

        .vsp-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--vsp-border);
        }

        .vsp-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .vsp-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }
    </style>

    <div class="vsp-shell">
        <div class="vsp-inner">

            <?php if (!empty($review_request) && !empty($review_request->review_comment)) { ?>
                <div class="vsp-banner vsp-banner-info">
                    <div class="vsp-banner-icon">
                        <i data-feather="message-square" class="icon-16"></i>
                    </div>
                    <div class="vsp-banner-content">
                        <p class="vsp-banner-title">Admin review</p>
                        <p class="vsp-banner-text mb0">
                            <?php echo nl2br(esc($review_request->review_comment)); ?>
                        </p>
                        <div class="vsp-banner-note">
                            Please update your specialties and save again to re-submit for approval.
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($is_locked)) { ?>
                <div class="vsp-banner vsp-banner-warning">
                    <div class="vsp-banner-icon">
                        <i data-feather="alert-triangle" class="icon-16"></i>
                    </div>
                    <div class="vsp-banner-content">
                        <p class="vsp-banner-title"><?php echo app_lang("pending_review"); ?></p>
                        <p class="vsp-banner-text mb0">
                            Editing is locked until the admin reviews your request.
                            You can still add new specialties if allowed by configuration.
                        </p>
                    </div>
                </div>
            <?php } ?>

            <div class="vsp-header">
                <div class="vsp-header-title">
                    <div class="vsp-header-icon">
                        <i data-feather="grid" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("specialties"); ?></h4>
                        <p class="vsp-header-sub">
                            Define the services, categories, and domains your company is specialized in.
                        </p>
                    </div>
                </div>

                <div class="vsp-add-btn">
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_portal/specialty_modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add'),
                        ["class" => "btn btn-default", "title" => app_lang('add')]
                    );
                    ?>
                </div>
            </div>

            <div class="vsp-toolbar">
                <div class="vsp-legend">
                    <span class="vsp-pill">
                        <span class="vsp-pill-dot approved"></span>
                        <span>Approved</span>
                    </span>
                    <span class="vsp-pill">
                        <span class="vsp-pill-dot pending"></span>
                        <span>Pending</span>
                    </span>
                    <span class="vsp-pill">
                        <span class="vsp-pill-dot rejected"></span>
                        <span>Rejected</span>
                    </span>
                </div>
            </div>

            <div class="vsp-table-wrap">
                <div class="table-responsive mb0">
                    <table id="vendor-specialties-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        // Entrance animation
        setTimeout(function() {
            $(".vp-specialties").addClass("vp-specialties-ready");
            if (window.feather) feather.replace();
        }, 40);

        $("#vendor-specialties-table").appTable({
            source: '<?php echo_uri("vendor_portal/specialties_list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("type"); ?>'
                },
                {
                    title: '<?php echo app_lang("category"); ?>'
                },
                {
                    title: '<?php echo app_lang("sub_category"); ?>'
                },
                {
                    title: '<?php echo app_lang("name"); ?>'
                },
                {
                    title: '<?php echo app_lang("description"); ?>'
                },
                {
                    title: 'Approval',
                    "class": "text-center w10p"
                },
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
            ],
            onDrawCallback: function() {
                // Row micro animation
                $("#vendor-specialties-table tbody tr").each(function(idx, row) {
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