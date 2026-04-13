<div id="page-content" class="page-wrapper clearfix">
<div class="gp-rop-inbox p15">
    <style>
        .gp-rop-inbox {
            --gpro-radius: 16px;
            --gpro-border: rgba(15, 23, 42, .08);
            --gpro-shadow: 0 14px 40px rgba(15, 23, 42, .10);
            --gpro-muted: #64748b;
            --gpro-title: #0f172a;
        }
        .gpro-shell {
            border-radius: var(--gpro-radius);
            border: 1px solid var(--gpro-border);
            background: #ffffff;
            box-shadow: var(--gpro-shadow);
            padding: 18px 18px 14px;
            position: relative;
            overflow: hidden;
            opacity: 1;
            transform: none;
        }
        .gpro-shell::before {
            content: "";
            position: absolute;
            inset: -40%;
            background:
                radial-gradient(700px 220px at 0% 0%, rgba(59, 130, 246, .06), transparent 55%),
                radial-gradient(520px 200px at 100% 0%, rgba(34, 197, 94, .05), transparent 55%);
            opacity: 0.85;
            pointer-events: none;
        }
        .gpro-inner { position: relative; z-index: 2; }
        .gpro-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 14px;
        }
        .gpro-header-title { display: flex; align-items: center; gap: 8px; }
        .gpro-header-title h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: var(--gpro-title);
            letter-spacing: -.2px;
        }
        .gpro-header-sub { margin: 0; font-size: 12px; color: var(--gpro-muted); }
        .gpro-header-icon {
            width: 26px;
            height: 26px;
            border-radius: 999px;
            background: rgba(59, 130, 246, .12);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1d4ed8;
        }
        .gpro-table-wrap {
            border-radius: 14px;
            border: 1px solid var(--gpro-border);
            overflow: hidden;
            background: #ffffff;
        }
        .gpro-table-wrap table.dataTable thead th {
            background: rgba(15, 23, 42, .03);
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid var(--gpro-border);
        }
        .gpro-table-wrap table.dataTable tbody tr {
            transition: background-color .16s ease, transform .12s ease;
        }
        .gpro-table-wrap table.dataTable tbody tr:hover {
            background-color: rgba(15, 23, 42, .02);
            transform: translateY(-1px);
        }

        .gpro-table-wrap .btn {
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
        .gpro-table-wrap .btn-default {
            background: #e2e8f0;
            border-color: #94a3b8;
            color: #334155;
        }
        .gpro-table-wrap .btn-default:hover {
            background: #cbd5e1;
            border-color: #64748b;
            color: #1e293b;
        }
        .gpro-table-wrap .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }
        .gpro-table-wrap .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
            color: #fff;
        }
        .gpro-table-wrap .btn-warning {
            background: #d97706;
            border-color: #d97706;
            color: #fff;
        }
        .gpro-table-wrap .btn-warning:hover {
            background: #b45309;
            border-color: #b45309;
            color: #fff;
        }
        .gpro-table-wrap table.dataTable thead th.gp-rop-col-options,
        .gpro-table-wrap table.dataTable tbody td.gp-rop-col-options {
            vertical-align: middle;
        }
        .gpro-table-wrap .gp-rop-action-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
        }
        .gpro-table-wrap .gp-rop-action-btns .btn {
            margin: 0;
        }
    </style>

    <div class="gpro-shell">
        <div class="gpro-inner">
            <?php $kpis = $kpis ?? []; ?>
            <?php echo view("gate_pass_includes/dashboard_kpis_widget", ["kpis" => $kpis]); ?>
            <div class="mb15">
                <a class="btn btn-default btn-sm" href="<?php echo get_uri("gate_pass_rop_inbox/export_list_csv"); ?>">
                    <i data-feather="download" class="icon-16"></i> <?php echo app_lang("gate_pass_export_csv"); ?>
                </a>
            </div>
            <div class="gpro-header">
                <div class="gpro-header-title">
                    <div class="gpro-header-icon">
                        <i data-feather="check-circle" class="icon-16"></i>
                    </div>
                    <div>
                        <h4><?php echo app_lang("gate_pass_rop_requests"); ?></h4>
                        <p class="gpro-header-sub"><?php echo app_lang("gate_pass_rop_inbox_subtitle"); ?></p>
                    </div>
                </div>
            </div>
            <div class="gpro-table-wrap">
                <div class="table-responsive mb0">
                    <table id="gate-pass-rop-inbox-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof feather !== "undefined") feather.replace();
    var $t = $("#gate-pass-rop-inbox-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    $t.appTable({
        source: "<?php echo_uri('gate_pass_rop_inbox/list_data'); ?>",
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('reference'); ?>" },
            { title: "<?php echo app_lang('created_at'); ?>" },
            { title: "<?php echo app_lang('company'); ?>" },
            { title: "<?php echo app_lang('department'); ?>" },
            { title: "<?php echo app_lang('requester'); ?>" },
            { title: "<?php echo app_lang('phone'); ?>" },
            { title: "<?php echo app_lang('visit_from'); ?>" },
            { title: "<?php echo app_lang('visit_to'); ?>" },
            { title: "<?php echo app_lang('status'); ?>" },
            { title: "<?php echo app_lang('options'); ?>", class: "text-end option w300 gp-rop-col-options" }
        ],
        order: [[1, "desc"]],
        onDrawCallback: function() {
            if (window.feather) feather.replace();
        }
    });
});
</script>
</div>
</div>
