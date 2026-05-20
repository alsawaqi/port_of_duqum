ALTER TABLE `pod_vendors`
    ADD COLUMN IF NOT EXISTS `cr_number` VARCHAR(100) DEFAULT NULL AFTER `email`,
    ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) DEFAULT NULL AFTER `cr_number`,
    ADD COLUMN IF NOT EXISTS `phone_country_code` VARCHAR(12) DEFAULT NULL AFTER `phone`,
    ADD COLUMN IF NOT EXISTS `contact_person` VARCHAR(255) DEFAULT NULL AFTER `phone_country_code`,
    ADD COLUMN IF NOT EXISTS `contact_designation` VARCHAR(255) DEFAULT NULL AFTER `contact_person`;

ALTER TABLE `pod_vendors`
    MODIFY COLUMN `status` ENUM('new','pending_payment','submitted','approved','rejected','revise','suspended','expired') NOT NULL DEFAULT 'new';

CREATE INDEX IF NOT EXISTS `idx_vendors_cr_number` ON `pod_vendors` (`cr_number`);

UPDATE `pod_vendors`
SET `status` = 'new'
WHERE `deleted` = 0
  AND (`status` IS NULL OR `status` = '');

CREATE TABLE IF NOT EXISTS `pod_vendor_status_histories` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id` BIGINT(20) UNSIGNED NOT NULL,
    `from_status` VARCHAR(255) DEFAULT NULL,
    `to_status` VARCHAR(255) NOT NULL,
    `action` ENUM('submit','approve','reject','revise','renew') DEFAULT NULL,
    `reason` TEXT DEFAULT NULL,
    `action_by` BIGINT(20) UNSIGNED DEFAULT NULL,
    `action_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_vendor_status_histories_vendor` (`vendor_id`),
    KEY `idx_vendor_status_histories_action_at` (`action_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
