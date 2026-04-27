<?php
$replies = $replies ?? [];
?>

<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="mb15">
        <a href="<?php echo get_uri('tender_clarifications/vendor/' . (int) ($tender->id ?? 0) . '/' . (int) ($vendor->id ?? 0)); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Vendor Questions
        </a>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h3 class="mb5">Clarification Thread</h3>
            <div class="text-off">
                <?php echo esc($tender->reference ?? '-'); ?> / <?php echo esc($tender->title ?? '-'); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb10">
                    <strong>Vendor:</strong><br>
                    <?php echo esc($vendor->vendor_name ?? '-'); ?>
                </div>
                <div class="col-md-6 mb10">
                    <strong>Asked At:</strong><br>
                    <?php echo !empty($clarification->published_at) ? format_to_datetime($clarification->published_at) : (!empty($clarification->created_at) ? format_to_datetime($clarification->created_at) : '-'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h4 class="mb0">Vendor Question</h4>
        </div>
        <div class="card-body">
            <?php echo nl2br(esc($clarification->message ?? "")); ?>
        </div>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h4 class="mb0">Replies</h4>
        </div>
        <div class="card-body">
            <?php if ($replies) { ?>
                <?php foreach ($replies as $reply) { ?>
                    <div class="card mb10">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <span class="badge bg-<?php echo ($reply->sent_to_all ?? 0) ? "success" : "info"; ?>">
                                        <?php echo ($reply->sent_to_all ?? 0) ? "All Vendors" : "Vendor Only"; ?>
                                    </span>
                                    <span class="ms-2 fw-semibold"><?php echo esc($reply->created_by_name ?: "Procurement"); ?></span>
                                </div>
                                <div class="text-muted">
                                    <?php echo !empty($reply->published_at) ? format_to_datetime($reply->published_at) : (!empty($reply->created_at) ? format_to_datetime($reply->created_at) : '-'); ?>
                                </div>
                            </div>
                            <div class="mt10"><?php echo nl2br(esc($reply->message ?? "")); ?></div>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="text-muted">No replies sent yet.</div>
            <?php } ?>
        </div>
    </div>

    <div class="card gp-pro-card">
        <div class="card-header">
            <h4 class="mb0">Reply To This Question</h4>
        </div>
        <div class="card-body">
            <?php echo form_open(get_uri("tender_clarifications/save_reply"), [
                "id" => "tender-clarification-reply-form",
                "class" => "general-form",
                "role" => "form"
            ]); ?>
                <input type="hidden" name="communication_id" value="<?php echo (int) ($clarification->id ?? 0); ?>" />

                <div class="form-group">
                    <label>Reply Visibility</label>
                    <?php echo form_dropdown(
                        "visibility",
                        [
                            "vendor" => "Reply to this vendor only",
                            "all" => "Publish to all participating vendors",
                        ],
                        "vendor",
                        "class='form-control select2'"
                    ); ?>
                </div>

                <div class="form-group mb15">
                    <label>Reply Message</label>
                    <textarea name="message" class="form-control" rows="5" required placeholder="Write the clarification response here"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Send Reply</button>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-clarification-reply-form").appForm({
        onSuccess: function (response) {
            appAlert.success(response.message || "Saved successfully.", {duration: 2000});
            setTimeout(function () {
                if (response.redirect_url) {
                    window.location.href = response.redirect_url;
                } else {
                    window.location.reload();
                }
            }, 400);
        }
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
