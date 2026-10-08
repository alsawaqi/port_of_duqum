<div id="page-content" class="page-wrapper clearfix">
    <?php echo view('includes/tender_page_header', ['title' => ucfirst($audience) . ' clarifications', 'subtitle' => 'Internal correspondence with procurement. Vendor replies are published by procurement.', 'icon' => 'message-circle']); ?>
    <div class="mb15"><a class="btn btn-default" href="<?php echo get_uri('tender_' . $audience . '_inbox'); ?>">Back to evaluation inbox</a></div>
    <?php if (!$rows) { ?><div class="card p20">No internal messages for your assigned tenders.</div><?php } ?>
    <?php foreach ($rows as $row) { ?>
    <section class="card p20 mb15">
        <h3><?php echo esc($row->tender_reference . ' — ' . $row->tender_title); ?></h3>
        <div class="text-muted mb10"><?php echo esc($row->created_at); ?> · <?php echo esc(ucwords(str_replace('_', ' ', $row->status))); ?></div>
        <p style="white-space:pre-wrap"><?php echo esc($row->message); ?></p>
        <?php foreach ($attachments[(int) $row->id] ?? [] as $file) { ?><a class="btn btn-default btn-sm mb10" href="<?php echo get_uri('tender_team_clarifications/download_attachment/' . (int) $file->id); ?>"><?php echo esc($file->original_name); ?></a><?php } ?>
        <?php if ($can_reply && !in_array($row->tender_status, ['awarded', 'cancelled'], true)) { ?>
        <?php echo form_open_multipart(get_uri('tender_team_clarifications/reply'), ['class' => 'team-clarification-form']); ?>
        <input type="hidden" name="communication_id" value="<?php echo (int) $row->id; ?>">
        <label for="reply-<?php echo (int) $row->id; ?>">Reply to procurement</label>
        <textarea id="reply-<?php echo (int) $row->id; ?>" name="message" class="form-control mb10" rows="3" required></textarea>
        <label>Attachments (PDF or image)</label><input type="file" name="clarification_files[]" class="form-control mb10" multiple accept=".pdf,.jpg,.jpeg,.png">
        <button class="btn btn-primary" type="submit">Send to procurement</button><span class="reply-result ms-2" role="status"></span>
        <?php echo form_close(); ?>
        <?php } ?>
    </section>
    <?php } ?>
    <nav class="d-flex gap-2 mb15" aria-label="Message pages">
        <?php if ($page > 1) { ?><a class="btn btn-default" href="<?php echo get_uri('tender_team_clarifications/index/' . $audience . '?page=' . ($page - 1)); ?>">Previous</a><?php } ?>
        <?php if ($has_more) { ?><a class="btn btn-default" href="<?php echo get_uri('tender_team_clarifications/index/' . $audience . '?page=' . ($page + 1)); ?>">Next</a><?php } ?>
    </nav>
</div>
<script>$(function () {$('.team-clarification-form').each(function () {
    var form = $(this); form.appForm({isModal: false, onSuccess: function (result) {
        form.find('.reply-result').text(result.message); form.find('textarea, input[type=file]').val('');
    }});
});});</script>
