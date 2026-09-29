<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-gate-pass-page">
    <?php echo view("includes/gate_pass_page_header", [
        "title" => app_lang("gate_pass_blocked_visitors"),
        "subtitle" => app_lang("gate_pass_blocked_visitors_hint"),
        "icon" => "slash",
        "breadcrumbs" => [
            ["label" => "Gate Pass"],
            ["label" => app_lang("gate_pass_blocked_visitors")]
        ]
    ]); ?>
    <div class="gp-blocked-visitors p15">
        <style>
            .gp-blocked-visitors {
                --gp-blocked-line: rgba(15, 23, 42, .08);
                --gp-blocked-muted: #64748b;
                --gp-blocked-ink: #0f172a;
            }
            .gp-blocked-shell {
                border: 1px solid var(--gp-blocked-line);
                border-radius: 14px;
                background: #fff;
                box-shadow: 0 14px 38px rgba(15, 23, 42, .08);
                overflow: hidden;
            }
            .gp-blocked-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 14px;
                padding: 18px 20px;
                border-bottom: 1px solid var(--gp-blocked-line);
                background: linear-gradient(180deg, rgba(248, 250, 252, .96), #fff);
            }
            .gp-blocked-title {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .gp-blocked-title-icon {
                width: 34px;
                height: 34px;
                border-radius: 10px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                color: #b91c1c;
                background: rgba(220, 38, 38, .10);
                border: 1px solid rgba(220, 38, 38, .16);
            }
            .gp-blocked-title h1 {
                margin: 0;
                font-size: 18px;
                line-height: 1.25;
                font-weight: 800;
                color: var(--gp-blocked-ink);
            }
            .gp-blocked-subtitle {
                margin-top: 3px;
                color: var(--gp-blocked-muted);
                font-size: 12px;
            }
            .gp-blocked-table-wrap {
                padding: 14px;
            }
            .gp-blocked-table-wrap table.dataTable thead th {
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: .04em;
                color: var(--gp-blocked-muted);
                background: rgba(248, 250, 252, .95) !important;
            }
            .gp-blocked-actions {
                display: flex;
                flex-wrap: wrap;
                justify-content: flex-end;
                gap: 6px;
            }
            .gp-blocked-actions .btn {
                border-radius: 8px;
                font-size: 12px;
                font-weight: 650;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                margin: 0;
            }
            @media (max-width: 767px) {
                .gp-blocked-header {
                    flex-direction: column;
                }
                .gp-blocked-header .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>

        <div class="gp-blocked-shell">
            <div class="gp-blocked-header">
                <div class="gp-blocked-title">
                    <span class="gp-blocked-title-icon"><i data-feather="slash" class="icon-18"></i></span>
                    <div>
                        <h1><?php echo app_lang("gate_pass_blocked_visitors"); ?></h1>
                        <div class="gp-blocked-subtitle"><?php echo app_lang("gate_pass_blocked_visitors_hint"); ?></div>
                    </div>
                </div>
                <div class="title-button-group">
                    <?php if (!empty($can_create_blocked_visitors)) { ?>
                    <?php echo modal_anchor(
                        get_uri("gate_pass_blocked_visitors/modal_form"),
                        "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gate_pass_add_blocked_visitor"),
                        ["class" => "btn btn-primary", "title" => app_lang("gate_pass_add_blocked_visitor")]
                    ); ?>
                    <?php } ?>
                </div>
            </div>

            <div class="gp-blocked-table-wrap table-responsive">
                <table id="gate-pass-blocked-visitors-table" class="display" cellspacing="0" width="100%"></table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var $table = $("#gate-pass-blocked-visitors-table");
    if ($.fn.DataTable.isDataTable($table)) {
        $table.DataTable().clear().destroy();
        $table.empty();
    }

    $table.appTable({
        source: "<?php echo_uri('gate_pass_blocked_visitors/list_data'); ?>",
        displayLength: 25,
        columnShowHideOption: false,
        columns: [
            { title: "<?php echo app_lang('status'); ?>", class: "text-center w100" },
            { title: "<?php echo app_lang('id_number'); ?>" },
            { title: "<?php echo app_lang('id_type'); ?>" },
            { title: "<?php echo app_lang('full_name'); ?>" },
            { title: "<?php echo app_lang('nationality'); ?>" },
            { title: "<?php echo app_lang('reason'); ?>" },
            { title: "<?php echo app_lang('gate_pass_blocked_at'); ?>" },
            { title: "<?php echo app_lang('blocked_by'); ?>" },
            { title: "<?php echo app_lang('gate_pass_unblocked_at'); ?>" },
            { title: "<?php echo app_lang('gate_pass_unblocked_by'); ?>" },
            { title: "<?php echo app_lang('actions'); ?>", class: "text-end option w260" }
        ],
        order: [[6, "desc"]],
        onDrawCallback: function () {
            if (window.feather) {
                feather.replace();
            }
        }
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
