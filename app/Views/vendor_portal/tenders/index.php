<div class="vp-tenders p15">
    <style>
        .vp-tenders {
            --vpt-radius: 8px;
            --vpt-border: rgba(15, 23, 42, .08);
            --vpt-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --vpt-muted: #64748b;
            --vpt-title: #0f172a;
        }

        .vpt-shell {
            border-radius: var(--vpt-radius);
            border: 1px solid var(--vpt-border);
            background: #ffffff;
            box-shadow: var(--vpt-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }

        .vp-tenders-ready .vpt-shell {
            opacity: 1;
            transform: translateY(0);
        }

        .vpt-shell::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #0ea5e9;
            pointer-events: none;
        }

        .vpt-inner {
            position: relative;
            z-index: 2;
        }

        .vpt-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }

        .vpt-header-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .vpt-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(14, 165, 233, .13);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0369a1;
        }

        .vpt-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--vpt-title);
        }

        .vpt-header-sub {
            margin: 0;
            font-size: 12px;
            color: var(--vpt-muted);
        }

        .vpt-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .vpt-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            font-size: 11px;
            color: var(--vpt-muted);
        }

        .vpt-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            border: 1px solid var(--vpt-border);
            background: rgba(255, 255, 255, .82);
        }

        .vpt-pill-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
        }

        .vpt-pill-dot.open {
            background: #2563eb;
        }

        .vpt-pill-dot.match {
            background: #334155;
        }

        .vpt-pill-dot.invited {
            background: #22c55e;
        }

        .vpt-hint {
            font-size: 11px;
            color: var(--vpt-muted);
        }

        .vpt-table-wrap {
            border-radius: 8px;
            border: 1px solid var(--vpt-border);
            overflow: hidden;
            background: #ffffff;
        }

        .vpt-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--vpt-border);
        }

        .vpt-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }

        .vpt-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        .vpt-open-tender {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
    </style>

    <div class="vpt-shell">
        <div class="vpt-inner">
            <div class="vpt-header">
                <div class="vpt-header-title">
                    <div class="vpt-header-icon">
                        <i data-feather="briefcase" class="icon-16"></i>
                    </div>
                    <div>
                        <h4>Available Tenders</h4>
                        <p class="vpt-header-sub">Review issued tenders, download documents, submit bids, and track clarifications.</p>
                    </div>
                </div>
            </div>

            <div class="vpt-toolbar">
                <div class="vpt-legend">
                    <span class="vpt-pill">
                        <span class="vpt-pill-dot open"></span>
                        <span>Open for submission</span>
                    </span>
                    <span class="vpt-pill">
                        <span class="vpt-pill-dot match"></span>
                        <span>Profile matched</span>
                    </span>
                    <span class="vpt-pill">
                        <span class="vpt-pill-dot invited"></span>
                        <span>Invited or selected</span>
                    </span>
                </div>
                <div class="vpt-hint">Only active tenders matching your vendor profile are shown.</div>
            </div>

            <div class="vpt-table-wrap">
                <div class="table-responsive mb0">
                    <table id="vendor-tenders-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    setTimeout(function () {
        $(".vp-tenders").addClass("vp-tenders-ready");
        if (window.feather) {
            feather.replace();
        }
    }, 40);

    $("#vendor-tenders-table").appTable({
        source: '<?php echo_uri("vendor_portal/tenders_list_data"); ?>',
        columns: [
            {title: "Reference"},
            {title: "Title"},
            {title: "Type", class: "w10p"},
            {title: "Tender Status", class: "w10p"},
            {title: "Target Specialty"},
            {title: "Eligibility", class: "w10p"},
            {title: "Published At", class: "w15p"},
            {title: "Closing At", class: "w15p"},
            {title: "Invite Status", class: "w10p"},
            {title: '<i data-feather="menu" class="icon-16"></i>', class: "text-center option w100"}
        ],
        onDrawCallback: function () {
            $("#vendor-tenders-table tbody tr").each(function (idx, row) {
                $(row).css({
                    opacity: 0,
                    transform: "translateY(4px)"
                });
                setTimeout(function () {
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
});
</script>
