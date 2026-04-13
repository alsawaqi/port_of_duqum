<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-sec-hub-page">
    <div class="gp-sec-hub-page-inner p15">
        <?php echo view("gate_pass_security_inbox/_hub_nav", ["active" => $security_nav_active ?? "requests"]); ?>

        <div class="gp-sec-req-shell">
            <div class="gp-sec-req-head">
                <div>
                    <h1 class="gp-sec-page-title"><?php echo app_lang("gate_pass_security_nav_requests"); ?></h1>
                    <p class="gp-sec-page-desc text-off mb0"><?php echo app_lang("gate_pass_security_requests_queue_hint"); ?></p>
                </div>
                <div class="gp-sec-req-tools">
                    <a class="btn btn-default btn-sm" href="<?php echo get_uri("gate_pass_security_inbox/export_list_csv"); ?>">
                        <i data-feather="download" class="icon-16"></i> <?php echo app_lang("gate_pass_export_csv"); ?>
                    </a>
                </div>
            </div>

            <div class="gp-sec-table-card">
                <div class="table-responsive mb0">
                    <table id="gate-pass-security-inbox-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.gp-sec-hub-page .gp-sec-hub-page-inner { max-width: 1280px; }
.gp-sec-req-shell {
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, .08);
    background: #fff;
    box-shadow: 0 14px 40px rgba(15, 23, 42, .08);
    overflow: hidden;
}
.gp-sec-req-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 20px 22px 16px;
    border-bottom: 1px solid rgba(15, 23, 42, .07);
    background: linear-gradient(180deg, rgba(248,250,252,.98), #fff);
}
.gp-sec-req-tools .btn { border-radius: 10px; font-weight: 650; }
.gp-sec-table-card { padding: 0 4px 12px; }
.gp-sec-table-card table.dataTable thead th {
    background: rgba(15, 23, 42, .03);
    font-size: 11px;
    letter-spacing: .04em;
    text-transform: uppercase;
    font-weight: 700;
    color: #64748b;
    border-bottom: 1px solid rgba(15, 23, 42, .08);
}
.gp-sec-table-card table.dataTable tbody td { vertical-align: middle; font-size: 13px; }
.gp-sec-table-card .gp-sec-view-btn {
    border-radius: 10px;
    font-weight: 650;
    padding: 6px 12px;
    border: 1px solid rgba(15, 23, 42, .12);
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.gp-sec-table-card .gp-sec-view-btn:hover {
    background: rgba(37, 99, 235, .08);
    border-color: rgba(37, 99, 235, .25);
    color: #1d4ed8;
}
.gp-sec-table-card .gp-sec-action-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: flex-end;
}
.gp-sec-table-card .gp-sec-action-btns .btn { margin: 0; }
</style>

<script type="text/javascript">
$(document).ready(function() {
    if (typeof feather !== "undefined") feather.replace();
    var $t = $("#gate-pass-security-inbox-table");
    if ($.fn.DataTable.isDataTable($t)) {
        $t.DataTable().clear().destroy();
        $t.empty();
    }
    $t.appTable({
        source: "<?php echo_uri('gate_pass_security_inbox/list_data'); ?>",
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
            { title: "<?php echo app_lang('options'); ?>", class: "text-end option w300 gp-sec-col-actions" }
        ],
        order: [[1, "desc"]],
        onDrawCallback: function() {
            if (window.feather) feather.replace();
        }
    });
});
</script>
