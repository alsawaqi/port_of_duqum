<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="mb15">
        <a href="<?php echo get_uri('tender_clarifications/tender/' . (int) $tender->id); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Vendor List
        </a>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h3 class="mb5"><?php echo esc($vendor->vendor_name ?? '-'); ?></h3>
            <div class="text-off">
                Tender: <?php echo esc($tender->reference ?? '-'); ?> / <?php echo esc($tender->title ?? '-'); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb10">
                    <strong>Vendor Questions:</strong>
                    <?php echo (int) ($question_count ?? 0); ?>
                </div>
                <div class="col-md-6 mb10">
                    <strong>Latest Message:</strong>
                    <?php echo !empty($latest_message_at) ? format_to_datetime($latest_message_at) : '-'; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card gp-pro-card mb15">
        <div class="card-header">
            <h4 class="mb0">Clarification Chat</h4>
        </div>
        <div class="card-body">
            <div class="clarification-chat-shell">
                <?php if (empty($messages)) { ?>
                    <div class="text-center text-off p20">No clarification messages found for this vendor yet.</div>
                <?php } else { ?>
                    <?php foreach ($messages as $message) {
                        $is_vendor_message = strtolower((string) ($message->type ?? '')) === 'clarification'
                            && (empty($message->parent_id) || (int) $message->parent_id === 0);
                        $message_time = $message->published_at ?: $message->created_at;
                        $sender_name = $is_vendor_message
                            ? ($vendor->vendor_name ?? 'Vendor')
                            : (trim((string) ($message->created_by_name ?? '')) ?: 'Procurement');
                    ?>
                        <div class="d-flex mb15 <?php echo $is_vendor_message ? 'justify-content-start' : 'justify-content-end'; ?>">
                            <div class="clarification-bubble <?php echo $is_vendor_message ? 'vendor-bubble' : 'procurement-bubble'; ?>">
                                <div class="clarification-meta">
                                    <strong><?php echo esc($sender_name); ?></strong>
                                    <span><?php echo !empty($message_time) ? format_to_datetime($message_time) : '-'; ?></span>
                                </div>
                                <div class="clarification-text"><?php echo nl2br(esc($message->message ?? '')); ?></div>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="card gp-pro-card">
        <div class="card-header">
            <h4 class="mb0">Send Reply</h4>
        </div>
        <div class="card-body">
            <?php echo form_open(get_uri("tender_clarifications/save_reply"), [
                "id" => "tender-clarification-chat-form",
                "class" => "general-form",
                "role" => "form"
            ]); ?>
                <input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
                <input type="hidden" name="vendor_id" value="<?php echo (int) ($vendor->id ?? 0); ?>" />

                <div class="form-group mb15">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Write your reply to this vendor"></textarea>
                    <div class="mt5 text-off">This message will appear in the clarification chat for this vendor.</div>
                </div>

                <button type="submit" class="btn btn-primary">Send Reply</button>
            <?php echo form_close(); ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $("#tender-clarification-chat-form").appForm({
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

    var chatShell = document.querySelector(".clarification-chat-shell");
    if (chatShell) {
        chatShell.scrollTop = chatShell.scrollHeight;
    }

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>

<style>
.clarification-chat-shell {
    background: #f7f9fc;
    border: 1px solid #e5e9f2;
    border-radius: 12px;
    padding: 18px;
    max-height: 65vh;
    overflow-y: auto;
}

.clarification-bubble {
    max-width: 72%;
    padding: 14px 16px;
    border-radius: 14px;
    box-shadow: 0 6px 16px rgba(20, 31, 56, 0.08);
}

.clarification-bubble.vendor-bubble {
    background: #ffffff;
    border: 1px solid #dfe6f1;
    color: #223047;
}

.clarification-bubble.procurement-bubble {
    background: #103d73;
    color: #ffffff;
}

.clarification-meta {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 12px;
    margin-bottom: 8px;
    opacity: 0.85;
}

.clarification-text {
    white-space: normal;
    word-break: break-word;
    line-height: 1.6;
}
</style>

<script>
if (typeof feather !== "undefined") {
    feather.replace();
}
</script>
