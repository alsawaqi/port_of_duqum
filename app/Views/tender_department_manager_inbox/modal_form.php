<div class="modal-body">
    <h5 class="mb-3">Department Manager Review</h5>

    <div class="row">
        <div class="col-md-6">
            <div><strong>Reference:</strong> <?php echo esc($request->reference); ?></div>
            <div><strong>Company:</strong> <?php echo esc($request->company_name ?? "-"); ?></div>
            <div><strong>Department:</strong> <?php echo esc($request->department_name ?? "-"); ?></div>
            <div><strong>Requester:</strong> <?php echo esc($request->requester_name ?? "-"); ?></div>
            <div><strong>Request Date:</strong> <?php echo esc($request->request_date ?? "-"); ?></div>
        </div>

        <div class="col-md-6">
            <div><strong>Budget (OMR):</strong> <?php echo esc($request->budget_omr); ?></div>
            <div><strong>Fee (OMR):</strong> <?php echo esc($request->tender_fee); ?></div>
            <div><strong>Announcement:</strong> <?php echo esc($request->announcement); ?></div>
            <div><strong>Tender Type:</strong> <?php echo esc($request->tender_type); ?></div>
            <div><strong>Status:</strong> <span class="badge bg-secondary"><?php echo esc($request->status); ?></span></div>
        </div>
    </div>

    <hr>

    <div><strong>Subject:</strong> <?php echo esc($request->subject ?? "-"); ?></div>
    <div class="mt-2"><strong>Brief Description:</strong><br><?php echo nl2br(esc($request->brief_description ?? "")); ?></div>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <div><strong>Evaluation Method:</strong> <?php echo esc($request->evaluation_method); ?></div>
        </div>
        <div class="col-md-3">
            <div><strong>Tech Weight:</strong> <?php echo esc($request->technical_weight); ?></div>
        </div>
        <div class="col-md-3">
            <div><strong>Comm Weight:</strong> <?php echo esc($request->commercial_weight); ?></div>
        </div>
    </div>

    <hr>

    <div class="row">
    <div class="col-md-6">
        <div><strong>Department Manager:</strong> <?php echo esc($request->department_manager_name ?? "-"); ?></div>
        <div><strong>Manager Title:</strong> <?php echo esc($request->department_manager_title ?? "-"); ?></div>
    </div>

    <div class="col-md-6">
        <div><strong>Manager Signed At:</strong> <?php echo esc($request->department_manager_signed_at ?? "-"); ?></div>
    </div>
</div>

<?php if (!empty($request->department_manager_reject_comment)) { ?>
    <div class="mt-2">
        <strong>Previous Manager Reject Comment:</strong><br>
        <?php echo nl2br(esc($request->department_manager_reject_comment)); ?>
    </div>
<?php } ?>

    <?php if (($request->tender_type ?? "open") === "close") { ?>
        <hr>
        <div><strong>Invited Suppliers:</strong></div>
        <?php if (empty($selected_vendors)) { ?>
            <div class="text-muted">No invited suppliers selected.</div>
        <?php } else { ?>
            <ul class="mt-2">
                <?php foreach ($selected_vendors as $v) { ?>
                    <li><?php echo esc($v->vendor_name); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>
    <?php } ?>

    <hr>

    <div class="row">
        <div class="col-md-6">
            <div><strong>Technical Evaluation Team:</strong></div>
            <?php if (empty($team_members["technical_evaluator"])) { ?>
                <div class="text-muted">No technical team selected.</div>
            <?php } else { ?>
                <ul class="mt-2">
                    <?php foreach ($team_members["technical_evaluator"] as $u) { ?>
                        <li><?php echo esc($u->full_name); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>

        <div class="col-md-6">
            <div><strong>Commercial Evaluation Team:</strong></div>
            <?php if (empty($team_members["commercial_evaluator"])) { ?>
                <div class="text-muted">No commercial team selected.</div>
            <?php } else { ?>
                <ul class="mt-2">
                    <?php foreach ($team_members["commercial_evaluator"] as $u) { ?>
                        <li><?php echo esc($u->full_name); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </div>
    </div>

    <hr>
    <div><strong>ITT Chairman:</strong> <?php echo !empty($team_members["chairman"][0]) ? esc($team_members["chairman"][0]->full_name) : "-"; ?></div>
    <div><strong>ITT Secretary:</strong> <?php echo !empty($team_members["secretary"][0]) ? esc($team_members["secretary"][0]->full_name) : "-"; ?></div>

    <div class="mt-2"><strong>ITT Members:</strong></div>
    <?php if (empty($team_members["itc_member"])) { ?>
        <div class="text-muted">No ITT members selected.</div>
    <?php } else { ?>
        <ul class="mt-2">
            <?php foreach ($team_members["itc_member"] as $u) { ?>
                <li><?php echo esc($u->full_name); ?></li>
            <?php } ?>
        </ul>
    <?php } ?>

    <?php if (!empty($request->estimated_previous_amount) || !empty($request->estimated_previous_notes)) { ?>
        <hr>
        <div><strong>Estimated Previous Amount:</strong> <?php echo esc($request->estimated_previous_amount ?? "-"); ?></div>
        <div class="mt-2"><strong>Estimated Notes:</strong><br><?php echo nl2br(esc($request->estimated_previous_notes ?? "")); ?></div>
    <?php } ?>

    <?php if (!empty($approval_history)) { ?>
        <hr>
        <h6>Approval History</h6>

        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Decision</th>
                        <th>By</th>
                        <th>Date</th>
                        <th>Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approval_history as $a) { ?>
                        <tr>
                            <td><?php echo esc(ucwords(str_replace("_", " ", $a->stage ?? "-"))); ?></td>
                            <td><?php echo esc(ucfirst($a->decision ?? "-")); ?></td>
                            <td><?php echo esc($a->decided_by_name ?? "-"); ?></td>
                            <td><?php echo esc($a->decided_at ?? "-"); ?></td>
                            <td><?php echo nl2br(esc($a->comment ?? "")); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>