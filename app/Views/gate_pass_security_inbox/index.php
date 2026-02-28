<div id="page-content" class="page-wrapper clearfix">
<div class="gp-security-inbox p15">
    <style>
        .gp-security-inbox {
            --gpsi-radius: 16px;
            --gpsi-border: rgba(15, 23, 42, .08);
            --gpsi-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --gpsi-muted: #64748b;
            --gpsi-title: #0f172a;
        }
        .gpsi-shell {
            border-radius: var(--gpsi-radius);
            border: 1px solid var(--gpsi-border);
            background: #ffffff;
            box-shadow: var(--gpsi-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            transition: opacity .35s ease, transform .35s ease;
        }
        .gp-security-inbox-ready .gpsi-shell { opacity: 1; transform: translateY(0); }
        .gpsi-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }
        .gpsi-inner { position: relative; z-index: 2; }
        .gpsi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }
        .gpsi-header-title { display: flex; align-items: center; gap: 8px; }
        .gpsi-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--gpsi-title);
            letter-spacing: -.2px;
        }
        .gpsi-header-sub { margin: 0; font-size: 12px; color: var(--gpsi-muted); }
        .gpsi-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }
        .gpsi-add-btn .btn {
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            overflow: hidden;
        }
        .gpsi-add-btn .btn::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, .18), transparent 55%);
            transform: translateX(-100%);
            pointer-events: none;
        }
        .gpsi-add-btn .btn:hover::after {
            transform: translateX(100%);
            transition: transform .55s ease;
        }
        .gpsi-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--gpsi-border);
            overflow: hidden;
            background: #ffffff;
        }
        .gpsi-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--gpsi-border);
        }
        .gpsi-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }
        .gpsi-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        /* Action buttons – clear visibility */
        .gpsi-table-wrap .btn {
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
        .gpsi-table-wrap .btn-default {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #334155;
        }
        .gpsi-table-wrap .btn-default:hover {
            background: #cbd5e1;
            border-color: #64748b;
            color: #1e293b;
        }
        .gpsi-table-wrap .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .gpsi-table-wrap .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
    </style>

    <div class="gpsi-shell">
        <div class="gpsi-inner">
            <div class="gpsi-header">
                <div class="gpsi-header-title">
                    <div class="gpsi-header-icon">
                        <i data-feather="shield" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("gate_pass_security_requests"); ?></h4>
                        <p class="gpsi-header-sub">View and manage gate pass requests at the gate; scan QR to validate.</p>
                    </div>
                </div>
                <div class="gpsi-add-btn">
                    <a class="btn btn-default" href="<?php echo get_uri("gate_pass_security_inbox/scan"); ?>">
                        <i data-feather="camera" class="icon-16"></i> Scan QR
                    </a>
                </div>
            </div>
            <div class="gpsi-table-wrap">
                <div class="table-responsive mb0">
                    <table id="gate-pass-security-inbox-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
    </div>
</div>
</div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    var $t = $("#gate-pass-security-inbox-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    setTimeout(function() {
        $(".gp-security-inbox").addClass("gp-security-inbox-ready");
        if (window.feather) feather.replace();
    }, 40);
    $t.appTable({
        source: "<?php echo_uri('gate_pass_security_inbox/list_data'); ?>",
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
            $("#gate-pass-security-inbox-table tbody tr").each(function(idx, row) {
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
