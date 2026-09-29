-- Port of Duqm: 29 September 2026 soft-delete repair. Select the EXISTING
-- production schema first. Back up the database, pause traffic/jobs, run the
-- WHOLE script, then upload the matching application patch.
-- No fee reset, password/phone replacement, hard delete, or payment mutation.
-- Requires MySQL 5.7+/8.x or MariaDB 10.2+ (stored generated columns).
-- Each ALTER commits independently. Stop on any error. Safe to run again.
SELECT DATABASE() AS selected_database;
CREATE TABLE IF NOT EXISTS pod_soft_delete_items (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 batch_key CHAR(32) NOT NULL,
 root_table VARCHAR(100) NOT NULL,
 root_id BIGINT UNSIGNED NOT NULL,
 table_name VARCHAR(100) NOT NULL,
 record_id BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL,
 restored_at DATETIME NULL,
 UNIQUE KEY uq_soft_delete_batch_row(batch_key,table_name,record_id),
 KEY idx_soft_delete_root(root_table,root_id,restored_at),
 KEY idx_soft_delete_record(table_name,record_id,restored_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SET @pod_old_group_concat = @@SESSION.group_concat_max_len;
SET SESSION group_concat_max_len=65535;
-- NULL does not reserve a unique value. Unlike UNIQUE(code,deleted), this
-- supports any number of archived copies and still forbids live duplicates.
-- Keep original key names and column prefixes. Preserve functional/previously
-- generated identity indexes, accounting idempotency keys, pass/QR tokens.

SET @pod_sd_table = 'pod_country';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_regions';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_cities';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_companies';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_legal_types';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_vendor_document_types';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_vendor_roles';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_vendor_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_gate_pass_department_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_gate_pass_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_applicant_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_hmo_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_hsse_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_terminal_users';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_requirement_definitions';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_requirement_responses';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_ptw_reviews';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_tender_bids';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_tender_evaluation_scores';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_tender_invited_vendors';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_tender_request_vendors';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET @pod_sd_table = 'pod_tender_rfq_details';
SET @pod_sd_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='deleted');
SET @pod_sd_slot = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=@pod_sd_table AND COLUMN_NAME='_live_row');
SET @pod_sd_sql = IF(@pod_sd_exists=1 AND @pod_sd_slot=0, CONCAT('ALTER TABLE `',@pod_sd_table,'` ADD COLUMN `_live_row` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 THEN 1 ELSE NULL END) STORED'), 'SELECT 1 AS soft_delete_column_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;
SET @pod_sd_sql = (
 SELECT CONCAT('ALTER TABLE `',@pod_sd_table,'` ', GROUP_CONCAT(CONCAT('DROP INDEX `', idx.INDEX_NAME,'`, ADD UNIQUE KEY `',idx.INDEX_NAME,'` (',idx.cols,',`_live_row`)') SEPARATOR ', '))
 FROM (
  SELECT s.INDEX_NAME, GROUP_CONCAT(IF(s.COLUMN_NAME='deleted',NULL,CONCAT('`',s.COLUMN_NAME,'`',IF(s.SUB_PART IS NULL,'',CONCAT('(',s.SUB_PART,')')))) ORDER BY s.SEQ_IN_INDEX SEPARATOR ',') AS cols
  FROM information_schema.STATISTICS s
  JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=s.TABLE_SCHEMA AND c.TABLE_NAME=s.TABLE_NAME AND c.COLUMN_NAME=s.COLUMN_NAME
  WHERE s.TABLE_SCHEMA=DATABASE() AND s.TABLE_NAME=@pod_sd_table AND s.NON_UNIQUE=0 AND s.INDEX_NAME<>'PRIMARY'
  GROUP BY s.INDEX_NAME
  HAVING SUM(c.EXTRA LIKE '%GENERATED%')=0 AND SUM(s.COLUMN_NAME<>'deleted')>0
 ) idx
);
SET @pod_sd_sql=IF(@pod_sd_exists=1 AND @pod_sd_sql IS NOT NULL,@pod_sd_sql,'SELECT 1 AS soft_delete_indexes_ready');
PREPARE pod_sd_stmt FROM @pod_sd_sql; EXECUTE pod_sd_stmt; DEALLOCATE PREPARE pod_sd_stmt;

SET SESSION group_concat_max_len=@pod_old_group_concat;
SELECT 'Soft-delete schema installed. Run 02_VERIFY.sql before uploading.' AS result;

