<?php
$tender = $tender ?? null;
$request = $request ?? null;
$existing_team_ids = $existing_team_ids ?? ["technical" => [], "commercial" => [], "chairman" => 0, "secretary" => 0, "itc_member" => []];
$existing_required_codes = $existing_required_codes ?? [];
$bid_requirement_labels = $bid_requirement_labels ?? [];
$testing_stage_options = $testing_stage_options ?? ["" => "- Keep normal date-based flow -"];
$procurement_manager_status = (string)($tender->procurement_manager_status ?? "draft");
$can_publish_after_manager_approval = $procurement_manager_status === "approved";
$evaluation_method_value = $tender->evaluation_method ?? $request->evaluation_method ?? "separate";
$technical_weight_value = $tender->technical_weight ?? $request->technical_weight ?? 70;
$commercial_weight_value = $tender->commercial_weight ?? $request->commercial_weight ?? 30;

$dtValue = function ($value) {
    if (empty($value)) {
        return "";
    }
    return date("Y-m-d\\TH:i", strtotime($value));
};
?>

<?php echo form_open(get_uri("tender_procurement_inbox/save"), ["id" => "tender-procurement-form", "class" => "general-form", "role" => "form"]); ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
        <input type="hidden" name="tender_request_id" value="<?php echo (int) ($request->id ?? 0); ?>" />

        <?php if (!empty($request->id)) { ?>
            <div class="alert alert-info">
                This tender is linked to request <strong><?php echo esc($request->reference); ?></strong>.
                Procurement can complete the setup here and submit it for procurement manager approval.
            </div>
        <?php } else { ?>
            <div class="alert alert-info">
                This is a procurement-led tender draft. If there is an offline internal request, upload it below as a supporting tender document.
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Reference</label>
                    <?php echo form_input([
                        "name" => "reference",
                        "value" => esc($tender->reference ?? $request->reference ?? ""),
                        "class" => "form-control",
                        "data-rule-required" => true,
                    ]); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Title</label>
                    <?php echo form_input([
                        "name" => "title",
                        "value" => esc($tender->title ?? $request->subject ?? ""),
                        "class" => "form-control",
                        "data-rule-required" => true,
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Company</label>
                    <?php
                    echo form_dropdown(
                        "company_id",
                        $company_dropdown ?? ["" => "- " . app_lang("select_company") . " -"],
                        $company_id ?? "",
                        "class='form-control select2'"
                    );
                    ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Department</label>
                    <?php
                    echo form_dropdown(
                        "department_id",
                        $department_dropdown ?? ["" => "- " . app_lang("select") . " -"],
                        $department_id ?? "",
                        "class='form-control select2'"
                    );
                    ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Tender Type</label>
                    <?php
                    echo form_dropdown(
                        "tender_type",
                        ["open" => "Open", "close" => "Close"],
                        $tender->tender_type ?? $request->tender_type ?? "open",
                        "class='form-control select2'"
                    );
                    ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label><?php echo $can_publish_after_manager_approval ? "Publish After Manager Approval" : "Submit for Procurement Manager Approval"; ?></label>
                    <div class="mt10">
                        <label class="form-check">
                            <?php if ($can_publish_after_manager_approval) { ?>
                                <input type="checkbox" class="form-check-input" name="publish_now" value="1" <?php echo (($tender->status ?? "draft") === "published" ? "" : "checked"); ?>>
                                <span class="form-check-label">Release tender immediately after saving</span>
                            <?php } else { ?>
                                <input type="checkbox" class="form-check-input" name="submit_for_approval" value="1" checked>
                                <span class="form-check-label">Send this tender to the procurement manager before publishing</span>
                            <?php } ?>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Evaluation Method</label>
                    <?php
                    echo form_dropdown(
                        "evaluation_method",
                        ["separate" => "Technical & Commercial Separate", "combined" => "Combined"],
                        $evaluation_method_value,
                        "class='form-control select2'"
                    );
                    ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Technical Weight (%)</label>
                    <?php echo form_input([
                        "name" => "technical_weight",
                        "type" => "number",
                        "min" => "0",
                        "max" => "100",
                        "step" => "1",
                        "value" => esc($technical_weight_value),
                        "class" => "form-control",
                        "data-rule-required" => true,
                    ]); ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label>Commercial Weight (%)</label>
                    <?php echo form_input([
                        "name" => "commercial_weight",
                        "type" => "number",
                        "min" => "0",
                        "max" => "100",
                        "step" => "1",
                        "value" => esc($commercial_weight_value),
                        "class" => "form-control",
                        "data-rule-required" => true,
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Tender Fees (OMR)</label>
                    <?php echo form_input([
                        "name" => "tender_fee",
                        "type" => "number",
                        "step" => "0.001",
                        "min" => "0",
                        "value" => esc($tender->tender_fee ?? $request->tender_fee ?? ""),
                        "class" => "form-control",
                        "placeholder" => "0.000",
                    ]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Brief Description</label>
            <textarea name="brief_description" class="form-control" rows="3"><?php echo esc($tender->brief_description ?? $request->brief_description ?? ""); ?></textarea>
        </div>

        <div class="form-group">
            <label>Temporary Testing Stage</label>
            <?php
            echo form_dropdown(
                "testing_workflow_stage",
                $testing_stage_options,
                "",
                "class='form-control select2' id='testing_workflow_stage'"
            );
            ?>
        </div>

        <hr>
        <h5 class="mb15">Milestones</h5>
        <div class="alert alert-light">Friday and Saturday cannot be selected for tender milestones.</div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Tender Release Date</label>
                    <input type="datetime-local" name="release_at" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->release_at ?? "")); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Last Date of Document Purchase</label>
                    <input type="datetime-local" name="document_purchase_deadline" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->document_purchase_deadline ?? "")); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Site Visit Date / Deadline</label>
                    <input type="datetime-local" name="site_visit_at" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->site_visit_at ?? "")); ?>">
                    <small class="text-muted">When set or changed, a site visit notice is logged for vendors in the tender communication history.</small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Clarification Submission Deadline</label>
                    <input type="datetime-local" name="clarification_deadline" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->clarification_deadline ?? "")); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Tender Submission Deadline</label>
                    <input type="datetime-local" name="closing_at" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->closing_at ?? "")); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Bid Opening Date</label>
                    <input type="datetime-local" name="bid_opening_at" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->bid_opening_at ?? "")); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Technical Evaluation Deadline</label>
                    <input type="datetime-local" name="technical_eval_deadline" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->technical_eval_deadline ?? "")); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Commercial Evaluation Deadline</label>
                    <input type="datetime-local" name="commercial_eval_deadline" class="form-control tender-workday-datetime" value="<?php echo esc($dtValue($tender->commercial_eval_deadline ?? "")); ?>">
                </div>
            </div>
        </div>

        <hr>
        <h5 class="mb15">Team Assignment</h5>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Technical Evaluation Team</label>
                    <?php echo form_dropdown(
                        "technical_user_ids[]",
                        $technical_users_dropdown ?? [],
                        $existing_team_ids["technical"] ?? [],
                        "class='form-control select2' multiple='multiple'"
                    ); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Commercial Evaluation Team</label>
                    <?php echo form_dropdown(
                        "commercial_user_ids[]",
                        $commercial_users_dropdown ?? [],
                        $existing_team_ids["commercial"] ?? [],
                        "class='form-control select2' multiple='multiple'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Chairman</label>
                    <?php echo form_dropdown(
                        "chairman_user_id",
                        ["" => "- " . app_lang("select") . " -"] + ($committee_users_dropdown ?? []),
                        $existing_team_ids["chairman"] ?? "",
                        "class='form-control select2' data-committee-role='chairman'"
                    ); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Secretary</label>
                    <?php echo form_dropdown(
                        "secretary_user_id",
                        ["" => "- " . app_lang("select") . " -"] + ($committee_users_dropdown ?? []),
                        $existing_team_ids["secretary"] ?? "",
                        "class='form-control select2' data-committee-role='secretary' " . (empty($existing_team_ids["chairman"]) ? "disabled='disabled'" : "")
                    ); ?>
                    <small class="form-text text-muted" data-committee-lock-message="secretary">Choose a chairman first.</small>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>ITC Members</label>
            <?php echo form_dropdown(
                "itc_member_user_ids[]",
                $committee_users_dropdown ?? [],
                $existing_team_ids["itc_member"] ?? [],
                "class='form-control select2' multiple='multiple' data-committee-role='itc_member' " . (empty($existing_team_ids["secretary"]) ? "disabled='disabled'" : "")
            ); ?>
            <small class="form-text text-muted" data-committee-lock-message="itc_member">Choose a secretary before selecting ITC members.</small>
        </div>

        <hr>
        <h5 class="mb15">Bid Submission Requirements</h5>
        <div class="row">
            <?php foreach ($bid_requirement_labels as $code => $label) { ?>
                <div class="col-md-6">
                    <label class="form-check mb10">
                        <input type="checkbox" class="form-check-input" name="required_sections[]" value="<?php echo esc($code); ?>" <?php echo in_array($code, $existing_required_codes, true) ? "checked" : ""; ?>>
                        <span class="form-check-label"><?php echo esc($label); ?></span>
                    </label>
                </div>
            <?php } ?>
        </div>

        <hr>
        <h5 class="mb15">Target Vendors</h5>

        <?php if (!empty($request_selected_vendors)) { ?>
            <div class="alert alert-light">
                Close tender vendors from request:
                <?php echo esc(implode(", ", array_map(fn($v) => $v->vendor_name ?? "-", $request_selected_vendors))); ?>
            </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Target By</label>
                    <?php echo form_dropdown(
                        "target_mode",
                        [
                            "specialty" => "Vendor Specialty",
                            "group" => "Vendor Group",
                            "specific_vendors" => "Specific Vendors",
                            "group_and_specific_vendors" => "Vendor Group + Specific Vendors",
                            "grade" => "Vendor Grade",
                        ],
                        $selected_target_mode ?? "specialty",
                        "class='form-control select2' id='target_mode'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="row" id="target-by-specialty-wrap">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vendor Category</label>
                    <?php echo form_dropdown(
                        "vendor_category_id",
                        $vendor_categories_dropdown ?? ["" => "- " . app_lang("select") . " -"],
                        !empty($target_cat->id) ? (int) $target_cat->id : "",
                        "class='form-control select2' id='vendor_category_id'"
                    ); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vendor Subcategory</label>
                    <select name="vendor_sub_category_id" id="vendor_sub_category_id" class="form-control select2">
                        <option value=""><?php echo "- " . app_lang("select") . " -"; ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="row" id="target-by-group-wrap" style="display:none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vendor Group</label>
                    <?php echo form_dropdown(
                        "vendor_group_id",
                        $vendor_groups_dropdown ?? ["" => "- " . app_lang("select_vendor_group") . " -"],
                        (int) ($selected_vendor_group_id ?? 0),
                        "class='form-control select2' id='vendor_group_id'"
                    ); ?>
                </div>
            </div>
        </div>

        <div class="row" id="target-by-grade-wrap" style="display:none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Vendor Grade</label>
                    <?php echo form_dropdown(
                        "vendor_grade_id",
                        $vendor_grades_dropdown ?? ["" => "- Select vendor grade -"],
                        (int) ($selected_vendor_grade_id ?? 0),
                        "class='form-control select2' id='vendor_grade_id'"
                    ); ?>
                </div>
            </div>
        </div>

        <div id="target-by-specific-vendors-wrap" style="display:none;">
            <div class="form-group">
                <label>Specific Vendors</label>
                <div id="selected-vendor-tags" class="list-group mb10">
                    <?php foreach (($selected_specific_vendors ?? []) as $vendor) { ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center tender-selected-vendor-tag" data-vendor-id="<?php echo (int) $vendor->id; ?>">
                            <input type="hidden" name="specific_vendor_ids[]" value="<?php echo (int) $vendor->id; ?>">
                            <span>
                                <strong><?php echo esc($vendor->vendor_name ?? "Vendor #" . (int) $vendor->id); ?></strong>
                                <?php if (!empty($vendor->cr_number)) { ?><small class="d-block text-muted">CR <?php echo esc($vendor->cr_number); ?></small><?php } ?>
                            </span>
                            <button type="button" class="btn btn-default btn-sm tender-remove-selected-vendor" aria-label="Remove vendor">&times;</button>
                        </div>
                    <?php } ?>
                </div>
                <div class="input-group mb10">
                    <input type="search" id="vendor_picker_search" class="form-control" placeholder="Search approved vendors by name, email, or CR">
                    <button type="button" class="btn btn-default" id="vendor_picker_search_btn">
                        <i data-feather="search" class="icon-14"></i> Search
                    </button>
                </div>
                <div id="vendor_picker_results" class="list-group" style="max-height:260px; overflow-y:auto;">
                    <div class="list-group-item text-muted">Search for approved vendors to add.</div>
                </div>
                <small class="form-text text-muted">In combined mode, specific vendors are added to all approved vendors in the selected group.</small>
            </div>
        </div>

        <?php if (!empty($invited_vendors)) { ?>
            <div class="table-responsive mb15">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Invited At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invited_vendors as $vendor) { ?>
                            <tr>
                                <td><?php echo esc($vendor->vendor_name ?? "-"); ?></td>
                                <td><?php echo esc($vendor->email ?? "-"); ?></td>
                                <td><?php echo esc($vendor->invite_status ?? "-"); ?></td>
                                <td><?php echo esc($vendor->invited_at ?? "-"); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>

        <hr>
        <h5 class="mb15">Tender Documents</h5>

        <div class="alert alert-light">
            Add each tender document separately and choose its document type. Vendors will see all uploaded documents in the tender portal.
        </div>

        <button type="button" class="btn btn-default btn-sm mb10 tender-add-document-file">
            <i data-feather="plus-circle" class="icon-14"></i> Add Document File
        </button>

        <div class="mt-3">
            <?php
            $document_extra_fields = '
                <div class="row mt10 tender-document-file-meta">
                    <div class="col-md-4">
                        <label class="small text-muted mb5">Document Type</label>
                        <select class="form-control" data-name-template="doc_type___SERIAL__">
                            <option value="RFP">RFP</option>
                            <option value="BOQ">BOQ</option>
                            <option value="DRAWING">DRAWING</option>
                            <option value="SUPPORTING">SUPPORTING</option>
                            <option value="OTHER">OTHER</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted mb5">Download Access</label>
                        <label class="form-check mt5">
                            <input type="checkbox" class="form-check-input" value="1" data-name-template="time_limited___SERIAL__">
                            <span class="form-check-label">Time-limited</span>
                        </label>
                    </div>
                    <div class="col-md-4">
                        <label class="small text-muted mb5">Expires (hours)</label>
                        <input type="number" min="1" step="1" value="72" class="form-control" data-name-template="expires_in_hours___SERIAL__">
                    </div>
                </div>';

            echo view("includes/multi_file_uploader", [
                "max_files" => 10,
                "description_placeholder" => "Document title (optional)",
                "file_preview_extra_fields" => $document_extra_fields
            ]); ?>
        </div>

        <?php if (!empty($docs)) { ?>
            <hr>
            <h6>Existing Documents</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Limited</th>
                            <th style="width:260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docs as $doc) { ?>
                            <tr id="doc-row-<?php echo (int) $doc->id; ?>">
                                <td><?php echo esc($doc->doc_type); ?></td>
                                <td><?php echo esc($doc->title ?? "-"); ?></td>
                                <td><?php echo esc($doc->original_name ?? "-"); ?></td>
                                <td><?php echo esc($doc->size_bytes ? convert_file_size($doc->size_bytes) : "-"); ?></td>
                                <td><?php echo ((int) $doc->time_limited) ? ("Yes (" . (int) $doc->expires_in_hours . "h)") : "No"; ?></td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end flex-wrap gap-1">
                                        <?php echo js_anchor(
                                            "<i data-feather='eye' class='icon-14'></i> Preview",
                                            [
                                                "title" => "Preview Document",
                                                "class" => "btn btn-primary btn-sm",
                                                "data-toggle" => "app-modal",
                                                "data-sidebar" => "0",
                                                "data-url" => get_uri("tender_procurement_inbox/preview_tender_document/" . (int) $doc->id),
                                            ]
                                        ); ?>
                                        <a href="<?php echo get_uri("tender_procurement_inbox/download_tender_document/" . (int) $doc->id); ?>" class="btn btn-default btn-sm">
                                            <i data-feather="download" class="icon-14"></i> Download
                                        </a>
                                        <?php echo js_anchor(
                                            "<i data-feather='x' class='icon-14'></i> Delete",
                                            [
                                                "title" => app_lang("delete"),
                                                "class" => "btn btn-default btn-sm delete-doc",
                                                "data-id" => $doc->id,
                                                "data-action-url" => get_uri("tender_procurement_inbox/delete_document"),
                                            ]
                                        ); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo !empty($tender->id) ? "Save Tender" : "Create Tender"; ?></button>
</div>

<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    function initTenderCommitteeRoleSelection(scope) {
        var $scope = $(scope);
        var $chairman = $scope.find("[data-committee-role='chairman']");
        var $secretary = $scope.find("[data-committee-role='secretary']");
        var $itcMembers = $scope.find("[data-committee-role='itc_member']");
        var isSyncing = false;

        if (!$chairman.length || !$secretary.length || !$itcMembers.length) {
            return;
        }

        function selectedValues($field) {
            var value = $field.val();
            if (Array.isArray(value)) {
                return value.filter(function (item) {
                    return item !== "";
                }).map(String);
            }

            return value ? [String(value)] : [];
        }

        function setFieldValue($field, value) {
            isSyncing = true;
            $field.val(value).trigger("change");
            isSyncing = false;
        }

        function syncDisabledSelect2($field, disabled) {
            var role = $field.data("committee-role");
            $field.prop("disabled", disabled);
            $scope.find("[data-committee-lock-message='" + role + "']").toggle(disabled);

            if ($field.data("select2")) {
                try {
                    $field.select2("enable", !disabled);
                } catch (e) {
                    $field.trigger("change.select2");
                }
            }
        }

        function blockOptions($field, blockedValues) {
            $field.find("option").prop("disabled", false);
            blockedValues.forEach(function (value) {
                $field.find("option[value='" + value + "']").prop("disabled", true);
            });

            if ($field.data("select2")) {
                $field.trigger("change.select2");
            }
        }

        function updateCommitteeRoleSelection() {
            var chairmanId = String($chairman.val() || "");
            var secretaryId = String($secretary.val() || "");

            syncDisabledSelect2($secretary, !chairmanId);
            blockOptions($secretary, chairmanId ? [chairmanId] : []);
            if (!chairmanId || secretaryId === chairmanId) {
                setFieldValue($secretary, "");
                secretaryId = "";
            }

            var reservedIds = [chairmanId, secretaryId].filter(Boolean);
            syncDisabledSelect2($itcMembers, !secretaryId);
            blockOptions($itcMembers, reservedIds);

            if (!secretaryId) {
                setFieldValue($itcMembers, []);
                return;
            }

            var currentMembers = selectedValues($itcMembers);
            var allowedMembers = currentMembers.filter(function (id) {
                return reservedIds.indexOf(id) === -1;
            });

            if (allowedMembers.length !== currentMembers.length) {
                setFieldValue($itcMembers, allowedMembers);
            }
        }

        $chairman.add($secretary).add($itcMembers).on("change", function () {
            if (!isSyncing) {
                updateCommitteeRoleSelection();
            }
        });

        updateCommitteeRoleSelection();
    }

    initTenderCommitteeRoleSelection("#tender-procurement-form");

    function toggleTargetMode() {
        var mode = $("#target_mode").val();
        $("#target-by-specialty-wrap").toggle(mode === "specialty");
        $("#target-by-group-wrap").toggle(mode === "group" || mode === "group_and_specific_vendors");
        $("#target-by-specific-vendors-wrap").toggle(mode === "specific_vendors" || mode === "group_and_specific_vendors");
        $("#target-by-grade-wrap").toggle(mode === "grade");
    }

    var vendorSearchTimer = null;

    function htmlEscape(value) {
        return $("<div>").text(value || "").html();
    }

    function selectedVendorIds() {
        return $("#selected-vendor-tags .tender-selected-vendor-tag").map(function () {
            return String($(this).data("vendor-id"));
        }).get();
    }

    function addSelectedVendor(vendor) {
        if (!vendor || !vendor.id || $.inArray(String(vendor.id), selectedVendorIds()) !== -1) {
            return;
        }

        var meta = [];
        if (vendor.cr_number) meta.push("CR " + vendor.cr_number);
        if (vendor.group) meta.push(vendor.group);
        if (vendor.grade && vendor.grade !== "-") meta.push(vendor.grade);

        $("#selected-vendor-tags").append(
            '<div class="list-group-item d-flex justify-content-between align-items-center tender-selected-vendor-tag" data-vendor-id="' + parseInt(vendor.id, 10) + '">' +
                '<input type="hidden" name="specific_vendor_ids[]" value="' + parseInt(vendor.id, 10) + '">' +
                '<span><strong>' + htmlEscape(vendor.name) + '</strong>' +
                    (meta.length ? '<small class="d-block text-muted">' + htmlEscape(meta.join(" / ")) + '</small>' : '') +
                '</span>' +
                '<button type="button" class="btn btn-default btn-sm tender-remove-selected-vendor" aria-label="Remove vendor">&times;</button>' +
            '</div>'
        );
    }

    function renderVendorSearchResults(vendors) {
        var selected = selectedVendorIds();
        if (!vendors || !vendors.length) {
            $("#vendor_picker_results").html('<div class="list-group-item text-muted">No approved vendors found.</div>');
            return;
        }

        var html = "";
        $.each(vendors, function (index, vendor) {
            var alreadySelected = $.inArray(String(vendor.id), selected) !== -1;
            var meta = [];
            if (vendor.email) meta.push(vendor.email);
            if (vendor.cr_number) meta.push("CR " + vendor.cr_number);
            if (vendor.group) meta.push(vendor.group);
            if (vendor.grade && vendor.grade !== "-") meta.push(vendor.grade);

            html += '<div class="list-group-item d-flex justify-content-between align-items-center">' +
                '<span><strong>' + htmlEscape(vendor.name) + '</strong><small class="d-block text-muted">' + htmlEscape(meta.join(" / ") || "Approved vendor") + '</small></span>' +
                '<button type="button" class="btn btn-sm ' + (alreadySelected ? 'btn-success' : 'btn-primary') + ' tender-add-vendor-from-search" ' +
                    'data-vendor-id="' + parseInt(vendor.id, 10) + '" data-vendor-name="' + htmlEscape(vendor.name) + '" ' +
                    'data-vendor-cr-number="' + htmlEscape(vendor.cr_number || "") + '" data-vendor-group="' + htmlEscape(vendor.group || "") + '" ' +
                    'data-vendor-grade="' + htmlEscape(vendor.grade || "") + '"' + (alreadySelected ? ' disabled' : '') + '>' +
                    (alreadySelected ? 'Added' : 'Add') +
                '</button></div>';
        });
        $("#vendor_picker_results").html(html);
    }

    function searchVendors() {
        var query = $.trim($("#vendor_picker_search").val() || "");
        $("#vendor_picker_results").html('<div class="list-group-item text-muted">Searching...</div>');
        $.getJSON("<?php echo get_uri('tender_procurement_inbox/search_vendors'); ?>", {q: query}, function (res) {
            renderVendorSearchResults((res && res.vendors) || []);
        }).fail(function () {
            $("#vendor_picker_results").html('<div class="list-group-item text-danger">Unable to load vendors. Please try again.</div>');
        });
    }

    $("#vendor_picker_search_btn").on("click", searchVendors);
    $("#vendor_picker_search").on("keydown", function (event) {
        if (event.keyCode === 13) {
            event.preventDefault();
            searchVendors();
            return;
        }
    }).on("keyup", function (event) {
        if (event.keyCode === 13) {
            return;
        }
        clearTimeout(vendorSearchTimer);
        vendorSearchTimer = setTimeout(searchVendors, 350);
    });

    $(document).on("click", ".tender-add-vendor-from-search", function () {
        addSelectedVendor({
            id: $(this).data("vendor-id"),
            name: $(this).data("vendor-name"),
            cr_number: $(this).data("vendor-cr-number"),
            group: $(this).data("vendor-group"),
            grade: $(this).data("vendor-grade")
        });
        $(this).removeClass("btn-primary").addClass("btn-success").text("Added").prop("disabled", true);
    });

    $(document).on("click", ".tender-remove-selected-vendor", function () {
        $(this).closest(".tender-selected-vendor-tag").remove();
    });

    function loadSubcategories() {
        var categoryId = $("#vendor_category_id").val();
        var selectedId = "<?php echo !empty($target_sub->id) ? (int) $target_sub->id : ""; ?>";
        $("#vendor_sub_category_id").load("<?php echo get_uri('tender_procurement_inbox/get_vendor_sub_categories_dropdown'); ?>?vendor_category_id=" + categoryId, function () {
            if (selectedId) {
                $("#vendor_sub_category_id").val(selectedId).trigger("change");
            }
        });
    }

    toggleTargetMode();
    $("#target_mode").on("change", toggleTargetMode);
    $("#vendor_category_id").on("change", loadSubcategories);
    if ($("#vendor_category_id").val()) {
        loadSubcategories();
    }

    function openTenderDocumentChooser() {
        var dropzoneElement = document.getElementById("file-upload-dropzone");
        var dropzoneInstance = dropzoneElement && dropzoneElement.dropzone ? dropzoneElement.dropzone : null;

        if (dropzoneInstance && dropzoneInstance.hiddenFileInput) {
            dropzoneInstance.hiddenFileInput.click();
            return;
        }

        var fallbackInput = document.querySelector("#file-upload-dropzone input[type='file'], .dz-hidden-input");
        if (fallbackInput) {
            fallbackInput.click();
            return;
        }

        $("#file-upload-dropzone").trigger("click");
    }

    $(".tender-add-document-file").on("click", function (event) {
        event.preventDefault();
        openTenderDocumentChooser();
    });

    function tenderWorkdayNumber(value) {
        var parts = String(value || "").split("T")[0].split("-");
        if (parts.length !== 3) {
            return null;
        }
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12).getDay();
    }

    function validateTenderWorkdays(showMessage) {
        var valid = true;
        $(".tender-workday-datetime").each(function () {
            var day = tenderWorkdayNumber($(this).val());
            var invalid = day === 5 || day === 6;
            $(this).closest(".form-group").toggleClass("has-error", invalid);
            valid = valid && !invalid;
        });
        if (!valid && showMessage !== false) {
            appAlert.error("Tender milestones cannot be scheduled on Friday or Saturday.", {duration: 3500});
        }
        return valid;
    }

    $(".tender-workday-datetime").on("change", function () {
        var day = tenderWorkdayNumber($(this).val());
        if (day === 5 || day === 6) {
            $(this).val("");
            $(this).closest(".form-group").addClass("has-error");
            appAlert.error("Friday and Saturday cannot be selected for tender milestones.", {duration: 3500});
        } else {
            $(this).closest(".form-group").removeClass("has-error");
        }
    });

    $("#tender-procurement-form").appForm({
        beforeAjaxSubmit: function () {
            return validateTenderWorkdays(true);
        },
        onSuccess: function () {
            $("#tender-procurement-inbox-table").appTable({reload: true});
        }
    });

    $(document).on("click", ".delete-doc", function () {
        var id = $(this).attr("data-id");
        var actionUrl = $(this).attr("data-action-url");
        appLoader.show();
        $.post(actionUrl, {id: id}, function (res) {
            appLoader.hide();
            if (res && res.success) {
                $("#doc-row-" + id).remove();
            } else {
                appAlert.error((res && res.message) || "Unable to delete document.", {duration: 3000});
            }
        }, "json").fail(function () {
            appLoader.hide();
            appAlert.error("Unable to delete document.", {duration: 3000});
        });
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
