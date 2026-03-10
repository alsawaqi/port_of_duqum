ALTER TABLE `pod_tender_requests`
  ADD COLUMN `finance_reject_comment` TEXT NULL AFTER `finance_verified_at`,
  ADD COLUMN `committee_approved_by` BIGINT(20) UNSIGNED NULL AFTER `finance_reject_comment`,
  ADD COLUMN `committee_approved_at` DATETIME NULL AFTER `committee_approved_by`,
  ADD COLUMN `committee_reject_comment` TEXT NULL AFTER `committee_approved_at`;




  CREATE TABLE `pod_tender_request_vendors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_request_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tender_request_vendor_active` (`tender_request_id`,`vendor_id`,`deleted`),
  KEY `idx_trv_request` (`tender_request_id`),
  KEY `idx_trv_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




ALTER TABLE pod_tender_requests
  MODIFY COLUMN department_id BIGINT(20) UNSIGNED NULL,
  MODIFY COLUMN company_id BIGINT(20) UNSIGNED NULL;



  -- Tender Finance Users
CREATE TABLE `pod_tender_finance_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tender Committee Users
CREATE TABLE `pod_tender_committee_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tender Procurement Users
CREATE TABLE `pod_tender_procurement_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tender Department Users (Requester)
CREATE TABLE `pod_tender_department_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `company_id` (`company_id`),
  KEY `department_id` (`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




-- Stores which specialty was used to target vendors
CREATE TABLE `pod_tender_target_specialties` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_category_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_sub_category_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tts_tender` (`tender_id`),
  KEY `idx_tts_cat` (`vendor_category_id`),
  KEY `idx_tts_sub` (`vendor_sub_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stores actual invited vendors snapshot (deduped)
CREATE TABLE `pod_tender_invited_vendors` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` bigint(20) UNSIGNED NOT NULL,
  `vendor_id` bigint(20) UNSIGNED NOT NULL,
  `invite_status` enum('sent','delivered','opened','declined') NOT NULL DEFAULT 'sent',
  `invited_by` bigint(20) UNSIGNED DEFAULT NULL,
  `invited_at` datetime DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tender_vendor_active` (`tender_id`,`vendor_id`,`deleted`),
  KEY `idx_tiv_tender` (`tender_id`),
  KEY `idx_tiv_vendor` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




ALTER TABLE `pod_tender_request_approvals`
  CHANGE `approver_id` `decided_by` bigint(20) UNSIGNED DEFAULT NULL,
  MODIFY `decision` enum('submitted','approved','rejected') DEFAULT NULL,
  ADD COLUMN `stage` enum('requester_submit','finance','committee') DEFAULT NULL AFTER `tender_request_id`,
  ADD COLUMN `ip_address` varchar(45) DEFAULT NULL AFTER `decided_at`,
  ADD COLUMN `user_agent` varchar(500) DEFAULT NULL AFTER `ip_address`;



  ALTER TABLE `pod_tender_requests`
  ADD COLUMN `department_manager_user_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `department_id`,
  ADD COLUMN `department_manager_title` varchar(255) DEFAULT NULL AFTER `department_manager_user_id`,
  ADD COLUMN `department_manager_signed_at` datetime DEFAULT NULL AFTER `department_manager_title`;

CREATE TABLE `pod_tender_request_team_members` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_request_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `team_role` enum('technical_evaluator','commercial_evaluator','itc_member','chairman','secretary') NOT NULL DEFAULT 'technical_evaluator',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_trtm_request_role_deleted` (`tender_request_id`,`team_role`,`deleted`),
  KEY `idx_trtm_user_deleted` (`user_id`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- =========================================================
-- PHASE 1 + PHASE 2 SQL PATCH
-- =========================================================

CREATE TABLE `pod_tender_department_manager_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `department_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tdmu_user` (`user_id`),
  KEY `idx_tdmu_company_department` (`company_id`,`department_id`),
  KEY `idx_tdmu_status_deleted` (`status`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pod_tender_technical_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ttu_user` (`user_id`),
  KEY `idx_ttu_company` (`company_id`),
  KEY `idx_ttu_status_deleted` (`status`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pod_tender_commercial_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tcu_user` (`user_id`),
  KEY `idx_tcu_company` (`company_id`),
  KEY `idx_tcu_status_deleted` (`status`,`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pod_tender_requests`
  ADD COLUMN `department_manager_reject_comment` text DEFAULT NULL AFTER `department_manager_signed_at`,
  MODIFY `status` enum('draft','submitted','manager_approved','finance_verified','committee_approved','rejected') NOT NULL DEFAULT 'draft';

ALTER TABLE `pod_tender_request_approvals`
  MODIFY `stage` enum('requester_submit','manager','finance','committee') DEFAULT NULL;



  CREATE TABLE `pod_tender_bid_openings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` bigint(20) UNSIGNED NOT NULL,
  `stage` enum('commercial') NOT NULL DEFAULT 'commercial',
  `status` enum('pending','codes_generated','unlocked','expired') NOT NULL DEFAULT 'pending',
  `chairman_code` varchar(20) DEFAULT NULL,
  `secretary_code` varchar(20) DEFAULT NULL,
  `member_code` varchar(20) DEFAULT NULL,
  `generated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `unlocked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pod_tender_bid_openings_tender_id_idx` (`tender_id`),
  CONSTRAINT `pod_tender_bid_openings_tender_id_fk`
    FOREIGN KEY (`tender_id`) REFERENCES `pod_tenders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pod_tender_bid_opening_entries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_bid_opening_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('chairman','secretary','itc_member') NOT NULL,
  `input_chairman_code` varchar(20) DEFAULT NULL,
  `input_secretary_code` varchar(20) DEFAULT NULL,
  `input_member_code` varchar(20) DEFAULT NULL,
  `is_valid` tinyint(1) NOT NULL DEFAULT 0,
  `confirmed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pod_tender_bid_opening_entries_opening_id_idx` (`tender_bid_opening_id`),
  KEY `pod_tender_bid_opening_entries_user_id_idx` (`user_id`),
  CONSTRAINT `pod_tender_bid_opening_entries_opening_fk`
    FOREIGN KEY (`tender_bid_opening_id`) REFERENCES `pod_tender_bid_openings` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




ALTER TABLE `pod_tenders`
ADD COLUMN `workflow_stage` ENUM(
  'bidding',
  'technical',
  'committee_3key',
  'commercial',
  'award_decision'
) NOT NULL DEFAULT 'bidding' AFTER `status`,
ADD COLUMN `technical_start_at` DATETIME NULL AFTER `closing_at`,
ADD COLUMN `technical_end_at` DATETIME NULL AFTER `technical_start_at`,
ADD COLUMN `technical_locked_at` DATETIME NULL AFTER `technical_end_at`,
ADD COLUMN `committee_3key_start_at` DATETIME NULL AFTER `technical_locked_at`,
ADD COLUMN `committee_3key_end_at` DATETIME NULL AFTER `committee_3key_start_at`,
ADD COLUMN `commercial_start_at` DATETIME NULL AFTER `committee_3key_end_at`,
ADD COLUMN `commercial_end_at` DATETIME NULL AFTER `commercial_start_at`,
ADD COLUMN `commercial_unlocked_at` DATETIME NULL AFTER `commercial_end_at`,
ADD COLUMN `award_ready_at` DATETIME NULL AFTER `commercial_unlocked_at`;



ALTER TABLE `pod_tender_evaluations`
ADD COLUMN `decision` enum('accepted','rejected') DEFAULT NULL AFTER `status`;