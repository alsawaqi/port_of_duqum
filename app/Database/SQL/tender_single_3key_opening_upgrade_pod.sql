-- tender_single_3key_opening_upgrade_pod.sql
-- Single 3-key bid opening with committee digital signatures and procurement manual form bypass.

SET SESSION sql_safe_updates = 0;

ALTER TABLE `pod_tender_bid_openings`
  MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'codes_generated';

ALTER TABLE `pod_tender_bid_openings`
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `unlocked_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_path` varchar(255) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_original_name` varchar(255) DEFAULT NULL AFTER `manual_form_path`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `manual_form_original_name`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_at` datetime DEFAULT NULL AFTER `manual_form_uploaded_by`;

ALTER TABLE `pod_tender_bid_opening_entries`
  ADD COLUMN IF NOT EXISTS `signature_statement` text DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN IF NOT EXISTS `signature_name` varchar(255) DEFAULT NULL AFTER `signature_statement`,
  ADD COLUMN IF NOT EXISTS `signature_image_path` varchar(500) DEFAULT NULL AFTER `signature_name`,
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `signature_image_path`,
  ADD COLUMN IF NOT EXISTS `signature_ip_address` varchar(45) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `signature_user_agent` text DEFAULT NULL AFTER `signature_ip_address`;

UPDATE `pod_tender_bid_openings`
SET `status` = 'expired',
    `updated_at` = NOW()
WHERE `deleted` = 0
  AND `stage` = 'commercial'
  AND `status` IN ('codes_generated', 'unlocked');

UPDATE `pod_tenders`
SET `workflow_stage` = 'commercial',
    `commercial_unlocked_at` = COALESCE(`commercial_unlocked_at`, `committee_3key_start_at`, `technical_locked_at`, NOW()),
    `commercial_start_at` = COALESCE(`commercial_start_at`, `committee_3key_start_at`, `technical_locked_at`, NOW()),
    `updated_at` = NOW()
WHERE `deleted` = 0
  AND `status` = 'closed'
  AND `workflow_stage` = 'committee_3key';

ALTER TABLE `pod_tenders`
  MODIFY COLUMN `workflow_stage` enum(
    'bidding',
    'technical_3key',
    'technical',
    'commercial',
    'award_decision'
  ) NOT NULL DEFAULT 'bidding';

SET SESSION sql_safe_updates = 1;
