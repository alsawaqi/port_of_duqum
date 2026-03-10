<div id="page-content" class="page-wrapper clearfix">
    <div class="mb15">
        <a href="<?php echo get_uri('tender_technical_inbox'); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Technical Inbox
        </a>
    </div>

    <div class="card mb15">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb5">Technical Evaluation - <?php echo esc($tender->reference ?? '-'); ?></h3>
                <div class="text-off"><?php echo esc($tender->title ?? '-'); ?></div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <span class="badge bg-warning text-dark">Pending: <?php echo (int) $pending_count; ?></span>
                <span class="badge bg-success">Accepted: <?php echo (int) $accepted_count; ?></span>
                <span class="badge bg-danger">Rejected: <?php echo (int) $rejected_count; ?></span>
                <span class="badge bg-info text-dark">Max Score: <?php echo number_format((float) $stage_max_score, 3); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 mb10"><strong>Reference:</strong><br><?php echo esc($tender->reference ?? '-'); ?></div>
                <div class="col-md-3 mb10"><strong>Tender Type:</strong><br><?php echo esc($tender->tender_type ?? '-'); ?></div>
                <div class="col-md-3 mb10"><strong>Status:</strong><br><span class="badge bg-dark"><?php echo esc(ucfirst($tender->status ?? 'closed')); ?></span></div>
                <div class="col-md-3 mb10"><strong>Closed At:</strong><br><?php echo !empty($tender->closing_at) ? format_to_datetime($tender->closing_at) : '-'; ?></div>
            </div>

            <?php if ((int) $pending_count === 0) { ?>
                <div class="alert alert-success mt15 mb0">
                    All bids in this tender have already been technically finalized. This tender is now ready for the committee 3-key commercial opening.
                </div>
            <?php } else { ?>
                <div class="alert alert-info mt15 mb0">
                    Evaluate each vendor one by one. Once a bid is technically accepted or rejected by an evaluator, other technical evaluators can no longer change that bid.
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="card mb15">
        <div class="card-header">
            <h4 class="mb0">Bids Pending Your Action</h4>
        </div>
        <div class="card-body p0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Submitted At</th>
                            <th>Technical Document</th>
                            <th>Status</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_bids)) { ?>
                            <tr>
                                <td colspan="5" class="text-center text-off p20">No pending technical bids for your action.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($pending_bids as $bid) { ?>
                                <tr>
                                    <td><?php echo esc($bid->vendor_name ?? '-'); ?></td>
                                    <td><?php echo !empty($bid->submitted_at) ? format_to_datetime($bid->submitted_at) : '-'; ?></td>
                                    <td>
                                        <?php if (!empty($bid->technical_doc_id)) { ?>
                                            <a href="<?php echo get_uri('tender_technical_inbox/download_bid_document/' . $bid->technical_doc_id); ?>" class="btn btn-default btn-sm">
                                                <i data-feather="download" class="icon-14"></i>
                                                Download
                                            </a>
                                        <?php } else { ?>
                                            <span class="text-off">No technical file</span>
                                        <?php } ?>
                                    </td>
                                    <td><span class="badge bg-warning text-dark">Pending</span></td>
                                    <td class="text-center">
                                        <?php echo modal_anchor(
                                            get_uri('tender_technical_inbox/bid_modal_form'),
                                            "<i data-feather='check-square' class='icon-16'></i>",
                                            [
                                                'title' => 'Evaluate Technical Bid',
                                                'class' => 'edit',
                                                'data-post-tender_id' => (int) $tender->id,
                                                'data-post-bid_id' => (int) $bid->id,
                                            ]
                                        ); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb15">
        <div class="card-header">
            <h4 class="mb0">My Finalized Decisions</h4>
        </div>
        <div class="card-body p0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Decision</th>
                            <th>Total Score</th>
                            <th>Finalized At</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($my_finalized_bids)) { ?>
                            <tr>
                                <td colspan="5" class="text-center text-off p20">You have not finalized any bid in this tender yet.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($my_finalized_bids as $bid) {
                                $decision_class = strtolower((string) $bid->status) === 'accepted' ? 'bg-success' : 'bg-danger';
                            ?>
                                <tr>
                                    <td><?php echo esc($bid->vendor_name ?? '-'); ?></td>
                                    <td><span class="badge <?php echo $decision_class; ?>"><?php echo esc(ucfirst($bid->status ?? '-')); ?></span></td>
                                    <td><?php echo $bid->decision_total_score !== null ? number_format((float) $bid->decision_total_score, 3) : '-'; ?></td>
                                    <td><?php echo !empty($bid->decision_submitted_at) ? format_to_datetime($bid->decision_submitted_at) : '-'; ?></td>
                                    <td class="text-center">
                                        <?php echo modal_anchor(
                                            get_uri('tender_technical_inbox/bid_modal_form'),
                                            "<i data-feather='edit' class='icon-16'></i>",
                                            [
                                                'title' => 'Edit My Technical Evaluation',
                                                'class' => 'edit',
                                                'data-post-tender_id' => (int) $tender->id,
                                                'data-post-bid_id' => (int) $bid->id,
                                            ]
                                        ); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="mb0">Locked Decisions by Other Evaluators</h4>
        </div>
        <div class="card-body p0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Decision</th>
                            <th>Finalized By</th>
                            <th>Finalized At</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($locked_bids)) { ?>
                            <tr>
                                <td colspan="5" class="text-center text-off p20">No bids are locked by other evaluators.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($locked_bids as $bid) {
                                $decision_class = strtolower((string) $bid->status) === 'accepted' ? 'bg-success' : 'bg-danger';
                                $who = trim((string) ($bid->decision_evaluator_name ?? ''));
                                if ($who === '') {
                                    $who = $bid->decision_evaluator_email ?? 'Another evaluator';
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc($bid->vendor_name ?? '-'); ?></td>
                                    <td><span class="badge <?php echo $decision_class; ?>"><?php echo esc(ucfirst($bid->status ?? '-')); ?></span></td>
                                    <td><?php echo esc($who); ?></td>
                                    <td><?php echo !empty($bid->decision_submitted_at) ? format_to_datetime($bid->decision_submitted_at) : '-'; ?></td>
                                    <td class="text-center">
                                        <?php echo modal_anchor(
                                            get_uri('tender_technical_inbox/bid_modal_form'),
                                            "<i data-feather='eye' class='icon-16'></i>",
                                            [
                                                'title' => 'View Technical Evaluation',
                                                'class' => 'edit',
                                                'data-post-tender_id' => (int) $tender->id,
                                                'data-post-bid_id' => (int) $bid->id,
                                            ]
                                        ); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>