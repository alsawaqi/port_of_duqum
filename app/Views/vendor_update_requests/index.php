<?php
// index.php (Vendor Update Requests) — Pro UI + animation
?>

<div id="page-content" class="page-wrapper clearfix vur-page">

    <style>
        /* =========================
           Vendor Update Requests – Pro UI
        ========================== */
        .vur-page {
            --vur-radius: 18px;
            --vur-shadow: 0 10px 30px rgba(16, 24, 40, .08);
            --vur-border: rgba(15, 23, 42, .08);
            --vur-text: #0f172a;
            --vur-muted: #64748b;
        }

        .vur-shell {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Entrance animation */
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

        /* Hero header */
        .vur-hero {
            padding: 22px 22px 16px;
            background:
                radial-gradient(900px 260px at 15% 0%, rgba(59, 130, 246, .20), transparent 60%),
                radial-gradient(700px 220px at 85% 35%, rgba(34, 197, 94, .16), transparent 55%),
                linear-gradient(180deg, rgba(15, 23, 42, .04), rgba(15, 23, 42, 0));
            border-bottom: 1px solid var(--vur-border);
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .vur-title h4 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -.2px;
            color: var(--vur-text);
        }

        .vur-title p {
            margin: 6px 0 0;
            font-size: 12.5px;
            color: var(--vur-muted);
        }

        .vur-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .vur-btn {
            border-radius: 12px;
            height: 40px;
            padding: 0 14px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: transform .15s ease, box-shadow .2s ease;
        }

        .vur-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 20px rgba(2, 6, 23, .08);
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
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid var(--vur-border);
            background: rgba(255, 255, 255, .6);
            font-size: 12px;
            color: #334155;
            backdrop-filter: blur(6px);
        }

        /* Table wrapper + animation */
        .vur-body {
            padding: 14px 16px 18px;
        }

        .vur-table-wrap {
            border: 1px solid var(--vur-border);
            border-radius: 16px;
            overflow: hidden;
            background: #fff;
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .5s ease, transform .5s ease;
        }

        .vur-ready .vur-table-wrap.vur-table-ready {
            opacity: 1;
            transform: translateY(0);
        }

        /* DataTables polish (safe, non-invasive) */
        .vur-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            border-bottom: 1px solid var(--vur-border) !important;
            color: #0f172a;
            font-weight: 800;
            font-size: 12.5px;
        }

        .vur-table-wrap table.dataTable tbody td {
            font-size: 13px;
            color: #0f172a;
            vertical-align: middle;
        }

        .vur-table-wrap table.dataTable tbody tr:hover {
            background: rgba(59, 130, 246, .04);
        }

        /* Search + pagination spacing */
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, .14);
            height: 38px;
            padding: 0 10px;
            outline: none;
        }

        .dataTables_wrapper .dataTables_length select {
            border-radius: 12px;
            border: 1px solid rgba(15, 23, 42, .14);
            height: 38px;
        }

        /* Micro shake for errors (optional use) */
        .vur-shake {
            animation: vurShake .28s ease-in-out 0s 2;
        }

        @keyframes vurShake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-6px);
            }

            75% {
                transform: translateX(6px);
            }
        }
    </style>

    <div class="vur-shell">
        <div class="card vur-card">

            <div class="vur-hero">
                <div class="vur-title">
                    <h4><?php echo app_lang("vendor_update_requests"); ?></h4>
                    <p>Review submitted change requests, filter by vendor/module, and take action quickly.</p>

                    <div class="vur-badges">
                        <span class="vur-badge"><i data-feather="shield" class="icon-16"></i> Audit-friendly</span>
                        <span class="vur-badge"><i data-feather="filter" class="icon-16"></i> Fast filtering</span>
                        <span class="vur-badge"><i data-feather="clock" class="icon-16"></i> Real-time list</span>
                    </div>
                </div>

                <div class="vur-actions">
                    <?php if (!empty($can_view_vendor_update_requests_by_vendor)) { ?>
                        <?php echo anchor(
                            get_uri("vendor_update_requests/vendors"),
                            "<i data-feather='layers' class='icon-16'></i><span>Group by Vendor</span>",
                            ["class" => "btn btn-outline-primary vur-btn"]
                        ); ?>
                    <?php } ?>

                    <button type="button" id="vur-refresh" class="btn btn-default vur-btn">
                        <i data-feather="refresh-cw" class="icon-16"></i>
                        <span>Refresh</span>
                    </button>
                </div>
            </div>

            <div class="vur-body">
                <div class="table-responsive vur-table-wrap">
                    <table id="vendor-update-requests-table" class="display" width="100%"></table>
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

        // Init table
        $("#vendor-update-requests-table").appTable({
            source: "<?php echo_uri('vendor_update_requests/list_data'); ?>",
            order: [
                [0, "desc"]
            ],
            columns: [{
                    title: "<?php echo app_lang('id'); ?>"
                },
                {
                    title: "<?php echo app_lang('vendor'); ?>"
                },
                {
                    title: "<?php echo app_lang('module'); ?>"
                },
                {
                    title: "<?php echo app_lang('action'); ?>"
                },
                {
                    title: "<?php echo app_lang('requested_by'); ?>"
                },
                {
                    title: "<?php echo app_lang('date'); ?>"
                },
                {
                    title: "<?php echo app_lang('status'); ?>",
                    class: "text-center w10p"
                },
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    class: "text-center option w100"
                }
            ]
        });

        // Reveal table once DataTables is ready
        $("#vendor-update-requests-table").on("init.dt", function() {
            $(".vur-table-wrap").addClass("vur-table-ready");
            if (window.feather) feather.replace();
        });

        // Re-render icons after every draw (pagination/filter)
        $("#vendor-update-requests-table").on("draw.dt", function() {
            if (window.feather) feather.replace();
        });

        // Refresh button (safe)
        $("#vur-refresh").on("click", function() {
            try {
                var dt = $("#vendor-update-requests-table").DataTable();
                dt.ajax.reload(null, false);
            } catch (e) {
                // If DataTables instance isn't available for any reason, just re-init icons.
                if (window.feather) feather.replace();
            }
        });

        if (window.feather) feather.replace();
    });
</script>