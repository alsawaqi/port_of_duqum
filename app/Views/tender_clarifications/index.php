<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="mb15">
        <a href="<?php echo get_uri('tender_procurement_inbox'); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Procurement Inbox
        </a>
    </div>

    <div class="card gp-pro-card">
        <div class="page-title clearfix">
            <h1>Procurement Clarifications By Tender</h1>
        </div>

        <div class="card-body p0">
            <div class="table-responsive gp-pro-table-shell">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Tender</th>
                            <th class="text-center">Vendors Asked</th>
                            <th class="text-center">Total Questions</th>
                            <th>Latest Question</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)) { ?>
                            <tr>
                                <td colspan="6" class="text-center text-off p20">No clarification questions found yet.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($rows as $row) { ?>
                                <tr>
                                    <td><?php echo esc($row->tender_reference ?? '-'); ?></td>
                                    <td><?php echo esc($row->tender_title ?? '-'); ?></td>
                                    <td class="text-center"><?php echo (int) ($row->vendor_count ?? 0); ?></td>
                                    <td class="text-center"><?php echo (int) ($row->total_questions ?? 0); ?></td>
                                    <td><?php echo !empty($row->latest_question_at) ? format_to_datetime($row->latest_question_at) : '-'; ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo get_uri('tender_clarifications/tender/' . (int) $row->tender_id); ?>" class="btn btn-default btn-sm">
                                            <i data-feather="eye" class="icon-14"></i>
                                            Open
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
