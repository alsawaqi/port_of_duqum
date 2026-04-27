<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="mb15 d-flex flex-wrap gap-2">
        <a href="<?php echo get_uri('tender_clarifications'); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Tenders
        </a>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h3 class="mb5">Tender Clarifications - <?php echo esc($tender->reference ?? '-'); ?></h3>
            <div class="text-off"><?php echo esc($tender->title ?? '-'); ?></div>
        </div>
    </div>

    <div class="card gp-pro-card">
        <div class="card-header">
            <h4 class="mb0">Vendor Clarification Chats</h4>
        </div>
        <div class="card-body p0">
            <div class="table-responsive gp-pro-table-shell">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th class="text-center">Vendor Questions</th>
                            <th class="text-center">Answered</th>
                            <th>Latest Question</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) { ?>
                            <tr>
                                <td colspan="5" class="text-center text-off p20">No vendors have started a clarification chat for this tender.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($rows as $row) { ?>
                                <tr>
                                    <td><?php echo esc($row->vendor_name ?? '-'); ?></td>
                                    <td class="text-center"><?php echo (int) ($row->total_questions ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int) ($row->answered_questions ?? 0); ?></td>
                                    <td><?php echo !empty($row->latest_question_at) ? format_to_datetime($row->latest_question_at) : '-'; ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo get_uri('tender_clarifications/vendor/' . (int) $tender->id . '/' . (int) $row->vendor_id); ?>" class="btn btn-default btn-sm">
                                            <i data-feather="eye" class="icon-14"></i>
                                            Open Chat
                                        </a>
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

<script>
if (typeof feather !== "undefined") {
    feather.replace();
}
</script>
