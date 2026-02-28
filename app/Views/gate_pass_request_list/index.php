<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gate_pass_filter_requests"); ?></h1>
        </div>

        <div class="card-body">
            <form id="gp-request-filter-form" class="general-form">
                <div class="row">
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("company"); ?></label>
                        <select name="company_id" id="filter_company_id" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?php echo (int)$c->id; ?>"><?php echo esc($c->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("department"); ?></label>
                        <select name="department_id" id="filter_department_id" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <?php foreach ($departments as $d): ?>
                                <option value="<?php echo (int)$d->id; ?>"><?php echo esc($d->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("status"); ?></label>
                        <select name="status" id="filter_status" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <option value="draft"><?php echo app_lang("gate_pass_status_draft"); ?></option>
                            <option value="submitted"><?php echo app_lang("gate_pass_status_submitted"); ?></option>
                            <option value="department_approved"><?php echo app_lang("gate_pass_status_department_approved"); ?></option>
                            <option value="commercial_approved"><?php echo app_lang("gate_pass_status_commercial_approved"); ?></option>
                            <option value="security_approved"><?php echo app_lang("gate_pass_status_security_approved"); ?></option>
                            <option value="rop_approved"><?php echo app_lang("gate_pass_status_rop_approved"); ?></option>
                            <option value="rejected"><?php echo app_lang("gate_pass_status_rejected"); ?></option>
                            <option value="returned"><?php echo app_lang("gate_pass_status_returned"); ?></option>
                            <option value="cancelled"><?php echo app_lang("gate_pass_status_cancelled"); ?></option>
                            <option value="issued"><?php echo app_lang("gate_pass_status_issued"); ?></option>
                            <option value="expired"><?php echo app_lang("gate_pass_status_expired"); ?></option>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("purpose"); ?></label>
                        <select name="gate_pass_purpose_id" id="filter_purpose_id" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <?php foreach ($purposes as $p): ?>
                                <option value="<?php echo (int)$p->id; ?>"><?php echo esc($p->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("date_from"); ?></label>
                        <input type="date" name="date_from" id="filter_date_from" class="form-control">
                    </div>
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("date_to"); ?></label>
                        <input type="date" name="date_to" id="filter_date_to" class="form-control">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button" id="gp-filter-btn" class="btn btn-primary">
                            <i data-feather="filter" class="icon-16"></i> <?php echo app_lang("filter"); ?>
                        </button>
                        <button type="button" id="gp-reset-btn" class="btn btn-default">
                            <?php echo app_lang("reset"); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table id="gp-request-list-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var baseUrl = "<?php echo get_uri('gate_pass_request_list/list_data'); ?>";

    function getFilterParams() {
        return {
            company_id: $("#filter_company_id").val() || "",
            department_id: $("#filter_department_id").val() || "",
            status: $("#filter_status").val() || "",
            gate_pass_purpose_id: $("#filter_purpose_id").val() || "",
            date_from: $("#filter_date_from").val() || "",
            date_to: $("#filter_date_to").val() || ""
        };
    }

    function buildListUrl() {
        var params = getFilterParams();
        var q = [];
        $.each(params, function (k, v) {
            if (v) q.push(encodeURIComponent(k) + "=" + encodeURIComponent(v));
        });
        return baseUrl + (q.length ? "?" + q.join("&") : "");
    }

    function initTable() {
        var url = buildListUrl();
        if ($.fn.DataTable.isDataTable("#gp-request-list-table")) {
            $("#gp-request-list-table").DataTable().ajax.url(url).load();
            return;
        }
        $("#gp-request-list-table").appTable({
            source: url,
            columns: [
                { title: "<?php echo app_lang('reference'); ?>" },
                { title: "<?php echo app_lang('company'); ?>" },
                { title: "<?php echo app_lang('department'); ?>" },
                { title: "<?php echo app_lang('requester'); ?>" },
                { title: "<?php echo app_lang('phone'); ?>" },
                { title: "<?php echo app_lang('purpose'); ?>" },
                { title: "<?php echo app_lang('visit_from'); ?>" },
                { title: "<?php echo app_lang('visit_to'); ?>" },
                { title: "<?php echo app_lang('status'); ?>" },
                { title: "<?php echo app_lang('stage'); ?>" },
                { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w120" }
            ],
            order: [[0, "desc"]]
        });
    }

    initTable();

    $("#gp-filter-btn").on("click", function () {
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });

    $("#gp-reset-btn").on("click", function () {
        $("#gp-request-filter-form")[0].reset();
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });
});
</script>
