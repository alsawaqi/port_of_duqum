<?php
$summary = $summary ?? [];
$timeline = $timeline ?? [];
$teams = $teams ?? [];
$vendors = $vendors ?? [];
$weighted_evaluation_scores = $weighted_evaluation_scores ?? [];
$technical_evaluations = $technical_evaluations ?? [];
$commercial_evaluations = $commercial_evaluations ?? [];
$communications = $communications ?? [];
$communication_attachments = $communication_attachments ?? [];
$extensions = $extensions ?? [];
$workflow_history = $workflow_history ?? [];
$opening_audit = $opening_audit ?? [];
$opening_session = $opening_session ?? null;
$opening_signatures = $opening_signatures ?? [];
$proposal_review = $proposal_review ?? null;
$tender_documents = $tender_documents ?? [];
$document_access = $document_access ?? [];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];
$can_override_workflow = $can_override_workflow ?? false;
$can_reply_clarifications = $can_reply_clarifications ?? false;

$date_value = function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
};

$score_value = function ($value, int $decimals = 3) {
    if ($value === null || $value === "" || !is_numeric($value)) {
        return "-";
    }

    return number_format((float) $value, $decimals);
};

$percent_value = function ($value) {
    if ($value === null || $value === "" || !is_numeric($value)) {
        return "-";
    }

    return number_format((float) $value, 2) . "%";
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
        "submitted" => "bg-info text-dark",
        "accepted" => "bg-success",
        "approved" => "bg-success",
        "rejected" => "bg-danger",
        "opened" => "bg-info text-dark",
        "sent" => "bg-secondary",
        "pending_approval" => "bg-warning text-dark",
        "declined" => "bg-danger",
    ][$status] ?? "bg-light text-dark";

    $label = [
        "technical_3key" => "Bid Opening",
    ][$status] ?? ucwords(str_replace("_", " ", $status ?: "-"));

    return "<span class='badge $class'>" . esc($label) . "</span>";
};

$timeline_badge = function ($status) {
    $status = strtolower((string) $status);
    $class = [
        "completed" => "bg-success",
        "active" => "bg-primary",
        "scheduled" => "bg-info text-dark",
        "cancelled" => "bg-danger",
        "pending" => "bg-light text-dark",
    ][$status] ?? "bg-light text-dark";

    return "<span class='badge $class'>" . esc(ucfirst($status ?: "-")) . "</span>";
};

$team_list = function (string $role) use ($teams) {
    $rows = $teams[$role] ?? [];
    if (!$rows) {
        return "<span class='text-off'>Not assigned</span>";
    }

    $items = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row->full_name ?? ""));
        if ($name === "") {
            $name = $row->email ?? "-";
        }
        $items[] = "<span class='badge bg-light text-dark me-1 mb5'>" . esc($name) . "</span>";
    }

    return implode("", $items);
};

$doc_button = function ($doc_id, string $section, string $label) use ($document_access) {
    if (empty($doc_id)) {
        return "<span class='badge bg-light text-dark me-1 mb5'>" . esc($label) . ": missing</span>";
    }

    if (empty($document_access[$section])) {
        return "<span class='badge bg-warning text-dark me-1 mb5'><i data-feather='lock' class='icon-12'></i> " . esc($label) . "</span>";
    }

    return "<a href='" . get_uri("tender_reports/download_bid_document/" . (int) $doc_id) . "' class='btn btn-default btn-sm me-1 mb5'>"
        . "<i data-feather='download' class='icon-14'></i> " . esc($label) . "</a>";
};

$milestone_label = function ($code) {
    $labels = [
        "release_at" => "Tender Release",
        "document_purchase_deadline" => "Document Purchase Deadline",
        "site_visit_at" => "Site Visit",
        "clarification_deadline" => "Clarification Deadline",
        "closing_at" => "Submission Deadline",
        "bid_opening_at" => "Bid Opening",
        "technical_eval_deadline" => "Technical Evaluation Deadline",
        "commercial_eval_deadline" => "Commercial Evaluation Deadline",
    ];

    $code = (string) $code;
    return $labels[$code] ?? ucwords(str_replace("_", " ", $code ?: "Milestone"));
};

$workflow_stage_options = [
    "bidding" => "Bid Submission / Bidding",
    "technical_3key" => "Bid Opening",
    "technical" => "Technical Evaluation",
    "commercial" => "Commercial Evaluation",
    "award_decision" => "Award Decision",
];

$default_open_until = date("Y-m-d\\TH:i", strtotime("+1 day"));
$opening_status = (string) ($opening_session->status ?? "");
$opening_ready_for_technical = in_array($opening_status, ["signed", "manual_accepted"], true)
    && (string) ($tender->status ?? "") === "closed"
    && (string) ($tender->workflow_stage ?? "") === "technical_3key";
$opening_signature_roles = ["chairman" => false, "secretary" => false, "itc_member" => false];
foreach ($opening_signatures as $signature) {
    if (!empty($signature->signed_at) && array_key_exists((string) ($signature->role ?? ""), $opening_signature_roles)) {
        $opening_signature_roles[(string) $signature->role] = true;
    }
}
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-report-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => "Tender Register Detail",
        "subtitle" => "Review tender timeline, teams, vendors, documents, evaluations, clarifications, and audit trail.",
        "icon" => "activity",
        "actions" => '<a href="' . esc(get_uri("tender_reports"), "attr") . '" class="btn btn-default gp-pro-btn gp-pro-btn-icon">'
            . '<i data-feather="arrow-left" class="icon-16"></i> Back to Tender Register'
            . '</a>'
    ]);
    ?>

    <div class="card gp-pro-card mb15 tender-report-hero">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-off mb5">Tender Register Detail</div>
                    <h2 class="mb5"><?php echo esc($tender->reference ?? "-"); ?> - <?php echo esc($tender->title ?? "-"); ?></h2>
                    <div><?php echo esc($tender->company_name ?? "-"); ?> / <?php echo esc($tender->department_name ?? "-"); ?></div>
                </div>
                <div class="text-end">
                    <?php echo $status_badge($tender->status ?? "-"); ?>
                    <div class="mt10"><?php echo $status_badge($tender->workflow_stage ?? "bidding"); ?></div>
                    <div class="mt10">
                        <a href="<?php echo get_uri("tender_procurement_inbox/form?tender_id=" . (int) $tender->id); ?>" class="btn btn-default btn-sm">
                            <i data-feather="calendar" class="icon-14"></i> Edit Schedule
                        </a>
                        <a href="<?php echo get_uri("tender_reports/bid_opening_form/" . (int) $tender->id); ?>" class="btn btn-default btn-sm" target="_blank">
                            <i data-feather="clipboard" class="icon-14"></i> Bid Opening Form
                        </a>
                    </div>
                </div>
            </div>

            <div class="row mt20">
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Invited Vendors</div>
                        <strong><?php echo (int) ($summary["invited_count"] ?? 0); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Submitted Bids</div>
                        <strong><?php echo (int) ($summary["submitted_count"] ?? 0); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Technical Accepted</div>
                        <strong><?php echo (int) ($summary["technical_accepted_count"] ?? 0); ?></strong>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb10">
                    <div class="tender-report-stat">
                        <div class="text-off">Commercial Finalized</div>
                        <strong><?php echo (int) ($summary["commercial_evaluation_count"] ?? 0); ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_override_workflow && (string) ($tender->status ?? "") !== "cancelled") { ?>
        <div class="card gp-pro-card mb15 tender-workflow-control">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
                    <div>
                        <h4 class="mb5">Workflow Stage Control</h4>
                        <div class="text-off">Use this only when procurement needs to reopen or manually move a delayed tender stage. Later stage dates are pushed forward automatically if needed.</div>
                    </div>
                    <i data-feather="unlock" class="icon-24"></i>
                </div>

                <?php echo form_open(get_uri("tender_reports/save_stage_override"), [
                    "id" => "tender-stage-override-form",
                    "class" => "general-form",
                    "role" => "form"
                ]); ?>
                    <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />

                    <div class="row align-items-end">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Open Workflow Stage</label>
                                <?php echo form_dropdown(
                                    "workflow_stage",
                                    $workflow_stage_options,
                                    $tender->workflow_stage ?? "bidding",
                                    "class='form-control select2'"
                                ); ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Stage Open Until</label>
                                <input type="datetime-local" name="open_until" class="form-control" value="<?php echo esc($default_open_until); ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Reason / Note</label>
                                <input type="text" name="reason" class="form-control" maxlength="255" placeholder="Reason for reopening this stage">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary w-100" title="Open selected stage">
                                    <i data-feather="unlock" class="icon-16"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php echo form_close(); ?>
            </div>
        </div>
    <?php } ?>

    <?php if ($can_override_workflow && (string) ($tender->status ?? "") === "closed" && (string) ($tender->workflow_stage ?? "") === "technical_3key") { ?>
        <div class="card gp-pro-card mb15 tender-opening-control">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
                    <div>
                        <h4 class="mb5">Procurement Proposal Review</h4>
                        <div class="text-off">After bid opening, procurement reviews both technical and commercial proposals before sending the tender to the concerned evaluation department.</div>
                    </div>
                    <span class="badge bg-light text-dark"><?php echo esc(ucwords(str_replace("_", " ", $opening_status ?: "pending"))); ?></span>
                </div>

                <div class="row">
                    <div class="col-md-4 mb10">
                        <div class="tender-report-stat">
                            <div class="text-off">Chairman Signature</div>
                            <strong><?php echo $opening_signature_roles["chairman"] ? "Signed" : ($opening_status === "manual_accepted" ? "Bypassed" : "-"); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4 mb10">
                        <div class="tender-report-stat">
                            <div class="text-off">Secretary / Member</div>
                            <strong><?php echo ($opening_signature_roles["secretary"] && $opening_signature_roles["itc_member"]) ? "Signed" : ($opening_status === "manual_accepted" ? "Bypassed" : "-"); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4 mb10">
                        <div class="tender-report-stat">
                            <div class="text-off">Next Action</div>
                            <strong><?php echo $opening_ready_for_technical ? "Proposal Review" : "Waiting"; ?></strong>
                        </div>
                    </div>
                </div>

                <?php if ($opening_ready_for_technical) { ?>
                    <div class="proposal-review-documents table-responsive mt10 mb20">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Bid</th>
                                    <th>Technical Proposal</th>
                                    <th>Commercial Proposal</th>
                                    <th>Bank Guarantee</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $submitted_review_rows = 0;
                                foreach ($vendors as $vendor) {
                                    if (empty($vendor->bid_id)) {
                                        continue;
                                    }
                                    $submitted_review_rows++;
                                    $priced_doc_id = $vendor->commercial_priced_doc_id ?: $vendor->commercial_legacy_doc_id;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc($vendor->vendor_name ?? "-"); ?></strong><br><span class="text-off"><?php echo esc($vendor->email ?? "-"); ?></span></td>
                                        <td><?php echo $status_badge($vendor->bid_status ?? "submitted"); ?><br><span class="text-off"><?php echo $date_value($vendor->submitted_at ?? null); ?></span></td>
                                        <td><?php echo $doc_button($vendor->technical_doc_id ?? 0, "technical", "Technical"); ?></td>
                                        <td>
                                            <?php echo $doc_button($vendor->commercial_unpriced_doc_id ?? 0, "commercial_unpriced", "Without Price"); ?>
                                            <?php echo $doc_button($priced_doc_id, "commercial_priced", "With Price"); ?>
                                        </td>
                                        <td><?php echo $doc_button($vendor->bank_guarantee_doc_id ?? 0, "bank_guarantee", "Bank Guarantee"); ?></td>
                                    </tr>
                                <?php } ?>
                                <?php if (!$submitted_review_rows) { ?>
                                    <tr><td colspan="5" class="text-center text-off p20">No submitted bid documents are available for procurement review.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>

                <div class="row mt10">
                    <div class="col-md-6">
                        <div class="mb15">
                            <a href="<?php echo get_uri("tender_reports/bid_opening_form/" . (int) $tender->id); ?>" class="btn btn-default" target="_blank">
                                <i data-feather="clipboard" class="icon-16"></i> Review Generated Bid Opening Form
                            </a>
                        </div>
                        <?php echo form_open_multipart(get_uri("tender_reports/save_manual_bid_opening_form"), [
                            "id" => "manual-bid-opening-form",
                            "class" => "general-form",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                            <div class="form-group">
                                <label>Manual Signed Bid Opening Form (Fallback)</label>
                                <input type="file" name="manual_bid_opening_form" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-default">
                                <i data-feather="upload" class="icon-16"></i> Upload Manual Form
                            </button>
                        <?php echo form_close(); ?>
                    </div>
                    <div class="col-md-6">
                        <?php echo form_open(get_uri("tender_reports/start_technical_review"), [
                            "id" => "start-technical-review-form",
                            "class" => "general-form",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />
                            <input type="hidden" name="technical_proposals_reviewed" value="1" />
                            <input type="hidden" name="commercial_proposals_reviewed" value="1" />
                            <div class="form-group mb10">
                                <label class="d-flex align-items-start gap-2">
                                    <input type="checkbox" name="generated_bid_opening_form_confirmed" value="1" <?php echo $opening_ready_for_technical && $opening_status !== "manual_accepted" ? "required" : "disabled"; ?>>
                                    <span>Confirm generated bid opening form is correct</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Procurement Review Note</label>
                                <textarea name="proposal_review_note" class="form-control" rows="3" placeholder="Optional note before sending to evaluation team" <?php echo $opening_ready_for_technical ? "" : "disabled"; ?>></textarea>
                            </div>
                            <div class="form-group">
                                <label>Technical Review Open Until</label>
                                <input type="datetime-local" name="technical_end_at" class="form-control" value="<?php echo esc($default_open_until); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary" title="Start Technical Review" <?php echo $opening_ready_for_technical ? "" : "disabled"; ?>>
                                <i data-feather="play-circle" class="icon-16"></i> Confirm Generated Form & Start Technical Review
                            </button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="card gp-pro-card">
        <div class="card-body p0">
            <ul class="nav nav-tabs tender-report-tabs" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tender-report-overview" type="button" role="tab">Overview</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-rfq" type="button" role="tab">RFQ/RFP</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-timeline" type="button" role="tab">Timeline</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-vendors" type="button" role="tab">Vendors</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-evaluations" type="button" role="tab">Evaluations</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-communications" type="button" role="tab">Communications</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tender-report-audit" type="button" role="tab">Documents & Audit</button></li>
            </ul>

            <div class="tab-content p20">
                <div class="tab-pane fade show active tender-report-section" id="tender-report-overview" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb15">Tender Information</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr><th>Type</th><td><?php echo esc(strtoupper($tender->tender_type ?? "open")); ?></td></tr>
                                        <tr><th>Announcement</th><td><?php echo esc(ucfirst($tender->announcement ?? "-")); ?></td></tr>
                                        <tr><th>Evaluation Method</th><td><?php echo esc(ucwords(str_replace("_", " ", $tender->evaluation_method ?? "-"))); ?></td></tr>
                                        <tr><th>Weights</th><td>Technical <?php echo (int) ($tender->technical_weight ?? 70); ?>% / Commercial <?php echo (int) ($tender->commercial_weight ?? 30); ?>%</td></tr>
                                        <tr><th>Budget</th><td><?php echo $money_value($tender->budget_omr ?? null); ?></td></tr>
                                        <tr><th>Tender Fees</th><td><?php echo $money_value($tender->tender_fee ?? null); ?></td></tr>
                                        <tr><th>Awarded Vendor</th><td><?php echo esc($tender->award_vendor_name ?? "-"); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="mb15">Key Dates</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr><th>Release</th><td><?php echo $date_value($tender->release_at ?? $tender->published_at ?? null); ?></td></tr>
                                        <tr><th>Document Purchase Deadline</th><td><?php echo $date_value($tender->document_purchase_deadline ?? null); ?></td></tr>
                                        <tr><th>Site Visit Date / Deadline</th><td><?php echo $date_value($tender->site_visit_at ?? null); ?></td></tr>
                                        <tr><th>Site Visit Location</th><td><?php echo esc($tender->site_visit_location ?? "-"); ?></td></tr>
                                        <tr><th>Site Visit Attendance</th><td><?php echo !empty($tender->site_visit_mandatory) ? "<span class='badge bg-warning text-dark'>Mandatory</span>" : "<span class='badge bg-light text-dark'>Optional</span>"; ?></td></tr>
                                        <tr><th>Site Visit Instructions</th><td><?php echo nl2br(esc($tender->site_visit_instructions ?? "-")); ?></td></tr>
                                        <tr><th>Clarification Deadline</th><td><?php echo $date_value($tender->clarification_deadline ?? null); ?></td></tr>
                                        <tr><th>Submission Deadline</th><td><?php echo $date_value($tender->closing_at ?? null); ?></td></tr>
                                        <tr><th>Bid Opening</th><td><?php echo $date_value($tender->bid_opening_at ?? null); ?></td></tr>
                                        <tr><th>LOA Issued</th><td><?php echo $date_value($tender->loa_issued_at ?? null); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <h4 class="mb15 mt15">Team Assignment</h4>
                    <div class="row">
                        <div class="col-md-6 mb10"><strong>Technical Evaluation Team</strong><br><?php echo $team_list("technical_evaluator"); ?></div>
                        <div class="col-md-6 mb10"><strong>Commercial Evaluation Team</strong><br><?php echo $team_list("commercial_evaluator"); ?></div>
                        <div class="col-md-4 mb10"><strong>Chairman</strong><br><?php echo $team_list("chairman"); ?></div>
                        <div class="col-md-4 mb10"><strong>Secretary</strong><br><?php echo $team_list("secretary"); ?></div>
                        <div class="col-md-4 mb10"><strong>ITC Members</strong><br><?php echo $team_list("itc_member"); ?></div>
                    </div>

                    <h4 class="mb15 mt15">Bid Document Visibility</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Visible To</th>
                                    <th>Current Gate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Technical Proposal</td><td>Technical evaluators, procurement, and admin after the single 3-key bid opening is completed.</td><td><?php echo !empty($document_access["technical"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Commercial Proposal Without Price</td><td>Commercial evaluators, procurement, and admin after the single 3-key bid opening is completed.</td><td><?php echo !empty($document_access["commercial_unpriced"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Commercial Proposal With Price</td><td>Commercial evaluators, procurement, and admin after the single 3-key bid opening is completed.</td><td><?php echo !empty($document_access["commercial_priced"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Bank Guarantee Documents</td><td>Commercial evaluators, procurement, and admin after the single 3-key bid opening is completed.</td><td><?php echo !empty($document_access["bank_guarantee"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-rfq" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h4 class="mb15">RFQ / RFP Header</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr><th>Reference Number</th><td><?php echo esc($rfq_detail->rfq_no ?? "-"); ?></td></tr>
                                        <tr><th>Request Date</th><td><?php echo !empty($rfq_detail->rfq_date) ? format_to_date($rfq_detail->rfq_date, false) : "-"; ?></td></tr>
                                        <tr><th>PR No</th><td><?php echo esc($rfq_detail->pr_no ?? "-"); ?></td></tr>
                                        <tr><th>Delivery Location</th><td><?php echo esc($rfq_detail->delivery_location ?? "-"); ?></td></tr>
                                        <tr><th>INCOTERM</th><td><?php echo esc($rfq_detail->incoterm ?? "-"); ?></td></tr>
                                        <tr><th>Material Required On</th><td><?php echo !empty($rfq_detail->material_required_on) ? format_to_date($rfq_detail->material_required_on, false) : "-"; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h4 class="mb15">Terms, Notes & Enclosures</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <tbody>
                                        <tr><th>Terms Reference</th><td><?php echo esc($rfq_detail->terms_reference ?? "-"); ?></td></tr>
                                        <tr><th>Notes</th><td><?php echo nl2br(esc($rfq_detail->notes ?? "-")); ?></td></tr>
                                        <tr><th>Enclosures</th><td><?php echo nl2br(esc($rfq_detail->enclosures ?? "-")); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <h4 class="mb15 mt15">Item Lines</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Sr No</th>
                                    <th>Description</th>
                                    <th>UOM</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Brand</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rfq_items) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No RFQ item lines recorded.</td></tr>
                                <?php } ?>
                                <?php foreach ($rfq_items as $item) { ?>
                                    <tr>
                                        <td><?php echo esc($item->sr_no ?? "-"); ?></td>
                                        <td><?php echo esc($item->description ?? "-"); ?></td>
                                        <td><?php echo esc($item->uom ?? "-"); ?></td>
                                        <td><?php echo $item->qty !== null ? number_format((float) $item->qty, 3) : "-"; ?></td>
                                        <td><?php echo $item->unit_price !== null ? number_format((float) $item->unit_price, 3) : "-"; ?></td>
                                        <td><?php echo esc($item->brand ?? "-"); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-timeline" role="tabpanel">
                    <div class="tender-timeline">
                        <?php foreach ($timeline as $item) { ?>
                            <div class="tender-timeline-item tender-timeline-<?php echo esc($item["status"] ?? "pending"); ?>">
                                <div class="tender-timeline-dot"></div>
                                <div class="tender-timeline-content">
                                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <h5 class="mb5"><?php echo esc($item["title"]); ?></h5>
                                            <div class="text-off"><?php echo esc($item["description"]); ?></div>
                                        </div>
                                        <div><?php echo $timeline_badge($item["status"] ?? "pending"); ?></div>
                                    </div>
                                    <div class="row mt10">
                                        <div class="col-md-4"><strong>Start:</strong> <?php echo $date_value($item["start_at"] ?? null); ?></div>
                                        <div class="col-md-4"><strong>End:</strong> <?php echo $date_value($item["end_at"] ?? null); ?></div>
                                        <div class="col-md-4"><strong>Duration:</strong> <?php echo esc($item["duration"] ?? "-"); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-vendors" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Invite</th>
                                    <th>Participation Approval</th>
                                    <th>Bid</th>
                                    <th>Submitted</th>
                                    <th>Amount</th>
                                    <th>Documents</th>
                                    <th>Technical</th>
                                    <th>Commercial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$vendors) { ?>
                                    <tr><td colspan="9" class="text-center text-off p20">No vendor participation recorded.</td></tr>
                                <?php } ?>
                                <?php foreach ($vendors as $vendor) {
                                    $priced_doc_id = $vendor->commercial_priced_doc_id ?: $vendor->commercial_legacy_doc_id;
                                    $participation_status = strtolower((string) ($vendor->invite_status ?? ""));
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc($vendor->vendor_name ?? "-"); ?></strong><br><span class="text-off"><?php echo esc($vendor->email ?? "-"); ?></span></td>
                                        <td><?php echo $status_badge($vendor->invite_status ?? "-"); ?><br><span class="text-off"><?php echo $date_value($vendor->invited_at ?? null); ?></span></td>
                                        <td>
                                            <?php if ($participation_status === "pending_approval") { ?>
                                                <?php echo $status_badge("pending_approval"); ?>
                                                <?php if ($can_override_workflow) { ?>
                                                    <div class="mt10 d-flex flex-wrap gap-1">
                                                        <button
                                                            type="button"
                                                            class="btn btn-success btn-sm tender-vendor-participation-action"
                                                            data-action-url="<?php echo get_uri("tender_reports/approve_vendor_participation"); ?>"
                                                            data-tender-id="<?php echo (int) $tender->id; ?>"
                                                            data-vendor-id="<?php echo (int) $vendor->vendor_id; ?>">
                                                            <i data-feather="check" class="icon-14"></i> Approve participation
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-danger btn-sm tender-vendor-participation-action"
                                                            data-action-url="<?php echo get_uri("tender_reports/reject_vendor_participation"); ?>"
                                                            data-tender-id="<?php echo (int) $tender->id; ?>"
                                                            data-vendor-id="<?php echo (int) $vendor->vendor_id; ?>">
                                                            <i data-feather="x" class="icon-14"></i> Reject participation
                                                        </button>
                                                    </div>
                                                <?php } ?>
                                            <?php } elseif (in_array($participation_status, ["approved", "rejected", "declined"], true)) { ?>
                                                <?php echo $status_badge($participation_status); ?>
                                            <?php } elseif (in_array($participation_status, ["sent", "delivered", "opened"], true)) { ?>
                                                <?php echo $status_badge("approved"); ?>
                                            <?php } else { ?>
                                                <span class="text-off">No request</span>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $status_badge($vendor->bid_status ?? "not submitted"); ?></td>
                                        <td><?php echo $date_value($vendor->submitted_at ?? null); ?></td>
                                        <td><?php echo $money_value($vendor->total_amount ?? null, $vendor->currency ?? "OMR"); ?></td>
                                        <td>
                                            <?php echo $doc_button($vendor->technical_doc_id ?? 0, "technical", "Technical"); ?>
                                            <?php echo $doc_button($vendor->commercial_unpriced_doc_id ?? 0, "commercial_unpriced", "Without Price"); ?>
                                            <?php echo $doc_button($priced_doc_id, "commercial_priced", "With Price"); ?>
                                            <?php echo $doc_button($vendor->bank_guarantee_doc_id ?? 0, "bank_guarantee", "Bank Guarantee"); ?>
                                        </td>
                                        <td>
                                            <?php echo $status_badge($vendor->technical_decision ?? $vendor->bid_status ?? "-"); ?>
                                            <div class="text-off mt5">Score: <?php echo $vendor->technical_score !== null ? number_format((float) $vendor->technical_score, 3) : "-"; ?></div>
                                            <div class="text-off"><?php echo esc(trim((string) ($vendor->technical_evaluator_name ?? "")) ?: "-"); ?></div>
                                        </td>
                                        <td>
                                            <?php echo $status_badge($vendor->commercial_decision ?? "-"); ?>
                                            <div class="text-off mt5">Score: <?php echo $vendor->commercial_score !== null ? number_format((float) $vendor->commercial_score, 3) : "-"; ?></div>
                                            <div class="text-off"><?php echo esc(trim((string) ($vendor->commercial_evaluator_name ?? "")) ?: "-"); ?></div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-evaluations" role="tabpanel">
                    <div class="tender-weighted-ranking mb25">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
                            <div>
                                <h4 class="mb5">Weighted Evaluation Ranking</h4>
                                <div class="text-off">Final score is calculated from the procurement technical and commercial weights for this tender.</div>
                            </div>
                            <?php if (!empty($weighted_evaluation_scores)) {
                                $first_weight_row = $weighted_evaluation_scores[0];
                            ?>
                                <span class="badge bg-light text-dark">
                                    Technical <?php echo $score_value($first_weight_row["technical_weight"] ?? null, 2); ?>% /
                                    Commercial <?php echo $score_value($first_weight_row["commercial_weight"] ?? null, 2); ?>%
                                </span>
                            <?php } ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped tender-weighted-score-table">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Vendor</th>
                                        <th>Technical Raw</th>
                                        <th>Technical Weighted</th>
                                        <th>Commercial Raw</th>
                                        <th>Commercial Weighted</th>
                                        <th>Final Weighted Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$weighted_evaluation_scores) { ?>
                                        <tr><td colspan="8" class="text-center text-off p20">No submitted bids are ready for weighted evaluation.</td></tr>
                                    <?php } ?>
                                    <?php foreach ($weighted_evaluation_scores as $index => $row) {
                                        $is_complete = !empty($row["is_complete"]);
                                    ?>
                                        <tr class="<?php echo $is_complete ? "weighted-score-complete" : "weighted-score-incomplete"; ?>">
                                            <td><span class="badge bg-light text-dark">#<?php echo $index + 1; ?></span></td>
                                            <td>
                                                <strong><?php echo esc($row["vendor_name"] ?? "-"); ?></strong>
                                                <div class="text-off">Bid #<?php echo (int) ($row["bid_id"] ?? 0); ?></div>
                                            </td>
                                            <td>
                                                <strong><?php echo $score_value($row["technical_score"] ?? null); ?></strong>
                                                <div class="text-off">of <?php echo $score_value($row["technical_score_max"] ?? null); ?> (<?php echo $percent_value($row["technical_percent"] ?? null); ?>)</div>
                                            </td>
                                            <td>
                                                <strong><?php echo $score_value($row["technical_weighted"] ?? null); ?></strong>
                                                <div class="text-off">of <?php echo $score_value($row["technical_weight"] ?? null); ?> points</div>
                                            </td>
                                            <td>
                                                <strong><?php echo $score_value($row["commercial_score"] ?? null); ?></strong>
                                                <div class="text-off">of <?php echo $score_value($row["commercial_score_max"] ?? null); ?> (<?php echo $percent_value($row["commercial_percent"] ?? null); ?>)</div>
                                            </td>
                                            <td>
                                                <strong><?php echo $score_value($row["commercial_weighted"] ?? null); ?></strong>
                                                <div class="text-off">of <?php echo $score_value($row["commercial_weight"] ?? null); ?> points</div>
                                            </td>
                                            <td>
                                                <strong class="final-weighted-score"><?php echo $score_value($row["final_weighted_score"] ?? null); ?></strong>
                                                <div class="text-off">of <?php echo $score_value($row["max_weighted_score"] ?? null); ?> points</div>
                                            </td>
                                            <td>
                                                <?php echo $is_complete ? "<span class='badge bg-success'>Complete</span>" : "<span class='badge bg-warning text-dark'>Waiting for both evaluations</span>"; ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <h4 class="mb15">Technical Evaluation Summary</h4>
                    <?php echo view("tender_reports/evaluation_table", ["rows" => $technical_evaluations, "evaluation_attachments" => $technical_evaluation_attachments ?? [], "attachments_label" => "Technical Findings", "status_badge" => $status_badge, "date_value" => $date_value]); ?>

                    <h4 class="mb15 mt20">Commercial Evaluation Summary</h4>
                    <?php echo view("tender_reports/evaluation_table", ["rows" => $commercial_evaluations, "evaluation_attachments" => $commercial_evaluation_attachments ?? [], "attachments_label" => "Commercial Findings", "status_badge" => $status_badge, "date_value" => $date_value]); ?>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-communications" role="tabpanel">
                    <div class="tender-update-composer mb20">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb15">
                            <div>
                                <h4 class="mb5">Publish Group Update</h4>
                                <div class="text-off">Circulars and addenda are visible to all vendors who can access this tender.</div>
                            </div>
                            <i data-feather="send" class="icon-24"></i>
                        </div>

                        <?php echo form_open_multipart(get_uri("tender_reports/save_update"), [
                            "id" => "tender-group-update-form",
                            "class" => "general-form",
                            "role" => "form"
                        ]); ?>
                            <input type="hidden" name="tender_id" value="<?php echo (int) $tender->id; ?>" />

                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Update Type</label>
                                        <?php echo form_dropdown(
                                            "update_type",
                                            ["circular" => "Circular", "addendum" => "Addendum"],
                                            "circular",
                                            "class='form-control select2'"
                                        ); ?>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="form-group">
                                        <label>Subject</label>
                                        <input type="text" name="subject" class="form-control" required maxlength="255">
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label>Message</label>
                                        <textarea name="message" class="form-control" rows="4" required></textarea>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Attachment</label>
                                        <input type="file" name="update_files[]" class="form-control" multiple>
                                        <small class="form-text text-muted">Optional files visible to all tender participants.</small>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i data-feather="send" class="icon-16"></i> Publish Update
                            </button>
                        <?php echo form_close(); ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Vendor</th>
                                    <th>Subject / Message</th>
                                    <th>Visible</th>
                                    <th>By</th>
                                    <th>When</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$communications) { ?>
                                    <tr><td colspan="7" class="text-center text-off p20">No clarifications, circulars, addenda, or site visit notices yet.</td></tr>
                                <?php } ?>
                                <?php foreach ($communications as $item) {
                                    $communication_type = strtolower((string) ($item->type ?? ""));
                                    $item_attachments = $communication_attachments[(int) ($item->id ?? 0)] ?? [];
                                    $is_team_request = in_array($communication_type, ["technical_clarification_request", "commercial_clarification_request"], true) && !(int) ($item->parent_id ?? 0);
                                    $reply_visibility = $communication_type === "commercial_clarification_request" ? "commercial" : "technical";
                                    $reply_team_label = $reply_visibility === "commercial" ? "Commercial Team" : "Technical Team";
                                    $reply_target_id = "internal-clarification-reply-" . (int) ($item->id ?? 0);
                                    $can_show_team_reply = $can_reply_clarifications && $is_team_request;

                                    if (in_array($communication_type, ["technical_clarification_request", "technical_clarification_response"], true)) {
                                        $visible_badge = "<span class='badge bg-info text-dark'>Technical team</span>";
                                    } elseif (in_array($communication_type, ["commercial_clarification_request", "commercial_clarification_response"], true)) {
                                        $visible_badge = "<span class='badge bg-primary'>Commercial team</span>";
                                    } else {
                                        $visible_badge = (int) ($item->is_vendor_visible ?? 0) ? "<span class='badge bg-success'>Vendor visible</span>" : "<span class='badge bg-secondary'>Internal</span>";
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo esc(ucwords(str_replace("_", " ", $item->type ?? "-"))); ?></td>
                                        <td><?php echo esc($item->vendor_name ?? "All participants"); ?></td>
                                        <td>
                                            <strong><?php echo esc($item->subject ?? "-"); ?></strong>
                                            <div class="text-off mt5"><?php echo nl2br(esc($item->message ?? "")); ?></div>
                                            <?php if ($item_attachments) { ?>
                                                <div class="communication-attachments mt10">
                                                    <?php foreach ($item_attachments as $attachment) { ?>
                                                        <a href="<?php echo get_uri("tender_reports/download_update_attachment/" . (int) $attachment->id); ?>" class="badge bg-light text-dark me-1 mb5">
                                                            <i data-feather="paperclip" class="icon-12"></i>
                                                            <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $visible_badge; ?></td>
                                        <td><?php echo esc($item->created_by_name ?: "-"); ?></td>
                                        <td><?php echo $date_value($item->published_at ?? $item->created_at ?? null); ?></td>
                                        <td>
                                            <?php if ($can_show_team_reply) { ?>
                                                <button type="button"
                                                    class="btn btn-default btn-sm"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#<?php echo $reply_target_id; ?>"
                                                    aria-expanded="false"
                                                    aria-controls="<?php echo $reply_target_id; ?>">
                                                    <i data-feather="message-square" class="icon-14"></i>
                                                    Reply
                                                </button>
                                            <?php } else { ?>
                                                <span class="text-off">-</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                    <?php if ($can_show_team_reply) { ?>
                                        <tr class="internal-clarification-reply-row">
                                            <td colspan="7" class="p0 border-top-0">
                                                <div class="collapse" id="<?php echo $reply_target_id; ?>">
                                                    <div class="internal-clarification-reply-box">
                                                        <?php echo form_open_multipart(get_uri("tender_clarifications/save_reply"), [
                                                            "class" => "general-form internal-clarification-reply-form",
                                                            "role" => "form"
                                                        ]); ?>
                                                            <input type="hidden" name="communication_id" value="<?php echo (int) ($item->id ?? 0); ?>" />
                                                            <input type="hidden" name="visibility" value="<?php echo esc($reply_visibility); ?>" />

                                                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb10">
                                                                <div>
                                                                    <strong>Reply to <?php echo esc($reply_team_label); ?></strong>
                                                                    <span class="badge bg-secondary ms-2">Internal</span>
                                                                </div>
                                                                <span class="badge bg-light text-dark"><?php echo esc($item->vendor_name ?? "General tender question"); ?></span>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-8">
                                                                    <div class="form-group mb10">
                                                                        <label>Reply Message</label>
                                                                        <textarea name="message" class="form-control" rows="4" required></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="form-group mb10">
                                                                        <label>Attach Files</label>
                                                                        <input type="file" name="clarification_files[]" class="form-control" multiple>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <button type="submit" class="btn btn-primary">
                                                                <i data-feather="send" class="icon-16"></i>
                                                                Send Reply
                                                            </button>
                                                        <?php echo form_close(); ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="tab-pane fade tender-report-section" id="tender-report-audit" role="tabpanel">
                    <h4 class="mb15">Tender Documents</h4>
                    <div class="table-responsive mb20">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Type</th><th>Title</th><th>File</th><th>Time Limited</th><th>Uploaded By</th><th>Uploaded At</th></tr></thead>
                            <tbody>
                                <?php if (!$tender_documents) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No tender documents uploaded.</td></tr>
                                <?php } ?>
                                <?php foreach ($tender_documents as $doc) { ?>
                                    <tr>
                                        <td><?php echo esc($doc->doc_type ?? "-"); ?></td>
                                        <td><?php echo esc($doc->title ?? "-"); ?></td>
                                        <td><?php echo esc($doc->original_name ?? "-"); ?></td>
                                        <td><?php echo (int) ($doc->time_limited ?? 0) ? "Yes (" . (int) ($doc->expires_in_hours ?? 0) . "h)" : "No"; ?></td>
                                        <td><?php echo esc($doc->uploaded_by_name ?: "-"); ?></td>
                                        <td><?php echo $date_value($doc->created_at ?? null); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mb15">Extensions</h4>
                    <div class="table-responsive mb20">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Milestone</th><th>Old Date</th><th>New Date</th><th>Status</th><th>Reason</th><th>By</th></tr></thead>
                            <tbody>
                                <?php if (!$extensions) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No extension records.</td></tr>
                                <?php } ?>
                                <?php foreach ($extensions as $extension) { ?>
                                    <tr>
                                        <td><?php echo esc($milestone_label($extension->milestone_code ?? "closing_at")); ?></td>
                                        <td><?php echo $date_value($extension->old_close_at ?? null); ?></td>
                                        <td><?php echo $date_value($extension->new_close_at ?? null); ?></td>
                                        <td><?php echo $status_badge($extension->status ?? "-"); ?></td>
                                        <td><?php echo nl2br(esc($extension->reason ?? "-")); ?></td>
                                        <td><?php echo esc($extension->created_by_name ?: "-"); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mb15">Workflow History</h4>
                    <div class="table-responsive mb20">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Status</th>
                                    <th>Stage</th>
                                    <th>Open Until</th>
                                    <th>Reason / Details</th>
                                    <th>By</th>
                                    <th>When</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$workflow_history) { ?>
                                    <tr><td colspan="7" class="text-center text-off p20">No workflow history records.</td></tr>
                                <?php } ?>
                                <?php foreach ($workflow_history as $history) { ?>
                                    <tr>
                                        <td><?php echo esc(ucwords(str_replace("_", " ", $history->action_type ?? "-"))); ?></td>
                                        <td>
                                            <?php echo esc(ucwords(str_replace("_", " ", $history->from_status ?? "-"))); ?>
                                            <i data-feather="arrow-right" class="icon-14"></i>
                                            <?php echo esc(ucwords(str_replace("_", " ", $history->to_status ?? "-"))); ?>
                                        </td>
                                        <td>
                                            <?php echo esc($workflow_stage_options[$history->from_stage] ?? ucwords(str_replace("_", " ", $history->from_stage ?? "-"))); ?>
                                            <i data-feather="arrow-right" class="icon-14"></i>
                                            <?php echo esc($workflow_stage_options[$history->to_stage] ?? ucwords(str_replace("_", " ", $history->to_stage ?? "-"))); ?>
                                        </td>
                                        <td><?php echo $date_value($history->open_until ?? null); ?></td>
                                        <td>
                                            <strong><?php echo esc($history->reason ?: "-"); ?></strong>
                                            <div class="text-off mt5"><?php echo nl2br(esc($history->details ?? "")); ?></div>
                                        </td>
                                        <td><?php echo esc(trim((string) ($history->created_by_name ?? "")) ?: ($history->created_by_email ?? "-")); ?></td>
                                        <td><?php echo $date_value($history->created_at ?? null); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <h4 class="mb15">3-Key Opening Audit</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Session</th><th>Stage</th><th>Status</th><th>Generated</th><th>Unlocked</th><th>Signed</th><th>Member</th><th>Role</th><th>Valid</th><th>IP</th></tr></thead>
                            <tbody>
                                <?php if (!$opening_audit) { ?>
                                    <tr><td colspan="10" class="text-center text-off p20">No 3-key opening audit records.</td></tr>
                                <?php } ?>
                                <?php foreach ($opening_audit as $audit) { ?>
                                    <tr>
                                        <td>#<?php echo (int) ($audit->opening_id ?? 0); ?></td>
                                        <td>Bid Opening</td>
                                        <td><?php echo $status_badge($audit->opening_status ?? "-"); ?></td>
                                        <td><?php echo $date_value($audit->generated_at ?? null); ?></td>
                                        <td><?php echo $date_value($audit->unlocked_at ?? null); ?></td>
                                        <td><?php echo $date_value($audit->entry_signed_at ?? $audit->signed_at ?? null); ?></td>
                                        <td><?php echo esc(trim((string) ($audit->signature_name ?? "")) ?: (trim((string) ($audit->member_name ?? "")) ?: ($audit->member_email ?? "-"))); ?></td>
                                        <td><?php echo esc(ucwords(str_replace("_", " ", $audit->role ?? "-"))); ?></td>
                                        <td><?php echo (int) ($audit->is_valid ?? 0) ? "<span class='badge bg-success'>Yes</span>" : "<span class='badge bg-secondary'>No</span>"; ?></td>
                                        <td><?php echo esc($audit->ip_address ?? "-"); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") {
        feather.replace();
    }

    var hashTab = window.location.hash ? document.querySelector('[data-bs-target="' + window.location.hash + '"]') : null;
    if (hashTab && window.bootstrap) {
        new bootstrap.Tab(hashTab).show();
    }

    $("#tender-group-update-form").appForm({
        isModal: false,
        onSuccess: function (response) {
            appAlert.success(response.message || "Update published.", {duration: 2000});
            setTimeout(function () {
                window.location.href = response.redirect_url || window.location.href;
                window.location.reload();
            }, 450);
        }
    });

    if ($(".internal-clarification-reply-form").length) {
        $(".internal-clarification-reply-form").appForm({
            isModal: false,
            onSuccess: function (response) {
                var communicationsUrl = <?php echo json_encode(get_uri("tender_reports/details/" . (int) $tender->id) . "#tender-report-communications"); ?>;
                appAlert.success(response.message || "Clarification reply sent.", {duration: 2000});
                setTimeout(function () {
                    if (window.location.href === communicationsUrl) {
                        window.location.reload();
                    } else {
                        window.location.href = communicationsUrl;
                    }
                }, 450);
            }
        });
    }

    if ($("#tender-stage-override-form").length) {
        $("#tender-stage-override-form").appForm({
            isModal: false,
            beforeAjaxSubmit: function () {
                return confirm("Open the selected workflow stage for this tender?");
            },
            onSuccess: function (response) {
                appAlert.success(response.message || "Workflow stage updated.", {duration: 2000});
                setTimeout(function () {
                    window.location.href = response.redirect_url || window.location.href;
                    window.location.reload();
                }, 450);
            }
        });
    }

    $("#manual-bid-opening-form").appForm({
        isModal: false,
        onSuccess: function (response) {
            appAlert.success(response.message || "Manual form uploaded.", {duration: 2000});
            setTimeout(function () {
                window.location.href = response.redirect_url || window.location.href;
                window.location.reload();
            }, 450);
        }
    });

    $("#start-technical-review-form").appForm({
        isModal: false,
        beforeAjaxSubmit: function () {
            return confirm("Confirm the bid opening form is correct, then send this tender to technical evaluation?");
        },
        onSuccess: function (response) {
            appAlert.success(response.message || "Procurement proposal review recorded.", {duration: 2000});
            setTimeout(function () {
                window.location.href = response.redirect_url || window.location.href;
                window.location.reload();
            }, 450);
        }
    });

    $(document).on("click", ".tender-vendor-participation-action", function () {
        var $button = $(this);
        var isReject = ($button.data("action-url") || "").indexOf("reject_vendor_participation") !== -1;
        var message = isReject ? "Reject this vendor participation request?" : "Approve this vendor participation request?";

        if (!confirm(message)) {
            return;
        }

        $button.prop("disabled", true);
        $.ajax({
            url: $button.data("action-url"),
            type: "POST",
            dataType: "json",
            data: {
                tender_id: $button.data("tender-id"),
                vendor_id: $button.data("vendor-id")
            },
            success: function (response) {
                if (response && response.success) {
                    appAlert.success(response.message || "Participation decision saved.", {duration: 2000});
                    setTimeout(function () {
                        window.location.href = response.redirect_url || window.location.href;
                        window.location.reload();
                    }, 450);
                } else {
                    $button.prop("disabled", false);
                    appAlert.error((response && response.message) || "Participation decision failed.");
                }
            },
            error: function () {
                $button.prop("disabled", false);
                appAlert.error("Participation decision failed.");
            }
        });
    });

    $('button[data-bs-toggle="tab"]').on("shown.bs.tab", function () {
        if (typeof feather !== "undefined") {
            feather.replace();
        }
    });
});
</script>

<style>
.tender-report-hero h2 {
    font-size: 23px;
    line-height: 1.25;
}
.tender-report-stat {
    border: 1px solid #e6edf8;
    background: #fbfdff;
    border-radius: 12px;
    padding: 14px;
    min-height: 78px;
}
.tender-report-stat strong {
    display: block;
    margin-top: 4px;
    font-size: 24px;
    color: #1f2a44;
}
.tender-report-tabs {
    padding: 0 14px;
    background: #fbfdff;
}
.tender-report-tabs .nav-link {
    border-radius: 0;
    font-weight: 600;
}
.tender-report-section {
    animation: gpProFadeInUp 0.28s ease both;
}
.tender-update-composer {
    border: 1px solid #e6edf8;
    background: #fbfdff;
    border-radius: 12px;
    padding: 16px;
}
.internal-clarification-reply-box {
    border-top: 1px solid #dbe8f7;
    background: #f7fbff;
    padding: 16px;
}
.internal-clarification-reply-box textarea {
    min-height: 96px;
    resize: vertical;
}
.tender-workflow-control {
    border-left: 4px solid #2f66f2;
}
.tender-opening-control {
    border-left: 4px solid #1fa97a;
}
.tender-weighted-ranking {
    border: 1px solid #dce8f8;
    background: #fbfdff;
    border-radius: 10px;
    padding: 16px;
}
.tender-weighted-score-table th {
    background: #f4f7fb;
    color: #23324d;
}
.tender-weighted-score-table .final-weighted-score {
    color: #1f6f4a;
    font-size: 18px;
}
.weighted-score-incomplete {
    opacity: 0.82;
}
.tender-timeline {
    position: relative;
    margin-left: 10px;
}
.tender-timeline:before {
    content: "";
    position: absolute;
    top: 4px;
    bottom: 4px;
    left: 10px;
    width: 2px;
    background: #dbe5f2;
}
.tender-timeline-item {
    position: relative;
    padding-left: 38px;
    margin-bottom: 16px;
}
.tender-timeline-dot {
    position: absolute;
    left: 3px;
    top: 14px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #aab7cf;
    border: 3px solid #fff;
    box-shadow: 0 0 0 1px #dbe5f2;
}
.tender-timeline-completed .tender-timeline-dot {
    background: #1fa97a;
}
.tender-timeline-active .tender-timeline-dot {
    background: #2f66f2;
    animation: gpProSoftPulse 1.6s ease infinite;
}
.tender-timeline-cancelled .tender-timeline-dot {
    background: #df425a;
}
.tender-timeline-content {
    border: 1px solid #e6edf8;
    background: #fff;
    border-radius: 12px;
    padding: 14px;
}
</style>
