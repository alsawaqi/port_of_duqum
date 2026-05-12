CREATE TABLE IF NOT EXISTS `pod_tender_evaluation_attachments` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tender_evaluation_id` BIGINT(20) UNSIGNED NOT NULL,
    `tender_id` BIGINT(20) UNSIGNED NOT NULL,
    `tender_bid_id` BIGINT(20) UNSIGNED NOT NULL,
    `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
    `path` VARCHAR(500) NOT NULL,
    `original_name` VARCHAR(255) DEFAULT NULL,
    `mime_type` VARCHAR(255) DEFAULT NULL,
    `size_bytes` BIGINT(20) UNSIGNED DEFAULT NULL,
    `uploaded_by` BIGINT(20) UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tender_eval_att_eval` (`tender_evaluation_id`, `deleted`),
    KEY `idx_tender_eval_att_tender` (`tender_id`, `tender_bid_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_tender_communications_type_scope`
    ON `pod_tender_communications` (`type`, `clarification_scope`, `tender_id`, `vendor_id`, `deleted`);

ALTER TABLE `pod_tender_communications`
    ADD COLUMN IF NOT EXISTS `tender_bid_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `vendor_id`;

ALTER TABLE `pod_tender_communications`
    ADD COLUMN IF NOT EXISTS `internal_audience` VARCHAR(50) DEFAULT NULL AFTER `clarification_scope`;

CREATE INDEX IF NOT EXISTS `idx_tender_communications_bid_audience`
    ON `pod_tender_communications` (`tender_bid_id`, `internal_audience`, `deleted`);

-- Internal root communication type used when a technical evaluator asks procurement
-- to clarify a vendor's technical bid before or after scoring.
-- type = technical_clarification_request
-- type = commercial_clarification_request
