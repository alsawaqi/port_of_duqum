<div class="modal-body clearfix">
    <?php if (empty($approval_history) || !is_array($approval_history)): ?>
        <p class="text-off p15"><?php echo app_lang("no_approval_history"); ?></p>
    <?php else: ?>
        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
            <table class="table table-bordered table-hover mb0">
                <thead class="sticky-top bg-default">
                    <tr>
                        <th><?php echo app_lang("stage"); ?></th>
                        <th><?php echo app_lang("decision"); ?></th>
                        <th><?php echo app_lang("comment"); ?></th>
                        <th><?php echo app_lang("decided_by"); ?></th>
                        <th><?php echo app_lang("decided_at"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approval_history as $a): ?>
                    <tr>
                        <td><?php echo esc($a->stage ?? "-"); ?></td>
                        <td>
                            <?php
                            $badge = "bg-secondary";
                            if (($a->decision ?? "") === "approved") $badge = "bg-success";
                            if (($a->decision ?? "") === "rejected") $badge = "bg-danger";
                            if (($a->decision ?? "") === "returned") $badge = "bg-warning";
                            ?>
                            <span class="badge <?php echo $badge; ?>"><?php echo esc($a->decision ?? "-"); ?></span>
                        </td>
                        <td class="text-break">
                            <?php
                            $lines = [];
                            if (!empty($a->reason_title)) {
                                $lines[] = app_lang("reason") . ": " . $a->reason_title;
                            }
                            if (!empty($a->comment)) {
                                $lines[] = $a->comment;
                            }
                            $comment_text = $lines ? implode("\n", $lines) : "-";
                            echo nl2br(esc($comment_text));
                            ?>
                        </td>
                        <td><?php echo esc(trim(($a->first_name ?? "") . " " . ($a->last_name ?? "")) ?: "-"); ?></td>
                        <td><?php echo !empty($a->decided_at) ? format_to_datetime($a->decided_at) : "-"; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>

<script>
$(document).ready(function () {
    if (typeof feather !== "undefined") feather.replace();
});
</script>
