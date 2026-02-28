<div class="vp-credentials p15">
    <style>
        /* =========================
           Vendor Portal – Credentials
        ========================== */
        .vp-credentials {
            --vcr-radius: 16px;
            --vcr-border: rgba(15, 23, 42, .08);
            --vcr-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --vcr-muted: #64748b;
            --vcr-title: #0f172a;
        }

        .vcr-shell {
            border-radius: var(--vcr-radius);
            border: 1px solid var(--vcr-border);
            background: #ffffff;
            box-shadow: var(--vcr-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;

            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }

        .vp-credentials-ready .vcr-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .vcr-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(720px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }

        .vcr-inner {
            position: relative;
            z-index: 2;
        }

        .vcr-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .vcr-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vcr-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--vcr-title);
            letter-spacing: -.2px;
        }

        .vcr-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--vcr-muted);
        }

        .vcr-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(129, 140, 248, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4f46e5;
        }

        .vcr-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .vcr-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }

        .vcr-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }

        /* Banners */
        .vcr-banner {
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: flex-start;
            border: 1px solid transparent;
            background: #f8fafc;
            color: #0f172a;
            animation: vcrSlideIn .4s ease forwards;
        }

        .vcr-banner-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vcr-banner-content {
            flex: 1;
        }

        .vcr-banner-title {
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 700;
        }

        .vcr-banner-text {
            margin: 0;
            font-size: 12px;
        }

        .vcr-banner-note {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .vcr-banner-warning {
            border-color: rgba(245, 158, 11, .45);
            background: rgba(245, 158, 11, .06);
        }

        .vcr-banner-warning .vcr-banner-icon {
            background: rgba(245, 158, 11, .16);
            color: #b45309;
        }

        .vcr-banner-info {
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .05);
        }

        .vcr-banner-info .vcr-banner-icon {
            background: rgba(59, 130, 246, .12);
            color: #1d4ed8;
        }

        @keyframes vcrSlideIn {
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
        .vcr-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .vcr-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            color: var(--vcr-muted);
        }

        .vcr-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid var(--vcr-border);
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(6px);
        }

        .vcr-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .vcr-pill-dot.approved {
            background: #22c55e;
        }

        .vcr-pill-dot.pending {
            background: #f59e0b;
        }

        .vcr-pill-dot.rejected {
            background: #ef4444;
        }

        /* Table polish */
        .vcr-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--vcr-border);
            overflow: hidden;
            background: #ffffff;
        }

        .vcr-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--vcr-border);
        }

        .vcr-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .vcr-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }
    </style>

    <div class="vcr-shell">
        <div class="vcr-inner">

            <?php if (isset($review_request) && !empty($review_request->review_comment)) { ?>
                <div class="vcr-banner vcr-banner-info">
                    <div class="vcr-banner-icon">
                        <i data-feather="message-square" class="icon-16"></i>
                    </div>
                    <div class="vcr-banner-content">
                        <p class="vcr-banner-title">Admin review</p>
                        <p class="vcr-banner-text mb0">
                            <?php echo nl2br(esc($review_request->review_comment)); ?>
                        </p>
                        <div class="vcr-banner-note">
                            Please update your credentials and save again to re-submit for approval.
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($is_locked)) { ?>
                <div class="vcr-banner vcr-banner-warning">
                    <div class="vcr-banner-icon">
                        <i data-feather="lock" class="icon-16"></i>
                    </div>
                    <div class="vcr-banner-content">
                        <p class="vcr-banner-title"><?php echo app_lang("pending_review"); ?></p>
                        <p class="vcr-banner-text mb0">
                            You can still <strong>add new credentials</strong>, but
                            <strong>edit/delete</strong> are disabled until approval.
                        </p>
                    </div>
                </div>
            <?php } ?>

            <div class="vcr-header">
                <div class="vcr-header-title">
                    <div class="vcr-header-icon">
                        <i data-feather="file-text" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("credentials"); ?></h4>
                        <p class="vcr-header-sub">
                            Manage commercial registrations, licenses, and other compliance documents.
                        </p>
                    </div>
                </div>

                <div class="vcr-add-btn">
                    <?php
                    echo modal_anchor(
                        get_uri("vendor_portal/credential_modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_credential'),
                        ["class" => "btn btn-default", "title" => app_lang('add_credential')]
                    );
                    ?>
                </div>
            </div>

            <div class="vcr-toolbar">
                <div class="vcr-legend">
                    <span class="vcr-pill">
                        <span class="vcr-pill-dot approved"></span>
                        <span>Approved</span>
                    </span>
                    <span class="vcr-pill">
                        <span class="vcr-pill-dot pending"></span>
                        <span>Pending</span>
                    </span>
                    <span class="vcr-pill">
                        <span class="vcr-pill-dot rejected"></span>
                        <span>Rejected</span>
                    </span>
                </div>
            </div>

            <div class="vcr-table-wrap">
                <div class="table-responsive mb0">
                    <table id="vendor-credentials-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        // Entrance animation
        setTimeout(function() {
            $(".vp-credentials").addClass("vp-credentials-ready");
            if (window.feather) feather.replace();
        }, 40);

        $("#vendor-credentials-table").appTable({
            source: '<?php echo_uri("vendor_portal/credentials_list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("type"); ?>'
                },
                {
                    title: '<?php echo app_lang("number"); ?>'
                },
                {
                    title: '<?php echo app_lang("issue_date"); ?>'
                },
                {
                    title: '<?php echo app_lang("expiry_date"); ?>'
                },
                {
                    title: '<?php echo app_lang("notes"); ?>'
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
                $("#vendor-credentials-table tbody tr").each(function(idx, row) {
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