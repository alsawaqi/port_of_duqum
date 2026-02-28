<div class="vp-branches p15">
    <style>
        /* =========================
           Vendor Portal – Branches
        ========================== */
        .vp-branches {
            --vbz-radius: 16px;
            --vbz-border: rgba(15, 23, 42, .08);
            --vbz-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --vbz-muted: #64748b;
            --vbz-title: #0f172a;
        }

        .vbz-shell {
            border-radius: var(--vbz-radius);
            border: 1px solid var(--vbz-border);
            background: #ffffff;
            box-shadow: var(--vbz-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;

            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }

        .vp-branches-ready .vbz-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .vbz-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(720px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }

        .vbz-inner {
            position: relative;
            z-index: 2;
        }

        .vbz-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .vbz-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vbz-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--vbz-title);
            letter-spacing: -.2px;
        }

        .vbz-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--vbz-muted);
        }

        .vbz-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(56, 189, 248, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0284c7;
        }

        .vbz-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .vbz-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }

        .vbz-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }

        /* Banners */
        .vbz-banner {
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: flex-start;
            border: 1px solid transparent;
            background: #f8fafc;
            color: #0f172a;
            animation: vbzSlideIn .4s ease forwards;
        }

        .vbz-banner-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vbz-banner-content {
            flex: 1;
        }

        .vbz-banner-title {
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 700;
        }

        .vbz-banner-text {
            margin: 0;
            font-size: 12px;
        }

        .vbz-banner-note {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .vbz-banner-warning {
            border-color: rgba(245, 158, 11, .45);
            background: rgba(245, 158, 11, .06);
        }

        .vbz-banner-warning .vbz-banner-icon {
            background: rgba(245, 158, 11, .16);
            color: #b45309;
        }

        .vbz-banner-info {
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .05);
        }

        .vbz-banner-info .vbz-banner-icon {
            background: rgba(59, 130, 246, .12);
            color: #1d4ed8;
        }

        @keyframes vbzSlideIn {
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
        .vbz-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .vbz-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            color: var(--vbz-muted);
        }

        .vbz-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid var(--vbz-border);
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(6px);
        }

        .vbz-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .vbz-pill-dot.primary {
            background: #22c55e;
        }

        .vbz-pill-dot.pending {
            background: #f59e0b;
        }

        .vbz-pill-dot.inactive {
            background: #94a3b8;
        }

        /* Table polish */
        .vbz-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--vbz-border);
            overflow: hidden;
            background: #ffffff;
        }

        .vbz-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--vbz-border);
        }

        .vbz-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .vbz-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }
    </style>

    <div class="vbz-shell">
        <div class="vbz-inner">

            <?php if (!empty($review_request) && !empty($review_request->review_comment)) { ?>
                <div class="vbz-banner vbz-banner-info">
                    <div class="vbz-banner-icon">
                        <i data-feather="message-square" class="icon-16"></i>
                    </div>
                    <div class="vbz-banner-content">
                        <p class="vbz-banner-title">Admin review</p>
                        <p class="vbz-banner-text mb0">
                            <?php echo nl2br(esc($review_request->review_comment)); ?>
                        </p>
                        <div class="vbz-banner-note">
                            Please update your branches and save again to re-submit for approval.
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($is_locked)) { ?>
                <div class="vbz-banner vbz-banner-warning">
                    <div class="vbz-banner-icon">
                        <i data-feather="lock" class="icon-16"></i>
                    </div>
                    <div class="vbz-banner-content">
                        <p class="vbz-banner-title"><?php echo app_lang("pending_review"); ?></p>
                        <p class="vbz-banner-text mb0">
                            You can still <strong>add new branches</strong>, but
                            <strong>edit/delete</strong> are disabled until approval.
                        </p>
                    </div>
                </div>
            <?php } ?>

            <div class="vbz-header">
                <div class="vbz-header-title">
                    <div class="vbz-header-icon">
                        <i data-feather="map-pin" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("branches"); ?></h4>
                        <p class="vbz-header-sub">
                            Manage all vendor locations and primary branches.
                        </p>
                    </div>
                </div>

                <div class="vbz-add-btn">
                    <?php echo modal_anchor(
                        get_uri("vendor_portal/branch_modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_branch'),
                        ["class" => "btn btn-default", "title" => app_lang('add_branch')]
                    ); ?>
                </div>
            </div>

            <div class="vbz-toolbar">
                <div class="vbz-legend">
                    <span class="vbz-pill">
                        <span class="vbz-pill-dot primary"></span>
                        <span>Primary branch</span>
                    </span>
                    <span class="vbz-pill">
                        <span class="vbz-pill-dot pending"></span>
                        <span>Pending update</span>
                    </span>
                    <span class="vbz-pill">
                        <span class="vbz-pill-dot inactive"></span>
                        <span>Inactive / closed</span>
                    </span>
                </div>
            </div>

            <div class="vbz-table-wrap">
                <div class="table-responsive mb0">
                    <table id="vendor-branches-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        // Entrance animation
        setTimeout(function() {
            $(".vp-branches").addClass("vp-branches-ready");
            if (window.feather) feather.replace();
        }, 40);

        $("#vendor-branches-table").appTable({
            source: '<?php echo_uri("vendor_portal/branches_list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("name"); ?>'
                },
                {
                    title: '<?php echo app_lang("email"); ?>'
                },
                {
                    title: '<?php echo app_lang("phone"); ?>'
                },
                {
                    title: '<?php echo app_lang("country"); ?>'
                },
                {
                    title: '<?php echo app_lang("region"); ?>'
                },
                {
                    title: '<?php echo app_lang("city"); ?>'
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
                    title: '<?php echo app_lang("status"); ?>',
                    "class": "text-center w10p"
                },
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
            ],
            onDrawCallback: function() {
                // Row micro animation
                $("#vendor-branches-table tbody tr").each(function(idx, row) {
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