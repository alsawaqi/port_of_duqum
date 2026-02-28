<div id="page-content" class="page-wrapper clearfix">
<div class="gp-commercial-inbox p15">
    <style>
        .gp-commercial-inbox {
            --gpci-radius: 16px;
            --gpci-border: rgba(15, 23, 42, .08);
            --gpci-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --gpci-muted: #64748b;
            --gpci-title: #0f172a;
        }
        .gpci-shell {
            border-radius: var(--gpci-radius);
            border: 1px solid var(--gpci-border);
            background: #ffffff;
            box-shadow: var(--gpci-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }
        .gp-commercial-inbox-ready .gpci-shell { opacity: 1; transform: translateY(0); }
        .gpci-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }
        .gpci-inner { position: relative; z-index: 2; }
        .gpci-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }
        .gpci-header-title { display: flex; align-items: center; gap: 8px; }
        .gpci-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--gpci-title);
            letter-spacing: -.2px;
        }
        .gpci-header-sub { margin: 0; font-size: 12px; color: var(--gpci-muted); }
        .gpci-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }
        .gpci-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--gpci-border);
            overflow: hidden;
            background: #ffffff;
        }
        .gpci-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--gpci-border);
        }
        .gpci-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }
        .gpci-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        /* Action buttons – clear visibility */
        .gpci-table-wrap .btn {
            font-weight: 600;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin: 0 2px;
            border: 1px solid transparent;
        }
        .gpci-table-wrap .btn-default {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #334155;
        }
        .gpci-table-wrap .btn-default:hover {
            background: #cbd5e1;
            border-color: #64748b;
            color: #1e293b;
        }
        .gpci-table-wrap .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .gpci-table-wrap .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
    </style>

    <div class="gpci-shell">
        <div class="gpci-inner">
            <div class="gpci-header">
                <div class="gpci-header-title">
                    <div class="gpci-header-icon">
                        <i data-feather="credit-card" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("gate_pass_commercial_requests"); ?></h4>
                        <p class="gpci-header-sub">Review fee and waiver for gate pass requests.</p>
                    </div>
                </div>
            </div>
            <div class="gpci-table-wrap">
                <div class="table-responsive mb0">
                    <table id="gate-pass-commercial-inbox-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var $t = $("#gate-pass-commercial-inbox-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    setTimeout(function() {
        $(".gp-commercial-inbox").addClass("gp-commercial-inbox-ready");
        if (window.feather) feather.replace();
    }, 40);
    $t.appTable({
        source: '<?php echo_uri("gate_pass_commercial_inbox/list_data"); ?>',
        columns: [
            { title: "<?php echo app_lang('reference'); ?>" },
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('department'); ?>" },
            { title: "<?php echo app_lang('requester'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('visit_from'); ?>" },
            { title: "<?php echo app_lang('visit_to'); ?>" },
            { title: "<?php echo app_lang('currency'); ?>" },
            { title: "<?php echo app_lang('fee_amount'); ?>" },
            { title: "<?php echo app_lang('waived'); ?>" },
            { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w100" }
        ],
        order: [[0, "desc"]],
        onDrawCallback: function() {
            $("#gate-pass-commercial-inbox-table tbody tr").each(function(idx, row) {
                $(row).css({ opacity: 0, transform: "translateY(4px)" });
                setTimeout(function() {
                    $(row).css({ opacity: 1, transform: "translateY(0)", transition: "opacity .18s ease, transform .18s ease" });
                }, 30 * idx);
            });
            if (window.feather) feather.replace();
        }
    });
});
</script>
