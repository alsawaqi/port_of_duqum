<div id="page-content" class="page-wrapper clearfix gp-pro-page">
    <div class="mb15">
        <a href="<?php echo get_uri('tender_clarifications/tender/' . (int) $tender->id); ?>" class="btn btn-default">
            <i data-feather="arrow-left" class="icon-16"></i>
            Back to Vendor List
        </a>
    </div>

    <?php
    $attachments = $attachments ?? [];
    $reply_threads = $reply_threads ?? [];
    $clarification_scope_options = $clarification_scope_options ?? ["general" => "General", "tender" => "Tender / Procurement", "technical" => "Technical Team", "commercial" => "Commercial Team", "vendor" => "Vendor Specific"];
    ?>

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
                        $message_type = strtolower((string) ($message->type ?? ''));
                        $is_vendor_message = $message_type === 'clarification'
                            && (int) ($message->is_vendor_visible ?? 0) === 1
                            && (empty($message->parent_id) || (int) $message->parent_id === 0);
                        $message_time = $message->published_at ?: $message->created_at;
                        $sender_name = $is_vendor_message ? ($vendor->vendor_name ?? 'Vendor') : (trim((string) ($message->created_by_name ?? '')) ?: 'Procurement');
                        if ($message_type === 'technical_clarification_request') {
                            $sender_name = trim((string) ($message->created_by_name ?? '')) ?: 'Technical Team';
                        } elseif ($message_type === 'commercial_clarification_request') {
                            $sender_name = trim((string) ($message->created_by_name ?? '')) ?: 'Commercial Team';
                        }
                        $scope_label = \App\Models\Tender_communications_model::clarification_scope_label($message->clarification_scope ?? "general");
                        $message_attachments = $attachments[(int) $message->id] ?? [];
                    ?>
                        <div class="d-flex mb15 <?php echo $is_vendor_message ? 'justify-content-start' : 'justify-content-end'; ?>">
                            <div class="clarification-bubble <?php echo $is_vendor_message ? 'vendor-bubble' : 'procurement-bubble'; ?>">
                                <div class="clarification-meta">
                                    <strong><?php echo esc($sender_name); ?></strong>
                                    <span><?php echo !empty($message_time) ? format_to_datetime($message_time) : '-'; ?></span>
                                </div>
                                <div class="mb10">
                                    <span class="badge bg-light text-dark"><?php echo esc($scope_label); ?></span>
                                    <?php if ($message_type === 'technical_clarification_request') { ?>
                                        <span class="badge bg-warning text-dark">Technical request</span>
                                    <?php } elseif ($message_type === 'commercial_clarification_request') { ?>
                                        <span class="badge bg-warning text-dark">Commercial request</span>
                                    <?php } elseif ($message_type === 'technical_clarification_response') { ?>
                                        <span class="badge bg-info text-dark">Forwarded to technical</span>
                                    <?php } elseif ($message_type === 'commercial_clarification_response') { ?>
                                        <span class="badge bg-info text-dark">Forwarded to commercial</span>
                                    <?php } ?>
                                </div>
                                <?php if (!empty($message->subject)) { ?>
                                    <div class="fw-semibold mb5"><?php echo esc($message->subject); ?></div>
                                <?php } ?>
                                <div class="clarification-text"><?php echo nl2br(esc($message->message ?? '')); ?></div>
                                <?php if (!empty($message_attachments)) { ?>
                                    <div class="clarification-attachments mt10">
                                        <?php foreach ($message_attachments as $attachment) { ?>
                                            <a href="<?php echo get_uri("tender_clarifications/download_attachment/" . (int) $attachment->id); ?>" class="clarification-attachment-link">
                                                <i data-feather="paperclip" class="icon-14"></i>
                                                <?php echo esc($attachment->original_name ?: basename((string) $attachment->path)); ?>
                                            </a>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
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
            <?php echo form_open_multipart(get_uri("tender_clarifications/save_reply"), [
                "id" => "tender-clarification-chat-form",
                "class" => "general-form",
                "role" => "form"
            ]); ?>
                <input type="hidden" name="tender_id" value="<?php echo (int) ($tender->id ?? 0); ?>" />
                <input type="hidden" name="vendor_id" value="<?php echo (int) ($vendor->id ?? 0); ?>" />

                <?php if (!empty($reply_threads)) { ?>
                    <div class="form-group mb15">
                        <label>Related Thread</label>
                        <select name="communication_id" class="form-control">
                            <?php foreach ($reply_threads as $thread) {
                                $thread_type = strtolower((string) ($thread->type ?? ''));
                                $thread_label = $thread_type === 'technical_clarification_request'
                                    ? "Technical request"
                                    : ($thread_type === 'commercial_clarification_request'
                                        ? "Commercial request"
                                        : "Vendor clarification");
                                $thread_time = $thread->published_at ?: $thread->created_at;
                            ?>
                                <option value="<?php echo (int) $thread->id; ?>">
                                    <?php echo esc($thread_label . " - " . ($thread->subject ?: short_text($thread->message ?? "", 60)) . " - " . (!empty($thread_time) ? format_to_datetime($thread_time) : "-")); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>

                <div class="form-group mb15">
                    <label>Clarification Type</label>
                    <?php echo form_dropdown("clarification_scope", $clarification_scope_options, "general", "class='form-control'"); ?>
                </div>

                <div class="form-group mb15">
                    <label>Visibility</label>
                    <?php echo form_dropdown("visibility", ["vendor" => "Reply to this vendor", "technical" => "Forward internally to technical team", "commercial" => "Forward internally to commercial team", "all" => "Publish to all vendors"], "vendor", "class='form-control'"); ?>
                </div>

                <div class="form-group mb15">
                    <label>Message</label>
                    <textarea name="message" class="form-control" rows="4" required placeholder="Write your reply to this vendor"></textarea>
                    <div class="mt5 text-off">This message will appear in the clarification chat for this vendor.</div>
                </div>

                <div class="form-group mb15">
                    <label>Attach Files</label>
                    <input type="file" name="clarification_files[]" class="form-control" multiple>
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

.clarification-attachments {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.clarification-attachment-link {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.14);
    color: inherit;
    padding: 5px 9px;
    font-size: 12px;
    max-width: 100%;
    overflow-wrap: anywhere;
}

.vendor-bubble .clarification-attachment-link {
    border-color: #d9e2ef;
    background: #f5f8fc;
}
</style>

<script>
if (typeof feather !== "undefined") {
    feather.replace();
}
</script>
