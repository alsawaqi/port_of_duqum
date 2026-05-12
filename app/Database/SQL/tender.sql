use bedotscpanel_poderp;
 
 
SET SESSION sql_safe_updates = 0;

ALTER TABLE `pod_tenders`
  ADD COLUMN `company_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `title`,
  ADD COLUMN `department_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `company_id`,
  ADD COLUMN `brief_description` text DEFAULT NULL AFTER `department_id`,
  ADD COLUMN `tender_fee` DECIMAL(15,3) DEFAULT NULL AFTER `brief_description`,
  ADD COLUMN `release_at` datetime DEFAULT NULL AFTER `workflow_stage`,
  ADD COLUMN `document_purchase_deadline` datetime DEFAULT NULL AFTER `release_at`,
  ADD COLUMN `site_visit_at` datetime DEFAULT NULL AFTER `document_purchase_deadline`,
  ADD COLUMN `clarification_deadline` datetime DEFAULT NULL AFTER `site_visit_at`,
  ADD COLUMN `bid_opening_at` datetime DEFAULT NULL AFTER `closing_at`,
  ADD COLUMN `technical_eval_deadline` datetime DEFAULT NULL AFTER `bid_opening_at`,
  ADD COLUMN `commercial_eval_deadline` datetime DEFAULT NULL AFTER `technical_eval_deadline`,
  ADD COLUMN `award_vendor_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `award_ready_at`,
  ADD COLUMN `loa_reference` varchar(255) DEFAULT NULL AFTER `award_vendor_id`,
  ADD COLUMN `loa_issued_at` datetime DEFAULT NULL AFTER `loa_reference`;

UPDATE `pod_tenders` t
LEFT JOIN `pod_tender_requests` r
  ON r.`id` = t.`tender_request_id`
SET
  t.`company_id` = COALESCE(t.`company_id`, r.`company_id`),
  t.`department_id` = COALESCE(t.`department_id`, r.`department_id`),
  t.`brief_description` = COALESCE(t.`brief_description`, r.`brief_description`),
  t.`release_at` = COALESCE(t.`release_at`, t.`published_at`)
WHERE t.`id` > 0
  AND t.`deleted` = 0;

ALTER TABLE `pod_tender_target_specialties`
  ADD COLUMN `vendor_group_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `vendor_sub_category_id`;

ALTER TABLE `pod_tender_bid_documents`
  MODIFY COLUMN `section` enum(
    'technical',
    'commercial',
    'commercial_priced',
    'commercial_unpriced',
    'bank_guarantee'
  ) NOT NULL DEFAULT 'technical';

ALTER TABLE `pod_tenders`
  MODIFY COLUMN `workflow_stage` enum(
    'bidding',
    'technical_3key',
    'technical',
    'commercial',
    'award_decision'
  ) NOT NULL DEFAULT 'bidding';

ALTER TABLE `pod_tender_bid_openings`
  MODIFY COLUMN `stage` enum('technical','commercial') NOT NULL DEFAULT 'technical',
  MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'codes_generated',
  ADD COLUMN `signed_at` datetime DEFAULT NULL AFTER `unlocked_at`,
  ADD COLUMN `manual_form_path` varchar(255) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN `manual_form_original_name` varchar(255) DEFAULT NULL AFTER `manual_form_path`,
  ADD COLUMN `manual_form_uploaded_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `manual_form_original_name`,
  ADD COLUMN `manual_form_uploaded_at` datetime DEFAULT NULL AFTER `manual_form_uploaded_by`;

ALTER TABLE `pod_tender_bid_opening_entries`
  ADD COLUMN `signature_statement` text DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN `signature_name` varchar(255) DEFAULT NULL AFTER `signature_statement`,
  ADD COLUMN `signed_at` datetime DEFAULT NULL AFTER `signature_name`,
  ADD COLUMN `signature_ip_address` varchar(45) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN `signature_user_agent` text DEFAULT NULL AFTER `signature_ip_address`;

CREATE TABLE `pod_tender_bid_requirements` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_bid_requirements_tender` (`tender_id`),
  KEY `idx_tender_bid_requirements_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pod_tender_communications`
  ADD COLUMN `vendor_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `tender_id`,
  ADD COLUMN `parent_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `vendor_id`,
  ADD COLUMN `is_vendor_visible` tinyint(1) NOT NULL DEFAULT 1 AFTER `sent_to_all`,
  ADD COLUMN `status` varchar(50) DEFAULT NULL AFTER `created_by`;

ALTER TABLE `pod_tender_extensions`
  ADD COLUMN `milestone_code` varchar(50) DEFAULT NULL AFTER `tender_id`,
  ADD COLUMN `approved_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`,
  ADD COLUMN `status` varchar(50) NOT NULL DEFAULT 'approved' AFTER `approved_at`;

ALTER TABLE `pod_tenders`
  ADD INDEX `idx_tenders_status_flow_release` (`status`, `workflow_stage`, `release_at`, `closing_at`),
  ADD INDEX `idx_tenders_company_department` (`company_id`, `department_id`),
  ADD INDEX `idx_tenders_award_vendor` (`award_vendor_id`);

ALTER TABLE `pod_tender_target_specialties`
  ADD INDEX `idx_tender_target_specialties_vendor_group` (`vendor_group_id`);

ALTER TABLE `pod_tender_communications`
  ADD INDEX `idx_tender_communications_tender_type` (`tender_id`, `type`),
  ADD INDEX `idx_tender_communications_vendor` (`vendor_id`);

ALTER TABLE `pod_tender_extensions`
  ADD INDEX `idx_tender_extensions_tender_status` (`tender_id`, `status`);

SET SESSION sql_safe_updates = 1;
 
