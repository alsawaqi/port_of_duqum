<div id="page-content" class="page-wrapper clearfix vur-page">
    <style>
        /* =========================
           Vendor Update Requests – Pro UI (Grouped)
           (same style as index)
        ========================== */
        .vur-page {
            --vur-radius: 18px;
            --vur-shadow: 0 10px 30px rgba(16, 24, 40, .08);
            --vur-border: rgba(15, 23, 42, .08);
        }

        .vur-shell {
            max-width: 1200px;
            margin: 0 auto;
        }

        .vur-card {
            border: 1px solid var(--vur-border);
            border-radius: var(--vur-radius);
            box-shadow: var(--vur-shadow);
            overflow: hidden;
            background: #fff;
            transform: translateY(12px);
            opacity: 0;
            transition: transform .6s ease, opacity .6s ease;
        }

        .vur-ready .vur-card {
            transform: translateY(0);
            opacity: 1;
        }

        .vur-hero {
            padding: 22px 26px 16px;
            background:
                radial-gradient(900px 260px at 20% 0%, rgba(59, 130, 246, .20), transparent 60%),
                radial-gradient(700px 220px at 85% 30%, rgba(34, 197, 94, .18), transparent 55%),
                linear-gradient(180deg, rgba(15, 23, 42, .04), rgba(15, 23, 42, 0));
            border-bottom: 1px solid var(--vur-border);
        }

        .vur-hero-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .vur-hero h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -.2px;
            color: #0f172a;
        }

        .vur-hero p {
            margin: 6px 0 0;
            color: #475569;
            font-size: 13px;
        }

        .vur-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .vur-btn {
            border-radius: 12px !important;
            height: 40px;
            padding: 0 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(15, 23, 42, .12) !important;
            background: rgba(255, 255, 255, .75) !important;
            transition: transform .15s ease, box-shadow .2s ease;
        }

        .vur-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(16, 24, 40, .10);
        }

        .vur-badges {
            margin-top: 12px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .vur-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 999px;
            border: 1px solid var(--vur-border);
            background: rgba(255, 255, 255, .6);
            font-size: 12px;
            color: #334155;
            backdrop-filter: blur(6px);
        }

        .vur-body {
            padding: 18px 26px 24px;
        }

        .vur-table {
            border: 1px solid rgba(15, 23, 42, .08);
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
        }

        /* DataTables polish */
        .vur-page .dataTables_wrapper .dataTables_filter input,
        .vur-page .dataTables_wrapper .dataTables_length select {
            border-radius: 12px !important;
            border: 1px solid rgba(15, 23, 42, .14) !important;
            height: 38px;
            outline: none;
            box-shadow: none;
        }

        .vur-page table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            border-bottom: 1px solid rgba(15, 23, 42, .08) !important;
            color: #0f172a;
            font-weight: 800;
        }

        .vur-page table.dataTable tbody td {
            border-top: 1px solid rgba(15, 23, 42, .06) !important;
        }

        .vur-page .dataTables_wrapper .dataTables_paginate .paginate_button {
            border-radius: 10px !important;
            border: 1px solid rgba(15, 23, 42, .10) !important;
            margin: 0 2px;
        }
    </style>

    <div class="vur-shell">
        <div class="card vur-card">
            <div class="vur-hero">
                <div class="vur-hero-top">
                    <div>
                        <h1>Vendor Update Requests (Grouped)</h1>
                        <p>View totals per vendor to prioritize reviews faster.</p>
                    </div>

                    <div class="vur-actions">
                        <?php echo anchor(
                            get_uri("vendor_update_requests"),
                            "<i data-feather='list' class='icon-16'></i> All Requests",
                            ["class" => "btn btn-default vur-btn"]
                        ); ?>
                    </div>
                </div>

                <div class="vur-badges">
                    <span class="vur-badge"><i data-feather="users"></i> Vendor overview</span>
                    <span class="vur-badge"><i data-feather="alert-circle"></i> Pending focus</span>
                    <span class="vur-badge"><i data-feather="bar-chart-2"></i> Totals summary</span>
                </div>
            </div>

            <div class="vur-body">
                <div class="table-responsive vur-table">
                    <table id="vur-vendors-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {

        // Entrance animation
        setTimeout(function() {
            $(".vur-page").addClass("vur-ready");
            if (window.feather) feather.replace();
        }, 60);

        $("#vur-vendors-table").appTable({
            source: '<?php echo_uri("vendor_update_requests/vendors_list_data"); ?>',
            columns: [{
                    title: '<?php echo app_lang("vendor"); ?>'
                },
                {
                    title: 'Pending',
                    "class": "text-center w10p"
                },
                {
                    title: 'Review',
                    "class": "text-center w10p"
                },
                {
                    title: 'Total',
                    "class": "text-center w10p"
                },
                {
                    title: 'Last request',
                    "class": "text-center w15p"
                }
                <?php if (!empty($can_view_vendor_update_requests_by_vendor)) { ?>
                , {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w100"
                }
                <?php } ?>
            ],
            onInitComplete: function() {
                if (window.feather) feather.replace();
            }
        });

        if (window.feather) feather.replace();
    });
</script>