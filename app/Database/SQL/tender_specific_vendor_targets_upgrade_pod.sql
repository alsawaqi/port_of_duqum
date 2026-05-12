CREATE TABLE IF NOT EXISTS `pod_tender_target_vendors` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tender_id` BIGINT(20) UNSIGNED NOT NULL,
    `vendor_id` BIGINT(20) UNSIGNED NOT NULL,
    `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tender_target_vendors_tender` (`tender_id`, `deleted`),
    KEY `idx_tender_target_vendors_vendor` (`vendor_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pod_tender_target_specialties`
    ADD COLUMN IF NOT EXISTS `vendor_grade_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `vendor_group_id`;

CREATE INDEX IF NOT EXISTS `idx_tender_target_specialties_vendor_grade`
    ON `pod_tender_target_specialties` (`vendor_grade_id`);
