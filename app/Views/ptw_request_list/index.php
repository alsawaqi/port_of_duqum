<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("ptw_filter_requests"); ?></h1>
        </div>

        <div class="card-body">
            <form id="ptw-request-filter-form" class="general-form">
                <div class="row">
                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("company"); ?></label>
                        <select name="company_name" id="filter_company_name" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <?php foreach ($companies as $c): ?>
                                <option value="<?php echo esc($c->name); ?>"><?php echo esc($c->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("stage"); ?></label>
                        <select name="stage" id="filter_stage" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <option value="draft">Draft</option>
                            <option value="hsse">HSSE</option>
                            <option value="hmo">HMO</option>
                            <option value="terminal">Terminal</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("status"); ?></label>
                        <select name="status" id="filter_status" class="form-control">
                            <option value="">— <?php echo app_lang("all"); ?> —</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="revise">Revise</option>
                            <option value="rejected">Rejected</option>
                            <option value="approved">Approved</option>
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

                    <div class="col-md-2 col-sm-6">
                        <label class="form-label"><?php echo app_lang("search"); ?></label>
                        <input type="text" name="search" id="filter_search" class="form-control" placeholder="Reference / Applicant / Email / Location">
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <button type="button" id="ptw-filter-btn" class="btn btn-primary gp-pro-btn">
                            <i data-feather="filter" class="icon-16"></i> <?php echo app_lang("filter"); ?>
                        </button>
                        <button type="button" id="ptw-reset-btn" class="btn gp-pro-btn-secondary">
                            <?php echo app_lang("reset"); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive gp-pro-table-shell">
            <table id="ptw-request-list-table" class="display" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var baseUrl = "<?php echo get_uri('ptw_request_list/list_data'); ?>";

    function getFilterParams() {
        return {
            company_name: $("#filter_company_name").val() || "",
            stage: $("#filter_stage").val() || "",
            status: $("#filter_status").val() || "",
            date_from: $("#filter_date_from").val() || "",
            date_to: $("#filter_date_to").val() || "",
            search: $("#filter_search").val() || ""
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
        if ($.fn.DataTable.isDataTable("#ptw-request-list-table")) {
            $("#ptw-request-list-table").DataTable().ajax.url(url).load();
            return;
        }

        $("#ptw-request-list-table").appTable({
            source: url,
            columns: [
                { title: "<?php echo app_lang('reference'); ?>" },
                { title: "<?php echo app_lang('company'); ?>" },
                { title: "<?php echo app_lang('applicant_name'); ?>" },
                { title: "<?php echo app_lang('work_supervisor_name'); ?>" },
                { title: "<?php echo app_lang('starting_date_time'); ?>" },
                { title: "<?php echo app_lang('completion_date_time'); ?>" },
                { title: "<?php echo app_lang('status'); ?>" },
                { title: "<?php echo app_lang('stage'); ?>" },
                { title: "<i data-feather='menu' class='icon-16'></i>", class: "text-center option w120" }
            ],
            order: [[0, "desc"]]
        });
    }

    initTable();

    $("#ptw-filter-btn").on("click", function () {
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });

    $("#ptw-reset-btn").on("click", function () {
        $("#ptw-request-filter-form")[0].reset();
        initTable();
        if (typeof feather !== "undefined") feather.replace();
    });

    // Enter key in search triggers filter
    $("#filter_search").on("keydown", function(e){
        if(e.key === "Enter"){
            e.preventDefault();
            $("#ptw-filter-btn").click();
        }
    });
});
</script>