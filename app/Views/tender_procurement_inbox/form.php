<?php
$tender = $tender ?? null;
$request = $request ?? null;
$existing_team_ids = $existing_team_ids ?? ["technical" => [], "commercial" => [], "chairman" => 0, "secretary" => 0, "itc_member" => []];
$existing_required_codes = $existing_required_codes ?? [];
$bid_requirement_labels = $bid_requirement_labels ?? [];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];
$testing_stage_options = $testing_stage_options ?? ["" => "- Keep normal date-based flow -"];

$dtValue = function ($value) {
    if (empty($value)) {
        return "";
    }
    return date("Y-m-d\\TH:i", strtotime($value));
};

$dateOnlyValue = function ($value) {
    if (empty($value)) {
        return "";
    }
    return date("Y-m-d", strtotime($value));
};

$is_edit = !empty($tender->id);
$page_title = $is_edit ? "Edit Tender" : "Create New Tender";
$procurement_manager_status = (string)($tender->procurement_manager_status ?? "draft");
$tender_status = (string)($tender->status ?? "draft");
$requires_change_approval = $is_edit && (in_array($tender_status, ["published", "closed"], true) || ($tender_status === "draft" && $procurement_manager_status === "approved"));
$can_publish_after_manager_approval = $procurement_manager_status === "approved";
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-wizard-page">
    <div class="mb15">
        <a href="<?php echo get_uri("tender_procurement_inbox"); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i> Back to Procurement Inbox
        </a>
    </div>

    <div class="tender-wizard-hero mb15">
        <div>
            <div class="text-off mb5">Tender Procurement</div>
            <h1><?php echo esc($page_title); ?></h1>
            <div class="tender-wizard-hero-meta">
                <?php echo esc($tender->reference ?? $request->reference ?? "Draft tender"); ?>
                <span></span>
                <?php echo esc($tender->title ?? $request->subject ?? "Tender setup"); ?>
            </div>
        </div>
        <div class="tender-wizard-hero-badge">
            <i data-feather="clipboard" class="icon-18"></i>
            <?php echo $is_edit ? "Draft / Existing Tender" : "Procurement-led Tender"; ?>
        </div>
    </div>

    <?php echo form_open(get_uri("tender_procurement_inbox/save"), ["id" => "tender-procurement-form", "class" => "general-form tender-wizard-form", "role" => "form"]); ?>
    <input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
    <input type="hidden" name="tender_request_id" value="<?php echo (int) ($request->id ?? 0); ?>" />

    <?php if ($procurement_manager_status === "revision_requested" && !empty($tender->procurement_manager_comment)) { ?>
        <div class="alert alert-warning">
            <strong>Procurement manager requested updates.</strong>
            <div class="mt5"><?php echo nl2br(esc($tender->procurement_manager_comment)); ?></div>
        </div>
    <?php } ?>

    <div class="tender-wizard-layout">
        <nav class="tender-wizard-steps" aria-label="Tender setup steps">
            <button type="button" class="tender-step is-active" data-step="0">
                <span>1</span>
                <strong>Tender Details</strong>
                <small>Reference, company, scope</small>
            </button>
            <button type="button" class="tender-step" data-step="1">
                <span>2</span>
                <strong>Milestones</strong>
                <small>Release, site visit, closing</small>
            </button>
            <button type="button" class="tender-step" data-step="2">
                <span>3</span>
                <strong>Team Assignment</strong>
                <small>Technical, commercial, ITC</small>
            </button>
            <button type="button" class="tender-step" data-step="3">
                <span>4</span>
                <strong>Target Vendors</strong>
                <small>Specialty or group</small>
            </button>
            <button type="button" class="tender-step" data-step="4">
                <span>5</span>
                <strong>Documents</strong>
                <small>Requirements and files</small>
            </button>
            <button type="button" class="tender-step" data-step="5">
                <span>6</span>
                <strong>Preview</strong>
                <small>Review before submit</small>
            </button>
        </nav>

        <section class="tender-wizard-panel">
            <div class="tender-wizard-progress">
                <div class="tender-wizard-progress-bar"></div>
            </div>

            <div class="tender-wizard-step-panel is-active" data-step-panel="0">
                <div class="tender-step-heading">
                    <div>
                        <h2>Tender Details</h2>
                        <p>Core tender identity, ownership, and high-level scope.</p>
                    </div>
                    <i data-feather="file-text" class="icon-24"></i>
                </div>

                <?php if (!empty($request->id)) { ?>
                    <div class="alert alert-info">
                        Linked request: <strong><?php echo esc($request->reference); ?></strong>
                    </div>
                <?php } else { ?>
                    <div class="alert alert-light">
                        Procurement can start the tender directly from this page.
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
                                "data-wizard-required" => "1",
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
                                "data-wizard-required" => "1",
                            ]); ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Company</label>
                            <?php echo form_dropdown(
                                "company_id",
                                $company_dropdown ?? ["" => "- " . app_lang("select_company") . " -"],
                                $company_id ?? "",
                                "class='form-control select2' data-wizard-required='1'"
                            ); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Department</label>
                            <?php echo form_dropdown(
                                "department_id",
                                $department_dropdown ?? ["" => "- " . app_lang("select") . " -"],
                                $department_id ?? "",
                                "class='form-control select2' data-wizard-required='1'"
                            ); ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Tender Type</label>
                            <?php echo form_dropdown(
                                "tender_type",
                                ["open" => "Open", "close" => "Close"],
                                $tender->tender_type ?? $request->tender_type ?? "open",
                                "class='form-control select2' id='tender_type'"
                            ); ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group tender-publish-choice">
                            <label><?php echo $requires_change_approval ? "Submit Change for Procurement Manager Approval" : ($can_publish_after_manager_approval ? "Publish After Manager Approval" : "Submit for Procurement Manager Approval"); ?></label>
                            <label class="form-check mt10">
                                <?php if ($requires_change_approval) { ?>
                                    <input type="checkbox" class="form-check-input" checked disabled>
                                    <span class="form-check-label">Changes are sent to the procurement manager before they affect the tender</span>
                                <?php } elseif ($can_publish_after_manager_approval) { ?>
                                    <input type="checkbox" class="form-check-input" name="publish_now" value="1" <?php echo (($tender->status ?? "draft") === "published" ? "" : "checked"); ?>>
                                    <span class="form-check-label">Release tender immediately after saving</span>
                                <?php } else { ?>
                                    <input type="checkbox" class="form-check-input" name="submit_for_approval" value="1" checked>
                                    <span class="form-check-label">Send this tender to the procurement manager before publishing</span>
                                <?php } ?>
                            </label>
                            <?php if (!$can_publish_after_manager_approval && $procurement_manager_status === "pending") { ?>
                                <div class="text-off mt5">Current approval status: Pending procurement manager approval.</div>
                            <?php } ?>
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
                    <textarea name="brief_description" class="form-control" rows="5"><?php echo esc($tender->brief_description ?? $request->brief_description ?? ""); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Temporary Testing Stage</label>
                    <?php echo form_dropdown(
                        "testing_workflow_stage",
                        $testing_stage_options,
                        "",
                        "class='form-control select2' id='testing_workflow_stage'"
                    ); ?>
                </div>
            </div>

            <div class="tender-wizard-step-panel" data-step-panel="1">
                <div class="tender-step-heading">
                    <div>
                        <h2>Milestones</h2>
                        <p>Key tender dates and stage deadlines.</p>
                    </div>
                    <i data-feather="calendar" class="icon-24"></i>
                </div>

                <div class="tender-milestone-grid">
                    <div class="form-group">
                        <label>Tender Release Date</label>
                        <input type="datetime-local" name="release_at" class="form-control" value="<?php echo esc($dtValue($tender->release_at ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Date of Document Purchase</label>
                        <input type="datetime-local" name="document_purchase_deadline" class="form-control" value="<?php echo esc($dtValue($tender->document_purchase_deadline ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Site Visit Date / Deadline</label>
                        <input type="datetime-local" name="site_visit_at" class="form-control" value="<?php echo esc($dtValue($tender->site_visit_at ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Site Visit Location</label>
                        <input type="text" name="site_visit_location" class="form-control" value="<?php echo esc($tender->site_visit_location ?? ""); ?>" placeholder="Meeting point or site address">
                    </div>
                    <div class="form-group">
                        <label>Site Visit Attendance</label>
                        <label class="form-check mt10">
                            <input type="checkbox" class="form-check-input" name="site_visit_mandatory" value="1" <?php echo !empty($tender->site_visit_mandatory) ? "checked" : ""; ?>>
                            <span class="form-check-label">Mandatory for participating vendors</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Clarification Submission Deadline</label>
                        <input type="datetime-local" name="clarification_deadline" class="form-control" value="<?php echo esc($dtValue($tender->clarification_deadline ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tender Submission Deadline</label>
                        <input type="datetime-local" name="closing_at" class="form-control" value="<?php echo esc($dtValue($tender->closing_at ?? "")); ?>" data-wizard-required="1">
                    </div>
                    <div class="form-group">
                        <label>Bid Opening Date</label>
                        <input type="datetime-local" name="bid_opening_at" class="form-control" value="<?php echo esc($dtValue($tender->bid_opening_at ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Technical Evaluation Deadline</label>
                        <input type="datetime-local" name="technical_eval_deadline" class="form-control" value="<?php echo esc($dtValue($tender->technical_eval_deadline ?? "")); ?>">
                    </div>
                    <div class="form-group">
                        <label>Commercial Evaluation Deadline</label>
                        <input type="datetime-local" name="commercial_eval_deadline" class="form-control" value="<?php echo esc($dtValue($tender->commercial_eval_deadline ?? "")); ?>">
                    </div>
                    <div class="form-group span-all">
                        <label>Site Visit Instructions</label>
                        <textarea name="site_visit_instructions" class="form-control" rows="3" placeholder="Safety instructions, contact person, PPE, or attendance notes"><?php echo esc($tender->site_visit_instructions ?? ""); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="tender-wizard-step-panel" data-step-panel="2">
                <div class="tender-step-heading">
                    <div>
                        <h2>Team Assignment</h2>
                        <p>Assign evaluators and ITC members before publishing.</p>
                    </div>
                    <i data-feather="users" class="icon-24"></i>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Technical Evaluation Team</label>
                            <?php echo form_dropdown(
                                "technical_user_ids[]",
                                $technical_users_dropdown ?? [],
                                $existing_team_ids["technical"] ?? [],
                                "class='form-control select2' multiple='multiple' data-publish-required='1'"
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
                                "class='form-control select2' multiple='multiple' data-publish-required='1'"
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
                                "class='form-control select2' data-publish-required='1'"
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
                                "class='form-control select2' data-publish-required='1'"
                            ); ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>ITC Members</label>
                    <?php echo form_dropdown(
                        "itc_member_user_ids[]",
                        $committee_users_dropdown ?? [],
                        $existing_team_ids["itc_member"] ?? [],
                        "class='form-control select2' multiple='multiple' data-publish-required='1'"
                    ); ?>
                </div>
            </div>

            <div class="tender-wizard-step-panel" data-step-panel="3">
                <div class="tender-step-heading">
                    <div>
                        <h2>Target Vendors</h2>
                        <p>Select the vendor pool for open or close tender distribution.</p>
                    </div>
                    <i data-feather="target" class="icon-24"></i>
                </div>

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
                        <div class="tender-vendor-picker-shell">
                            <div id="selected-vendor-tags" class="tender-selected-vendors">
                                <?php foreach (($selected_specific_vendors ?? []) as $vendor) {
                                    $grade_label = function_exists("vendor_grade_label") ? vendor_grade_label($vendor->grade_name ?? "", $vendor->grade_code ?? "") : trim((string) ($vendor->grade_code ?? ""));
                                    $group_label = trim((string) ($vendor->group_name ?? ""));
                                    if ($group_label !== "" && !empty($vendor->group_code)) {
                                        $group_label .= " (" . $vendor->group_code . ")";
                                    }
                                    $meta = array_filter([
                                        !empty($vendor->cr_number) ? "CR " . $vendor->cr_number : "",
                                        $group_label,
                                        $grade_label !== "-" ? $grade_label : "",
                                    ]);
                                ?>
                                    <span class="tender-selected-vendor-tag" data-vendor-id="<?php echo (int) $vendor->id; ?>" data-vendor-name="<?php echo esc($vendor->vendor_name ?? "Vendor #" . (int) $vendor->id); ?>">
                                        <input type="hidden" name="specific_vendor_ids[]" value="<?php echo (int) $vendor->id; ?>">
                                        <span>
                                            <strong><?php echo esc($vendor->vendor_name ?? "Vendor #" . (int) $vendor->id); ?></strong>
                                            <?php if (!empty($meta)) { ?><small><?php echo esc(implode(" / ", $meta)); ?></small><?php } ?>
                                        </span>
                                        <button type="button" class="tender-remove-selected-vendor" aria-label="Remove vendor">&times;</button>
                                    </span>
                                <?php } ?>
                            </div>
                            <button type="button" class="btn btn-default tender-open-vendor-picker">
                                <i data-feather="search" class="icon-16"></i> Search and Add Vendors
                            </button>
                            <div class="text-off mt5">Add as many approved vendors as needed. Only selected vendors will be invited when this target mode is used.</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($invited_vendors)) { ?>
                    <div class="table-responsive mt15">
                        <table class="table table-bordered table-striped">
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
            </div>

            <div class="tender-wizard-step-panel" data-step-panel="4">
                <div class="tender-step-heading">
                    <div>
                        <h2>Documents & Requirements</h2>
                        <p>Bid submission requirements and tender package documents.</p>
                    </div>
                    <i data-feather="folder-plus" class="icon-24"></i>
                </div>

                <div class="tender-requirement-grid mb20">
                    <?php foreach ($bid_requirement_labels as $code => $label) { ?>
                        <label class="tender-requirement-option">
                            <input type="checkbox" name="required_sections[]" value="<?php echo esc($code); ?>" <?php echo in_array($code, $existing_required_codes, true) ? "checked" : ""; ?>>
                            <span>
                                <i data-feather="check-circle" class="icon-16"></i>
                                <?php echo esc($label); ?>
                            </span>
                        </label>
                    <?php } ?>
                </div>

                <div class="tender-rfq-panel mb20">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
                        <div>
                            <h4 class="mb5">RFQ / RFP Details</h4>
                            <div class="text-off">External-facing RFQ header and item lines for vendor download/reference.</div>
                        </div>
                        <button type="button" class="btn btn-default btn-sm tender-add-rfq-row">
                            <i data-feather="plus-circle" class="icon-14"></i> Add Item Line
                        </button>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Reference Number</label>
                                <input type="text" name="rfq_no" class="form-control" value="<?php echo esc($rfq_detail->rfq_no ?? ""); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Request Date</label>
                                <input type="date" name="rfq_date" class="form-control" value="<?php echo esc($dateOnlyValue($rfq_detail->rfq_date ?? "")); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>PR No</label>
                                <input type="text" name="pr_no" class="form-control" value="<?php echo esc($rfq_detail->pr_no ?? ""); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Delivery Location</label>
                                <input type="text" name="delivery_location" class="form-control" value="<?php echo esc($rfq_detail->delivery_location ?? ""); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>INCOTERM</label>
                                <input type="text" name="incoterm" class="form-control" value="<?php echo esc($rfq_detail->incoterm ?? ""); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Material Required On</label>
                                <input type="date" name="material_required_on" class="form-control" value="<?php echo esc($dateOnlyValue($rfq_detail->material_required_on ?? "")); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Terms & Conditions Reference</label>
                                <input type="text" name="terms_reference" class="form-control" value="<?php echo esc($rfq_detail->terms_reference ?? "PODC GTC"); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Notes</label>
                                <textarea name="rfq_notes" class="form-control" rows="2"><?php echo esc($rfq_detail->notes ?? ""); ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Enclosures</label>
                                <textarea name="rfq_enclosures" class="form-control" rows="2"><?php echo esc($rfq_detail->enclosures ?? ""); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb0" id="rfq-items-table">
                            <thead>
                                <tr>
                                    <th style="width:80px;">Sr No</th>
                                    <th>Description</th>
                                    <th style="width:110px;">UOM</th>
                                    <th style="width:120px;">Qty</th>
                                    <th style="width:140px;">Unit Price</th>
                                    <th style="width:160px;">Brand</th>
                                    <th class="text-center" style="width:60px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $rfq_rows = $rfq_items ?: [(object) ["sr_no" => "1", "description" => "", "uom" => "", "qty" => "", "unit_price" => "", "brand" => ""]];
                                foreach ($rfq_rows as $index => $item) { ?>
                                    <tr>
                                        <td><input type="text" name="rfq_item_sr_no[]" class="form-control" value="<?php echo esc($item->sr_no ?? ($index + 1)); ?>"></td>
                                        <td><input type="text" name="rfq_item_description[]" class="form-control" value="<?php echo esc($item->description ?? ""); ?>"></td>
                                        <td><input type="text" name="rfq_item_uom[]" class="form-control" value="<?php echo esc($item->uom ?? ""); ?>"></td>
                                        <td><input type="number" step="0.001" min="0" name="rfq_item_qty[]" class="form-control" value="<?php echo esc($item->qty ?? ""); ?>"></td>
                                        <td><input type="number" step="0.001" min="0" name="rfq_item_unit_price[]" class="form-control" value="<?php echo esc($item->unit_price ?? ""); ?>"></td>
                                        <td><input type="text" name="rfq_item_brand[]" class="form-control" value="<?php echo esc($item->brand ?? ""); ?>"></td>
                                        <td class="text-center"><button type="button" class="btn btn-default btn-sm tender-remove-rfq-row"><i data-feather="trash-2" class="icon-14"></i></button></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="button" class="btn btn-default btn-sm mb15 tender-add-document-file">
                    <i data-feather="plus-circle" class="icon-14"></i> Add Document File
                </button>

                <div class="tender-upload-shell">
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
                        "max_files" => 20,
                        "description_placeholder" => "Document title (optional)",
                        "file_preview_extra_fields" => $document_extra_fields
                    ]); ?>
                </div>

                <?php if (!empty($docs)) { ?>
                    <h4 class="mb15 mt20">Existing Documents</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="existing-documents-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Limited</th>
                                    <th class="text-center" style="width:70px;"></th>
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
                                        <td class="text-center">
                                            <?php echo js_anchor(
                                                "<i data-feather='x' class='icon-16'></i>",
                                                [
                                                    "title" => app_lang("delete"),
                                                    "class" => "delete-doc",
                                                    "data-id" => $doc->id,
                                                    "data-action-url" => get_uri("tender_procurement_inbox/delete_document"),
                                                ]
                                            ); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>

            <div class="tender-wizard-step-panel" data-step-panel="5">
                <div class="tender-step-heading">
                    <div>
                        <h2>Preview Tender</h2>
                        <p>Review the setup before saving or publishing.</p>
                    </div>
                    <i data-feather="eye" class="icon-24"></i>
                </div>

                <div class="tender-preview-grid">
                    <section class="tender-preview-card">
                        <h3><i data-feather="file-text" class="icon-16"></i> Tender Details</h3>
                        <dl class="tender-preview-list">
                            <div><dt>Reference</dt><dd data-preview="reference">-</dd></div>
                            <div><dt>Title</dt><dd data-preview="title">-</dd></div>
                            <div><dt>Company</dt><dd data-preview="company">-</dd></div>
                            <div><dt>Department</dt><dd data-preview="department">-</dd></div>
                            <div><dt>Type</dt><dd data-preview="tender_type">-</dd></div>
                            <div><dt>Tender Fees</dt><dd data-preview="tender_fee">-</dd></div>
                            <div><dt>Manager Step</dt><dd data-preview="publish_now">-</dd></div>
                            <div><dt>Testing Stage</dt><dd data-preview="testing_workflow_stage">-</dd></div>
                            <div class="span-all"><dt>Description</dt><dd data-preview="brief_description">-</dd></div>
                        </dl>
                    </section>

                    <section class="tender-preview-card">
                        <h3><i data-feather="calendar" class="icon-16"></i> Milestones</h3>
                        <dl class="tender-preview-list">
                            <div><dt>Release</dt><dd data-preview="release_at">-</dd></div>
                            <div><dt>Document Purchase</dt><dd data-preview="document_purchase_deadline">-</dd></div>
                            <div><dt>Site Visit</dt><dd data-preview="site_visit_at">-</dd></div>
                            <div><dt>Site Visit Location</dt><dd data-preview="site_visit_location">-</dd></div>
                            <div><dt>Site Visit Attendance</dt><dd data-preview="site_visit_mandatory">-</dd></div>
                            <div><dt>Clarification</dt><dd data-preview="clarification_deadline">-</dd></div>
                            <div><dt>Submission</dt><dd data-preview="closing_at">-</dd></div>
                            <div><dt>Bid Opening</dt><dd data-preview="bid_opening_at">-</dd></div>
                            <div><dt>Technical Evaluation</dt><dd data-preview="technical_eval_deadline">-</dd></div>
                            <div><dt>Commercial Evaluation</dt><dd data-preview="commercial_eval_deadline">-</dd></div>
                            <div class="span-all"><dt>Site Visit Instructions</dt><dd data-preview="site_visit_instructions">-</dd></div>
                        </dl>
                    </section>

                    <section class="tender-preview-card">
                        <h3><i data-feather="users" class="icon-16"></i> Team Assignment</h3>
                        <dl class="tender-preview-list">
                            <div class="span-all"><dt>Technical Team</dt><dd data-preview="technical_team">-</dd></div>
                            <div class="span-all"><dt>Commercial Team</dt><dd data-preview="commercial_team">-</dd></div>
                            <div><dt>Chairman</dt><dd data-preview="chairman">-</dd></div>
                            <div><dt>Secretary</dt><dd data-preview="secretary">-</dd></div>
                            <div class="span-all"><dt>ITC Members</dt><dd data-preview="itc_members">-</dd></div>
                        </dl>
                    </section>

                    <section class="tender-preview-card">
                        <h3><i data-feather="target" class="icon-16"></i> Target Vendors</h3>
                        <dl class="tender-preview-list">
                            <div><dt>Target By</dt><dd data-preview="target_mode">-</dd></div>
                            <div><dt>Category</dt><dd data-preview="vendor_category">-</dd></div>
                            <div><dt>Subcategory</dt><dd data-preview="vendor_subcategory">-</dd></div>
                            <div><dt>Group</dt><dd data-preview="vendor_group">-</dd></div>
                            <div><dt>Grade</dt><dd data-preview="vendor_grade">-</dd></div>
                            <div class="span-all"><dt>Specific Vendors</dt><dd data-preview="specific_vendors">-</dd></div>
                            <div class="span-all"><dt>Existing Invites</dt><dd><?php echo !empty($invited_vendors) ? count($invited_vendors) . " vendor(s)" : "None yet"; ?></dd></div>
                        </dl>
                    </section>

                    <section class="tender-preview-card span-all">
                        <h3><i data-feather="list" class="icon-16"></i> RFQ / RFP</h3>
                        <dl class="tender-preview-list mb15">
                            <div><dt>Reference Number</dt><dd data-preview="rfq_no">-</dd></div>
                            <div><dt>Request Date</dt><dd data-preview="rfq_date">-</dd></div>
                            <div><dt>PR No</dt><dd data-preview="pr_no">-</dd></div>
                            <div><dt>Delivery</dt><dd data-preview="delivery_location">-</dd></div>
                            <div><dt>INCOTERM</dt><dd data-preview="incoterm">-</dd></div>
                            <div><dt>Material Required</dt><dd data-preview="material_required_on">-</dd></div>
                            <div class="span-all"><dt>Terms Reference</dt><dd data-preview="terms_reference">-</dd></div>
                        </dl>
                        <div class="tender-preview-subtitle">Item Lines</div>
                        <ul class="tender-preview-tags" data-preview-list="rfq_items">
                            <li>No RFQ item lines</li>
                        </ul>
                    </section>

                    <section class="tender-preview-card span-all">
                        <h3><i data-feather="folder" class="icon-16"></i> Documents & Requirements</h3>
                        <div class="tender-preview-columns">
                            <div>
                                <div class="tender-preview-subtitle">Bid Requirements</div>
                                <ul class="tender-preview-tags" data-preview-list="requirements">
                                    <li>None selected</li>
                                </ul>
                            </div>
                            <div>
                                <div class="tender-preview-subtitle">New Uploads</div>
                                <ul class="tender-preview-tags" data-preview-list="new_documents">
                                    <li>No new uploaded files</li>
                                </ul>
                            </div>
                            <div>
                                <div class="tender-preview-subtitle">Existing Documents</div>
                                <ul class="tender-preview-tags" data-preview-list="existing_documents">
                                    <li><?php echo !empty($docs) ? count($docs) . " document(s)" : "No existing documents"; ?></li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="tender-wizard-actions">
                <button type="button" class="btn btn-default tender-prev" disabled>
                    <i data-feather="arrow-left" class="icon-16"></i> Previous
                </button>
                <button type="button" class="btn btn-primary tender-next">
                    Next <i data-feather="arrow-right" class="icon-16"></i>
                </button>
                <button type="submit" class="btn btn-success tender-save">
                    <i data-feather="check-circle" class="icon-16"></i>
                    <?php echo $requires_change_approval ? "Submit Change for Approval" : ($is_edit ? "Save Tender" : "Create Tender"); ?>
                </button>
            </div>
        </section>
    </div>

    <?php echo form_close(); ?>
</div>

<div class="modal fade" id='vendor_picker_modal' tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Vendors</h5>
                <button type="button" class="close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="input-group mb15">
                    <input type="text" id="vendor_picker_search" class="form-control" placeholder="Search by vendor name, CR number, email, or phone">
                    <button type="button" class="btn btn-primary" id="vendor_picker_search_btn">
                        <i data-feather="search" class="icon-16"></i> Search
                    </button>
                </div>
                <div id="vendor_picker_results" class="tender-vendor-picker-results">
                    <div class="text-off p15">Search for approved vendors to add them to this tender.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal" data-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    var currentStep = 0;
    var maxStep = 5;
    var hasRequestSelectedVendors = <?php echo !empty($request_selected_vendors) ? "true" : "false"; ?>;
    var vendorSearchTimer = null;

    $("#tender-procurement-form .select2").select2();

    function stepPercent() {
        return ((currentStep + 1) / (maxStep + 1)) * 100;
    }

    function showStep(step) {
        currentStep = Math.max(0, Math.min(maxStep, step));
        if (currentStep === maxStep) {
            renderPreview();
        }

        $(".tender-step").removeClass("is-active is-complete").each(function () {
            var index = parseInt($(this).attr("data-step"), 10);
            if (index === currentStep) {
                $(this).addClass("is-active");
            } else if (index < currentStep) {
                $(this).addClass("is-complete");
            }
        });

        $(".tender-wizard-step-panel").removeClass("is-active");
        $('.tender-wizard-step-panel[data-step-panel="' + currentStep + '"]').addClass("is-active");
        $(".tender-wizard-progress-bar").css("width", stepPercent() + "%");
        $(".tender-prev").prop("disabled", currentStep === 0);
        $(".tender-next").toggle(currentStep < maxStep);
        $(".tender-save").toggle(currentStep === maxStep);
        $(".tender-next").html(currentStep === maxStep - 1
            ? "Preview <i data-feather='eye' class='icon-16'></i>"
            : "Next <i data-feather='arrow-right' class='icon-16'></i>");

        if (typeof feather !== "undefined") {
            feather.replace();
        }
    }

    function displayText(value) {
        value = $.trim(value || "");
        return value || "Not set";
    }

    function fieldValue(name) {
        return $('[name="' + name + '"]').val() || "";
    }

    function selectedText(selector) {
        var $field = $(selector);
        var value = $field.val();
        if ((Array.isArray(value) && !value.length) || (!Array.isArray(value) && !value)) {
            return "Not set";
        }

        var texts = [];
        $field.find("option:selected").each(function () {
            var text = $.trim($(this).text());
            if (text && text.charAt(0) !== "-") {
                texts.push(text);
            }
        });

        return texts.length ? texts.join(", ") : "Not set";
    }

    function dateText(name) {
        return displayText(fieldValue(name).replace("T", " "));
    }

    function fieldTime(name) {
        var value = fieldValue(name);
        return value ? new Date(value).getTime() : null;
    }

    function clearScheduleMarkers() {
        $.each([
            "release_at",
            "document_purchase_deadline",
            "site_visit_at",
            "clarification_deadline",
            "closing_at",
            "bid_opening_at",
            "technical_eval_deadline",
            "commercial_eval_deadline"
        ], function (index, name) {
            markField($('[name="' + name + '"]'), false);
        });
    }

    function scheduleRule(first, second, message, strictAfter) {
        var firstTime = fieldTime(first);
        var secondTime = fieldTime(second);
        if (firstTime && secondTime && (strictAfter ? firstTime >= secondTime : firstTime > secondTime)) {
            markField($('[name="' + first + '"]'), true);
            markField($('[name="' + second + '"]'), true);
            return message;
        }

        return "";
    }

    function validateSchedule() {
        clearScheduleMarkers();

        var rules = [
            ["release_at", "document_purchase_deadline", "Tender release date must be on or before the document purchase deadline.", false],
            ["release_at", "site_visit_at", "Tender release date must be on or before the site visit date.", false],
            ["release_at", "clarification_deadline", "Tender release date must be on or before the clarification deadline.", false],
            ["release_at", "closing_at", "Tender release date must be on or before the submission deadline.", false],
            ["document_purchase_deadline", "closing_at", "Document purchase deadline must be on or before the submission deadline.", false],
            ["site_visit_at", "closing_at", "Site visit date must be on or before the submission deadline.", false],
            ["clarification_deadline", "closing_at", "Clarification deadline must be on or before the submission deadline.", false],
            ["closing_at", "bid_opening_at", "Bid opening date must be after the submission deadline.", true],
            ["closing_at", "technical_eval_deadline", "Technical evaluation deadline must be after the submission deadline.", true],
            ["bid_opening_at", "technical_eval_deadline", "Technical evaluation deadline must be after the bid opening date.", true],
            ["technical_eval_deadline", "commercial_eval_deadline", "Commercial evaluation deadline must be after the technical evaluation deadline.", true]
        ];

        for (var i = 0; i < rules.length; i++) {
            var error = scheduleRule(rules[i][0], rules[i][1], rules[i][2], rules[i][3]);
            if (error) {
                appAlert.error(error, {duration: 3500});
                showStep(1);
                return false;
            }
        }

        if (fieldTime("commercial_eval_deadline") && !fieldTime("technical_eval_deadline")) {
            markField($('[name="technical_eval_deadline"]'), true);
            markField($('[name="commercial_eval_deadline"]'), true);
            appAlert.error("Technical evaluation deadline is required before setting the commercial evaluation deadline.", {duration: 3500});
            showStep(1);
            return false;
        }

        return true;
    }

    function setPreview(key, value) {
        $('[data-preview="' + key + '"]').text(displayText(value));
    }

    function setPreviewList(key, items, emptyText) {
        var html = "";
        if (!items.length) {
            items = [emptyText || "None"];
        }

        $.each(items, function (index, item) {
            html += "<li>" + $("<div>").text(item).html() + "</li>";
        });

        $('[data-preview-list="' + key + '"]').html(html);
    }

    function checkedRequirementTexts() {
        return $('input[name="required_sections[]"]:checked').map(function () {
            return $.trim($(this).closest(".tender-requirement-option").text());
        }).get();
    }

    function newDocumentTexts() {
        var docs = [];
        $("#uploaded-file-previews .box").each(function () {
            var fileName = $.trim($(this).find(".name").text());
            if (!fileName) {
                return;
            }

            var docType = $(this).find("[name^='doc_type_']").val() || "RFP";
            var title = $.trim($(this).find(".description-field").val() || "");
            docs.push(docType + " - " + (title || fileName));
        });
        return docs;
    }

    function existingDocumentTexts() {
        var docs = [];
        $("#existing-documents-table tbody tr:visible").each(function () {
            var $cells = $(this).find("td");
            var docType = $.trim($cells.eq(0).text());
            var title = $.trim($cells.eq(1).text());
            var fileName = $.trim($cells.eq(2).text());
            docs.push(docType + " - " + (title && title !== "-" ? title : fileName));
        });
        return docs;
    }

    function selectedVendorTexts() {
        return $("#selected-vendor-tags .tender-selected-vendor-tag").map(function () {
            return $.trim($(this).data("vendor-name") || $(this).find("strong").text());
        }).get();
    }

    function rfqItemTexts() {
        var rows = [];
        $("#rfq-items-table tbody tr").each(function () {
            var $row = $(this);
            var sr = $.trim($row.find('[name="rfq_item_sr_no[]"]').val() || "");
            var description = $.trim($row.find('[name="rfq_item_description[]"]').val() || "");
            var uom = $.trim($row.find('[name="rfq_item_uom[]"]').val() || "");
            var qty = $.trim($row.find('[name="rfq_item_qty[]"]').val() || "");
            var price = $.trim($row.find('[name="rfq_item_unit_price[]"]').val() || "");
            var brand = $.trim($row.find('[name="rfq_item_brand[]"]').val() || "");

            if (!sr && !description && !uom && !qty && !price && !brand) {
                return;
            }

            rows.push((sr || "-") + " - " + (description || "Item") + (qty ? " | Qty " + qty : "") + (uom ? " " + uom : "") + (brand ? " | " + brand : ""));
        });
        return rows;
    }

    function renderPreview() {
        setPreview("reference", fieldValue("reference"));
        setPreview("title", fieldValue("title"));
        setPreview("company", selectedText('[name="company_id"]'));
        setPreview("department", selectedText('[name="department_id"]'));
        setPreview("tender_type", selectedText("#tender_type"));
        setPreview("tender_fee", fieldValue("tender_fee") ? fieldValue("tender_fee") + " OMR" : "-");
        var publishAfterSave = $('input[name="publish_now"]').is(":checked");
        var submitForApproval = $('input[name="submit_for_approval"]').is(":checked");
        setPreview("publish_now", publishAfterSave ? "Publish after save" : (submitForApproval ? "Submit for procurement manager approval" : "Save as draft"));
        setPreview("testing_workflow_stage", selectedText("#testing_workflow_stage"));
        setPreview("brief_description", fieldValue("brief_description"));

        setPreview("release_at", dateText("release_at"));
        setPreview("document_purchase_deadline", dateText("document_purchase_deadline"));
        setPreview("site_visit_at", dateText("site_visit_at"));
        setPreview("site_visit_location", fieldValue("site_visit_location"));
        setPreview("site_visit_mandatory", $('input[name="site_visit_mandatory"]').is(":checked") ? "Mandatory" : "Optional");
        setPreview("site_visit_instructions", fieldValue("site_visit_instructions"));
        setPreview("clarification_deadline", dateText("clarification_deadline"));
        setPreview("closing_at", dateText("closing_at"));
        setPreview("bid_opening_at", dateText("bid_opening_at"));
        setPreview("technical_eval_deadline", dateText("technical_eval_deadline"));
        setPreview("commercial_eval_deadline", dateText("commercial_eval_deadline"));

        setPreview("technical_team", selectedText('[name="technical_user_ids[]"]'));
        setPreview("commercial_team", selectedText('[name="commercial_user_ids[]"]'));
        setPreview("chairman", selectedText('[name="chairman_user_id"]'));
        setPreview("secretary", selectedText('[name="secretary_user_id"]'));
        setPreview("itc_members", selectedText('[name="itc_member_user_ids[]"]'));

        setPreview("target_mode", selectedText("#target_mode"));
        setPreview("vendor_category", selectedText("#vendor_category_id"));
        setPreview("vendor_subcategory", selectedText("#vendor_sub_category_id"));
        setPreview("vendor_group", selectedText("#vendor_group_id"));
        setPreview("vendor_grade", selectedText("#vendor_grade_id"));
        setPreview("specific_vendors", selectedVendorTexts().join(", ") || "-");

        setPreview("rfq_no", fieldValue("rfq_no"));
        setPreview("rfq_date", fieldValue("rfq_date"));
        setPreview("pr_no", fieldValue("pr_no"));
        setPreview("delivery_location", fieldValue("delivery_location"));
        setPreview("incoterm", fieldValue("incoterm"));
        setPreview("material_required_on", fieldValue("material_required_on"));
        setPreview("terms_reference", fieldValue("terms_reference"));
        setPreviewList("rfq_items", rfqItemTexts(), "No RFQ item lines");
        setPreviewList("requirements", checkedRequirementTexts(), "None selected");
        setPreviewList("new_documents", newDocumentTexts(), "No new uploaded files");
        setPreviewList("existing_documents", existingDocumentTexts(), "No existing documents");
    }

    function fieldHasValue($field) {
        var value = $field.val();
        return Array.isArray(value) ? value.length > 0 : $.trim(value || "") !== "";
    }

    function markField($field, hasError) {
        $field.closest(".form-group").toggleClass("has-error", hasError);
    }

    function validateStep(step, silent) {
        var isValid = true;
        var $panel = $('.tender-wizard-step-panel[data-step-panel="' + step + '"]');
        $panel.find("[data-wizard-required]").each(function () {
            var $field = $(this);
            var skipClosingForTesting = $("#testing_workflow_stage").val() && $field.attr("name") === "closing_at";
            if (skipClosingForTesting) {
                markField($field, false);
                return;
            }
            var hasValue = fieldHasValue($field);
            markField($field, !hasValue);
            if (!hasValue) {
                isValid = false;
            }
        });

        var testingStage = $("#testing_workflow_stage").val();
        var testingStageNeedsTeam = $.inArray(testingStage, ["technical_3key", "technical", "commercial"]) !== -1;
        var needsPublishFields = $("input[name='publish_now']").is(":checked") || ($("input[name='submit_for_approval']").is(":checked") && !testingStage) || testingStageNeedsTeam;
        if (needsPublishFields) {
            $panel.find("[data-publish-required]").each(function () {
                var $field = $(this);
                var hasValue = fieldHasValue($field);
                markField($field, !hasValue);
                if (!hasValue) {
                    isValid = false;
                }
            });
        } else {
            $panel.find("[data-publish-required]").each(function () {
                markField($(this), false);
            });
        }

        if (step === 3) {
            var targetMode = $("#target_mode").val();
            var $targetField = targetMode === "group" ? $("#vendor_group_id") : (targetMode === "grade" ? $("#vendor_grade_id") : $("#vendor_category_id"));
            var mustHaveTarget = targetMode === "group" || targetMode === "grade" || targetMode === "specific_vendors" || ($("#tender_type").val() === "close" && !hasRequestSelectedVendors);
            var hasTarget = !mustHaveTarget || fieldHasValue($targetField);
            if (targetMode === "specific_vendors") {
                hasTarget = selectedVendorTexts().length > 0;
                $targetField = $("#selected-vendor-tags");
            }
            markField($targetField, !hasTarget);
            if (!hasTarget) {
                isValid = false;
            }
        } else {
            markField($("#vendor_category_id"), false);
            markField($("#vendor_group_id"), false);
            markField($("#vendor_grade_id"), false);
            markField($("#selected-vendor-tags"), false);
        }

        if (!isValid && !silent) {
            appAlert.error("Please complete the required fields in this step.", {duration: 3000});
        }

        return isValid;
    }

    function canMoveToStep(targetStep) {
        if (targetStep <= currentStep) {
            return true;
        }

        for (var step = currentStep; step < targetStep; step++) {
            if (!validateStep(step)) {
                showStep(step);
                return false;
            }
        }

        return true;
    }

    function toggleTargetMode() {
        var mode = $("#target_mode").val();
        $("#target-by-specialty-wrap").toggle(mode === "specialty");
        $("#target-by-group-wrap").toggle(mode === "group");
        $("#target-by-specific-vendors-wrap").toggle(mode === "specific_vendors");
        $("#target-by-grade-wrap").toggle(mode === "grade");
        renderPreview();
    }

    function loadSubcategories() {
        var categoryId = $("#vendor_category_id").val();
        var selectedId = "<?php echo !empty($target_sub->id) ? (int) $target_sub->id : ""; ?>";
        $("#vendor_sub_category_id").load("<?php echo get_uri('tender_procurement_inbox/get_vendor_sub_categories_dropdown'); ?>?vendor_category_id=" + categoryId, function () {
            if (selectedId) {
                $("#vendor_sub_category_id").val(selectedId).trigger("change");
            }
        });
    }

    $(".tender-step").on("click", function () {
        var targetStep = parseInt($(this).attr("data-step"), 10);
        if (canMoveToStep(targetStep)) {
            showStep(targetStep);
        }
    });

    $(".tender-next").on("click", function () {
        if (validateStep(currentStep)) {
            showStep(currentStep + 1);
        }
    });

    $(".tender-prev").on("click", function () {
        showStep(currentStep - 1);
    });

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

        var html = '<span class="tender-selected-vendor-tag" data-vendor-id="' + parseInt(vendor.id, 10) + '" data-vendor-name="' + htmlEscape(vendor.name) + '">' +
            '<input type="hidden" name="specific_vendor_ids[]" value="' + parseInt(vendor.id, 10) + '">' +
            '<span><strong>' + htmlEscape(vendor.name) + '</strong>' +
            (meta.length ? '<small>' + htmlEscape(meta.join(" / ")) + '</small>' : '') +
            '</span>' +
            '<button type="button" class="tender-remove-selected-vendor" aria-label="Remove vendor">&times;</button>' +
            '</span>';

        $("#selected-vendor-tags").append(html);
        markField($("#selected-vendor-tags"), false);
        renderPreview();
    }

    function renderVendorSearchResults(vendors) {
        var selected = selectedVendorIds();
        if (!vendors || !vendors.length) {
            $("#vendor_picker_results").html('<div class="text-off p15">No approved vendors found.</div>');
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

            html += '<div class="tender-vendor-result">' +
                '<div><strong>' + htmlEscape(vendor.name) + '</strong>' +
                '<small>' + htmlEscape(meta.join(" / ") || "Approved vendor") + '</small></div>' +
                '<button type="button" class="btn btn-sm ' + (alreadySelected ? 'btn-success' : 'btn-primary') + ' tender-add-vendor-from-search" ' +
                'data-vendor-id="' + parseInt(vendor.id, 10) + '" ' +
                'data-vendor-name="' + htmlEscape(vendor.name) + '" ' +
                'data-vendor-email="' + htmlEscape(vendor.email || "") + '" ' +
                'data-vendor-cr-number="' + htmlEscape(vendor.cr_number || "") + '" ' +
                'data-vendor-group="' + htmlEscape(vendor.group || "") + '" ' +
                'data-vendor-grade="' + htmlEscape(vendor.grade || "") + '"' + (alreadySelected ? ' disabled' : '') + '>' +
                (alreadySelected ? 'Added' : 'Add') +
                '</button>' +
                '</div>';
        });

        $("#vendor_picker_results").html(html);
    }

    function searchVendors() {
        var query = $.trim($("#vendor_picker_search").val() || "");
        $("#vendor_picker_results").html('<div class="text-off p15">Searching...</div>');
        $.getJSON("<?php echo get_uri('tender_procurement_inbox/search_vendors'); ?>", {q: query}, function (res) {
            renderVendorSearchResults((res && res.vendors) || []);
        }).fail(function () {
            $("#vendor_picker_results").html('<div class="text-danger p15">Unable to load vendors. Please try again.</div>');
        });
    }

    $(".tender-open-vendor-picker").on("click", function () {
        $("#vendor_picker_modal").modal("show");
        if (!$.trim($("#vendor_picker_search").val() || "")) {
            searchVendors();
        }
    });

    $("#vendor_picker_search_btn").on("click", searchVendors);
    $("#vendor_picker_search").on("keyup", function (event) {
        if (event.keyCode === 13) {
            searchVendors();
            return;
        }

        clearTimeout(vendorSearchTimer);
        vendorSearchTimer = setTimeout(searchVendors, 350);
    });

    $(document).on("click", ".tender-add-vendor-from-search", function () {
        var vendor = {
            id: $(this).data("vendor-id"),
            name: $(this).data("vendor-name"),
            email: $(this).data("vendor-email"),
            cr_number: $(this).data("vendor-cr-number"),
            group: $(this).data("vendor-group"),
            grade: $(this).data("vendor-grade")
        };

        addSelectedVendor(vendor);
        $(this).removeClass("btn-primary").addClass("btn-success").text("Added").prop("disabled", true);
    });

    $(document).on("click", ".tender-remove-selected-vendor", function () {
        $(this).closest(".tender-selected-vendor-tag").remove();
        renderPreview();
    });

    function rfqRowTemplate(nextNo) {
        return '<tr>' +
            '<td><input type="text" name="rfq_item_sr_no[]" class="form-control" value="' + nextNo + '"></td>' +
            '<td><input type="text" name="rfq_item_description[]" class="form-control"></td>' +
            '<td><input type="text" name="rfq_item_uom[]" class="form-control"></td>' +
            '<td><input type="number" step="0.001" min="0" name="rfq_item_qty[]" class="form-control"></td>' +
            '<td><input type="number" step="0.001" min="0" name="rfq_item_unit_price[]" class="form-control"></td>' +
            '<td><input type="text" name="rfq_item_brand[]" class="form-control"></td>' +
            '<td class="text-center"><button type="button" class="btn btn-default btn-sm tender-remove-rfq-row"><i data-feather="trash-2" class="icon-14"></i></button></td>' +
            '</tr>';
    }

    $(".tender-add-rfq-row").on("click", function () {
        var nextNo = $("#rfq-items-table tbody tr").length + 1;
        $("#rfq-items-table tbody").append(rfqRowTemplate(nextNo));
        if (typeof feather !== "undefined") {
            feather.replace();
        }
    });

    $(document).on("click", ".tender-remove-rfq-row", function () {
        var $rows = $("#rfq-items-table tbody tr");
        if ($rows.length <= 1) {
            $(this).closest("tr").find("input").val("");
            return;
        }
        $(this).closest("tr").remove();
    });

    $("#target_mode").on("change", toggleTargetMode);
    $("#vendor_category_id").on("change", loadSubcategories);
    $("#vendor_group_id, #vendor_grade_id, #vendor_sub_category_id").on("change", renderPreview);
    toggleTargetMode();
    if ($("#vendor_category_id").val()) {
        loadSubcategories();
    }

    $("#tender-procurement-form").appForm({
        isModal: false,
        beforeAjaxSubmit: function () {
            for (var step = 0; step <= maxStep; step++) {
                if (!validateStep(step, true)) {
                    showStep(step);
                    appAlert.error("Please complete the required fields in this step.", {duration: 3000});
                    return false;
                }
            }

            if (!validateSchedule()) {
                return false;
            }

            return true;
        },
        onSuccess: function (response) {
            appAlert.success(response.message || "Tender saved successfully.", {duration: 2000});
            setTimeout(function () {
                window.location.href = response.redirect_url || "<?php echo get_uri('tender_procurement_inbox'); ?>";
            }, 450);
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
                appAlert.success(res.message || "Document deleted.", {duration: 2000});
            } else {
                appAlert.error((res && res.message) || "Unable to delete document.", {duration: 3000});
            }
        }, "json").fail(function () {
            appLoader.hide();
            appAlert.error("Unable to delete document.", {duration: 3000});
        });
    });

    showStep(0);
});
</script>

<style>
.tender-wizard-page {
    --tw-ink: #1f2a44;
    --tw-muted: #667085;
    --tw-line: #dfe7f2;
    --tw-soft: #f7fafc;
    --tw-primary: #2364d2;
    --tw-teal: #138a72;
    --tw-amber: #b7791f;
}
.tender-wizard-hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    border: 1px solid var(--tw-line);
    background: linear-gradient(135deg, #ffffff 0%, #f6f9fc 58%, #f1f8f6 100%);
    border-radius: 14px;
    padding: 20px 22px;
    box-shadow: 0 12px 34px rgba(31, 42, 68, 0.07);
    animation: tenderWizardRise 0.35s ease both;
}
.tender-wizard-hero h1 {
    margin: 0;
    font-size: 25px;
    color: var(--tw-ink);
    line-height: 1.2;
}
.tender-wizard-hero-meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    color: var(--tw-muted);
    font-size: 13px;
}
.tender-wizard-hero-meta span {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #9aa8bd;
}
.tender-wizard-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: 1px solid #cfe1dc;
    background: #f1fbf8;
    color: #176f60;
    border-radius: 999px;
    padding: 10px 14px;
    font-weight: 700;
    white-space: nowrap;
}
.tender-wizard-layout {
    display: block;
}
.tender-wizard-steps {
    position: sticky;
    top: 76px;
    z-index: 5;
    display: grid;
    grid-template-columns: repeat(6, minmax(132px, 1fr));
    gap: 8px;
    border: 1px solid var(--tw-line);
    background: #fff;
    border-radius: 14px;
    padding: 10px;
    margin-bottom: 14px;
    box-shadow: 0 10px 28px rgba(31, 42, 68, 0.06);
    overflow-x: auto;
}
.tender-step {
    display: grid;
    grid-template-columns: 32px minmax(0, 1fr);
    grid-template-rows: auto auto;
    width: 100%;
    gap: 10px;
    border: 1px solid transparent;
    background: transparent;
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--tw-ink);
    text-align: left;
    min-height: 58px;
    align-items: start;
    transition: background 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
}
.tender-step:hover {
    background: var(--tw-soft);
    transform: translateY(-1px);
}
.tender-step span {
    grid-column: 1;
    grid-row: 1 / span 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #eef3fb;
    color: var(--tw-primary);
    font-weight: 800;
}
.tender-step strong,
.tender-step small {
    display: block;
    min-width: 0;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.tender-step strong {
    grid-column: 2;
    grid-row: 1;
    font-size: 13px;
    line-height: 1.25;
}
.tender-step small {
    grid-column: 2;
    grid-row: 2;
    color: var(--tw-muted);
    margin-top: 3px;
    font-size: 11px;
    line-height: 1.25;
}
.tender-step.is-active {
    border-color: #c9d9f4;
    background: #f4f8ff;
}
.tender-step.is-active span {
    background: var(--tw-primary);
    color: #fff;
}
.tender-step.is-complete span {
    background: var(--tw-teal);
    color: #fff;
}
.tender-wizard-panel {
    border: 1px solid var(--tw-line);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 12px 34px rgba(31, 42, 68, 0.07);
    overflow: hidden;
}
.tender-wizard-progress {
    height: 4px;
    background: #edf2f7;
}
.tender-wizard-progress-bar {
    width: 20%;
    height: 100%;
    background: linear-gradient(90deg, var(--tw-primary), var(--tw-teal), var(--tw-amber));
    transition: width 0.28s ease;
}
.tender-wizard-step-panel {
    display: none;
    padding: 22px;
}
.tender-wizard-step-panel.is-active {
    display: block;
    animation: tenderWizardPanelIn 0.28s ease both;
}
.tender-step-heading {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 18px;
    padding-bottom: 14px;
    border-bottom: 1px solid #edf2f7;
}
.tender-step-heading h2 {
    margin: 0 0 5px;
    font-size: 22px;
    color: var(--tw-ink);
}
.tender-step-heading p {
    margin: 0;
    color: var(--tw-muted);
}
.tender-step-heading > i {
    color: var(--tw-primary);
}
.tender-milestone-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.tender-milestone-grid .span-all {
    grid-column: 1 / -1;
}
.tender-requirement-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}
.tender-requirement-option {
    cursor: pointer;
}
.tender-requirement-option input {
    position: absolute;
    opacity: 0;
}
.tender-requirement-option span {
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid var(--tw-line);
    border-radius: 10px;
    padding: 12px 13px;
    background: #fff;
    color: var(--tw-ink);
    font-weight: 650;
    transition: border-color 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
}
.tender-requirement-option input:checked + span {
    border-color: #b7d8ce;
    background: #f1fbf8;
    color: #176f60;
    box-shadow: 0 8px 18px rgba(19, 138, 114, 0.08);
}
.tender-upload-shell {
    border: 1px dashed #cbd7e8;
    border-radius: 12px;
    background: #fbfdff;
    padding: 12px;
}
.tender-rfq-panel {
    border: 1px solid #e0e8f3;
    border-radius: 12px;
    background: #fbfdff;
    padding: 16px;
}
.tender-rfq-panel h4 {
    color: var(--tw-ink);
    font-size: 16px;
}
#rfq-items-table input {
    min-width: 0;
}
.tender-vendor-picker-shell {
    border: 1px solid #dfe7f2;
    border-radius: 12px;
    background: #fbfdff;
    padding: 12px;
}
.tender-selected-vendors {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    min-height: 46px;
    margin-bottom: 10px;
}
.tender-selected-vendor-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    max-width: 100%;
    border: 1px solid #bfd8ee;
    border-radius: 10px;
    background: #f5fbff;
    color: var(--tw-ink);
    padding: 8px 9px;
}
.tender-selected-vendor-tag strong,
.tender-selected-vendor-tag small {
    display: block;
    max-width: 360px;
    overflow-wrap: anywhere;
}
.tender-selected-vendor-tag small {
    color: var(--tw-muted);
    font-size: 11px;
    margin-top: 2px;
}
.tender-remove-selected-vendor {
    border: 0;
    background: transparent;
    color: #6b7890;
    font-size: 18px;
    line-height: 1;
    padding: 0 2px;
}
.tender-vendor-picker-results {
    border: 1px solid #edf2f7;
    border-radius: 10px;
    max-height: 420px;
    overflow: auto;
}
.tender-vendor-result {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-bottom: 1px solid #edf2f7;
}
.tender-vendor-result:last-child {
    border-bottom: 0;
}
.tender-vendor-result strong,
.tender-vendor-result small {
    display: block;
}
.tender-vendor-result small {
    color: var(--tw-muted);
    margin-top: 3px;
}
.tender-document-file-meta {
    border-top: 1px solid #edf2f7;
    padding-top: 10px;
}
.tender-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.tender-preview-card {
    border: 1px solid var(--tw-line);
    border-radius: 12px;
    background: #fff;
    padding: 16px;
    box-shadow: 0 8px 20px rgba(31, 42, 68, 0.04);
}
.tender-preview-card.span-all {
    grid-column: 1 / -1;
}
.tender-preview-card h3 {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    color: var(--tw-ink);
    font-size: 15px;
}
.tender-preview-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin: 0;
}
.tender-preview-list div {
    min-width: 0;
}
.tender-preview-list .span-all {
    grid-column: 1 / -1;
}
.tender-preview-list dt,
.tender-preview-subtitle {
    color: var(--tw-muted);
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 4px;
    text-transform: uppercase;
}
.tender-preview-list dd {
    margin: 0;
    color: var(--tw-ink);
    line-height: 1.45;
    overflow-wrap: anywhere;
}
.tender-preview-columns {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.tender-preview-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    list-style: none;
    margin: 0;
    padding: 0;
}
.tender-preview-tags li {
    border: 1px solid #d7e3f2;
    border-radius: 999px;
    background: #f7fbff;
    color: var(--tw-ink);
    padding: 6px 10px;
    font-size: 12px;
    line-height: 1.25;
    max-width: 100%;
    overflow-wrap: anywhere;
}
.tender-wizard-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    border-top: 1px solid #edf2f7;
    background: #fbfdff;
    padding: 16px 22px;
}
.tender-save {
    display: none;
    margin-left: auto;
}
.tender-next {
    margin-left: auto;
}
.tender-wizard-page .has-error .form-control,
.tender-wizard-page .has-error .tender-selected-vendors,
.tender-wizard-page .has-error .select2-container .select2-choice,
.tender-wizard-page .has-error .select2-container .select2-selection {
    border-color: #df425a !important;
}
@keyframes tenderWizardRise {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes tenderWizardPanelIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 991px) {
    .tender-wizard-steps {
        position: static;
        grid-template-columns: repeat(6, minmax(148px, 1fr));
    }
    .tender-preview-grid,
    .tender-preview-columns {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 767px) {
    .tender-wizard-hero,
    .tender-step-heading,
    .tender-wizard-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .tender-milestone-grid,
    .tender-requirement-grid,
    .tender-preview-list {
        grid-template-columns: 1fr;
    }
    .tender-next,
    .tender-save {
        margin-left: 0;
    }
}
</style>
