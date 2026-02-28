<div class="modal-body clearfix">
    <h4 class="mb15">
        <?php echo app_lang("review_history"); ?> - <?php echo esc((string)($application->reference ?? "-")); ?>
    </h4>

    <div class="table-responsive">
        <table class="table table-bordered table-hover mb0">
            <thead>
                <tr>
                    <th><?php echo app_lang("revision_no"); ?></th>
                    <th><?php echo app_lang("decision"); ?></th>
                    <th><?php echo app_lang("application_received_on"); ?></th>
                    <th><?php echo app_lang("application_completed_on"); ?></th>
                    <th><?php echo app_lang("application_reviewed_by"); ?></th>
                    <th><?php echo app_lang("status_change_reason"); ?></th>
                    <th><?php echo app_lang("remarks"); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($reviews)): ?>
                    <?php foreach ($reviews as $r): ?>
                        <tr>
                            <td><?php echo (int)($r->revision_no ?? 0); ?></td>
                            <td><?php echo esc((string)($r->decision ?? "-")); ?></td>
                            <td><?php echo !empty($r->received_at) ? format_to_datetime($r->received_at) : "-"; ?></td>
                            <td><?php echo !empty($r->completed_at) ? format_to_datetime($r->completed_at) : "-"; ?></td>
                            <td><?php echo esc(trim(($r->first_name ?? "") . " " . ($r->last_name ?? "")) ?: "-"); ?></td>
                            <td><?php echo nl2br(esc((string)($r->status_change_reason ?? "-"))); ?></td>
                            <td><?php echo nl2br(esc((string)($r->remarks ?? "-"))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center text-off"><?php echo app_lang("no_records_found"); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>