<div class="table-responsive">
    <table class="table table-bordered table-striped">
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
            <?php if (!empty($reviews)): foreach ($reviews as $r): ?>
                <tr>
                    <td><?php echo (int)($r->revision_no ?? 0); ?></td>
                    <td><?php echo esc((string)($r->decision ?? "-")); ?></td>
                    <td><?php echo !empty($r->received_at) ? format_to_datetime($r->received_at) : "-"; ?></td>
                    <td><?php echo !empty($r->completed_at) ? format_to_datetime($r->completed_at) : "-"; ?></td>
                    <td><?php echo esc(trim(($r->first_name ?? "") . " " . ($r->last_name ?? "")) ?: "-"); ?></td>
                    <td><?php echo nl2br(esc((string)($r->status_change_reason ?? "-"))); ?></td>
                    <td><?php echo nl2br(esc((string)($r->remarks ?? "-"))); ?></td>
                </tr>
            <?php endforeach; else: ?>
                <tr><td colspan="7" class="text-center text-off"><?php echo app_lang("no_records_found"); ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>