-- Manual equivalent of 2026_09_05_110000_vendor_fee_requests.php.
-- Run only against the intended application database with the pod_ prefix.
-- This creates the vendor registration/renewal request snapshot table only.
-- It does not backfill historical payments or run unrelated pending upgrades.
-- Existing financial records are deliberately retained on rollback.

CREATE TABLE IF NOT EXISTS `pod_vendor_fee_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `vendor_id` BIGINT UNSIGNED NOT NULL,
    `fee_type` VARCHAR(20) NOT NULL,
    `period_key` CHAR(64) NOT NULL,
    `fee_id` BIGINT UNSIGNED NOT NULL,
    `vendor_group_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(15,3) NOT NULL,
    `currency` CHAR(3) NOT NULL,
    `prior_valid_until` DATE NULL,
    `validity_days` INT UNSIGNED NOT NULL,
    `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
    `payment_id` BIGINT UNSIGNED NULL,
    `review_status` VARCHAR(24) NOT NULL DEFAULT 'pending',
    `requested_by` BIGINT UNSIGNED NOT NULL,
    `reviewed_by` BIGINT UNSIGNED NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_vendor_fee_period` (`vendor_id`, `period_key`),
    KEY `idx_vendor_fee_review` (`vendor_id`, `review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
