<?php
$summary = $summary ?? [];
$timeline = $timeline ?? [];
$teams = $teams ?? [];
$vendors = $vendors ?? [];
$technical_evaluations = $technical_evaluations ?? [];
$commercial_evaluations = $commercial_evaluations ?? [];
$communications = $communications ?? [];
$extensions = $extensions ?? [];
$opening_audit = $opening_audit ?? [];
$tender_documents = $tender_documents ?? [];
$document_access = $document_access ?? [];
$rfq_detail = $rfq_detail ?? null;
$rfq_items = $rfq_items ?? [];

$date_value = function ($value) {
    return !empty($value) ? format_to_datetime($value) : "-";
};

$money_value = function ($value, string $currency = "OMR") {
    if ($value === null || $value === "") {
        return "-";
    }

    return number_format((float) $value, 3) . " " . esc($currency);
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
        "committee_3key" => "bg-warning text-dark",
        "commercial" => "bg-primary",
        "award_decision" => "bg-success",
        "submitted" => "bg-info text-dark",
        "accepted" => "bg-success",
        "rejected" => "bg-danger",
        "opened" => "bg-info text-dark",
        "sent" => "bg-secondary",
        "declined" => "bg-danger",
    ][$status] ?? "bg-light text-dark";

    $label = [
        "technical_3key" => "3-Key Technical Opening",
        "committee_3key" => "3-Key Commercial Opening",
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
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page tender-report-page">
    <div class="mb15">
        <a href="<?php echo get_uri("tender_reports"); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i> Back to Tender Register
        </a>
    </div>

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
                        <a href="<?php echo get_uri("tender_reports/bid_opening_form/" . (int) $tender->id . "/technical"); ?>" class="btn btn-default btn-sm" target="_blank">
                            <i data-feather="clipboard" class="icon-14"></i> Technical Opening Form
                        </a>
                        <a href="<?php echo get_uri("tender_reports/bid_opening_form/" . (int) $tender->id . "/commercial"); ?>" class="btn btn-default btn-sm" target="_blank">
                            <i data-feather="file-text" class="icon-14"></i> Commercial Opening Form
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
                                <tr><td>Technical Proposal</td><td>Technical evaluators, procurement, and admin after technical 3-key opening.</td><td><?php echo !empty($document_access["technical"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Commercial Proposal Without Price</td><td>Commercial evaluators, procurement, and admin after commercial 3-key opening.</td><td><?php echo !empty($document_access["commercial_unpriced"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Commercial Proposal With Price</td><td>Commercial evaluators, procurement, and admin after commercial 3-key opening only.</td><td><?php echo !empty($document_access["commercial_priced"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
                                <tr><td>Bank Guarantee Documents</td><td>Commercial evaluators, procurement, and admin after commercial 3-key opening.</td><td><?php echo !empty($document_access["bank_guarantee"]) ? "<span class='badge bg-success'>Open</span>" : "<span class='badge bg-warning text-dark'>3-Key Locked</span>"; ?></td></tr>
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
                                        <tr><th>RFQ No</th><td><?php echo esc($rfq_detail->rfq_no ?? "-"); ?></td></tr>
                                        <tr><th>RFQ Date</th><td><?php echo !empty($rfq_detail->rfq_date) ? format_to_date($rfq_detail->rfq_date, false) : "-"; ?></td></tr>
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
                                    <tr><td colspan="8" class="text-center text-off p20">No vendor participation recorded.</td></tr>
                                <?php } ?>
                                <?php foreach ($vendors as $vendor) {
                                    $priced_doc_id = $vendor->commercial_priced_doc_id ?: $vendor->commercial_legacy_doc_id;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc($vendor->vendor_name ?? "-"); ?></strong><br><span class="text-off"><?php echo esc($vendor->email ?? "-"); ?></span></td>
                                        <td><?php echo $status_badge($vendor->invite_status ?? "-"); ?><br><span class="text-off"><?php echo $date_value($vendor->invited_at ?? null); ?></span></td>
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
                    <h4 class="mb15">Technical Evaluation Summary</h4>
                    <?php echo view("tender_reports/evaluation_table", ["rows" => $technical_evaluations, "status_badge" => $status_badge, "date_value" => $date_value]); ?>

                    <h4 class="mb15 mt20">Commercial Evaluation Summary</h4>
                    <?php echo view("tender_reports/evaluation_table", ["rows" => $commercial_evaluations, "status_badge" => $status_badge, "date_value" => $date_value]); ?>
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

                        <?php echo form_open(get_uri("tender_reports/save_update"), [
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

                            <div class="form-group">
                                <label>Message</label>
                                <textarea name="message" class="form-control" rows="4" required></textarea>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$communications) { ?>
                                    <tr><td colspan="6" class="text-center text-off p20">No clarifications, circulars, addenda, or site visit notices yet.</td></tr>
                                <?php } ?>
                                <?php foreach ($communications as $item) { ?>
                                    <tr>
                                        <td><?php echo esc(ucwords(str_replace("_", " ", $item->type ?? "-"))); ?></td>
                                        <td><?php echo esc($item->vendor_name ?? "All participants"); ?></td>
                                        <td>
                                            <strong><?php echo esc($item->subject ?? "-"); ?></strong>
                                            <div class="text-off mt5"><?php echo nl2br(esc($item->message ?? "")); ?></div>
                                        </td>
                                        <td><?php echo (int) ($item->is_vendor_visible ?? 0) ? "<span class='badge bg-success'>Vendor visible</span>" : "<span class='badge bg-secondary'>Internal</span>"; ?></td>
                                        <td><?php echo esc($item->created_by_name ?: "-"); ?></td>
                                        <td><?php echo $date_value($item->published_at ?? $item->created_at ?? null); ?></td>
                                    </tr>
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

                    <h4 class="mb15">3-Key Opening Audit</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead><tr><th>Session</th><th>Stage</th><th>Status</th><th>Generated</th><th>Unlocked</th><th>Member</th><th>Role</th><th>Valid</th><th>IP</th></tr></thead>
                            <tbody>
                                <?php if (!$opening_audit) { ?>
                                    <tr><td colspan="9" class="text-center text-off p20">No 3-key opening audit records.</td></tr>
                                <?php } ?>
                                <?php foreach ($opening_audit as $audit) { ?>
                                    <tr>
                                        <td>#<?php echo (int) ($audit->opening_id ?? 0); ?></td>
                                        <td><?php echo esc(ucwords(str_replace("_", " ", $audit->opening_stage ?? "-"))); ?></td>
                                        <td><?php echo $status_badge($audit->opening_status ?? "-"); ?></td>
                                        <td><?php echo $date_value($audit->generated_at ?? null); ?></td>
                                        <td><?php echo $date_value($audit->unlocked_at ?? null); ?></td>
                                        <td><?php echo esc(trim((string) ($audit->member_name ?? "")) ?: ($audit->member_email ?? "-")); ?></td>
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
