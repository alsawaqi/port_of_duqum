-- =============================================================================
-- Gate Pass — schema alignment for DATABASE `pod` (physical tables `pod_*`)
-- =============================================================================
-- Matches app/Config/Database.php defaults:
--   database = pod
--   DBPrefix = pod_   →  logical `gate_pass_request_vehicles` = `pod_gate_pass_request_vehicles`
--
-- Aligned with a typical `pod` export (prefixed tables, e.g. pod_gate_pass_request_vehicles).
--   • Vehicles: add mulkiyah_attachment_path (missing there); copy from legacy path when present
--   • Audit log: create pod_gate_pass_request_audit_log if missing
--   • Requests: add fee_waived_at only if missing (pod.sql already has stage, stage_updated_at, etc.)
--   • Approvals: drop UNIQUE(gate_pass_request_id, stage) if present; reason_id already exists in pod.sql
--
-- Before running: BACK UP `pod`. Run in phpMyAdmin with database `pod` selected.
--
-- MySQL Workbench "Safe Updates Mode" (sql_safe_updates) rejects UPDATE unless the
-- WHERE uses a key column. This script uses normal data predicates; disable for SESSION only.
-- =============================================================================

USE `pod`;

SET SESSION sql_safe_updates = 0;

-- -----------------------------------------------------------------------------
-- A) Vehicles: mulkiyah_attachment_path (skip if already added)
-- -----------------------------------------------------------------------------
SET @db := DATABASE();
SET @tabveh := 'pod_gate_pass_request_vehicles';
SET @colmul := 'mulkiyah_attachment_path';

SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabveh AND COLUMN_NAME = @colmul) > 0,
    'SELECT ''pod_gate_pass_request_vehicles: mulkiyah_attachment_path already exists'' AS info',
    CONCAT(
      'ALTER TABLE `', @tabveh, '` ADD COLUMN `', @colmul,
      '` VARCHAR(512) NULL DEFAULT NULL COMMENT ''Vehicle registration (mulkiyah) scan'''
    )
  )
);
PREPARE stmt_mul FROM @sql;
EXECUTE stmt_mul;
DEALLOCATE PREPARE stmt_mul;

-- Copy from legacy column when both exist
SET @has_legacy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabveh
    AND COLUMN_NAME = 'vehicle_registration_attachment_path'
);
SET @mulupd := IF(
  @has_legacy > 0,
  CONCAT(
    'UPDATE `', @tabveh, '` SET mulkiyah_attachment_path = vehicle_registration_attachment_path ',
    'WHERE (mulkiyah_attachment_path IS NULL OR mulkiyah_attachment_path = '''') ',
    'AND vehicle_registration_attachment_path IS NOT NULL ',
    'AND vehicle_registration_attachment_path != '''''
  ),
  'SELECT ''Skip mulkiyah copy: no vehicle_registration_attachment_path column'' AS info'
);
PREPARE mulstmt FROM @mulupd;
EXECUTE mulstmt;
DEALLOCATE PREPARE mulstmt;

-- -----------------------------------------------------------------------------
-- B) Audit log (portal activity log)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pod_gate_pass_request_audit_log` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `gate_pass_request_id` int UNSIGNED NOT NULL,
  `actor_user_id` int UNSIGNED NOT NULL DEFAULT 0,
  `action` varchar(100) NOT NULL,
  `details` text NULL,
  `created_at` datetime NOT NULL,
  `ip_address` varchar(64) NULL DEFAULT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_gp_audit_request` (`gate_pass_request_id`),
  KEY `idx_gp_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- C) Requests: fee_waived_at only (pod.sql already has stage, stage_updated_at, …)
-- -----------------------------------------------------------------------------
SET @tabreq := 'pod_gate_pass_requests';
SET @colfwa := 'fee_waived_at';

SET @sqlf := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabreq AND COLUMN_NAME = @colfwa) > 0,
    'SELECT ''pod_gate_pass_requests: fee_waived_at already exists'' AS info',
    CONCAT('ALTER TABLE `', @tabreq, '` ADD COLUMN `', @colfwa, '` DATETIME NULL DEFAULT NULL')
  )
);
PREPARE stmt_fwa FROM @sqlf;
EXECUTE stmt_fwa;
DEALLOCATE PREPARE stmt_fwa;

-- -----------------------------------------------------------------------------
-- C2) Requests: created_at — add if missing; backfill from submitted_at
-- -----------------------------------------------------------------------------
SET @colca := 'created_at';
SET @sqlca := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabreq AND COLUMN_NAME = @colca) > 0,
    'SELECT ''pod_gate_pass_requests: created_at column already exists'' AS info',
    CONCAT('ALTER TABLE `', @tabreq, '` ADD COLUMN `', @colca, '` DATETIME NULL DEFAULT NULL')
  )
);
PREPARE stmt_ca FROM @sqlca;
EXECUTE stmt_ca;
DEALLOCATE PREPARE stmt_ca;

UPDATE `pod_gate_pass_requests`
SET `created_at` = `submitted_at`
WHERE `deleted` = 0
  AND `submitted_at` IS NOT NULL
  AND `submitted_at` <> '0000-00-00 00:00:00'
  AND (`created_at` IS NULL OR `created_at` = '0000-00-00 00:00:00');

-- -----------------------------------------------------------------------------
-- C3) Requests: fee waiver commercial review (department requests; commercial decides)
-- -----------------------------------------------------------------------------
SET @colfwr := 'fee_waiver_requested';
SET @sqlfwr := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabreq AND COLUMN_NAME = @colfwr) > 0,
    'SELECT ''pod_gate_pass_requests: fee_waiver_requested already exists'' AS info',
    CONCAT('ALTER TABLE `', @tabreq, '` ADD COLUMN `', @colfwr, '` TINYINT(1) NOT NULL DEFAULT 0')
  )
);
PREPARE stmt_fwr FROM @sqlfwr;
EXECUTE stmt_fwr;
DEALLOCATE PREPARE stmt_fwr;

SET @colfwst := 'fee_waiver_commercial_status';
SET @sqlfwst := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = @tabreq AND COLUMN_NAME = @colfwst) > 0,
    'SELECT ''pod_gate_pass_requests: fee_waiver_commercial_status already exists'' AS info',
    CONCAT('ALTER TABLE `', @tabreq, '` ADD COLUMN `', @colfwst, '` VARCHAR(32) NULL DEFAULT NULL')
  )
);
PREPARE stmt_fwst FROM @sqlfwst;
EXECUTE stmt_fwst;
DEALLOCATE PREPARE stmt_fwst;

UPDATE `pod_gate_pass_requests`
SET `fee_waiver_requested` = 0, `fee_waiver_commercial_status` = 'approved'
WHERE `deleted` = 0 AND `fee_is_waived` = 1
  AND (`fee_waiver_commercial_status` IS NULL OR `fee_waiver_commercial_status` = '');

-- -----------------------------------------------------------------------------
-- D) Approvals: drop UNIQUE(gate_pass_request_id, stage) when present
-- -----------------------------------------------------------------------------
SET @tbl := 'pod_gate_pass_request_approvals';
SET @idx := (
  SELECT s.INDEX_NAME
  FROM information_schema.STATISTICS s
  WHERE s.TABLE_SCHEMA = @db
    AND s.TABLE_NAME = @tbl
    AND s.NON_UNIQUE = 0
    AND s.INDEX_NAME <> 'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING GROUP_CONCAT(s.COLUMN_NAME ORDER BY s.SEQ_IN_INDEX)
      IN ('gate_pass_request_id,stage', 'stage,gate_pass_request_id')
  LIMIT 1
);
SET @dropsql := IF(
  @idx IS NULL,
  'SELECT ''No matching UNIQUE(gate_pass_request_id,stage) on pod_gate_pass_request_approvals; skip.'' AS info',
  CONCAT('ALTER TABLE `', @tbl, '` DROP INDEX `', REPLACE(@idx, '`', '``'), '`')
);
PREPARE stmt_drop FROM @dropsql;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET SESSION sql_safe_updates = 1;

-- -----------------------------------------------------------------------------
-- E) reason_id on approvals — already present in pod.sql; nothing to run.
-- =============================================================================
-- OPTIONAL — pod_gate_pass_requests.status (only if your DB still needs it)
-- =============================================================================
-- SHOW COLUMNS FROM `pod_gate_pass_requests` LIKE 'status';
-- If ENUM is missing values, e.g.:
-- ALTER TABLE `pod_gate_pass_requests`
--   MODIFY COLUMN `status` VARCHAR(64) NOT NULL DEFAULT 'draft';
-- =============================================================================
