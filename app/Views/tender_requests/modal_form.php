<?php echo form_open(get_uri("tender_requests/save"), ["id" => "tender-request-form", "class" => "general-form"]); ?>
<input type="hidden" name="id" value="<?php echo $model_info->id ?? ""; ?>" />

<div class="modal-body">

    <div class="form-group">
        <label>Requester Name</label>
        <input type="text" class="form-control" value="<?php echo esc($requester_display->full_name ?? ($this->login_user->first_name . ' ' . $this->login_user->last_name)); ?>" readonly>
        <small class="text-muted">Auto-filled from the logged-in Tender Requester.</small>
    </div>

    <?php if (!empty($requester_context_locked) && !empty($requester_assignment)) { ?>

        <div class="form-group">
            <label>Company <span class="text-danger">*</span></label>
            <input type="text" class="form-control" value="<?php echo esc($requester_assignment->company_name ?? '-'); ?>" readonly>
            <input type="hidden" name="company_id" id="tender-company" value="<?php echo (int) ($model_info->company_id ?? 0); ?>">
        </div>

        <div class="form-group">
            <label>Department</label>
            <input type="text" class="form-control" value="<?php echo esc($requester_assignment->department_name ?? '-'); ?>" readonly>
            <input type="hidden" name="department_id" id="tender-department" value="<?php echo (int) ($model_info->department_id ?? 0); ?>">
        </div>

    <?php } else { ?>

        <div class="form-group">
            <label>Company <span class="text-danger">*</span></label>
            <?php echo form_dropdown(
                "company_id",
                $company_dropdown ?? ["" => "- " . app_lang("select") . " -"],
                $model_info->company_id ?? "",
                "class='form-control' id='tender-company' required"
            ); ?>
        </div>

        <div class="form-group">
            <label>Department</label>
            <select name="department_id" id="tender-department" class="form-control">
                <option value="">- <?php echo app_lang("select"); ?> -</option>
            </select>
        </div>

    <?php } ?>

    <div class="form-group">
        <label>Department Manager</label>
        <input type="text" id="department-manager-name" class="form-control" readonly
               value="<?php echo esc($department_manager_assignment->full_name ?? ''); ?>">
        <small class="text-muted">Auto-filled from Tender Department Manager Users for the same department.</small>
    </div>

    <div class="form-group">
        <label>Department Manager Title</label>
        <input type="text" id="department-manager-title" class="form-control" readonly
               value="<?php echo esc($department_manager_assignment->job_title ?? 'Department Manager'); ?>">
        <small class="text-muted">Manager will approve in Department Manager Inbox after submission.</small>
    </div>

    <div class="alert alert-warning" id="department-manager-warning" style="<?php echo !empty($department_manager_assignment) ? 'display:none;' : ''; ?>">
        No Department Manager is configured for this company/department yet.
    </div>

    <div class="form-group">
        <label>Reference <span class="text-danger">*</span></label>
        <?php echo form_input(["name" => "reference", "value" => $model_info->reference ?? "", "class" => "form-control", "required" => true]); ?>
    </div>

    <div class="form-group">
        <label>Request Date</label>
        <?php echo form_input(["name" => "request_date", "type" => "date", "value" => $model_info->request_date ?? date("Y-m-d"), "class" => "form-control"]); ?>
    </div>

    <div class="form-group">
        <label>Budget Assigned (OMR) <span class="text-danger">*</span></label>
        <?php echo form_input(["name" => "budget_omr", "type" => "number", "step" => "0.001", "value" => $model_info->budget_omr ?? "0.000", "class" => "form-control", "required" => true]); ?>
        <small class="text-muted">Fee auto-calculates as Budget × 0.05%</small>
    </div>

    <div class="form-group">
        <label>Estimated Previous Amount (optional)</label>
        <?php echo form_input(["name" => "estimated_previous_amount", "type" => "number", "step" => "0.001", "value" => $model_info->estimated_previous_amount ?? "", "class" => "form-control"]); ?>
    </div>

    <div class="form-group">
        <label>Estimated Previous Notes</label>
        <?php echo form_textarea(["name" => "estimated_previous_notes", "value" => $model_info->estimated_previous_notes ?? "", "class" => "form-control"]); ?>
    </div>

    <div class="form-group">
        <label>Subject <span class="text-danger">*</span></label>
        <?php echo form_input(["name" => "subject", "value" => $model_info->subject ?? "", "class" => "form-control", "required" => true]); ?>
    </div>

    <div class="form-group">
        <label>Brief Description <span class="text-danger">*</span></label>
        <?php echo form_textarea(["name" => "brief_description", "value" => $model_info->brief_description ?? "", "class" => "form-control", "required" => true]); ?>
    </div>

    <div class="form-group">
        <label>Announcement</label>
        <?php
        echo form_dropdown(
            "announcement",
            ["local" => "Local", "international" => "International"],
            $model_info->announcement ?? "local",
            "class='form-control' required"
        );
        ?>
    </div>

    <div class="form-group">
        <label>Tender Type</label>
        <?php
        echo form_dropdown(
            "tender_type",
            ["open" => "Open", "close" => "Close"],
            $model_info->tender_type ?? "open",
            "class='form-control' required"
        );
        ?>
    </div>

    <div class="form-group" id="close-vendors-wrap" style="display:none;">
        <label>Invited Suppliers (Close Tender)</label>
        <select name="invited_vendor_ids[]" id="invited_vendor_ids" class="form-control" multiple="multiple">
            <?php if (!empty($selected_vendors)) { ?>
                <?php foreach ($selected_vendors as $v) { ?>
                    <option value="<?php echo (int) $v->id; ?>" selected="selected">
                        <?php echo esc($v->vendor_name); ?>
                    </option>
                <?php } ?>
            <?php } ?>
        </select>
        <small class="text-muted">Required when Tender Type = Close.</small>
    </div>

    <div class="form-group">
        <label>Evaluation Method</label>
        <?php
        echo form_dropdown(
            "evaluation_method",
            ["separate" => "Technical & Commercial Separate", "combined" => "Combined"],
            $model_info->evaluation_method ?? "separate",
            "class='form-control' required"
        );
        ?>
    </div>

    <div class="form-group">
        <label>Weights</label>
        <div class="row">
            <div class="col-md-6">
                <label class="text-muted">Technical Weight</label>
                <?php echo form_input(["name" => "technical_weight", "type" => "number", "value" => $model_info->technical_weight ?? 70, "class" => "form-control", "required" => true]); ?>
            </div>
            <div class="col-md-6">
                <label class="text-muted">Commercial Weight</label>
                <?php echo form_input(["name" => "commercial_weight", "type" => "number", "value" => $model_info->commercial_weight ?? 30, "class" => "form-control", "required" => true]); ?>
            </div>
        </div>
    </div>

    <hr>
    <h6>Technical Evaluation Team</h6>
    <div class="form-group">
        <select name="technical_user_ids[]" id="technical_user_ids" class="form-control" multiple="multiple">
            <?php foreach (($selected_technical_users ?? []) as $u) { ?>
                <option value="<?php echo (int) $u->id; ?>" selected="selected">
                    <?php echo esc(trim($u->full_name . (!empty($u->email) ? " (" . $u->email . ")" : ""))); ?>
                </option>
            <?php } ?>
        </select>
        <small class="text-muted">Select from Tender Technical Users.</small>
    </div>

    <h6>Commercial Evaluation Team</h6>
    <div class="form-group">
        <select name="commercial_user_ids[]" id="commercial_user_ids" class="form-control" multiple="multiple">
            <?php foreach (($selected_commercial_users ?? []) as $u) { ?>
                <option value="<?php echo (int) $u->id; ?>" selected="selected">
                    <?php echo esc(trim($u->full_name . (!empty($u->email) ? " (" . $u->email . ")" : ""))); ?>
                </option>
            <?php } ?>
        </select>
        <small class="text-muted">Select from Tender Commercial Users.</small>
    </div>

    <hr>
<h6>Tender Committee / ITT</h6>

<div class="alert alert-info">
    For final submission, choose:
    <strong>1 Chairman</strong>,
    <strong>1 Secretary</strong>,
    and <strong>at least 1 separate ITT Member</strong>.
    Chairman and Secretary will not be counted as ITT Members.
</div>

<div class="form-group">
    <label>Chairman</label>
    <select name="chairman_user_id" id="chairman_user_id" class="form-control">
        <option value="">- <?php echo app_lang("select"); ?> -</option>
        <?php if (!empty($selected_chairman)) { ?>
            <option value="<?php echo (int) $selected_chairman->id; ?>" selected="selected">
                <?php echo esc(trim($selected_chairman->full_name . (!empty($selected_chairman->email) ? " (" . $selected_chairman->email . ")" : ""))); ?>
            </option>
        <?php } ?>
    </select>
    <small class="text-muted">Select from Tender Committee Users.</small>
</div>

<div class="form-group">
    <label>Secretary</label>
    <select name="secretary_user_id" id="secretary_user_id" class="form-control">
        <option value="">- <?php echo app_lang("select"); ?> -</option>
        <?php if (!empty($selected_secretary)) { ?>
            <option value="<?php echo (int) $selected_secretary->id; ?>" selected="selected">
                <?php echo esc(trim($selected_secretary->full_name . (!empty($selected_secretary->email) ? " (" . $selected_secretary->email . ")" : ""))); ?>
            </option>
        <?php } ?>
    </select>
    <small class="text-muted">Select from Tender Committee Users.</small>
</div>

<div class="form-group">
    <label>ITT Members</label>
    <select name="itc_member_user_ids[]" id="itc_member_user_ids" class="form-control" multiple="multiple">
        <?php foreach (($selected_itc_members ?? []) as $u) { ?>
            <option value="<?php echo (int) $u->id; ?>" selected="selected">
                <?php echo esc(trim($u->full_name . (!empty($u->email) ? " (" . $u->email . ")" : ""))); ?>
            </option>
        <?php } ?>
    </select>
    <small id="committee-helper-text" class="text-muted">
        Select separate committee members only. Chairman and Secretary are selected above.
    </small>
</div>


</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang('save'); ?></button>
</div>

<?php echo form_close(); ?>

<script>
    $(document).ready(function () {

        function initStaticSelect2(selector, multiple) {
    var $el = $(selector);

    if ($el.hasClass("select2-hidden-accessible")) {
        $el.select2("destroy");
    }

    $el.select2({
        multiple: !!multiple,
        minimumInputLength: 0,
        showSearchBox: true,
        width: "100%",
        closeOnSelect: multiple ? false : true
    });
}

        function rebuildOptions(selector, items, selectedValues, includeBlank) {
            const $el = $(selector);
            const isMultiple = $el.prop("multiple");

            if (!Array.isArray(selectedValues)) {
                selectedValues = selectedValues ? [String(selectedValues)] : [];
            } else {
                selectedValues = selectedValues.map(v => String(v));
            }

            let html = "";
            if (includeBlank) {
                html += "<option value=''>- <?php echo app_lang('select'); ?> -</option>";
            }

            (items || []).forEach(function (item) {
                const id = String(item.id);
                const text = item.text || "";
                const selected = selectedValues.includes(id) ? "selected" : "";
                html += "<option value='" + id + "' " + selected + ">" + text + "</option>";
            });

            $el.html(html);

            if ($el.hasClass("select2-hidden-accessible")) {
                $el.trigger("change.select2");
            }
        }

        function fetchOptions(url, payload, callback) {
            $.ajax({
                url: url,
                type: "POST",
                dataType: "json",
                data: payload || {},
                success: function (res) {
                    callback(res || []);
                },
                error: function () {
                    callback([]);
                }
            });
        }


        function uniqueValues(values) {
            return [...new Set((values || []).map(function (v) {
                return String(v || "").trim();
            }).filter(function (v) {
                return v !== "";
            }))];
        }

        function sanitizeCommitteeMembers() {
            const chairman = String($("#chairman_user_id").val() || "");
            const secretary = String($("#secretary_user_id").val() || "");
            const current = uniqueValues($("#itc_member_user_ids").val() || []);

            const cleaned = current.filter(function (id) {
                return id !== chairman && id !== secretary;
            });

            if (JSON.stringify(current) !== JSON.stringify(cleaned)) {
                $("#itc_member_user_ids").val(cleaned).trigger("change.select2");
            }

            refreshCommitteeHelper();
        }

        function refreshCommitteeHelper() {
            const chairman = String($("#chairman_user_id").val() || "");
            const secretary = String($("#secretary_user_id").val() || "");
            const members = uniqueValues($("#itc_member_user_ids").val() || []);

            const $helper = $("#committee-helper-text");
            $helper.removeClass("text-muted text-warning text-danger");

            if (chairman && secretary && chairman === secretary) {
                $helper
                    .addClass("text-danger")
                    .text("Chairman and Secretary must be different users.");
                return;
            }

            if (!members.length) {
                $helper
                    .addClass("text-warning")
                    .text("For final submission, add at least one separate ITT Member. Chairman and Secretary are not counted as members.");
                return;
            }

            $helper
                .addClass("text-muted")
                .text("Select separate committee members only. Chairman and Secretary are selected above.");
        }

        function loadDepartments(companyId, selectedId) {
            if ($("#tender-department").is("input[type='hidden']")) {
                return;
            }

            $("#tender-department").html("<option value=''>- <?php echo app_lang('select'); ?> -</option>");
            if (!companyId) return;

            $.getJSON("<?php echo_uri('tender_requests/departments_by_company'); ?>", {company_id: companyId})
                .done(function (rows) {
                    let opts = "<option value=''>- <?php echo app_lang('select'); ?> -</option>";
                    (rows || []).forEach(function (r) {
                        const sel = (selectedId && String(selectedId) === String(r.id)) ? "selected" : "";
                        opts += "<option value='" + r.id + "' " + sel + ">" + r.text + "</option>";
                    });
                    $("#tender-department").html(opts);
                    loadDepartmentManagerContext();
                });
        }

        function loadDepartmentManagerContext() {
            const companyId = $("#tender-company").val();
            const departmentId = $("#tender-department").val();

            if (!companyId || !departmentId) {
                $("#department-manager-name").val("");
                $("#department-manager-title").val("");
                $("#department-manager-warning").show();
                return;
            }

            $.getJSON("<?php echo_uri('tender_requests/department_manager_context'); ?>", {
                company_id: companyId,
                department_id: departmentId
            }).done(function (res) {
                $("#department-manager-name").val(res.name || "");
                $("#department-manager-title").val(res.title || "Department Manager");

                if (res.user_id) {
                    $("#department-manager-warning").hide();
                } else {
                    $("#department-manager-warning").show();
                }
            });
        }

        function loadTenderPools() {
            const companyId = $("#tender-company").val();

            if (!companyId) {
                rebuildOptions("#technical_user_ids", [], [], false);
                rebuildOptions("#commercial_user_ids", [], [], false);
                rebuildOptions("#chairman_user_id", [], "", true);
                rebuildOptions("#secretary_user_id", [], "", true);
                rebuildOptions("#itc_member_user_ids", [], [], false);
                return;
            }

            const selectedTechnical = $("#technical_user_ids").val() || [];
            const selectedCommercial = $("#commercial_user_ids").val() || [];
            const selectedChairman = $("#chairman_user_id").val() || "";
            const selectedSecretary = $("#secretary_user_id").val() || "";
            const selectedIttMembers = uniqueValues($("#itc_member_user_ids").val() || []);

            fetchOptions(
                "<?php echo get_uri('tender_requests/technical_users_suggestion'); ?>",
                { company_id: companyId, q: "" },
                function (items) {
                    rebuildOptions("#technical_user_ids", items, selectedTechnical, false);
                }
            );

            fetchOptions(
                "<?php echo get_uri('tender_requests/commercial_users_suggestion'); ?>",
                { company_id: companyId, q: "" },
                function (items) {
                    rebuildOptions("#commercial_user_ids", items, selectedCommercial, false);
                }
            );

            fetchOptions(
                "<?php echo get_uri('tender_requests/committee_users_suggestion'); ?>",
                { company_id: companyId, q: "" },
                function (items) {
                    rebuildOptions("#chairman_user_id", items, selectedChairman, true);
                    rebuildOptions("#secretary_user_id", items, selectedSecretary, true);
                    rebuildOptions("#itc_member_user_ids", items, selectedIttMembers, false);
                    sanitizeCommitteeMembers();
                }
            );
        }

        const initialCompany = $("#tender-company").val();
        const initialDept = "<?php echo esc($model_info->department_id ?? ""); ?>";

        if (initialCompany) {
            loadDepartments(initialCompany, initialDept);
            loadDepartmentManagerContext();
        }

        $("#tender-company").on("change", function () {
            loadDepartments($(this).val(), "");

            // clear current selections when company changes
            $("#technical_user_ids").val([]);
            $("#commercial_user_ids").val([]);
            $("#chairman_user_id").val("");
            $("#secretary_user_id").val("");
            $("#itc_member_user_ids").val([]);

            loadTenderPools();
        });

        $("#tender-department").on("change", function () {
            loadDepartmentManagerContext();
        });

        $("#tender-request-form").appForm({
            onSubmit: function () {
                sanitizeCommitteeMembers();

                const chairman = String($("#chairman_user_id").val() || "");
                const secretary = String($("#secretary_user_id").val() || "");

                if (chairman && secretary && chairman === secretary) {
                    appAlert.error("Chairman and Secretary must be different users.");
                    return false;
                }

                return true;
            },
            onSuccess: function (result) {
                $("#tender-requests-table").appTable({newData: result.data, dataId: result.id});
            }
        });

        $("#invited_vendor_ids").select2({
            multiple: true,
            minimumInputLength: 0,
            showSearchBox: true,
            ajax: {
                url: "<?php echo get_uri('tender_requests/vendors_suggestion'); ?>",
                type: "POST",
                dataType: "json",
                quietMillis: 250,
                data: function (term, page) {
                    return { q: term || "" };
                },
                results: function (data, page) {
                    return { results: data || [] };
                }
            }
        });

        initStaticSelect2("#technical_user_ids", true);
        initStaticSelect2("#commercial_user_ids", true);
        initStaticSelect2("#chairman_user_id", false);
        initStaticSelect2("#secretary_user_id", false);
        initStaticSelect2("#itc_member_user_ids", true);

        loadTenderPools();

        $("#chairman_user_id, #secretary_user_id").on("change", function () {
            sanitizeCommitteeMembers();
        });

        $("#itc_member_user_ids").on("change", function () {
            sanitizeCommitteeMembers();
        });

        refreshCommitteeHelper();
        sanitizeCommitteeMembers();

        function toggleCloseVendors() {
            var type = $("select[name='tender_type']").val();
            if (type === "close") {
                $("#close-vendors-wrap").show();
            } else {
                $("#close-vendors-wrap").hide();
                $("#invited_vendor_ids").select2("val", "");
            }
        }

        $("select[name='tender_type']").on("change", toggleCloseVendors);
        toggleCloseVendors();
    });
</script>