-- Vendor contact credential readiness and same-CR email uniqueness guard.
-- Idempotent for databases that already ran the multi-CR identity upgrade.
-- This script never drops or replaces the live-email identity/index. It stops
-- when existing metadata or data is incompatible so the guard is not weakened.
-- MariaDB does not allow SIGNAL in a prepared statement. Conditional failures
-- therefore select a deliberately nonexistent SECURITY_ERROR_* table; the
-- table name states the cause. When using the mysql/MariaDB command-line client,
-- enable --abort-source-on-error so a sourced script stops at that assertion:
-- mysql --abort-source-on-error DATABASE_NAME -e "source path/to/this_file.sql"

SET @pod_schema = DATABASE();

SELECT COUNT(*) INTO @pod_vendor_users_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_users';

SET @pod_sql = IF(
    @pod_vendor_users_table = 1,
    'SELECT ''pod_vendor_users is available.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_vendor_users_table_required`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_credentials_ready_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'credentials_ready_at';

SET @pod_sql = IF(
    @pod_credentials_ready_column = 0,
    'ALTER TABLE `pod_vendor_users` ADD COLUMN `credentials_ready_at` DATETIME NULL DEFAULT NULL AFTER `invited_at`',
    'SELECT ''credentials_ready_at already exists; validating it.'' AS info'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_credentials_ready_compatible
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'credentials_ready_at'
  AND LOWER(DATA_TYPE) = 'datetime'
  AND UPPER(IS_NULLABLE) = 'YES';

SET @pod_sql = IF(
    @pod_credentials_ready_compatible = 1,
    'SELECT ''credentials_ready_at is a nullable DATETIME.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_credentials_ready_at_incompatible`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_vendor_contacts_table
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_contacts';

SET @pod_sql = IF(
    @pod_vendor_contacts_table = 1,
    'SELECT ''pod_vendor_contacts is available.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_vendor_contacts_table_required`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_live_email_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_email_identity';

SET @pod_sql = IF(
    @pod_live_email_identity_column = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD COLUMN `live_email_identity` VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 THEN NULLIF(LOWER(TRIM(`email`)), '''') ELSE NULL END) STORED',
    'SELECT ''live_email_identity already exists; validating it.'' AS info'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_live_email_identity_compatible
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_email_identity'
  AND LOWER(DATA_TYPE) = 'varchar'
  AND CHARACTER_MAXIMUM_LENGTH = 255
  AND UPPER(EXTRA) LIKE '%STORED%'
  AND UPPER(EXTRA) LIKE '%GENERATED%'
  AND LOWER(REPLACE(REPLACE(GENERATION_EXPRESSION, '`', ''), ' ', '')) LIKE '%deleted=0%'
  AND LOWER(GENERATION_EXPRESSION) LIKE '%trim%'
  AND LOWER(GENERATION_EXPRESSION) LIKE '%email%'
  AND LOWER(GENERATION_EXPRESSION) LIKE '%nullif%'
  AND (
      LOWER(GENERATION_EXPRESSION) LIKE '%lower%'
      OR LOWER(GENERATION_EXPRESSION) LIKE '%lcase%'
  );

SET @pod_sql = IF(
    @pod_live_email_identity_compatible = 1,
    'SELECT ''live_email_identity is normalized and generated.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_live_email_identity_incompatible`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_duplicate_live_contact_emails
FROM (
    SELECT `vendor_id`, LOWER(TRIM(`email`)) AS normalized_email
    FROM `pod_vendor_contacts`
    WHERE `deleted` = 0 AND NULLIF(TRIM(`email`), '') IS NOT NULL
    GROUP BY `vendor_id`, LOWER(TRIM(`email`))
    HAVING COUNT(*) > 1
) duplicate_live_contact_emails;

SET @pod_sql = IF(
    @pod_duplicate_live_contact_emails = 0,
    'SELECT ''No duplicate live contact emails exist within a CR.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_same_cr_contact_email_duplicates`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_live_email_index_rows
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_live_email';

SELECT COUNT(*) INTO @pod_live_email_index_valid
FROM (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @pod_schema
      AND TABLE_NAME = 'pod_vendor_contacts'
      AND INDEX_NAME = 'uq_vendor_contacts_live_email'
    GROUP BY INDEX_NAME
    HAVING MIN(NON_UNIQUE) = 0
       AND MAX(NON_UNIQUE) = 0
       AND GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'vendor_id,live_email_identity'
) valid_live_email_index;

SET @pod_sql = IF(
    @pod_live_email_index_rows = 0 OR @pod_live_email_index_valid = 1,
    'SELECT ''uq_vendor_contacts_live_email is absent or valid.'' AS info',
    'SELECT * FROM `SECURITY_ERROR_live_email_unique_index_incompatible`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SET @pod_sql = IF(
    @pod_live_email_index_rows = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD UNIQUE INDEX `uq_vendor_contacts_live_email` (`vendor_id`, `live_email_identity`)',
    'SELECT ''uq_vendor_contacts_live_email is already enforced.'' AS info'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_live_email_index_verified
FROM (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @pod_schema
      AND TABLE_NAME = 'pod_vendor_contacts'
      AND INDEX_NAME = 'uq_vendor_contacts_live_email'
    GROUP BY INDEX_NAME
    HAVING MIN(NON_UNIQUE) = 0
       AND MAX(NON_UNIQUE) = 0
       AND GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = 'vendor_id,live_email_identity'
) verified_live_email_index;

SET @pod_sql = IF(
    @pod_live_email_index_verified = 1,
    'SELECT ''Vendor credential readiness and normalized same-CR contact email uniqueness are ready.'' AS result',
    'SELECT * FROM `SECURITY_ERROR_live_email_unique_index_missing`'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;
