-- Deployment repair for the errors reported on September 23-27, 2026.
-- Back up first, then select the application database in MySQL Workbench.
-- Uses the existing pod_ prefix. Safe to repeat; no rows or fees are deleted/reset.
-- No migration runner or SMTP/SMS/bank credentials required.
SELECT DATABASE() AS selected_database;

SET @pod_repair = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_users' AND COLUMN_NAME='auth_session_version')=0, 'ALTER TABLE `pod_users` ADD COLUMN `auth_session_version` INT UNSIGNED NOT NULL DEFAULT 1', 'SELECT ''auth_session_version already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

SET @pod_repair = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_users' AND COLUMN_NAME='otp_delivery_channel')=0, 'ALTER TABLE `pod_users` ADD COLUMN `otp_delivery_channel` VARCHAR(16) NOT NULL DEFAULT ''''', 'SELECT ''otp_delivery_channel already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

SET @pod_repair = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_gate_pass_requests' AND COLUMN_NAME='fee_breakdown')=0, 'ALTER TABLE `pod_gate_pass_requests` ADD COLUMN `fee_breakdown` LONGTEXT NULL', 'SELECT ''fee_breakdown already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

SET @pod_repair = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_gate_pass_fee_rules' AND COLUMN_NAME='induction_amount')=0, 'ALTER TABLE `pod_gate_pass_fee_rules` ADD COLUMN `induction_amount` DECIMAL(10,3) NOT NULL DEFAULT 0', 'SELECT ''induction_amount already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

-- Run in the application database before uploading the corresponding PHP files.
CREATE TABLE IF NOT EXISTS pod_gate_pass_notification_outbox (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 event_key CHAR(64) NOT NULL,
 request_id BIGINT UNSIGNED NOT NULL,
 visitor_id BIGINT UNSIGNED NULL,
 recipient_user_id INT NULL,
 channel VARCHAR(12) NOT NULL,
 destination VARCHAR(254) NOT NULL DEFAULT '',
 subject VARCHAR(190) NOT NULL,
 message TEXT NOT NULL,
 status VARCHAR(24) NOT NULL DEFAULT 'queued',
 is_preview TINYINT NOT NULL DEFAULT 0,
 created_at DATETIME NOT NULL,
 claimed_at DATETIME NULL,
 processed_at DATETIME NULL,
 UNIQUE KEY event_recipient (event_key),
 KEY dispatch_status (status,id),
 KEY request_notifications (request_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Retain any additional host-specific decision enum values.
SET @pod_decision_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_gate_pass_request_approvals' AND COLUMN_NAME='decision');
SET @pod_repair = IF(LEFT(@pod_decision_type,5)='enum(' AND LOCATE('''fee_waiver_rejected''',@pod_decision_type)=0,
 CONCAT('ALTER TABLE `pod_gate_pass_request_approvals` MODIFY `decision` ',LEFT(@pod_decision_type,CHAR_LENGTH(@pod_decision_type)-1),',''fee_waiver_rejected'') NULL DEFAULT NULL'),
 'SELECT ''Decision column already supports waiver rejection, or is not an enum'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='pod_users' AND COLUMN_NAME IN ('auth_session_version','otp_delivery_channel'))
 OR (TABLE_NAME='pod_gate_pass_requests' AND COLUMN_NAME='fee_breakdown')
 OR (TABLE_NAME='pod_gate_pass_fee_rules' AND COLUMN_NAME='induction_amount')
 OR (TABLE_NAME='pod_gate_pass_request_approvals' AND COLUMN_NAME='decision'));
SHOW COLUMNS FROM pod_gate_pass_notification_outbox;

-- Linked PTW applicant-assignment screen exists in the application, but this
-- table is absent from the older supplied schema. Existing assignments are kept.
-- Creating it does not grant any new roles or reviewer permissions.
CREATE TABLE IF NOT EXISTS pod_ptw_applicant_users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id INT NOT NULL,
 company_id BIGINT UNSIGNED NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'active',
 deleted TINYINT(1) NOT NULL DEFAULT 0,
 created_at DATETIME NULL,
 updated_at DATETIME NULL,
 PRIMARY KEY (id),
 UNIQUE KEY uq_ptw_applicant_user_company (user_id,company_id),
 KEY idx_ptw_applicant_active_user (user_id,status,deleted),
 KEY idx_ptw_applicant_company (company_id,status,deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PTW's built-in "Other" answers have no master definition ID. The older
-- NOT NULL definition prevents every PTW page from passing its readiness check.
-- Retain the existing integer type, foreign key and all answer rows.
SET @pod_ptw_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_ptw_requirement_responses' AND COLUMN_NAME='ptw_requirement_definition_id');
SET @pod_repair = IF((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_ptw_requirement_responses' AND COLUMN_NAME='ptw_requirement_definition_id')='NO',
 CONCAT('ALTER TABLE `pod_ptw_requirement_responses` MODIFY COLUMN `ptw_requirement_definition_id` ',@pod_ptw_type,' NULL DEFAULT NULL'),
 'SELECT ''PTW optional definition column already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;

-- The same optional definition applies to uploaded Other attachments.
SET @pod_ptw_type = (SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_ptw_attachments' AND COLUMN_NAME='ptw_requirement_id');
SET @pod_repair = IF((SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_ptw_attachments' AND COLUMN_NAME='ptw_requirement_id')='NO',
 CONCAT('ALTER TABLE `pod_ptw_attachments` MODIFY COLUMN `ptw_requirement_id` ',@pod_ptw_type,' NULL DEFAULT NULL'),
 'SELECT ''PTW optional definition column already installed'' AS result');
PREPARE pod_repair_stmt FROM @pod_repair;
EXECUTE pod_repair_stmt;
DEALLOCATE PREPARE pod_repair_stmt;
