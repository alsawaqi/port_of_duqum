<div class="modal-body gp-pro-modal-body">
    <div class="mb15">
        <div class="fw-bold"><?php echo esc($model_info->visitor_name ?: app_lang("gate_pass_unnamed_visitor")); ?></div>
        <div class="text-muted small"><?php echo esc($model_info->id_number ?? "-"); ?></div>
    </div>

    <?php if (empty($history)): ?>
        <div class="alert alert-info mb0"><?php echo app_lang("no_records_found"); ?></div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb0">
                <thead>
                    <tr>
                        <th><?php echo app_lang("date"); ?></th>
                        <th><?php echo app_lang("action"); ?></th>
                        <th><?php echo app_lang("user"); ?></th>
                        <th><?php echo app_lang("reason"); ?></th>
                        <th><?php echo app_lang("ip"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row): ?>
                        <?php
                        $action = strtolower((string) ($row->action ?? ""));
                        if ($action === "reblocked") {
                            $action_label = app_lang("gate_pass_block_again");
                        } elseif ($action === "unblocked") {
                            $action_label = app_lang("gate_pass_unblocked");
                        } else {
                            $action_label = app_lang("blocked");
                        }
                        $who = trim((string) ($row->action_by_name ?? ""));
                        if ($who === "" && !empty($row->action_by)) {
                            $who = "#" . (int) $row->action_by;
                        }
                        ?>
                        <tr>
                            <td><?php echo !empty($row->action_at) ? format_to_datetime($row->action_at) : "-"; ?></td>
                            <td><?php echo esc($action_label); ?></td>
                            <td><?php echo esc($who ?: "-"); ?></td>
                            <td><?php echo !empty($row->reason) ? nl2br(esc($row->reason)) : "-"; ?></td>
                            <td><?php echo esc($row->ip_address ?: "-"); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer gp-pro-modal-footer">
    <button type="button" class="btn btn-default gp-pro-btn-secondary" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>
