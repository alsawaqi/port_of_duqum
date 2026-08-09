-- Gate-pass QR replay hardening for the standard pod_ prefix.
-- Run once, after gate_pass_full_upgrade_pod.sql, with an error-stopping client.
--
-- This manual path deliberately FAILS CLOSED if an existing QR token is blank,
-- malformed, or duplicated after case normalization. Portable SQL cannot safely
-- reproduce PHP random_bytes(32). If the assertion fails, reissue the affected
-- passes through the application migration/approved CSPRNG utility, then rerun.
-- Do not replace the assertion with UUID(), RAND(), or another weak token source.

SET @pod_gate_qr_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

SET @pod_invalid_qr_count := (
  SELECT COUNT(*)
  FROM `pod_gate_passes`
  WHERE `qr_token` IS NULL
     OR TRIM(`qr_token`) NOT REGEXP BINARY '^[0-9A-Fa-f]{64}$'
);

SET @pod_duplicate_qr_count := (
  SELECT COUNT(*)
  FROM (
    SELECT LOWER(TRIM(`qr_token`)) AS normalized_token
    FROM `pod_gate_passes`
    WHERE `qr_token` IS NOT NULL
      AND TRIM(`qr_token`) REGEXP BINARY '^[0-9A-Fa-f]{64}$'
    GROUP BY LOWER(TRIM(`qr_token`))
    HAVING COUNT(*) > 1
  ) AS duplicate_tokens
);

SET @pod_qr_preflight_sql := IF(
  @pod_invalid_qr_count = 0 AND @pod_duplicate_qr_count = 0,
  'SELECT ''Gate-pass QR preflight passed'' AS info',
  'SELECT * FROM `__ABORT_GATE_PASS_QR_REISSUE_REQUIRED__`'
);
PREPARE pod_qr_preflight_stmt FROM @pod_qr_preflight_sql;
EXECUTE pod_qr_preflight_stmt;
DEALLOCATE PREPARE pod_qr_preflight_stmt;

-- Safe only after the fail-closed preflight above has succeeded.
UPDATE `pod_gate_passes`
SET `qr_token` = LOWER(TRIM(`qr_token`));

ALTER TABLE `pod_gate_passes`
  MODIFY COLUMN `qr_token` VARCHAR(64) NOT NULL;

SET @pod_qr_unique_index_sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_gate_passes'
      AND INDEX_NAME = 'uq_gate_passes_qr_token'
  ),
  'SELECT ''uq_gate_passes_qr_token already exists'' AS info',
  'ALTER TABLE `pod_gate_passes` ADD UNIQUE INDEX `uq_gate_passes_qr_token` (`qr_token`)'
);
PREPARE pod_qr_unique_index_stmt FROM @pod_qr_unique_index_sql;
EXECUTE pod_qr_unique_index_stmt;
DEALLOCATE PREPARE pod_qr_unique_index_stmt;

SET @pod_movement_index_sql := IF(
  EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_gate_pass_scan_log'
      AND INDEX_NAME = 'idx_gate_pass_scan_movement_lock'
  ),
  'SELECT ''idx_gate_pass_scan_movement_lock already exists'' AS info',
  'ALTER TABLE `pod_gate_pass_scan_log` ADD INDEX `idx_gate_pass_scan_movement_lock` (`gate_pass_id`, `gate_pass_request_visitor_id`, `action`, `recorded_at`, `id`)'
);
PREPARE pod_movement_index_stmt FROM @pod_movement_index_sql;
EXECUTE pod_movement_index_stmt;
DEALLOCATE PREPARE pod_movement_index_stmt;

SELECT
  @pod_invalid_qr_count AS invalid_qr_tokens_before_upgrade,
  @pod_duplicate_qr_count AS duplicate_qr_groups_before_upgrade;

SET SESSION SQL_SAFE_UPDATES = @pod_gate_qr_previous_sql_safe_updates;
