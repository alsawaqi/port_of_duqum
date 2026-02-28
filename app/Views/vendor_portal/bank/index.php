<div class="vp-banks p15">
    <style>
        /* =========================
           Vendor Portal – Bank Accounts
        ========================== */
        .vp-banks {
            --vp-radius: 16px;
            --vp-border: rgba(15, 23, 42, .08);
            --vp-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --vp-muted: #64748b;
            --vp-title: #0f172a;
        }

        .vp-banks-shell {
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

        .vp-banks-ready .vp-banks-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .vp-banks-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .05), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }

        .vp-banks-inner {
            position: relative;
            z-index: 2;
        }

        .vp-banks-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .vp-banks-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vp-banks-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--vp-title);
            letter-spacing: -.2px;
        }

        .vp-banks-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--vp-muted);
        }

        .vp-banks-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }

        .vp-banks-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .vp-banks-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            color: var(--vp-muted);
        }

        .vp-banks-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid var(--vp-border);
            background: rgba(255, 255, 255, .8);
            backdrop-filter: blur(6px);
        }

        .vp-banks-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .vp-banks-pill-dot.approved {
            background: #22c55e;
        }

        .vp-banks-pill-dot.pending {
            background: #f59e0b;
        }

        .vp-banks-pill-dot.inactive {
            background: #94a3b8;
        }

        .vp-banks-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .vp-banks-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }

        .vp-banks-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }

        /* Banners (lock / review) */
        .vp-banks-banner {
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: flex-start;
            border: 1px solid transparent;
            background: #f8fafc;
            color: #0f172a;
            animation: vpBanksSlideIn .4s ease forwards;
        }

        .vp-banks-banner-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vp-banks-banner-content {
            flex: 1;
        }

        .vp-banks-banner-title {
            margin: 0 0 2px;
            font-size: 13px;
            font-weight: 700;
        }

        .vp-banks-banner-text {
            margin: 0;
            font-size: 12px;
        }

        .vp-banks-banner-note {
            margin-top: 6px;
            font-size: 11px;
            color: #64748b;
        }

        .vp-banks-banner-info {
            border-color: rgba(59, 130, 246, .35);
            background: rgba(59, 130, 246, .05);
        }

        .vp-banks-banner-info .vp-banks-banner-icon {
            background: rgba(59, 130, 246, .12);
            color: #1d4ed8;
        }

        .vp-banks-banner-warning {
            border-color: rgba(245, 158, 11, .45);
            background: rgba(245, 158, 11, .06);
        }

        .vp-banks-banner-warning .vp-banks-banner-icon {
            background: rgba(245, 158, 11, .16);
            color: #b45309;
        }

        @keyframes vpBanksSlideIn {
            from {
                opacity: 0;
                transform: translateY(6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Table styling */
        .vp-banks-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--vp-border);
            overflow: hidden;
            background: #ffffff;
        }

        .vp-banks-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--vp-border);
        }

        .vp-banks-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .vp-banks-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }
    </style>

    <div class="vp-banks-shell">
        <div class="vp-banks-inner">

            <?php if (!empty($is_locked)) { ?>
                <div class="vp-banks-banner vp-banks-banner-warning">
                    <div class="vp-banks-banner-icon">
                        <i data-feather="lock" class="icon-16"></i>
                    </div>
                    <div class="vp-banks-banner-content">
                        <p class="vp-banks-banner-title"><?php echo app_lang("pending_review"); ?></p>
                        <p class="vp-banks-banner-text mb0">
                            You have a pending approval request. You can still <strong>add</strong> new bank accounts,
                            but <strong>edit/delete</strong> is disabled until it’s reviewed.
                        </p>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($review_request) && !empty($review_request->review_comment)) { ?>
                <div class="vp-banks-banner vp-banks-banner-info">
                    <div class="vp-banks-banner-icon">
                        <i data-feather="message-circle" class="icon-16"></i>
                    </div>
                    <div class="vp-banks-banner-content">
                        <p class="vp-banks-banner-title">Admin review</p>
                        <p class="vp-banks-banner-text mb0">
                            <?php echo nl2br(esc($review_request->review_comment)); ?>
                        </p>
                        <div class="vp-banks-banner-note">
                            Update your bank details and save again to re-submit for approval.
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="vp-banks-header">
                <div class="vp-banks-header-title">
                    <div class="vp-banks-header-icon">
                        <i data-feather="credit-card" class="icon-16"></i>
                    </div>
                    <div>
                        <h4 class="mb0"><?php echo app_lang("bank_accounts"); ?></h4>
                        <p class="vp-banks-header-sub">
                            Maintain your settlement bank accounts used for vendor payments.
                        </p>
                    </div>
                </div>

                <div class="vp-banks-add-btn">
                    <?php echo modal_anchor(
                        get_uri("vendor_portal/bank_account_modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("add"),
                        ["class" => "btn btn-default", "title" => app_lang("add")]
                    ); ?>
                </div>
            </div>

            <div class="vp-banks-toolbar">
                <div class="vp-banks-legend">
                    <span class="vp-banks-pill">
                        <span class="vp-banks-pill-dot approved"></span>
                        <span>Approved account</span>
                    </span>
                    <span class="vp-banks-pill">
                        <span class="vp-banks-pill-dot pending"></span>
                        <span>Pending changes</span>
                    </span>
                    <span class="vp-banks-pill">
                        <span class="vp-banks-pill-dot inactive"></span>
                        <span>Inactive / disabled</span>
                    </span>
                </div>
            </div>

            <div class="vp-banks-table-wrap">
                <div class="table-responsive mb0">
                    <table id="bank-accounts-table" class="display" width="100%"></table>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        // Card entrance animation
        setTimeout(function() {
            $(".vp-banks").addClass("vp-banks-ready");
            if (window.feather) feather.replace();
        }, 40);

        $("#bank-accounts-table").appTable({
            source: '<?php echo_uri("vendor_portal/bank_accounts_list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("bank_name"); ?>'
                },
                {
                    title: '<?php echo app_lang("branch"); ?>'
                },
                {
                    title: '<?php echo app_lang("account_no"); ?>'
                },
                {
                    title: '<?php echo app_lang("swift_code"); ?>'
                },
                {
                    title: '<?php echo app_lang("iban"); ?>'
                },
                {
                    title: '<?php echo app_lang("letter_head"); ?>'
                },
                {
                    title: '<?php echo app_lang("status"); ?>'
                },
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
            ],
            onDrawCallback: function() {
                // Row micro-animation
                $("#bank-accounts-table tbody tr").each(function(idx, row) {
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