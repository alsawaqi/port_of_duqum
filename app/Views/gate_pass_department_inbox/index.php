<div id="page-content" class="page-wrapper clearfix">
<div class="gp-department-inbox p15">
    <style>
        .gp-department-inbox {
            --gpdi-radius: 16px;
            --gpdi-border: rgba(15, 23, 42, .08);
            --gpdi-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --gpdi-muted: #64748b;
            --gpdi-title: #0f172a;
        }
        .gpdi-shell {
            border-radius: var(--gpdi-radius);
            border: 1px solid var(--gpdi-border);
            background: #ffffff;
            box-shadow: var(--gpdi-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }
        .gp-department-inbox-ready .gpdi-shell { opacity: 1; transform: translateY(0); }
        .gpdi-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }
        .gpdi-inner { position: relative; z-index: 2; }
        .gpdi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }
        .gpdi-header-title { display: flex; align-items: center; gap: 8px; }
        .gpdi-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--gpdi-title);
            letter-spacing: -.2px;
        }
        .gpdi-header-sub { margin: 0; font-size: 12px; color: var(--gpdi-muted); }
        .gpdi-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }
        .gpdi-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--gpdi-border);
            overflow: hidden;
            background: #ffffff;
        }
        .gpdi-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--gpdi-border);
        }
        .gpdi-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }
        .gpdi-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        /* Action buttons – clear visibility */
        .gpdi-table-wrap .btn {
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
        .gpdi-table-wrap .btn-default {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #334155;
        }
        .gpdi-table-wrap .btn-default:hover {
            background: #cbd5e1;
            border-color: #64748b;
            color: #1e293b;
        }
        .gpdi-table-wrap .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .gpdi-table-wrap .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
    </style>

    <div class="gpdi-shell">
        <div class="gpdi-inner">
            <div class="gpdi-header">
                <div class="gpdi-header-title">
                    <div class="gpdi-header-icon">
                        <i data-feather="building" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("gate_pass_department_requests"); ?></h4>
                        <p class="gpdi-header-sub">Approve or return gate pass requests for your department.</p>
                    </div>
                </div>
            </div>
            <div class="gpdi-table-wrap">
                <div class="table-responsive mb0">
                    <table id="gate-pass-dept-requests-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
    </div>
</div>
</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var $t = $("#gate-pass-dept-requests-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    setTimeout(function() {
        $(".gp-department-inbox").addClass("gp-department-inbox-ready");
        if (window.feather) feather.replace();
    }, 40);
    $t.appTable({
        source: '<?php echo_uri("gate_pass_department_requests/list_data"); ?>',
        columns: [
            { title: "<?php echo app_lang('reference'); ?>" },
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('department'); ?>" },
            { title: "<?php echo app_lang('requester'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('visit_from'); ?>" },
            { title: "<?php echo app_lang('visit_to'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w100" }
        ],
        order: [[0, "desc"]],
        onDrawCallback: function() {
            $("#gate-pass-dept-requests-table tbody tr").each(function(idx, row) {
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
