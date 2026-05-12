ALTER TABLE `pod_tender_communications`
    ADD COLUMN IF NOT EXISTS `clarification_scope` VARCHAR(50) NOT NULL DEFAULT 'general' AFTER `type`;

CREATE TABLE IF NOT EXISTS `pod_tender_communication_attachments` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `communication_id` BIGINT(20) UNSIGNED NOT NULL,
    `tender_id` BIGINT(20) UNSIGNED NOT NULL,
    `vendor_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
    `path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `mime_type` VARCHAR(255) DEFAULT NULL,
    `size_bytes` BIGINT(20) UNSIGNED DEFAULT NULL,
    `uploaded_by` BIGINT(20) UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tender_comm_att_communication` (`communication_id`, `deleted`),
    KEY `idx_tender_comm_att_tender_vendor` (`tender_id`, `vendor_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_tender_communications_scope`
    ON `pod_tender_communications` (`clarification_scope`, `tender_id`, `deleted`);
