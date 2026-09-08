-- Bank Muscat SmartPay accounting extension. Back up first and run on the intended database.
-- Requires the eservice_payment_integrity tables. For a fresh installation use migrations.
-- This script is rerunnable on MySQL/MariaDB. It never fabricates paid transactions.
DELIMITER $$
DROP PROCEDURE IF EXISTS pod_upgrade_smartpay_accounting$$
CREATE PROCEDURE pod_upgrade_smartpay_accounting()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='gateway_merchant_id') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `gateway_merchant_id` VARCHAR(100) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='bank_reference') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `bank_reference` VARCHAR(255) NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='response_json') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `response_json` MEDIUMTEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='status_response_json') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `status_response_json` MEDIUMTEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='verification_issues') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `verification_issues` TEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='verified_at') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `verified_at` DATETIME NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='returned_at') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `returned_at` DATETIME NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='handed_off_at') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `handed_off_at` DATETIME NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='last_status_check_at') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `last_status_check_at` DATETIME NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND COLUMN_NAME='settlement_status') THEN
    ALTER TABLE `pod_eservice_payments` ADD COLUMN `settlement_status` VARCHAR(24) NOT NULL DEFAULT 'pending';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payment_events' AND COLUMN_NAME='response_json') THEN
    ALTER TABLE `pod_eservice_payment_events` ADD COLUMN `response_json` MEDIUMTEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payment_events' AND COLUMN_NAME='verification_issues') THEN
    ALTER TABLE `pod_eservice_payment_events` ADD COLUMN `verification_issues` TEXT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_eservice_payments' AND INDEX_NAME='idx_eservice_payments_accounting') THEN
    ALTER TABLE `pod_eservice_payments` ADD INDEX `idx_eservice_payments_accounting` (`subject_type`, `initiated_at`, `id`);
  END IF;
  ALTER TABLE `pod_eservice_payments` MODIFY COLUMN `active_subject_key` VARCHAR(190)
    GENERATED ALWAYS AS (CASE WHEN `deleted`=0 AND `status` IN ('pending','processing','verification_required')
      THEN CONCAT(`subject_type`, ':', `subject_id`, ':', COALESCE(`vendor_id`, 0)) ELSE NULL END) STORED;
END$$
CALL pod_upgrade_smartpay_accounting()$$
DROP PROCEDURE pod_upgrade_smartpay_accounting$$
DELIMITER ;
