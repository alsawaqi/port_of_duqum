<?php
$tender = $tender ?? null;
$payload = $payload ?? [];
$pending_action_summary = $pending_action_summary ?? "";
$schedule = $schedule ?? [];
$teams = $teams ?? [];
$target_rules = $target_rules ?? [];
$target_vendors = $target_vendors ?? [];
$required_sections = $required_sections ?? [];
$tender_documents = $tender_documents ?? [];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];
$can_review = $can_review ?? false;

$date_value = function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$date_only_value = function ($value) {
    return !empty($value) ? format_to_date($value, false) : "-";
};

$money_value = function ($value, string $currency = "OMR") {
    if ($value === null || $value === "" || !is_numeric($value)) {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$decimal_value = function ($value) {
    if ($value === null || $value === "" || !is_numeric($value)) {
        return "-";
    }

    return number_format((float) $value, 3);
};

$status_badge = function ($status) {
    $status = strtolower((string) $status);
    $class = [
        "draft" => "bg-secondary",
        "published" => "bg-primary",
        "closed" => "bg-dark",
        "awarded" => "bg-success",
        "cancelled" => "bg-danger",
        "bidding" => "bg-primary",
        "technical_3key" => "bg-warning text-dark",
        "technical" => "bg-info text-dark",
        "commercial" => "bg-primary",
        "award_decision" => "bg-success",
        "pending" => "bg-warning text-dark",
        "approved" => "bg-success",
        "rejected" => "bg-danger",
        "revision_requested" => "bg-info text-dark",
    ][$status] ?? "bg-light text-dark";

    $label = [
        "technical_3key" => "Bid Opening",
        "award_decision" => "Award Decision",
    ][$status] ?? ucwords(str_replace("_", " ", $status ?: "-"));

    return "<span class='badge $class'>" . esc($label) . "</span>";
};

$team_role_label = function ($role) {
    return [
        "technical" => "Technical Evaluator",
        "commercial" => "Commercial Evaluator",
        "chairman" => "Chairman",
        "secretary" => "Secretary",
        "itc_member" => "ITC Member",
    ][(string) $role] ?? ucwords(str_replace("_", " ", (string) $role));
};

$target_label = function ($rule) {
    if (!empty($rule->vendor_grade_id)) {
        return "Grade: " . trim(($rule->vendor_grade_code ? $rule->vendor_grade_code . " - " : "") . ($rule->vendor_grade_name ?? ""));
    }

    if (!empty($rule->vendor_group_id)) {
        return "Group: " . trim(($rule->vendor_group_code ? $rule->vendor_group_code . " - " : "") . ($rule->vendor_group_name ?? ""));
    }

    $parts = [];
    if (!empty($rule->category_name)) {
        $parts[] = $rule->category_name;
    }
    if (!empty($rule->sub_category_name)) {
        $parts[] = $rule->sub_category_name;
    }

    return $parts ? implode(" / ", $parts) : "Specialty target";
};

$document_actions = function ($doc) {
    $doc_id = (int) ($doc->id ?? 0);
    if (!$doc_id) {
        return "";
    }

    $preview = js_anchor(
        "<i data-feather='eye' class='icon-14'></i> Preview",
        [
            "title" => "Preview Document",
            "class" => "btn btn-primary btn-sm mb5 me-1",
            "data-toggle" => "app-modal",
            "data-sidebar" => "0",
            "data-url" => get_uri("tender_procurement_manager_inbox/preview_tender_document/" . $doc_id),
        ]
    );

    $download = anchor(
        get_uri("tender_procurement_manager_inbox/download_tender_document/" . $doc_id),
        "<i data-feather='download' class='icon-14'></i> Download",
        ["class" => "btn btn-default btn-sm mb5"]
    );

    return "<div class='d-flex flex-wrap gap-1'>" . $preview . $download . "</div>";
};
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-manager-review-page">
    <div class="mb15">
        <a href="<?php echo get_uri("tender_procurement_manager_inbox"); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i> Back to Manager Inbox
        </a>
    </div>

    <div class="card gp-pro-card mb15 tender-report-hero">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-off mb5">Procurement Manager Review</div>
                    <h2 class="mb5"><?php echo esc($tender->reference ?? "-"); ?> - <?php echo esc($tender->title ?? "-"); ?></h2>
                    <div><?php echo esc($tender->company_name ?? "-"); ?> / <?php echo esc($tender->department_name ?? "-"); ?></div>
                </div>
                <div class="text-end">
                    <?php echo $status_badge($tender->procurement_manager_status ?? "draft"); ?>
                    <div class="mt10"><?php echo $status_badge($tender->status ?? "draft"); ?></div>
                    <div class="mt10"><?php echo $status_badge($tender->workflow_stage ?? "bidding"); ?></div>
                </div>
            </div>

            <div class="row mt20">
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Action</div>
                        <strong><?php echo esc(ucwords(str_replace("_", " ", $tender->procurement_manager_action ?? "initial"))); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Submitted At</div>
                        <strong><?php echo $date_value($tender->procurement_manager_submitted_at ?? null); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Submitted By</div>
                        <strong><?php echo esc(trim((string) ($tender->submitted_by_name ?? "")) ?: "-"); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Tender Fee</div>
                        <strong><?php echo $money_value($tender->tender_fee ?? null); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Tender Details</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb15">
                            <div class="text-off">Reference</div>
                            <strong><?php echo esc($tender->reference ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-6 mb15">
                            <div class="text-off">Tender Type</div>
                            <strong><?php echo esc(ucwords((string) ($tender->tender_type ?? "open"))); ?></strong>
                        </div>
                        <div class="col-md-6 mb15">
                            <div class="text-off">Company</div>
                            <strong><?php echo esc($tender->company_name ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-6 mb15">
                            <div class="text-off">Department</div>
                            <strong><?php echo esc($tender->department_name ?? "-"); ?></strong>
                        </div>
                    </div>

                    <div class="mb15">
                        <div class="text-off">Brief Description</div>
                        <div><?php echo nl2br(esc($tender->brief_description ?? "-")); ?></div>
                    </div>

                    <?php if (!empty($tender->site_visit_location) || !empty($tender->site_visit_instructions) || !empty($tender->site_visit_mandatory)) { ?>
                        <div class="alert alert-light mb0">
                            <strong>Site Visit</strong>
                            <div class="mt5">Location: <?php echo esc($tender->site_visit_location ?? "-"); ?></div>
                            <div>Mandatory: <?php echo (int) ($tender->site_visit_mandatory ?? 0) ? "Yes" : "No"; ?></div>
                            <?php if (!empty($tender->site_visit_instructions)) { ?>
                                <div class="mt5"><?php echo nl2br(esc($tender->site_visit_instructions)); ?></div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Schedule</h4>
                </div>
                <div class="card-body p0">
                    <div class="table-responsive gp-pro-table-shell">
                        <table class="table table-bordered table-striped mb0">
                            <thead>
                                <tr>
                                    <th>Milestone</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedule as $row) { ?>
                                    <tr>
                                        <td><?php echo esc($row["label"] ?? "-"); ?></td>
                                        <td><?php echo $date_value($row["value"] ?? null); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">RFQ Details</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb15">
                            <div class="text-off">RFQ No.</div>
                            <strong><?php echo esc($rfq_detail->rfq_no ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-4 mb15">
                            <div class="text-off">RFQ Date</div>
                            <strong><?php echo $date_only_value($rfq_detail->rfq_date ?? null); ?></strong>
                        </div>
                        <div class="col-md-4 mb15">
                            <div class="text-off">PR No.</div>
                            <strong><?php echo esc($rfq_detail->pr_no ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-4 mb15">
                            <div class="text-off">Delivery Location</div>
                            <strong><?php echo esc($rfq_detail->delivery_location ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-4 mb15">
                            <div class="text-off">Incoterm</div>
                            <strong><?php echo esc($rfq_detail->incoterm ?? "-"); ?></strong>
                        </div>
                        <div class="col-md-4 mb15">
                            <div class="text-off">Material Required On</div>
                            <strong><?php echo $date_only_value($rfq_detail->material_required_on ?? null); ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($rfq_detail->terms_reference) || !empty($rfq_detail->notes) || !empty($rfq_detail->enclosures)) { ?>
                        <div class="row">
                            <div class="col-md-4 mb15">
                                <div class="text-off">Terms Reference</div>
                                <div><?php echo esc($rfq_detail->terms_reference ?? "-"); ?></div>
                            </div>
                            <div class="col-md-4 mb15">
                                <div class="text-off">Notes</div>
                                <div><?php echo nl2br(esc($rfq_detail->notes ?? "-")); ?></div>
                            </div>
                            <div class="col-md-4 mb15">
                                <div class="text-off">Enclosures</div>
                                <div><?php echo nl2br(esc($rfq_detail->enclosures ?? "-")); ?></div>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="table-responsive gp-pro-table-shell">
                        <table class="table table-bordered table-striped mb0">
                            <thead>
                                <tr>
                                    <th>Sr.</th>
                                    <th>Description</th>
                                    <th>UOM</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Brand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rfq_items) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No RFQ items.</td></tr>
                                <?php } ?>
                                <?php foreach ($rfq_items as $item) { ?>
                                    <tr>
                                        <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                                        <td><?php echo nl2br(esc($item->description ?? "-")); ?></td>
                                        <td><?php echo esc($item->uom ?? "-"); ?></td>
                                        <td><?php echo $decimal_value($item->qty ?? null); ?></td>
                                        <td><?php echo $decimal_value($item->unit_price ?? null); ?></td>
                                        <td><?php echo esc($item->brand ?? "-"); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Tender Documents</h4>
                </div>
                <div class="card-body p0">
                    <div class="table-responsive gp-pro-table-shell">
                        <table class="table table-bordered table-striped mb0">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Title</th>
                                    <th>File</th>
                                    <th>Size</th>
                                    <th>Uploaded By</th>
                                    <th class="text-center" style="width: 210px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$tender_documents) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No tender documents uploaded.</td></tr>
                                <?php } ?>
                                <?php foreach ($tender_documents as $doc) { ?>
                                    <tr>
                                        <td><?php echo esc($doc->doc_type ?? "-"); ?></td>
                                        <td><?php echo esc($doc->title ?? "-"); ?></td>
                                        <td><?php echo esc($doc->original_name ?? "-"); ?></td>
                                        <td><?php echo !empty($doc->size_bytes) ? esc(convert_file_size($doc->size_bytes)) : "-"; ?></td>
                                        <td><?php echo esc(trim((string) ($doc->uploaded_by_name ?? "")) ?: "-"); ?></td>
                                        <td class="text-center"><?php echo $document_actions($doc); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Review Decision</h4>
                </div>
                <div class="card-body">
                    <div class="mb15">
                        <div class="text-off">Pending Change</div>
                        <div class="mt5"><?php echo $pending_action_summary; ?></div>
                    </div>

                    <?php if (!empty($tender->procurement_manager_comment)) { ?>
                        <div class="alert alert-warning">
                            <strong>Manager Comment</strong>
                            <div class="mt5"><?php echo nl2br(esc($tender->procurement_manager_comment)); ?></div>
                        </div>
                    <?php } ?>

                    <div class="mb15">
                        <div class="text-off">Reviewed By</div>
                        <strong><?php echo esc(trim((string) ($tender->reviewed_by_name ?? "")) ?: "-"); ?></strong>
                    </div>
                    <div class="mb15">
                        <div class="text-off">Reviewed At</div>
                        <strong><?php echo $date_value($tender->procurement_manager_reviewed_at ?? null); ?></strong>
                    </div>

                    <?php if ($can_review) { ?>
                        <div class="form-group">
                            <label>Comment</label>
                            <textarea id="manager-review-comment" class="form-control" rows="4" placeholder="Add a note for approval or explain what procurement must update."></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" id="manager-approve-tender" class="btn btn-success">
                                <i data-feather="check-circle" class="icon-16"></i> Approve
                            </button>
                            <button type="button" id="manager-return-tender" class="btn btn-warning">
                                <i data-feather="corner-up-left" class="icon-16"></i> Return for Update
                            </button>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Vendor Target</h4>
                </div>
                <div class="card-body">
                    <?php if (!$target_rules && !$target_vendors) { ?>
                        <div class="text-off">No vendor target selected.</div>
                    <?php } ?>

                    <?php foreach ($target_rules as $rule) { ?>
                        <div class="mb10">
                            <span class="badge bg-light text-dark"><?php echo esc($target_label($rule)); ?></span>
                        </div>
                    <?php } ?>

                    <?php foreach ($target_vendors as $vendor) { ?>
                        <div class="mb10">
                            <strong><?php echo esc($vendor->vendor_name ?? "-"); ?></strong>
                            <div class="text-off"><?php echo esc($vendor->email ?? "-"); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Bid Requirements</h4>
                </div>
                <div class="card-body">
                    <?php if (!$required_sections) { ?>
                        <div class="text-off">Default requirements apply.</div>
                    <?php } ?>
                    <?php foreach ($required_sections as $section) { ?>
                        <div class="d-flex justify-content-between align-items-center mb10">
                            <span><?php echo esc($section->label ?? $section->code ?? "-"); ?></span>
                            <?php echo (int) ($section->is_required ?? 0) ? "<span class='badge bg-success'>Required</span>" : "<span class='badge bg-light text-dark'>Optional</span>"; ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card gp-pro-card mb15">
                <div class="card-header">
                    <h4 class="mb0">Tender Team</h4>
                </div>
                <div class="card-body">
                    <?php if (!$teams) { ?>
                        <div class="text-off">No team members assigned.</div>
                    <?php } ?>
                    <?php foreach ($teams as $member) { ?>
                        <div class="mb10">
                            <span class="badge bg-light text-dark"><?php echo esc($team_role_label($member->team_role ?? "")); ?></span>
                            <div class="mt5"><strong><?php echo esc(trim((string) ($member->full_name ?? "")) ?: "-"); ?></strong></div>
                            <div class="text-off"><?php echo esc($member->email ?? "-"); ?></div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($can_review) { ?>
    <script>
        $(document).ready(function () {
            var tenderId = <?php echo (int) ($tender->id ?? 0); ?>;

            function postReview(url, requireComment) {
                var comment = $.trim($("#manager-review-comment").val());
                if (requireComment && !comment) {
                    appAlert.error("Comment is required when returning the tender for update.", {duration: 3000});
                    return;
                }

                appLoader.show();
                $.post(url, {tender_id: tenderId, comment: comment}, function (res) {
                    appLoader.hide();
                    if (res && res.success) {
                        appAlert.success(res.message || "Decision saved.", {duration: 2000});
                        setTimeout(function () {
                            window.location.href = res.redirect_url || "<?php echo get_uri("tender_procurement_manager_inbox"); ?>";
                        }, 500);
                    } else {
                        appAlert.error((res && res.message) || "Decision could not be saved.", {duration: 3000});
                    }
                }, "json").fail(function () {
                    appLoader.hide();
                    appAlert.error("Decision could not be saved.", {duration: 3000});
                });
            }

            $("#manager-approve-tender").on("click", function () {
                if (confirm("Approve this tender for publishing?")) {
                    postReview("<?php echo get_uri("tender_procurement_manager_inbox/approve"); ?>", false);
                }
            });

            $("#manager-return-tender").on("click", function () {
                postReview("<?php echo get_uri("tender_procurement_manager_inbox/request_revision"); ?>", true);
            });
        });
    </script>
<?php } ?>
