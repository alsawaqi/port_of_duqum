-- Point 3: select the application's pod_ database before running this SQL.
-- Additive and repeatable on MySQL and MariaDB. Existing records remain unchanged.
-- No code is generated or assigned automatically.
SET @vendor_code_ddl = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pod_vendors' AND COLUMN_NAME = 'vendor_code'),
    'SELECT ''vendor_code already exists'' AS result',
    'ALTER TABLE `pod_vendors` ADD COLUMN `vendor_code` VARCHAR(64) NULL DEFAULT NULL AFTER `cr_number`'
);
PREPARE vendor_code_statement FROM @vendor_code_ddl;
EXECUTE vendor_code_statement;
DEALLOCATE PREPARE vendor_code_statement;
