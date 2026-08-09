-- Run this once with a deployment/migration account before switching the web
-- application to a least-privilege DML-only database account.

SET @pod_runtime_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

ALTER TABLE `pod_gate_pass_request_vehicles`
  ADD COLUMN IF NOT EXISTS `is_international_plate` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `plate_country` VARCHAR(120) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `international_plate_no` VARCHAR(120) DEFAULT NULL;

ALTER TABLE `pod_vendors`
  ADD COLUMN IF NOT EXISTS `cr_number` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `phone_country_code` VARCHAR(12) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_person` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_designation` VARCHAR(255) DEFAULT NULL;

ALTER TABLE `pod_ptw_applications`
  ADD COLUMN IF NOT EXISTS `terminal_approval_required` TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE `pod_ptw_requirement_responses`
  MODIFY `ptw_requirement_definition_id` BIGINT(20) UNSIGNED NULL;

ALTER TABLE `pod_ptw_attachments`
  MODIFY `ptw_requirement_id` BIGINT(20) UNSIGNED NULL;

ALTER TABLE `pod_tenders`
  ADD COLUMN IF NOT EXISTS `procurement_manager_action` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `procurement_manager_payload` LONGTEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `tender_fee` DECIMAL(15,3) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `evaluation_method` ENUM('separate','combined') NOT NULL DEFAULT 'separate',
  ADD COLUMN IF NOT EXISTS `technical_weight` TINYINT(3) UNSIGNED NOT NULL DEFAULT 70,
  ADD COLUMN IF NOT EXISTS `commercial_weight` TINYINT(3) UNSIGNED NOT NULL DEFAULT 30,
  ADD COLUMN IF NOT EXISTS `site_visit_location` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `site_visit_instructions` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `site_visit_mandatory` TINYINT(1) NOT NULL DEFAULT 0;

UPDATE `pod_tenders`
SET `evaluation_method`='separate'
WHERE `evaluation_method` IS NULL OR `evaluation_method` NOT IN ('separate','combined');

ALTER TABLE `pod_tenders`
  MODIFY `evaluation_method` ENUM('separate','combined') NOT NULL DEFAULT 'separate';

ALTER TABLE `pod_tender_target_specialties`
  ADD COLUMN IF NOT EXISTS `vendor_grade_id` BIGINT(20) UNSIGNED DEFAULT NULL;

ALTER TABLE `pod_tender_invited_vendors`
  MODIFY `invite_status`
    ENUM('sent','delivered','opened','declined','pending_approval','approved','rejected')
    NOT NULL DEFAULT 'sent';

ALTER TABLE `pod_tender_communications`
  ADD COLUMN IF NOT EXISTS `clarification_scope` VARCHAR(50) NOT NULL DEFAULT 'general',
  ADD COLUMN IF NOT EXISTS `tender_bid_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `internal_audience` VARCHAR(50) DEFAULT NULL;

ALTER TABLE `pod_tender_communications`
  MODIFY `type` VARCHAR(50) NULL DEFAULT NULL;

ALTER TABLE `pod_tender_evaluations`
  ADD COLUMN IF NOT EXISTS `review_started_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `review_duration_seconds` INT(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `deadline_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `submitted_after_deadline` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `late_review_status` ENUM('pending','accepted','rejected') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `late_reviewed_by` BIGINT(20) UNSIGNED DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `late_reviewed_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `late_review_comment` TEXT DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `pod_gate_pass_blocked_visitors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_number` VARCHAR(120) NOT NULL,
  `normalized_id_number` VARCHAR(120) NOT NULL,
  `id_type` VARCHAR(80) DEFAULT NULL,
  `visitor_name` VARCHAR(255) DEFAULT NULL,
  `nationality` VARCHAR(120) DEFAULT NULL,
  `visitor_company` VARCHAR(255) DEFAULT NULL,
  `source_request_id` BIGINT UNSIGNED DEFAULT NULL,
  `source_visitor_id` BIGINT UNSIGNED DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'blocked',
  `blocked_by` BIGINT UNSIGNED DEFAULT NULL,
  `blocked_at` DATETIME DEFAULT NULL,
  `unblocked_by` BIGINT UNSIGNED DEFAULT NULL,
  `unblocked_at` DATETIME DEFAULT NULL,
  `unblock_reason` TEXT DEFAULT NULL,
  `last_action_by` BIGINT UNSIGNED DEFAULT NULL,
  `last_action_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gp_blocked_visitors_norm_unique` (`normalized_id_number`),
  KEY `gp_blocked_visitors_status_idx` (`status`),
  KEY `gp_blocked_visitors_last_action_idx` (`last_action_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_gate_pass_blocked_visitor_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocked_visitor_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(30) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `action_by` BIGINT UNSIGNED DEFAULT NULL,
  `action_at` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(80) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `gp_blocked_visitor_logs_parent_idx` (`blocked_visitor_id`),
  KEY `gp_blocked_visitor_logs_action_idx` (`action_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_target_vendors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `deleted` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_target_vendors_tender` (`tender_id`, `deleted`),
  KEY `idx_tender_target_vendors_vendor` (`vendor_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_fee_payments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15,3) NOT NULL DEFAULT 0.000,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'OMR',
  `status` VARCHAR(50) NOT NULL DEFAULT 'paid',
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_fee_payments_tender_vendor` (`tender_id`, `vendor_id`, `deleted`),
  KEY `idx_tender_fee_payments_status` (`status`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_workflow_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `action_type` VARCHAR(50) NOT NULL DEFAULT 'stage_override',
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) DEFAULT NULL,
  `from_stage` VARCHAR(50) DEFAULT NULL,
  `to_stage` VARCHAR(50) DEFAULT NULL,
  `open_until` DATETIME DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_workflow_history_tender` (`tender_id`),
  KEY `idx_tender_workflow_history_action` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_communication_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `communication_id` BIGINT UNSIGNED NOT NULL,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED DEFAULT NULL,
  `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
  `path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `mime_type` VARCHAR(255) DEFAULT NULL,
  `size_bytes` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `deleted` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_comm_att_communication` (`communication_id`, `deleted`),
  KEY `idx_tender_comm_att_tender_vendor` (`tender_id`, `vendor_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_evaluation_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_evaluation_id` BIGINT UNSIGNED NOT NULL,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `tender_bid_id` BIGINT UNSIGNED NOT NULL,
  `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
  `path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `mime_type` VARCHAR(255) DEFAULT NULL,
  `size_bytes` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `deleted` INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_eval_att_eval` (`tender_evaluation_id`, `deleted`),
  KEY `idx_tender_eval_att_tender` (`tender_id`, `tender_bid_id`, `deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_bid_item_prices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_bid_id` BIGINT UNSIGNED NOT NULL,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `tender_rfq_item_id` BIGINT UNSIGNED NOT NULL,
  `qty` DECIMAL(18,3) DEFAULT NULL,
  `unit_price` DECIMAL(18,3) NOT NULL,
  `line_total` DECIMAL(18,3) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_bid_item_prices_bid` (`tender_bid_id`),
  KEY `idx_tender_bid_item_prices_tender_vendor` (`tender_id`, `vendor_id`),
  KEY `idx_tender_bid_item_prices_rfq_item` (`tender_rfq_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_rfq_details` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `rfq_no` VARCHAR(100) DEFAULT NULL,
  `rfq_date` DATE DEFAULT NULL,
  `pr_no` VARCHAR(100) DEFAULT NULL,
  `delivery_location` VARCHAR(255) DEFAULT NULL,
  `incoterm` VARCHAR(100) DEFAULT NULL,
  `material_required_on` DATE DEFAULT NULL,
  `terms_reference` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `enclosures` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tender_rfq_details_tender_unique` (`tender_id`),
  KEY `tender_rfq_details_tender_id_idx` (`tender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pod_tender_rfq_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_id` BIGINT UNSIGNED NOT NULL,
  `sr_no` VARCHAR(30) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `uom` VARCHAR(50) DEFAULT NULL,
  `qty` DECIMAL(18,3) DEFAULT NULL,
  `unit_price` DECIMAL(18,3) DEFAULT NULL,
  `brand` VARCHAR(150) DEFAULT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `tender_rfq_items_tender_id_idx` (`tender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE UNIQUE INDEX IF NOT EXISTS `gp_blocked_visitors_norm_unique`
  ON `pod_gate_pass_blocked_visitors` (`normalized_id_number`);
CREATE INDEX IF NOT EXISTS `gp_blocked_visitors_status_idx`
  ON `pod_gate_pass_blocked_visitors` (`status`);
CREATE INDEX IF NOT EXISTS `gp_blocked_visitors_last_action_idx`
  ON `pod_gate_pass_blocked_visitors` (`last_action_at`);
CREATE INDEX IF NOT EXISTS `gp_blocked_visitor_logs_parent_idx`
  ON `pod_gate_pass_blocked_visitor_logs` (`blocked_visitor_id`);
CREATE INDEX IF NOT EXISTS `gp_blocked_visitor_logs_action_idx`
  ON `pod_gate_pass_blocked_visitor_logs` (`action_at`);
CREATE INDEX IF NOT EXISTS `idx_tender_target_vendors_tender`
  ON `pod_tender_target_vendors` (`tender_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_target_vendors_vendor`
  ON `pod_tender_target_vendors` (`vendor_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_target_specialties_vendor_grade`
  ON `pod_tender_target_specialties` (`vendor_grade_id`);
CREATE INDEX IF NOT EXISTS `idx_tender_fee_payments_tender_vendor`
  ON `pod_tender_fee_payments` (`tender_id`, `vendor_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_fee_payments_status`
  ON `pod_tender_fee_payments` (`status`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_workflow_history_tender`
  ON `pod_tender_workflow_history` (`tender_id`);
CREATE INDEX IF NOT EXISTS `idx_tender_workflow_history_action`
  ON `pod_tender_workflow_history` (`action_type`);
CREATE INDEX IF NOT EXISTS `idx_tender_comm_att_communication`
  ON `pod_tender_communication_attachments` (`communication_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_comm_att_tender_vendor`
  ON `pod_tender_communication_attachments` (`tender_id`, `vendor_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_communications_scope`
  ON `pod_tender_communications` (`clarification_scope`, `tender_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_communications_type_scope`
  ON `pod_tender_communications` (`type`, `clarification_scope`, `tender_id`, `vendor_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_communications_bid_audience`
  ON `pod_tender_communications` (`tender_bid_id`, `internal_audience`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_eval_late_status`
  ON `pod_tender_evaluations` (`tender_id`, `type`, `submitted_after_deadline`, `late_review_status`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_eval_att_eval`
  ON `pod_tender_evaluation_attachments` (`tender_evaluation_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_eval_att_tender`
  ON `pod_tender_evaluation_attachments` (`tender_id`, `tender_bid_id`, `deleted`);
CREATE INDEX IF NOT EXISTS `idx_tender_bid_item_prices_bid`
  ON `pod_tender_bid_item_prices` (`tender_bid_id`);
CREATE INDEX IF NOT EXISTS `idx_tender_bid_item_prices_tender_vendor`
  ON `pod_tender_bid_item_prices` (`tender_id`, `vendor_id`);
CREATE INDEX IF NOT EXISTS `idx_tender_bid_item_prices_rfq_item`
  ON `pod_tender_bid_item_prices` (`tender_rfq_item_id`);
CREATE UNIQUE INDEX IF NOT EXISTS `tender_rfq_details_tender_unique`
  ON `pod_tender_rfq_details` (`tender_id`);
CREATE INDEX IF NOT EXISTS `tender_rfq_details_tender_id_idx`
  ON `pod_tender_rfq_details` (`tender_id`);
CREATE INDEX IF NOT EXISTS `tender_rfq_items_tender_id_idx`
  ON `pod_tender_rfq_items` (`tender_id`);

SET @pod_rfq_details_fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_tender_rfq_details'
    AND COLUMN_NAME='tender_id' AND REFERENCED_TABLE_NAME='pod_tenders'
    AND REFERENCED_COLUMN_NAME='id'
);
SET @pod_rfq_details_fk_sql := IF(
  @pod_rfq_details_fk_exists=0,
  'ALTER TABLE `pod_tender_rfq_details` ADD CONSTRAINT `pod_tender_rfq_details_tender_fk` FOREIGN KEY (`tender_id`) REFERENCES `pod_tenders` (`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE pod_runtime_stmt FROM @pod_rfq_details_fk_sql;
EXECUTE pod_runtime_stmt;
DEALLOCATE PREPARE pod_runtime_stmt;

SET @pod_rfq_items_fk_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_tender_rfq_items'
    AND COLUMN_NAME='tender_id' AND REFERENCED_TABLE_NAME='pod_tenders'
    AND REFERENCED_COLUMN_NAME='id'
);
SET @pod_rfq_items_fk_sql := IF(
  @pod_rfq_items_fk_exists=0,
  'ALTER TABLE `pod_tender_rfq_items` ADD CONSTRAINT `pod_tender_rfq_items_tender_fk` FOREIGN KEY (`tender_id`) REFERENCES `pod_tenders` (`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE pod_runtime_stmt FROM @pod_rfq_items_fk_sql;
EXECUTE pod_runtime_stmt;
DEALLOCATE PREPARE pod_runtime_stmt;

UPDATE `pod_vendors`
SET `status`='new'
WHERE `deleted`=0 AND (`status`='' OR `status` IS NULL);

SET @pod_tender_request_compatibility_columns := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'pod_tender_requests'
    AND COLUMN_NAME IN ('evaluation_method', 'technical_weight', 'commercial_weight')
);
SET @pod_tender_request_backfill_sql := IF(
  @pod_tender_request_compatibility_columns = 3,
  'UPDATE `pod_tenders` t INNER JOIN `pod_tender_requests` req ON req.id=t.tender_request_id AND req.deleted=0 SET t.evaluation_method=COALESCE(req.evaluation_method, t.evaluation_method), t.technical_weight=COALESCE(req.technical_weight, t.technical_weight), t.commercial_weight=COALESCE(req.commercial_weight, t.commercial_weight) WHERE t.deleted=0',
  'SELECT ''Tender-request compatibility columns are absent; tender backfill skipped'' AS info'
);
PREPARE pod_runtime_stmt FROM @pod_tender_request_backfill_sql;
EXECUTE pod_runtime_stmt;
DEALLOCATE PREPARE pod_runtime_stmt;

UPDATE `pod_tender_communications`
SET `type`=CONCAT(COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`), '_clarification_request')
WHERE `deleted`=0 AND (`type` IS NULL OR `type`='')
  AND COALESCE(NULLIF(`internal_audience`, ''), `clarification_scope`) IN ('technical','commercial')
  AND (`parent_id` IS NULL OR `parent_id`=0);

UPDATE `pod_tender_communications` child
INNER JOIN `pod_tender_communications` root ON root.id=child.parent_id AND root.deleted=0
SET child.`type`=CONCAT(COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`), '_clarification_response')
WHERE child.deleted=0 AND (child.`type` IS NULL OR child.`type`='')
  AND COALESCE(NULLIF(root.`internal_audience`, ''), root.`clarification_scope`) IN ('technical','commercial');

UPDATE `pod_tender_communications`
SET `type`='clarification'
WHERE `type` IS NULL OR `type`='';

ALTER TABLE `pod_tender_communications`
  MODIFY `type` VARCHAR(50) NOT NULL DEFAULT 'clarification';

SET SESSION SQL_SAFE_UPDATES = @pod_runtime_previous_sql_safe_updates;
