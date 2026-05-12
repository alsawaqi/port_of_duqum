CREATE TABLE IF NOT EXISTS `pod_tender_fee_payments` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tender_id` BIGINT(20) UNSIGNED NOT NULL,
    `vendor_id` BIGINT(20) UNSIGNED NOT NULL,
    `amount` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'OMR',
    `status` VARCHAR(50) NOT NULL DEFAULT 'paid',
    `payment_reference` VARCHAR(100) DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `created_by` BIGINT(20) UNSIGNED DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    `deleted` INT(11) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tender_fee_payments_tender_vendor` (`tender_id`, `vendor_id`, `deleted`),
    KEY `idx_tender_fee_payments_status` (`status`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
