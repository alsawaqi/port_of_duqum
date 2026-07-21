<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-gate-pass-page">
<?php echo view("includes/gate_pass_page_header", [
    "title" => app_lang("gate_pass_rop_requests"),
    "subtitle" => app_lang("gate_pass_rop_inbox_subtitle"),
    "icon" => "check-circle",
    "breadcrumbs" => [
        ["label" => "Gate Pass"],
        ["label" => app_lang("gate_pass_rop_requests")]
    ]
]); ?>
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
        .gpro-filter-panel {
            border: 1px solid var(--gpro-border);
            border-radius: 14px;
            background: rgba(248, 250, 252, .72);
            padding: 14px;
            margin-bottom: 14px;
        }
        .gpro-filter-panel label {
            font-size: 11px;
            font-weight: 800;
            color: var(--gpro-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 5px;
        }
        .gpro-filter-panel .form-control {
            border-radius: 10px;
            min-height: 38px;
        }
        .gpro-filter-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            padding-top: 21px;
        }
    </style>

    <div class="gpro-shell">
        <div class="gpro-inner">
            <?php $kpis = $kpis ?? []; ?>
            <?php echo view("gate_pass_includes/dashboard_kpis_widget", ["kpis" => $kpis]); ?>
            <div class="mb15">
                <a id="gp-rop-export-btn" class="btn btn-default btn-sm" href="<?php echo get_uri("gate_pass_rop_inbox/export_list_csv"); ?>">
                    <i data-feather="download" class="icon-16"></i> <?php echo app_lang("gate_pass_export_csv"); ?>
                </a>
            </div>
            <div class="gpro-filter-panel">
                <form id="gp-rop-filter-form" class="general-form">
                    <div class="row">
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("company"); ?></label>
                            <select name="company_id" id="filter_company_id" class="form-control">
                                <option value="">- <?php echo app_lang("all"); ?> -</option>
                                <?php foreach (($companies ?? []) as $c): ?>
                                    <option value="<?php echo (int)$c->id; ?>"><?php echo esc($c->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("gate_pass_concerned_department"); ?></label>
                            <select name="department_id" id="filter_department_id" class="form-control">
                                <option value="">- <?php echo app_lang("all"); ?> -</option>
                                <?php foreach (($departments ?? []) as $d): ?>
                                    <?php
                                    $department_label = trim((string)($d->name ?? ""));
                                    $department_company = trim((string)($d->company_name ?? ""));
                                    if ($department_company !== "") {
                                        $department_label .= " (" . $department_company . ")";
                                    }
                                    ?>
                                    <option value="<?php echo (int)$d->id; ?>"><?php echo esc($department_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("nationality"); ?></label>
                            <select name="nationality" id="filter_nationality" class="form-control">
                                <option value="">- <?php echo app_lang("all"); ?> -</option>
                                <?php foreach (($nationalities ?? []) as $n): ?>
                                    <?php $nationality = trim((string)($n->nationality ?? "")); ?>
                                    <?php if ($nationality !== ""): ?>
                                        <option value="<?php echo esc($nationality); ?>"><?php echo esc($nationality); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("status"); ?></label>
                            <select name="status" id="filter_status" class="form-control">
                                <option value="">- <?php echo app_lang("all"); ?> -</option>
                                <option value="security_approved"><?php echo app_lang("gate_pass_status_security_approved"); ?></option>
                                <option value="rop_approved"><?php echo app_lang("gate_pass_status_rop_approved"); ?></option>
                                <option value="issued"><?php echo app_lang("gate_pass_status_issued"); ?></option>
                                <option value="rejected"><?php echo app_lang("gate_pass_status_rejected"); ?></option>
                                <option value="returned"><?php echo app_lang("gate_pass_status_returned"); ?></option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("purpose"); ?></label>
                            <select name="gate_pass_purpose_id" id="filter_purpose_id" class="form-control">
                                <option value="">- <?php echo app_lang("all"); ?> -</option>
                                <?php foreach (($purposes ?? []) as $p): ?>
                                    <option value="<?php echo (int)$p->id; ?>"><?php echo esc($p->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("date_from"); ?></label>
                            <input type="date" name="date_from" id="filter_date_from" class="form-control">
                        </div>
                        <div class="col-md-2 col-sm-6 mb10">
                            <label><?php echo app_lang("date_to"); ?></label>
                            <input type="date" name="date_to" id="filter_date_to" class="form-control">
                        </div>
                        <div class="col-md-4 col-sm-6 mb10">
                            <div class="gpro-filter-actions">
                                <button type="button" id="gp-rop-filter-btn" class="btn btn-primary btn-sm">
                                    <i data-feather="filter" class="icon-16"></i> <?php echo app_lang("filter"); ?>
                                </button>
                                <button type="button" id="gp-rop-reset-btn" class="btn btn-default btn-sm">
                                    <?php echo app_lang("reset"); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
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
    var baseUrl = "<?php echo get_uri('gate_pass_rop_inbox/list_data'); ?>";
    var exportBaseUrl = "<?php echo get_uri('gate_pass_rop_inbox/export_list_csv'); ?>";

    function getFilterParams() {
        return {
            company_id: $("#filter_company_id").val() || "",
            department_id: $("#filter_department_id").val() || "",
            nationality: $("#filter_nationality").val() || "",
            status: $("#filter_status").val() || "",
            gate_pass_purpose_id: $("#filter_purpose_id").val() || "",
            date_from: $("#filter_date_from").val() || "",
            date_to: $("#filter_date_to").val() || ""
        };
    }

    function buildUrl(base) {
        var q = [];
        $.each(getFilterParams(), function(k, v) {
            if (v) q.push(encodeURIComponent(k) + "=" + encodeURIComponent(v));
        });
        return base + (q.length ? "?" + q.join("&") : "");
    }

    function updateExportUrl() {
        $("#gp-rop-export-btn").attr("href", buildUrl(exportBaseUrl));
    }

    function initTable() {
        var url = buildUrl(baseUrl);
        updateExportUrl();

        if ($.fn.DataTable.isDataTable($t)) {
            $t.DataTable().ajax.url(url).load();
            return;
        }

        $t.appTable({
            source: url,
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
    }

    initTable();

    $("#gp-rop-filter-btn").on("click", function() {
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });

    $("#gp-rop-reset-btn").on("click", function() {
        $("#gp-rop-filter-form")[0].reset();
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });
});
</script>
</div>
</div>
