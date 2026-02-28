<div id="page-content" class="page-wrapper clearfix gp-pro-page gp-pro-scan-page">
<div class="page-title clearfix gp-pro-title">
    <h1><?php echo app_lang("gate_pass_security_inbox"); ?> - Scan QR</h1>
</div>

<div class="card mb15 gp-pro-card gp-pro-scan-shell">
    <div class="p15">
        <div class="row">
            <div class="col-md-6">
                <label><strong>QR Text / Token</strong></label>
                <div class="input-group">
                    <input id="qr_text" class="form-control" placeholder="Scan or paste token here..." />
                    <span class="input-group-btn">
                        <button id="btn_lookup" class="btn btn-primary gp-pro-btn gp-pro-btn-icon">
                            <i data-feather="search" class="icon-16"></i> Lookup
                        </button>
                    </span>
                </div>
                <div class="text-off mt5 font-12">
                    Tip: with a handheld scanner, click the input then scan (it types the token).
                </div>
            </div>

            <div class="col-md-6">
                <label><strong>Security Action</strong></label>
                <div class="row">
                    <div class="col-md-4">
                        <select id="scan_action" class="form-control">
                            <option value="entry">Entry</option>
                            <option value="exit">Exit</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input id="scan_note" class="form-control" placeholder="Optional note..." />
                    </div>
                </div>
                <div class="mt10">
                    <button id="btn_save_action" class="btn btn-success gp-pro-btn-success gp-pro-btn-icon" disabled>
                        <i data-feather="check-circle" class="icon-16"></i> Save Action
                    </button>
                </div>
            </div>
        </div>

        <hr class="mt15 mb15">

        <div id="scan_info" style="display:none;">
            <div class="alert alert-info mb15 gp-pro-inline-alert">
                <div class="clearfix">
                    <div class="pull-left">
                        <strong id="info_title">Gate Pass</strong>
                        <div class="text-off" id="info_sub"></div>
                    </div>
                    <div class="pull-right">
                        <span class="badge bg-primary" id="info_status"></span>
                        <span class="badge bg-success" id="info_waived" style="display:none;">Waived</span>
                    </div>
                </div>
            </div>

            <div class="alert alert-danger mb15 gp-pro-inline-alert" id="gp-blocked-alert" style="display:none;">
                <strong>Blocked Visitor Alert:</strong>
                <span id="gp-blocked-alert-text"></span>
            </div>

            <div class="row">
                <div class="col-md-3"><div class="card p10 gp-pro-stat-card"><div class="text-off">Gate Pass No</div><div id="gp_no" class="font-16"><strong>-</strong></div></div></div>
                <div class="col-md-3"><div class="card p10 gp-pro-stat-card"><div class="text-off">Reference</div><div id="gp_ref" class="font-16"><strong>-</strong></div></div></div>
                <div class="col-md-3"><div class="card p10 gp-pro-stat-card"><div class="text-off">Valid From</div><div id="gp_valid_from" class="font-16"><strong>-</strong></div></div></div>
                <div class="col-md-3"><div class="card p10 gp-pro-stat-card"><div class="text-off">Valid To</div><div id="gp_valid_to" class="font-16"><strong>-</strong></div></div></div>
            </div>

            <div class="row mt10">
                <div class="col-md-4"><div class="card p10 gp-pro-stat-card"><div class="text-off">Company</div><div id="gp_company" class="font-16"><strong>-</strong></div></div></div>
                <div class="col-md-4"><div class="card p10 gp-pro-stat-card"><div class="text-off">Department</div><div id="gp_department" class="font-16"><strong>-</strong></div></div></div>
                <div class="col-md-4"><div class="card p10 gp-pro-stat-card"><div class="text-off">Fee</div><div id="gp_fee" class="font-16"><strong>-</strong></div></div></div>
            </div>

            <div class="mt15">
                <?php echo modal_anchor(
                    get_uri("gate_pass_security_inbox/request_edit_modal_form"),
                    "<i data-feather='edit' class='icon-16'></i> Edit Request",
                    ["class" => "btn btn-default gp-pro-btn-secondary gp-pro-btn-icon", "title" => "Edit Request", "id" => "btn_edit_request", "data-post-request_id" => 0]
                ); ?>
            </div>
        </div>
    </div>
</div>

<div id="scan_tables" style="display:none;">
    <div class="card mb15 gp-pro-card">
        <div class="p15 clearfix gp-pro-section-head">
            <h4 class="pull-left mt0 mb0"><?php echo app_lang("visitors"); ?></h4>
            <div class="pull-right">
                <?php echo modal_anchor(
                    get_uri("gate_pass_security_inbox/visitor_modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> Add Visitor",
                    ["class" => "btn btn-primary btn-sm gp-pro-btn gp-pro-btn-icon", "title" => "Add Visitor", "id" => "btn_add_visitor", "data-post-gate_pass_request_id" => 0]
                ); ?>
            </div>
        </div>
        <div class="table-responsive gp-pro-table-shell">
            <table id="gp-scan-visitors-table" class="display" width="100%"></table>
        </div>
    </div>

    <div class="card gp-pro-card">
        <div class="p15 clearfix gp-pro-section-head">
            <h4 class="pull-left mt0 mb0"><?php echo app_lang("vehicles"); ?></h4>
            <div class="pull-right">
                <?php echo modal_anchor(
                    get_uri("gate_pass_security_inbox/vehicle_modal_form"),
                    "<i data-feather='plus-circle' class='icon-16'></i> Add Vehicle",
                    ["class" => "btn btn-primary btn-sm gp-pro-btn gp-pro-btn-icon", "title" => "Add Vehicle", "id" => "btn_add_vehicle", "data-post-gate_pass_request_id" => 0]
                ); ?>
            </div>
        </div>
        <div class="table-responsive gp-pro-table-shell">
            <table id="gp-scan-vehicles-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    let currentRequestId = 0;
    let currentGatePassId = 0;

    const lookupUrl = "<?php echo get_uri('gate_pass_security_inbox/lookup_by_qr'); ?>";
    const actionUrl = "<?php echo get_uri('gate_pass_security_inbox/save_scan_action'); ?>";

    function resetTable($t){
        if ($.fn.DataTable.isDataTable($t)) {
            $t.DataTable().clear().destroy();
            $t.empty();
        }
    }

    function initTables(requestId){
        $("#btn_add_visitor").attr("data-post-gate_pass_request_id", requestId);
        $("#btn_add_vehicle").attr("data-post-gate_pass_request_id", requestId);
        $("#btn_edit_request").attr("data-post-request_id", requestId);

        const $v = $("#gp-scan-visitors-table");
        const $c = $("#gp-scan-vehicles-table");

        resetTable($v);
        resetTable($c);

        $v.appTable({
            source: "<?php echo_uri('gate_pass_security_inbox/visitors_list_data'); ?>/" + requestId,
            columns: [
                { title: "<?php echo app_lang('full_name'); ?>" },
                { title: "<?php echo app_lang('id_type'); ?>" },
                { title: "<?php echo app_lang('id_number'); ?>" },
                { title: "<?php echo app_lang('nationality'); ?>" },
                { title: "<?php echo app_lang('phone'); ?>" },
                { title: "<?php echo app_lang('role'); ?>" },
                { title: "<?php echo app_lang('blocked'); ?>" },
                { title: "<?php echo app_lang('reason'); ?>" },
                { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w120" }
            ]
        });

        $c.appTable({
            source: "<?php echo_uri('gate_pass_security_inbox/vehicles_list_data'); ?>/" + requestId,
            columns: [
                { title: "<?php echo app_lang('plate_no'); ?>" },
                { title: "Type" },
                { title: "<?php echo app_lang('make'); ?>" },
                { title: "<?php echo app_lang('model'); ?>" },
                { title: "<?php echo app_lang('color'); ?>" },
                { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w120" }
            ]
        });

        if (typeof feather !== "undefined") feather.replace();
    }

    function fillInfo(d){
        $("#scan_info").show();
        $("#scan_tables").show();
        $("#scan_info, #scan_tables").addClass("gp-pro-animated-in");
        setTimeout(function () {
            $("#scan_info, #scan_tables").removeClass("gp-pro-animated-in");
        }, 450);

        $("#gp_no").text(d.gate_pass_no || "-");
        $("#gp_ref").text(d.reference || "-");
        $("#gp_valid_from").text(d.valid_from || "-");
        $("#gp_valid_to").text(d.valid_to || "-");

        $("#gp_company").text(d.company || "-");
        $("#gp_department").text(d.department || "-");
        $("#gp_fee").text((d.currency || "") + " " + (d.fee_amount || "0.000"));

        $("#info_status").text(d.status_label || d.status || "-");
        if (parseInt(d.fee_is_waived || 0) === 1) $("#info_waived").show(); else $("#info_waived").hide();

        const blockedCount = parseInt(d.blocked_visitors_count || 0);
        if (blockedCount > 0) {
            const reasons = Array.isArray(d.blocked_reasons) ? d.blocked_reasons.filter(Boolean) : [];
            const reasonText = reasons.length ? (" Reason: " + reasons.join(" | ")) : "";
            $("#gp-blocked-alert-text").text(blockedCount + " blocked visitor(s)." + reasonText);
            $("#gp-blocked-alert").show().addClass("gp-pro-pulse");
        } else {
            $("#gp-blocked-alert").hide();
            $("#gp-blocked-alert-text").text("");
            $("#gp-blocked-alert").removeClass("gp-pro-pulse");
        }

        $("#btn_save_action").prop("disabled", false);
    }

    function lookup(){
        const qrText = $("#qr_text").val().trim();
        if (!qrText) {
            appAlert.error("Please scan/paste QR text first.");
            return;
        }

        appLoader.show();
        $.ajax({
            url: lookupUrl,
            type: "POST",
            dataType: "json",
            data: {
                qr_text: qrText,
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function(res){
                appLoader.hide();
                if (!res || !res.success) {
                    $("#scan_info").hide();
                    $("#scan_tables").hide();
                    $("#btn_save_action").prop("disabled", true);
                    appAlert.error(res && res.message ? res.message : "Not found");
                    return;
                }

                const d = res.data;
                currentRequestId = d.request_id;
                currentGatePassId = d.gate_pass_id;

                fillInfo(d);
                initTables(currentRequestId);

                appAlert.success("Gate pass loaded.");
            },
            error: function(){
                appLoader.hide();
                appAlert.error("Lookup failed.");
            }
        });
    }

    $("#btn_lookup").on("click", lookup);
    $("#qr_text").on("keydown", function(e){
        if (e.key === "Enter") lookup();
    });

    $("#btn_save_action").on("click", function(){
        if (!currentGatePassId) return;

        const action = $("#scan_action").val();
        const note = $("#scan_note").val();

        appLoader.show();
        $.ajax({
            url: actionUrl,
            type: "POST",
            dataType: "json",
            data: {
                gate_pass_id: currentGatePassId,
                action: action,
                note: note,
                "<?php echo csrf_token(); ?>": "<?php echo csrf_hash(); ?>"
            },
            success: function(res){
                appLoader.hide();
                if (res && res.success) {
                    appAlert.success(res.message || "Saved");
                    $("#scan_note").val("");
                } else {
                    appAlert.error(res && res.message ? res.message : "Error");
                }
            },
            error: function(){
                appLoader.hide();
                appAlert.error("Error saving action.");
            }
        });
    });

    if (typeof feather !== "undefined") feather.replace();
});
</script>
</div>
