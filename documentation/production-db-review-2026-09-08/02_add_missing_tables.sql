-- Production schema patch prepared for the supplied 2026-09-08 export.
-- Target: MariaDB 10.11.19, database bedotscpanel_poderp, prefix pod_.
-- Back up the production database and uploaded files first. Pause application writes.
-- Adds only the four missing application tables; existing tables/data are preserved.
-- Does not import localhost users, settings, payment records, or migration history.
-- If any of these tables already exists on the live server, compare its definition
-- with this file: IF NOT EXISTS preserves it but does not repair partial tables.
-- Run 01_preflight.sql before this file and 03_verify.sql afterward.
USE `bedotscpanel_poderp`;

CREATE TABLE IF NOT EXISTS `pod_eservice_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(32) NOT NULL,
  `subject_type` varchar(40) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `vendor_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `amount_minor` bigint(20) unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `provider` varchar(32) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `idempotency_key` char(64) NOT NULL,
  `provider_checkout_id` varchar(255) DEFAULT NULL,
  `checkout_url` text DEFAULT NULL,
  `provider_payment_id` varchar(255) DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `initiated_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `failure_code` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `active_subject_key` varchar(190) GENERATED ALWAYS AS (case when `deleted` = 0 and `status` in ('pending','processing','verification_required') then concat(`subject_type`,':',`subject_id`,':',coalesce(`vendor_id`,0)) else NULL end) STORED,
  `gateway_merchant_id` varchar(100) DEFAULT NULL,
  `bank_reference` varchar(255) DEFAULT NULL,
  `response_json` mediumtext DEFAULT NULL,
  `status_response_json` mediumtext DEFAULT NULL,
  `verification_issues` text DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `handed_off_at` datetime DEFAULT NULL,
  `last_status_check_at` datetime DEFAULT NULL,
  `settlement_status` varchar(24) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eservice_payments_public` (`public_id`),
  UNIQUE KEY `uq_eservice_payments_idempotency` (`idempotency_key`),
  UNIQUE KEY `uq_eservice_payments_checkout` (`provider`,`provider_checkout_id`),
  UNIQUE KEY `uq_eservice_payments_active_subject` (`active_subject_key`),
  KEY `idx_eservice_payments_subject` (`subject_type`,`subject_id`,`vendor_id`),
  KEY `idx_eservice_payments_status` (`status`,`expires_at`),
  KEY `idx_eservice_payments_accounting` (`subject_type`,`initiated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_eservice_payment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_event_id` varchar(255) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `payload_sha256` char(64) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'received',
  `received_at` datetime NOT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `response_json` mediumtext DEFAULT NULL,
  `verification_issues` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_eservice_payment_events_provider` (`provider`,`provider_event_id`),
  KEY `payment_id` (`payment_id`),
  KEY `status_received_at` (`status`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_vendor_fee_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `fee_type` varchar(20) NOT NULL,
  `period_key` char(64) NOT NULL,
  `fee_id` bigint(20) unsigned NOT NULL,
  `vendor_group_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `currency` char(3) NOT NULL,
  `prior_valid_until` date DEFAULT NULL,
  `validity_days` int(10) unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `payment_id` bigint(20) unsigned DEFAULT NULL,
  `review_status` varchar(24) NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned NOT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_fee_period` (`vendor_id`,`period_key`),
  KEY `idx_vendor_fee_review` (`vendor_id`,`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_sms_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_key` char(64) NOT NULL,
  `module` varchar(20) NOT NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `source_table` varchar(40) NOT NULL,
  `source_id` bigint(20) unsigned NOT NULL,
  `reference` varchar(100) NOT NULL,
  `reason` varchar(40) NOT NULL,
  `recipient_user_id` bigint(20) unsigned DEFAULT NULL,
  `recipient_name` varchar(190) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `language` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL,
  `is_preview` tinyint(4) NOT NULL DEFAULT 1,
  `provider_code` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `claimed_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_key` (`event_key`),
  KEY `status_id` (`status`,`id`),
  KEY `module_subject_id` (`module`,`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
