<div id="page-content" class="page-wrapper clearfix gp-pro-page pod-page-shell pod-tender-page">
    <?php
    echo view("includes/tender_page_header", [
        "title" => "Tender Clarifications",
        "subtitle" => "Review evaluator messages and vendor clarification chats for one tender.",
        "icon" => "message-square",
        "actions" => '<a href="' . esc(get_uri('tender_clarifications'), "attr") . '" class="btn btn-default gp-pro-btn gp-pro-btn-icon">'
            . '<i data-feather="arrow-left" class="icon-16"></i> Back to Tenders'
            . '</a>'
    ]);
    ?>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h3 class="mb5">Tender Clarifications - <?php echo esc($tender->reference ?? '-'); ?></h3>
            <div class="text-off"><?php echo esc($tender->title ?? '-'); ?></div>
        </div>
    </div>

    <?php $internal_rows = $internal_rows ?? []; ?>
    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h4 class="mb0">General Evaluator Messages</h4>
        </div>
        <div class="card-body p0">
            <div class="table-responsive gp-pro-table-shell">
                <table class="table table-bordered table-striped mb0">
                    <thead>
                        <tr>
                            <th>Team</th>
                            <th>Subject / Message</th>
                            <th>Status</th>
                            <th>By</th>
                            <th>When</th>
                            <th class="text-center" style="width: 120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($internal_rows)) { ?>
                            <tr>
                                <td colspan="6" class="text-center text-off p20">No general evaluator messages for this tender.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($internal_rows as $row) { ?>
                                <tr>
                                    <td><?php echo esc(ucfirst($row->internal_audience ?: str_replace("_clarification_request", "", $row->type ?? "-"))); ?></td>
                                    <td>
                                        <strong><?php echo esc($row->subject ?: "-"); ?></strong>
                                        <div class="text-off mt5"><?php echo nl2br(esc($row->message ?? "")); ?></div>
                                    </td>
                                    <td><?php echo esc(ucwords(str_replace("_", " ", $row->status ?? "-"))); ?></td>
                                    <td><?php echo esc(trim((string) ($row->created_by_name ?? "")) ?: "-"); ?></td>
                                    <td><?php echo !empty($row->published_at ?: $row->created_at) ? format_to_datetime($row->published_at ?: $row->created_at) : "-"; ?></td>
                                    <td class="text-center">
                                        <a href="<?php echo get_uri('tender_clarifications/thread/' . (int) $row->id); ?>" class="btn btn-default btn-sm">
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
