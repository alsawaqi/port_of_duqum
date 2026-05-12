CREATE TABLE IF NOT EXISTS `pod_vendor_grades` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort` INT(11) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_vendor_grades_code` (`code`),
    KEY `idx_vendor_grades_active` (`is_active`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pod_vendor_grades` DROP INDEX IF EXISTS `idx_vendor_grades_code_deleted`;
CREATE INDEX IF NOT EXISTS `idx_vendor_grades_code` ON `pod_vendor_grades` (`code`);

ALTER TABLE `pod_vendors`
    ADD COLUMN IF NOT EXISTS `vendor_grade_id` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `vendor_group_id`,
    ADD COLUMN IF NOT EXISTS `blocked_reason` TEXT DEFAULT NULL AFTER `notes`,
    ADD COLUMN IF NOT EXISTS `blocked_by` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `blocked_reason`,
    ADD COLUMN IF NOT EXISTS `blocked_at` DATETIME DEFAULT NULL AFTER `blocked_by`;

CREATE INDEX IF NOT EXISTS `idx_vendors_vendor_grade_id` ON `pod_vendors` (`vendor_grade_id`);
CREATE INDEX IF NOT EXISTS `idx_vendors_status_grade` ON `pod_vendors` (`status`, `vendor_grade_id`);

ALTER TABLE `pod_vendor_status_histories`
    MODIFY COLUMN `action` ENUM('submit','approve','reject','revise','renew','block','unblock') DEFAULT NULL;
