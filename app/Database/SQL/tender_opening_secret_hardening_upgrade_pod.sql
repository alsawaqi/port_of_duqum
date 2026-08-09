-- tender_opening_secret_hardening_upgrade_pod.sql
-- Run once during a controlled deployment before enabling the hardened 3-key flow.
--
-- Application prerequisites (these are process environment secrets, not SQL values):
--   TENDER_OPENING_CODE_HMAC_KEY       = base64:<at least 32 random decoded bytes>
--   TENDER_OPENING_CODE_ENCRYPTION_KEY = base64:<a different 32 random decoded bytes>
-- Provision both through the production secret manager before deploying the code.
-- Do not store either key in this script, source control, logs, or database backups.
--
-- Existing plaintext sessions are deliberately expired, not converted. Committee
-- members must generate a fresh set after deployment.

SET @pod_previous_sql_safe_updates := @@SESSION.sql_safe_updates;
SET SESSION sql_safe_updates = 0;

ALTER TABLE `pod_tender_bid_openings`
  MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'codes_generated';

ALTER TABLE `pod_tender_bid_openings`
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `unlocked_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_path` varchar(255) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_original_name` varchar(255) DEFAULT NULL AFTER `manual_form_path`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `manual_form_original_name`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_at` datetime DEFAULT NULL AFTER `manual_form_uploaded_by`;

ALTER TABLE `pod_tender_bid_openings`
  ADD COLUMN IF NOT EXISTS `chairman_code_hash` char(64) DEFAULT NULL AFTER `member_code`,
  ADD COLUMN IF NOT EXISTS `secretary_code_hash` char(64) DEFAULT NULL AFTER `chairman_code_hash`,
  ADD COLUMN IF NOT EXISTS `member_code_hash` char(64) DEFAULT NULL AFTER `secretary_code_hash`,
  ADD COLUMN IF NOT EXISTS `chairman_code_ciphertext` varchar(255) DEFAULT NULL AFTER `member_code_hash`,
  ADD COLUMN IF NOT EXISTS `secretary_code_ciphertext` varchar(255) DEFAULT NULL AFTER `chairman_code_ciphertext`,
  ADD COLUMN IF NOT EXISTS `member_code_ciphertext` varchar(255) DEFAULT NULL AFTER `secretary_code_ciphertext`;

ALTER TABLE `pod_tender_bid_opening_entries`
  ADD COLUMN IF NOT EXISTS `signature_statement` text DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN IF NOT EXISTS `signature_name` varchar(255) DEFAULT NULL AFTER `signature_statement`,
  ADD COLUMN IF NOT EXISTS `signature_image_path` varchar(500) DEFAULT NULL AFTER `signature_name`,
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `signature_image_path`,
  ADD COLUMN IF NOT EXISTS `signature_ip_address` varchar(45) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `signature_user_agent` text DEFAULT NULL AFTER `signature_ip_address`;

SET @pod_opening_expiry_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_openings'
      AND INDEX_NAME = 'idx_tender_opening_secret_expiry'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_openings` ADD INDEX `idx_tender_opening_secret_expiry` (`status`, `deleted`, `expires_at`)'
);
PREPARE pod_opening_expiry_index_stmt FROM @pod_opening_expiry_index_sql;
EXECUTE pod_opening_expiry_index_stmt;
DEALLOCATE PREPARE pod_opening_expiry_index_stmt;

SET @pod_opening_user_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND INDEX_NAME = 'idx_tender_opening_failure_user'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_opening_entries` ADD INDEX `idx_tender_opening_failure_user` (`tender_bid_opening_id`, `is_valid`, `user_id`, `confirmed_at`)'
);
PREPARE pod_opening_user_index_stmt FROM @pod_opening_user_index_sql;
EXECUTE pod_opening_user_index_stmt;
DEALLOCATE PREPARE pod_opening_user_index_stmt;

SET @pod_opening_ip_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND INDEX_NAME = 'idx_tender_opening_failure_ip'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_opening_entries` ADD INDEX `idx_tender_opening_failure_ip` (`tender_bid_opening_id`, `is_valid`, `ip_address`, `confirmed_at`)'
);
PREPARE pod_opening_ip_index_stmt FROM @pod_opening_ip_index_sql;
EXECUTE pod_opening_ip_index_stmt;
DEALLOCATE PREPARE pod_opening_ip_index_stmt;

UPDATE `pod_tender_bid_openings`
SET `status` = 'expired',
    `updated_at` = NOW()
WHERE `deleted` = 0
  AND `status` = 'codes_generated'
  AND (
       `expires_at` IS NULL
       OR `expires_at` <= NOW()
       OR `chairman_code_hash` IS NULL
       OR `secretary_code_hash` IS NULL
       OR `member_code_hash` IS NULL
       OR `chairman_code_ciphertext` IS NULL
       OR `secretary_code_ciphertext` IS NULL
       OR `member_code_ciphertext` IS NULL
  );

UPDATE `pod_tender_bid_openings`
SET `chairman_code` = NULL,
    `secretary_code` = NULL,
    `member_code` = NULL;

UPDATE `pod_tender_bid_openings` AS older
INNER JOIN `pod_tender_bid_openings` AS newer
  ON newer.`tender_id` = older.`tender_id`
 AND newer.`stage` = older.`stage`
 AND newer.`deleted` = 0
 AND newer.`status` = 'codes_generated'
 AND newer.`id` > older.`id`
SET older.`status` = 'expired',
    older.`chairman_code_hash` = NULL,
    older.`secretary_code_hash` = NULL,
    older.`member_code_hash` = NULL,
    older.`chairman_code_ciphertext` = NULL,
    older.`secretary_code_ciphertext` = NULL,
    older.`member_code_ciphertext` = NULL,
    older.`updated_at` = NOW()
WHERE older.`deleted` = 0
  AND older.`status` = 'codes_generated';

UPDATE `pod_tender_bid_openings` AS generated
INNER JOIN `pod_tender_bid_openings` AS terminal
  ON terminal.`tender_id` = generated.`tender_id`
 AND terminal.`stage` = generated.`stage`
 AND terminal.`deleted` = 0
 AND terminal.`status` IN ('unlocked', 'signed', 'manual_accepted')
SET generated.`status` = 'expired',
    generated.`chairman_code_hash` = NULL,
    generated.`secretary_code_hash` = NULL,
    generated.`member_code_hash` = NULL,
    generated.`chairman_code_ciphertext` = NULL,
    generated.`secretary_code_ciphertext` = NULL,
    generated.`member_code_ciphertext` = NULL,
    generated.`updated_at` = NOW()
WHERE generated.`deleted` = 0
  AND generated.`status` = 'codes_generated';

UPDATE `pod_tender_bid_openings`
SET `chairman_code_hash` = NULL,
    `secretary_code_hash` = NULL,
    `member_code_hash` = NULL,
    `chairman_code_ciphertext` = NULL,
    `secretary_code_ciphertext` = NULL,
    `member_code_ciphertext` = NULL
WHERE `status` <> 'codes_generated'
   OR `deleted` <> 0;

SET @pod_clear_legacy_input_codes_sql := IF(
  (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND COLUMN_NAME IN ('input_chairman_code', 'input_secretary_code', 'input_member_code')
  ) = 3,
  'UPDATE `pod_tender_bid_opening_entries` SET `input_chairman_code` = NULL, `input_secretary_code` = NULL, `input_member_code` = NULL WHERE `input_chairman_code` IS NOT NULL OR `input_secretary_code` IS NOT NULL OR `input_member_code` IS NOT NULL',
  'SELECT ''Legacy tender input-code columns are absent; nothing to clear'' AS info'
);
PREPARE pod_clear_legacy_input_codes_stmt FROM @pod_clear_legacy_input_codes_sql;
EXECUTE pod_clear_legacy_input_codes_stmt;
DEALLOCATE PREPARE pod_clear_legacy_input_codes_stmt;

SET SESSION sql_safe_updates = @pod_previous_sql_safe_updates;
