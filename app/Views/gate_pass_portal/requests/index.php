<div class="gp-requests p15">
    <style>
        /* =========================
           Gate Pass Portal – Requests
        ========================== */
        .gp-requests {
            --gpr-radius: 16px;
            --gpr-border: rgba(15, 23, 42, .08);
            --gpr-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --gpr-muted: #64748b;
            --gpr-title: #0f172a;
        }

        .gpr-shell {
            border-radius: var(--gpr-radius);
            border: 1px solid var(--gpr-border);
            background: #ffffff;
            box-shadow: var(--gpr-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;

            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }

        .gp-requests-ready .gpr-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .gpr-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }

        .gpr-inner {
            position: relative;
            z-index: 2;
        }

        .gpr-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .gpr-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gpr-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--gpr-title);
            letter-spacing: -.2px;
        }

        .gpr-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--gpr-muted);
        }

        .gpr-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }

        .gpr-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }

        .gpr-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }

        .gpr-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }

        /* Table polish */
        .gpr-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--gpr-border);
            overflow: hidden;
            background: #ffffff;
        }

        .gpr-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--gpr-border);
        }

        .gpr-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .gpr-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        /* Status badge & row actions (from list_data) */
        .gpr-table-wrap .gp-portal-status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            background: #e5e7eb;
            color: #374151;
        }
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-rop_approved {
            background: #d1fae5;
            color: #065f46;
        }
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-submitted,
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-department_approved,
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-commercial_approved {
            background: #dbeafe;
            color: #1e40af;
        }
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-returned {
            background: #fef3c7;
            color: #92400e;
        }
        .gpr-table-wrap .gp-portal-status-badge.gp-portal-status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        .gpr-table-wrap .gp-portal-row-actions {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .gpr-table-wrap .gp-portal-btn-details {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .gpr-table-wrap .gp-portal-btn-details:hover {
            background: #dbeafe;
            color: #1e3a8a;
        }
        .gpr-table-wrap .gp-portal-btn-edit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border-radius: 8px;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .gpr-table-wrap .gp-portal-btn-edit:hover {
            background: #e2e8f0;
            color: #1e293b;
        }
    </style>

    <div class="gpr-shell">
        <div class="gpr-inner">

            <div class="gpr-header">
                <div class="gpr-header-title">
                    <div class="gpr-header-icon">
                        <i data-feather="clipboard" class="icon-16"></i>
                    </div>
                    <div>
                        <h4>My Gate Pass Requests</h4>
                        <p class="gpr-header-sub">Track, review, and manage all your pass requests in one place.</p>
                    </div>
                </div>

                <div class="gpr-add-btn">
                    <?php echo modal_anchor(
                        get_uri("gate_pass_portal/request_modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> Create Request",
                        ["class" => "btn btn-default"]
                    ); ?>
                </div>
            </div>

            <div class="gpr-table-wrap">
                <div class="table-responsive mb0">
                    <table id="gp-requests-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>

        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {

    var $t = $("#gp-requests-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }

    setTimeout(function() {
        $(".gp-requests").addClass("gp-requests-ready");
        if (window.feather) feather.replace();
    }, 40);

    $t.appTable({
        source: '<?php echo_uri("gate_pass_portal/requests_list_data"); ?>',
        columns: [
            { title: "Reference" },
            { title: "Company" },
            { title: "Department" },
            { title: "Purpose" },
            { title: "Visit From" },
            { title: "Visit To" },
            { title: "Status", class: "text-center w10p" },
            { title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w100" }
        ],
        onDrawCallback: function() {
            $("#gp-requests-table tbody tr").each(function(idx, row) {
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
            if (window.feather) feather.replace();
        }
    });

    if (window.feather) feather.replace();
});
</script>
