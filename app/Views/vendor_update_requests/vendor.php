<div class="vur-page">
    <style>
        .vur-page {
            --vur-radius: 18px;
            --vur-shadow: 0 14px 40px rgba(15, 23, 42, .14);
            --vur-border: rgba(148, 163, 184, .45);
            --vur-soft: rgba(15, 23, 42, .03);
        }

        .vur-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 6px 0 0;
        }

        .vur-card {
            border-radius: var(--vur-radius);
            border: 1px solid var(--vur-border);
            box-shadow: var(--vur-shadow);
            overflow: hidden;
            background: #fff;
            transform: translateY(16px);
            opacity: 0;
            transition: transform .55s ease, opacity .55s ease, box-shadow .25s ease;
        }

        .vur-page.vur-ready .vur-card {
            transform: translateY(0);
            opacity: 1;
        }

        .vur-card:hover {
            box-shadow: 0 18px 50px rgba(15, 23, 42, .18);
        }

        .vur-header {
            padding: 22px 22px 14px;
            border-bottom: 1px solid rgba(148, 163, 184, .45);
            background:
                radial-gradient(900px 260px at 8% -10%, rgba(59, 130, 246, .20), transparent 60%),
                radial-gradient(700px 260px at 90% 0%, rgba(52, 211, 153, .22), transparent 55%),
                linear-gradient(180deg, rgba(15, 23, 42, .03), rgba(15, 23, 42, 0));
        }

        .vur-header-main h1 {
            margin: 0;
            font-size: 21px;
            font-weight: 700;
            letter-spacing: -.15px;
            color: #0f172a;
        }

        .vur-header-main .vur-kicker {
            text-transform: uppercase;
            letter-spacing: .16em;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 4px;
        }

        .vur-header-main p {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .vur-header-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            align-items: flex-end;
            font-size: 11px;
        }

        .vur-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(15, 23, 42, .04);
            border: 1px solid rgba(148, 163, 184, .55);
            color: #0f172a;
            font-size: 11px;
            font-weight: 500;
        }

        .vur-pill .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: rgba(34, 197, 94, .92);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, .25);
        }

        .vur-toolbar {
            padding: 10px 18px 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            border-bottom: 1px solid rgba(148, 163, 184, .35);
            background: linear-gradient(180deg, rgba(248, 250, 252, 1), #ffffff);
        }

        .vur-toolbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .vur-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .vur-back-link.btn {
            border-radius: 999px;
            padding-inline: 12px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .vur-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(15, 23, 42, .03);
            font-size: 11px;
            color: #64748b;
        }

        .vur-chip strong {
            color: #0f172a;
        }

        .vur-btn-primary,
        .vur-btn-danger {
            border-radius: 999px;
            height: 38px;
            padding: 0 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            position: relative;
            overflow: hidden;
            transition: transform .12s ease, box-shadow .12s ease, opacity .15s ease;
        }

        .vur-btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            box-shadow: 0 9px 18px rgba(37, 99, 235, .35);
        }

        .vur-btn-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: #fff;
            box-shadow: 0 9px 18px rgba(220, 38, 38, .35);
        }

        .vur-btn-primary:hover,
        .vur-btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, .35);
        }

        .vur-btn-primary:active,
        .vur-btn-danger:active {
            transform: translateY(0);
            box-shadow: 0 6px 12px rgba(15, 23, 42, .35);
        }

        .vur-btn-disabled,
        .vur-btn-primary:disabled,
        .vur-btn-danger:disabled {
            opacity: .55;
            box-shadow: none !important;
            cursor: not-allowed;
        }

        .vur-btn-spinner {
            width: 15px;
            height: 15px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, .3);
            border-top-color: #fff;
            animation: vurSpin .8s linear infinite;
            display: none;
        }

        .vur-btn-busy .vur-btn-spinner {
            display: inline-block;
        }

        .vur-btn-busy .vur-btn-text {
            opacity: .9;
        }

        @keyframes vurSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .vur-table-wrap {
            padding: 12px 18px 18px;
        }

        .vur-table-wrap .dataTables_wrapper .dataTables_filter input {
            border-radius: 999px;
        }

        .vur-row-selected {
            background: linear-gradient(90deg, rgba(59, 130, 246, .06), rgba(59, 130, 246, .01)) !important;
        }

        .vur-row-selected td {
            border-top-color: rgba(59, 130, 246, .30) !important;
            border-bottom-color: rgba(59, 130, 246, .24) !important;
        }

        .vur-subtitle {
            font-size: 12px;
            color: #6b7280;
        }

        /* bulk reject modal small polish */
        #bulkRejectModal .modal-content {
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .45);
            border: 1px solid rgba(148, 163, 184, .5);
        }

        #bulkRejectModal .modal-header {
            border-bottom-color: rgba(148, 163, 184, .4);
            background: radial-gradient(600px 220px at 0% 0%, rgba(248, 113, 113, .16), transparent 60%);
        }

        #bulkRejectModal .modal-title {
            font-size: 15px;
            font-weight: 700;
        }

        #bulkRejectModal textarea.form-control {
            border-radius: 12px;
        }

        .vur-badge-count {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 3px 9px;
            background: rgba(15, 23, 42, .03);
            font-size: 11px;
            color: #475569;
        }

        .vur-badge-count span {
            font-weight: 600;
            color: #111827;
        }
    </style>

    <div class="vur-shell">
        <div class="card vur-card">
            <div class="vur-header d-flex justify-content-between align-items-start">
                <div class="vur-header-main">
                    <div class="vur-kicker">Vendor update workflow</div>
                    <h1>
                        <?php echo esc($vendor_info->vendor_name); ?> — <?php echo app_lang("vendor_update_requests"); ?>
                    </h1>
                    <p>Review, approve or reject all profile changes submitted by this vendor in one place.</p>
                </div>

                <div class="vur-header-meta">
                    <div class="vur-pill">
                        <span class="dot"></span>
                        <span><?php echo app_lang("live_vendor_view"); ?></span>
                    </div>
                    <div class="vur-chip">
                        <i data-feather="user" class="icon-14"></i>
                        <span><strong>ID:</strong> <?php echo (int)$vendor_info->id; ?></span>
                    </div>
                </div>
            </div>

            <div class="vur-toolbar">
                <div class="vur-toolbar-left">
                    <?php echo anchor(
                        get_uri("vendor_update_requests/vendors"),
                        "<i data-feather='arrow-left' class='icon-16'></i> <span>" . app_lang('back') . "</span>",
                        ["class" => "btn btn-default vur-back-link"]
                    ); ?>

                    <div class="vur-chip">
                        <i data-feather="info" class="icon-14"></i>
                        <span class="vur-subtitle">
                            Select multiple pending requests and approve or reject them in bulk.
                        </span>
                    </div>
                </div>

                <div class="vur-toolbar-right">
                    <div class="vur-badge-count">
                        <i data-feather="check-square" class="icon-14"></i>
                        <span id="selected-count">0</span> <?php echo app_lang("selected"); ?>
                    </div>

                    <button id="bulk-approve-btn" class="vur-btn-primary vur-btn-disabled" disabled>
                        <span class="vur-btn-spinner"></span>
                        <span class="vur-btn-text">
                            <i data-feather="check-circle" class="icon-16"></i>
                            <?php echo app_lang("approve_selected"); ?>
                        </span>
                    </button>

                    <button id="bulk-reject-btn" class="vur-btn-danger vur-btn-disabled" disabled>
                        <span class="vur-btn-spinner"></span>
                        <span class="vur-btn-text">
                            <i data-feather="x-circle" class="icon-16"></i>
                            <?php echo app_lang("reject_selected"); ?>
                        </span>
                    </button>
                </div>
            </div>

            <div class="vur-table-wrap">
                <div class="table-responsive">
                    <table id="vur-by-vendor-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Reject Modal -->
<div class="modal fade" id="bulkRejectModal" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <form id="bulkRejectForm">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">
                            <i data-feather="x-octagon" class="icon-16 me-1"></i>
                            <?php echo app_lang("reject_selected"); ?>
                        </h5>
                        <small class="text-muted">
                            Provide a clear reason. It will be stored with all selected requests.
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="bulk_reject_ids" value="">
                    <div class="form-group mb0">
                        <label class="mb5"><?php echo app_lang("reason"); ?></label>
                        <textarea id="bulk_reject_reason" class="form-control" rows="4" required></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-bs-dismiss="modal">
                        <?php echo app_lang("cancel"); ?>
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i data-feather="x-circle" class="icon-16 me-1"></i>
                        <?php echo app_lang("reject"); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
    function getSelectedRequestIds() {
        var ids = [];
        $("#vur-by-vendor-table").find(".bulk-request-checkbox:checked").each(function() {
            ids.push($(this).val());
        });
        return ids;
    }

    function updateRowHighlight() {
        $("#vur-by-vendor-table tbody tr").each(function() {
            var $row = $(this);
            var checked = $row.find(".bulk-request-checkbox").is(":checked");
            $row.toggleClass("vur-row-selected", checked);
        });
    }

    function setBulkButtonsBusy(isBusy) {
        var $btns = $("#bulk-approve-btn, #bulk-reject-btn");
        $btns.toggleClass("vur-btn-busy", isBusy);
        $btns.prop("disabled", isBusy || $btns.hasClass("vur-btn-disabled"));
    }

    function refreshSelectedCount() {
        var count = getSelectedRequestIds().length;
        $("#selected-count").text(count);

        var disabled = count === 0;
        $("#bulk-approve-btn, #bulk-reject-btn")
            .prop("disabled", disabled)
            .toggleClass("vur-btn-disabled", disabled);

        updateRowHighlight();
    }

    function reloadVendorTable() {
        $("#vur-by-vendor-table").appTable({
            reload: true
        });
        $("#bulk-select-all").prop("checked", false);
        $("#bulk_reject_reason").val("");
        $("#bulk_reject_ids").val("");
        $("#selected-count").text("0");
        $("#bulk-approve-btn, #bulk-reject-btn")
            .prop("disabled", true)
            .addClass("vur-btn-disabled");
    }

    $(document).ready(function() {
        // entrance animation
        setTimeout(function() {
            $(".vur-page").addClass("vur-ready");
            if (window.feather) feather.replace();
        }, 60);

        $("#vur-by-vendor-table").appTable({


            source: "<?php echo_uri('vendor_update_requests/vendor_list_data/' . $vendor_id); ?>",
            columns: [{
                    title: "<input type='checkbox' class='select-all-rows' />",
                    class: "w50 text-center"
                }, // checkbox
                {
                    title: "<?php echo app_lang('module'); ?>"
                },
                {
                    title: "<?php echo app_lang('action'); ?>"
                },

                // 🆕 NEW COLUMN
                {
                    title: "<?php echo app_lang('specialties'); ?>"
                },

                {
                    title: "<?php echo app_lang('requested_by'); ?>"
                },
                {
                    title: "<?php echo app_lang('created_at'); ?>"
                },
                {
                    title: "<?php echo app_lang('status'); ?>"
                },
                {
                    title: "<i data-feather='menu' class='icon-16'></i>",
                    class: "text-center option w150"
                }
            ],
            onDrawCallback: function() {
                if (window.feather) {
                    feather.replace();
                }
                refreshSelectedCount();
            }
        });

        // Select all
        $(document).on("change", "#bulk-select-all", function() {
            var checked = $(this).is(":checked");
            $("#vur-by-vendor-table").find(".bulk-request-checkbox").prop("checked", checked);
            refreshSelectedCount();
        });

        // single checkbox
        $(document).on("change", ".bulk-request-checkbox", function() {
            refreshSelectedCount();
        });

        function confirmOrProceed($el, options, onYes) {
            if ($.fn && typeof $.fn.appConfirmation === "function") {
                $el.appConfirmation({
                    title: options.title || "Are you sure?",
                    btnConfirmLabel: options.yes || "Yes",
                    btnCancelLabel: options.no || "Cancel",
                    onConfirm: onYes
                });
                return;
            }
            if (window.confirm(options.title || "Are you sure?")) {
                onYes();
            }
        }

        // APPROVE single (row)
        $(document).on("click", ".approve-one", function(e) {
            e.preventDefault();
            e.stopPropagation();

            var id = $(this).data("id");
            if (!id) return;

            var $btn = $(this);

            confirmOrProceed($btn, {
                title: "<?php echo app_lang('are_you_sure'); ?>",
                yes: "<?php echo app_lang('yes'); ?>",
                no: "<?php echo app_lang('cancel'); ?>"
            }, function() {
                appLoader.show();
                setBulkButtonsBusy(true);

                appAjaxRequest({
                    url: "<?php echo get_uri('vendor_update_requests/approve/'); ?>" + id,
                    type: "POST",
                    dataType: "json",
                    success: function(result) {
                        appLoader.hide();
                        setBulkButtonsBusy(false);

                        if (result && result.success) {
                            appAlert.success(result.message || "Approved", {
                                duration: 10000
                            });
                            reloadVendorTable();
                        } else {
                            appAlert.error((result && result.message) ? result.message : "Approve failed");
                        }
                    },
                    error: function(xhr) {
                        appLoader.hide();
                        setBulkButtonsBusy(false);
                        appAlert.error(xhr.responseText || "Approve request failed");
                    }
                });
            });
        });

        // BULK approve
        $(document).on("click", "#bulk-approve-btn", function(e) {
            e.preventDefault();
            e.stopPropagation();

            var ids = getSelectedRequestIds();
            if (!ids.length) {
                appAlert.error("Please select at least one pending request.");
                return;
            }

            var $btn = $(this);

            confirmOrProceed($btn, {
                title: "<?php echo app_lang('are_you_sure'); ?>",
                yes: "<?php echo app_lang('yes'); ?>",
                no: "<?php echo app_lang('cancel'); ?>"
            }, function() {
                appLoader.show();
                setBulkButtonsBusy(true);

                appAjaxRequest({
                    url: "<?php echo get_uri('vendor_update_requests/bulk_approve'); ?>",
                    type: "POST",
                    dataType: "json",
                    data: {
                        ids: ids
                    },
                    success: function(result) {
                        appLoader.hide();
                        setBulkButtonsBusy(false);

                        if (result && result.success) {
                            appAlert.success(result.message || "Approved", {
                                duration: 10000
                            });
                            reloadVendorTable();
                        } else {
                            appAlert.error((result && result.message) ? result.message : "Bulk approve failed");
                        }
                    },
                    error: function(xhr) {
                        appLoader.hide();
                        setBulkButtonsBusy(false);
                        appAlert.error(xhr.responseText || "Bulk approve request failed");
                    }
                });
            });
        });

        // Reject single (row) -> uses bulk modal
        $(document).on("click", ".reject-one", function() {
            var id = $(this).data("id");
            $("#bulk_reject_ids").val(String(id));
            $("#bulk_reject_reason").val("");
            $("#bulkRejectModal").modal("show");
        });

        // Bulk reject button
        $("#bulk-reject-btn").on("click", function() {
            var ids = getSelectedRequestIds();
            if (!ids.length) {
                appAlert.error("Please select at least one pending request.");
                return;
            }
            $("#bulk_reject_ids").val(ids.join(","));
            $("#bulk_reject_reason").val("");
            $("#bulkRejectModal").modal("show");
        });

        // Bulk reject submit
        $("#bulkRejectForm").on("submit", function(e) {
            e.preventDefault();

            var idsStr = $("#bulk_reject_ids").val();
            var reason = $("#bulk_reject_reason").val().trim();

            if (!idsStr) {
                appAlert.error("No requests selected.");
                return;
            }
            if (!reason) {
                appAlert.error("Reject reason is required.");
                return;
            }

            var ids = idsStr.split(",").map(function(v) {
                return v.trim();
            }).filter(Boolean);

            appLoader.show();
            setBulkButtonsBusy(true);

            appAjaxRequest({
                url: "<?php echo get_uri('vendor_update_requests/bulk_reject'); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    ids: ids,
                    reason: reason
                },
                success: function(result) {
                    appLoader.hide();
                    setBulkButtonsBusy(false);

                    if (result.success) {
                        appAlert.success(result.message, {
                            duration: 10000
                        });
                        $("#bulkRejectModal").modal("hide");
                        reloadVendorTable();
                    } else {
                        appAlert.error(result.message || "Bulk reject failed");
                    }
                },
                error: function(xhr) {
                    appLoader.hide();
                    setBulkButtonsBusy(false);
                    appAlert.error(xhr.responseText || "Bulk reject request failed");
                }
            });
        });

        if (window.feather) {
            feather.replace();
        }
    });
</script>