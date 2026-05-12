USE `pod`;

CREATE TABLE IF NOT EXISTS `pod_tender_procurement_manager_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tender_procurement_manager_user` (`user_id`),
  KEY `idx_tender_procurement_manager_company` (`company_id`),
  KEY `idx_tender_procurement_manager_status` (`status`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pod_tenders`
  ADD COLUMN IF NOT EXISTS `procurement_manager_status` enum('draft','pending','approved','rejected','revision_requested') NOT NULL DEFAULT 'draft' AFTER `workflow_stage`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_action` varchar(50) DEFAULT NULL AFTER `procurement_manager_status`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_submitted_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `procurement_manager_status`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_submitted_at` datetime DEFAULT NULL AFTER `procurement_manager_submitted_by`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_reviewed_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `procurement_manager_submitted_at`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_reviewed_at` datetime DEFAULT NULL AFTER `procurement_manager_reviewed_by`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_comment` text DEFAULT NULL AFTER `procurement_manager_reviewed_at`,
  ADD COLUMN IF NOT EXISTS `procurement_manager_payload` longtext DEFAULT NULL AFTER `procurement_manager_comment`;

ALTER TABLE `pod_tenders`
  ADD INDEX IF NOT EXISTS `idx_tenders_procurement_manager_status` (`procurement_manager_status`, `status`, `deleted`);
