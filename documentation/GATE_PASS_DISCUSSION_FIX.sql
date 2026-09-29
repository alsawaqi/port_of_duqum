-- Run in the application database before uploading the corresponding PHP files.
CREATE TABLE IF NOT EXISTS pod_gate_pass_notification_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key CHAR(64) NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 visitor_id BIGINT UNSIGNED NULL,
 recipient_user_id INT NULL,
 channel VARCHAR(12) NOT NULL,
 destination VARCHAR(254) NOT NULL DEFAULT '',
 subject VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'queued',
 is_preview TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 claimed_at DATETIME NULL,
 processed_at DATETIME NULL,
 UNIQUE KEY event_recipient (event_key),
 KEY dispatch_status (status,id),
 KEY request_notifications (request_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
