-- Port of Duqm current-release combined security database upgrade.
-- Generated from the ten reviewed manual SQL files in deployment order.
-- Target: the selected database using the standard pod_ table prefix.
-- Local compatibility baseline: MariaDB 10.4.32. Verify production separately.
--
-- PAYMENT AND SMS ACTIVATION ARE NOT INCLUDED.
-- The generic auth_mfa_challenges table may be created, but no SMS provider is
-- enabled and no payment gateway migration is included by this file.
-- The runtime section does create dormant tender-fee bookkeeping schema because
-- it is part of that requested source file; it does not activate payment.
--
-- IMPORTANT OPERATOR RULES
-- 1. Take and restore-test a backup before running this file.
-- 2. Stop application writes and select the intended database first.
-- 3. In MySQL Workbench, set Preferences > SQL Editor > "Max number of result
--    sets" to at least 250 and disable "Continue SQL Script on Errors".
--    Reconnect, then execute the COMPLETE file in one connection/session,
--    never only a highlighted part.
-- 4. Stop at the first error. If an error occurs, reconnect before normal DB
--    work, fix the reported precondition, then rerun this complete guarded file.
-- 5. Do not also run the equivalent CodeIgniter migrations.
-- 6. DDL implicitly commits in MariaDB/MySQL; this upgrade is not atomic.
-- 7. QR preflight deliberately aborts if a cryptographically secure reissue is
--    required. Do not replace it with RAND() or UUID().
--
-- Source SHA-256 values cover the embedded canonical bodies (UTF-8, LF line
-- endings, trailing blank lines removed, and one final LF):
--   vendor_multi_cr_contact_identities_upgrade_pod.sql: 6EF0388A592A3D86153394ADBC822C8ED626BF173FD8226F382C1149789BDDB9
--   vendor_contact_credentials_readiness_upgrade_pod.sql: 1E3AE94EC86412D9B19844E114E98A2DCE25335B01B9F3ABBCCB99154C7347A0
--   authentication_hardening_upgrade_pod.sql: 7A6AB2CA796BF9E6D518B4E6D1F9698879B3350270097FF0BFFA3F7B8FA89A74
--   gate_pass_scan_replay_protection_upgrade_pod.sql: 312B984A6FE91C61988DF13BB620561B09F424A6309732538222C86E8AFCEBCC
--   vendor_portal_role_enforcement_upgrade_pod.sql: 8DD75375EBEA5C7A758E660FE57535D883CD44C7184808A6557E30A40C5712BD
--   notification_processor_hardening_upgrade_pod.sql: 7A388311E63CED36B5166AA975DB31B3D8F0C90D5E033823728D9A5345A27717
--   ptw_company_scope_upgrade_pod.sql: ED39602AB2C8381EF7AA605C5F4F48DD975E990BE33472AC87F58E4F247A8458
--   tender_opening_secret_hardening_upgrade_pod.sql: 00C8626685C68B175526662CB4C7E3488AEDD2BA3AF226EBF76571E28B33647F
--   ptw_applicant_company_assignments_upgrade_pod.sql: A98D14D02FDC342202AEF0DAB4632A540E774675CAE1EA60409756D9CA625AE1
--   runtime_schema_ownership_hardening_upgrade_pod.sql: 6E8789793912196BF325ED76A4293831AF83BE3207B7A3FD4F2C2E8E9E9BEA28


SELECT DATABASE() AS `pod_upgrade_target_database`, VERSION() AS `database_version`;

SET @pod_combined_required_table_count := (
  SELECT COUNT(*)
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN (
      'pod_users',
      'pod_vendors',
      'pod_vendor_contacts',
      'pod_vendor_users',
      'pod_vendor_roles',
      'pod_gate_passes',
      'pod_gate_pass_scan_log',
      'pod_companies',
      'pod_ptw_applications',
      'pod_tender_bid_openings',
      'pod_tender_bid_opening_entries',
      'pod_gate_pass_request_vehicles',
      'pod_ptw_requirement_responses',
      'pod_ptw_attachments',
      'pod_tenders',
      'pod_tender_target_specialties',
      'pod_tender_invited_vendors',
      'pod_tender_communications',
      'pod_tender_evaluations'
    )
);
SET @pod_combined_required_table_expected := 19;

-- The QR upgrade also depends on the full gate-pass foundation. Check its
-- security-critical columns before any schema or data mutation is attempted.
SET @pod_combined_gate_column_count := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND (
      (TABLE_NAME = 'pod_gate_passes' AND COLUMN_NAME = 'qr_token')
      OR (TABLE_NAME = 'pod_gate_pass_scan_log' AND COLUMN_NAME = 'gate_pass_id')
      OR (TABLE_NAME = 'pod_gate_pass_scan_log' AND COLUMN_NAME = 'gate_pass_request_visitor_id')
      OR (TABLE_NAME = 'pod_gate_pass_scan_log' AND COLUMN_NAME = 'action')
      OR (TABLE_NAME = 'pod_gate_pass_scan_log' AND COLUMN_NAME = 'recorded_at')
      OR (TABLE_NAME = 'pod_gate_pass_scan_log' AND COLUMN_NAME = 'id')
    )
);
SET @pod_combined_preflight_sql := IF(
  DATABASE() IS NOT NULL
    AND @pod_combined_required_table_count = @pod_combined_required_table_expected
    AND @pod_combined_gate_column_count = 6,
  'SELECT ''Combined security SQL preflight passed'' AS pod_upgrade_status',
  'SELECT * FROM `__ABORT_WRONG_DATABASE_OR_REQUIRED_FOUNDATION_MISSING__`'
);
PREPARE pod_combined_preflight_stmt FROM @pod_combined_preflight_sql;
EXECUTE pod_combined_preflight_stmt;
DEALLOCATE PREPARE pod_combined_preflight_stmt;

SET @pod_combined_started_at := NOW();
SET @pod_combined_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;
SELECT
  'Combined security database upgrade started' AS `pod_upgrade_status`,
  @pod_combined_started_at AS `started_at`,
  @pod_combined_previous_sql_safe_updates AS `previous_sql_safe_updates`;

-- Section 01 uses these legacy vendor fields. The runtime-ownership source also
-- declares them in section 10, but they must exist before its contact backfill.
ALTER TABLE `pod_vendors`
  ADD COLUMN IF NOT EXISTS `cr_number` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `phone` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `phone_country_code` VARCHAR(12) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_person` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `contact_designation` VARCHAR(255) DEFAULT NULL;

-- ============================================================================
-- SECTION 01/10 vendor_multi_cr_contact_identities_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 01/10 vendor_multi_cr_contact_identities_upgrade_pod.sql' AS `pod_upgrade_status`;

-- Vendor multi-CR/contact identity foundation for the standard `pod_` prefix.
-- Safe to run repeatedly on MariaDB/MySQL. It does not delete or merge rows.

SET @pod_vendor_identity_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

SET @pod_schema = DATABASE();

-- Approved contacts use the same vendor portal authority as an owner, while
-- retaining a distinct role for audit/display purposes.
SET @pod_contact_role_id = NULL;
SELECT `id` INTO @pod_contact_role_id
FROM `pod_vendor_roles`
WHERE UPPER(TRIM(`code`)) = 'CONTACT'
ORDER BY `id` ASC
LIMIT 1;

UPDATE `pod_vendor_roles`
SET `name` = 'Contact',
    `code` = 'CONTACT',
    `description` = 'Approved vendor contact with full access to the linked vendor portal.',
    `is_active` = 1,
    `updated_at` = NOW(),
    `deleted` = 0
WHERE `id` = @pod_contact_role_id;

INSERT INTO `pod_vendor_roles`
    (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Contact',
       'CONTACT',
       'Approved vendor contact with full access to the linked vendor portal.',
       1, NOW(), NOW(), 0
WHERE @pod_contact_role_id IS NULL;

-- Match vendor identity columns to the actual users.id integer definition.
-- Keep the target integer range so narrowing changes follow the same safety
-- rule as the framework migration.
SET @pod_users_id_type = NULL;
SET @pod_users_id_data_type = NULL;

SELECT `COLUMN_TYPE`, `DATA_TYPE`
INTO @pod_users_id_type, @pod_users_id_data_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_users' AND COLUMN_NAME = 'id'
LIMIT 1;

SET @pod_users_id_supported = LOWER(COALESCE(@pod_users_id_data_type, ''))
    IN ('tinyint', 'smallint', 'mediumint', 'int', 'bigint');

SET @pod_users_id_type = IF(
    @pod_users_id_supported = 1
        AND LOWER(COALESCE(@pod_users_id_type, '')) REGEXP '^(tinyint|smallint|mediumint|int|bigint)(\\([0-9]+\\))?( unsigned)?$',
    @pod_users_id_type,
    'int(11)'
);

SET @pod_users_id_data_type = IF(
    @pod_users_id_supported = 1,
    LOWER(@pod_users_id_data_type),
    'int'
);
SET @pod_users_id_unsigned = LOWER(@pod_users_id_type) LIKE '%unsigned%';
SET @pod_users_id_min = CASE
    WHEN @pod_users_id_unsigned = 1 THEN '0'
    WHEN @pod_users_id_data_type = 'tinyint' THEN '-128'
    WHEN @pod_users_id_data_type = 'smallint' THEN '-32768'
    WHEN @pod_users_id_data_type = 'mediumint' THEN '-8388608'
    WHEN @pod_users_id_data_type = 'bigint' THEN '-9223372036854775808'
    ELSE '-2147483648'
END;
SET @pod_users_id_max = CASE
    WHEN @pod_users_id_data_type = 'tinyint' AND @pod_users_id_unsigned = 1 THEN '255'
    WHEN @pod_users_id_data_type = 'tinyint' THEN '127'
    WHEN @pod_users_id_data_type = 'smallint' AND @pod_users_id_unsigned = 1 THEN '65535'
    WHEN @pod_users_id_data_type = 'smallint' THEN '32767'
    WHEN @pod_users_id_data_type = 'mediumint' AND @pod_users_id_unsigned = 1 THEN '16777215'
    WHEN @pod_users_id_data_type = 'mediumint' THEN '8388607'
    WHEN @pod_users_id_data_type = 'int' AND @pod_users_id_unsigned = 1 THEN '4294967295'
    WHEN @pod_users_id_data_type = 'int' THEN '2147483647'
    WHEN @pod_users_id_unsigned = 1 THEN '18446744073709551615'
    ELSE '9223372036854775807'
END;

SELECT COUNT(*) INTO @pod_contact_user_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_contacts' AND COLUMN_NAME = 'user_id';

SET @pod_sql = IF(
    @pod_contact_user_column = 0,
    CONCAT('ALTER TABLE `pod_vendor_contacts` ADD COLUMN `user_id` ', @pod_users_id_type, ' NULL DEFAULT NULL AFTER `vendor_id`'),
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_contact_user_orphans
FROM `pod_vendor_contacts` contacts
LEFT JOIN `pod_users` users ON users.`id` = contacts.`user_id`
WHERE contacts.`user_id` IS NOT NULL AND users.`id` IS NULL;

SELECT COUNT(*) INTO @pod_contact_user_out_of_range
FROM `pod_vendor_contacts`
WHERE `user_id` IS NOT NULL
  AND (
      CAST(`user_id` AS DECIMAL(65, 0)) < CAST(@pod_users_id_min AS DECIMAL(65, 0))
      OR CAST(`user_id` AS DECIMAL(65, 0)) > CAST(@pod_users_id_max AS DECIMAL(65, 0))
  );

SELECT `COLUMN_TYPE` INTO @pod_contact_user_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_contacts' AND COLUMN_NAME = 'user_id'
LIMIT 1;

SELECT COUNT(*) INTO @pod_contact_user_column_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'user_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SET @pod_sql = IF(
    LOWER(REPLACE(@pod_contact_user_type, ' ', '')) <> LOWER(REPLACE(@pod_users_id_type, ' ', ''))
        AND @pod_contact_user_out_of_range = 0
        AND @pod_contact_user_column_fk = 0,
    CONCAT('ALTER TABLE `pod_vendor_contacts` MODIFY COLUMN `user_id` ', @pod_users_id_type, ' NULL DEFAULT NULL'),
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_contact_user_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'idx_vendor_contacts_user_id';

SET @pod_sql = IF(
    @pod_contact_user_index = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD INDEX `idx_vendor_contacts_user_id` (`user_id`)',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- Conservative legacy backfill. Access is never inferred from a contact row:
-- only an existing active Owner membership proves the user/vendor identity.
-- If an email match is unambiguous, link only the earliest live contact and
-- change only user_id, preserving all of that contact's status/profile data.
-- Workbench safe-update mode can reject a joined UPDATE even though it is
-- joined by the contact primary key. Disable it only for this statement and
-- restore the caller's original session setting immediately afterwards.
SET @pod_previous_sql_safe_updates = @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

UPDATE `pod_vendor_contacts` contacts
INNER JOIN (
    SELECT materialized_matches.`vendor_id`,
           materialized_matches.`user_id`,
           MIN(materialized_matches.`contact_id`) AS `contact_id`
    FROM (
        SELECT memberships.`vendor_id`, memberships.`user_id`, candidate_contacts.`id` AS `contact_id`
        FROM `pod_vendor_users` memberships
        INNER JOIN `pod_users` users
            ON users.`id` = memberships.`user_id`
           AND users.`deleted` = 0
           AND users.`status` = 'active'
           AND users.`user_type` = 'staff'
        INNER JOIN `pod_vendors` vendors
            ON vendors.`id` = memberships.`vendor_id`
           AND vendors.`deleted` = 0
        INNER JOIN `pod_vendor_contacts` candidate_contacts
            ON candidate_contacts.`vendor_id` = memberships.`vendor_id`
           AND candidate_contacts.`deleted` = 0
           AND candidate_contacts.`user_id` IS NULL
           AND LOWER(TRIM(candidate_contacts.`email`)) = LOWER(TRIM(users.`email`))
        WHERE memberships.`deleted` = 0
          AND memberships.`is_owner` = 1
          AND memberships.`status` = 'active'
          AND NULLIF(TRIM(users.`email`), '') IS NOT NULL
          AND NOT EXISTS (
              SELECT 1
              FROM `pod_vendor_contacts` linked_contacts
              WHERE linked_contacts.`vendor_id` = memberships.`vendor_id`
                AND linked_contacts.`user_id` = memberships.`user_id`
          )
          AND NOT EXISTS (
              SELECT 1
              FROM `pod_vendor_users` other_memberships
              INNER JOIN `pod_users` other_users
                  ON other_users.`id` = other_memberships.`user_id`
                 AND other_users.`deleted` = 0
                 AND other_users.`status` = 'active'
                 AND other_users.`user_type` = 'staff'
              WHERE other_memberships.`vendor_id` = memberships.`vendor_id`
                AND other_memberships.`user_id` <> memberships.`user_id`
                AND other_memberships.`deleted` = 0
                AND other_memberships.`is_owner` = 1
                AND other_memberships.`status` = 'active'
                AND LOWER(TRIM(other_users.`email`)) = LOWER(TRIM(users.`email`))
          )
    ) materialized_matches
    GROUP BY materialized_matches.`vendor_id`, materialized_matches.`user_id`
) owner_matches ON owner_matches.`contact_id` = contacts.`id`
SET contacts.`user_id` = owner_matches.`user_id`
WHERE contacts.`id` = owner_matches.`contact_id`
  AND contacts.`user_id` IS NULL;

SET SESSION SQL_SAFE_UPDATES = @pod_previous_sql_safe_updates;

-- Owners without any historical linked contact get a new approved, active
-- Owner contact. A soft-deleted historical link is respected and not revived.
INSERT INTO `pod_vendor_contacts`
    (`vendor_id`, `user_id`, `contacts_name`, `phone`, `designation`, `email`, `mobile`,
     `role`, `is_primary`, `is_active`, `created_at`, `updated_at`, `deleted`, `status`)

SELECT owner_candidates.`vendor_id`,
       owner_candidates.`user_id`,
       owner_candidates.`contacts_name`,
       owner_candidates.`phone`,
       owner_candidates.`designation`,
       owner_candidates.`email`,
       owner_candidates.`mobile`,
       'Owner',
       CASE
           WHEN owner_candidates.`has_primary_contact` = 0
                AND owner_candidates.`owner_rank` = 1 THEN 1
           ELSE 0
       END,
       1,
       NOW(),
       NOW(),
       0,
       'approved'
    FROM (
        SELECT memberships.`id` AS `membership_id`,
            memberships.`vendor_id`,
            memberships.`user_id`,
            LEFT(COALESCE(
                NULLIF(TRIM(CONCAT_WS(' ', users.`first_name`, users.`last_name`)), ''),
                NULLIF(TRIM(vendors.`vendor_name`), ''),
                LOWER(TRIM(users.`email`))
            ), 255) AS `contacts_name`,
            LEFT(NULLIF(TRIM(COALESCE(NULLIF(TRIM(users.`phone`), ''), vendors.`phone`)), ''), 255) AS `phone`,
            LEFT(COALESCE(
                NULLIF(TRIM(users.`job_title`), ''),
                NULLIF(TRIM(vendors.`contact_designation`), ''),
                'Owner'
            ), 255) AS `designation`,
            LEFT(LOWER(TRIM(users.`email`)), 255) AS `email`,
            LEFT(NULLIF(TRIM(COALESCE(NULLIF(TRIM(users.`phone`), ''), vendors.`phone`)), ''), 255) AS `mobile`,
            ROW_NUMBER() OVER (PARTITION BY memberships.`vendor_id` ORDER BY memberships.`id` ASC) AS `owner_rank`,
            EXISTS(
                SELECT 1
                FROM `pod_vendor_contacts` primary_contacts
                WHERE primary_contacts.`vendor_id` = memberships.`vendor_id`
                    AND primary_contacts.`deleted` = 0
                    AND primary_contacts.`is_primary` = 1
            ) AS `has_primary_contact`
        FROM `pod_vendor_users` memberships
        INNER JOIN `pod_users` users
            ON users.`id` = memberships.`user_id`
        AND users.`deleted` = 0
        AND users.`status` = 'active'
        AND users.`user_type` = 'staff'
        INNER JOIN `pod_vendors` vendors
            ON vendors.`id` = memberships.`vendor_id`
        AND vendors.`deleted` = 0
        WHERE memberships.`deleted` = 0
        AND memberships.`is_owner` = 1
        AND memberships.`status` = 'active'
        AND NULLIF(TRIM(users.`email`), '') IS NOT NULL
        AND NOT EXISTS (
            SELECT 1
            FROM `pod_vendor_contacts` linked_contacts
            WHERE linked_contacts.`vendor_id` = memberships.`vendor_id`
                AND linked_contacts.`user_id` = memberships.`user_id`
        )
    ) owner_candidates;

-- One normalized, nonblank live contact email per CR. The generated value is
-- NULL for blank/deleted contacts, so those rows do not reserve an address.
-- The same normalized email remains valid under any different vendor_id.
SELECT COUNT(*) INTO @pod_contact_email_identity_column
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @pod_schema
        AND TABLE_NAME = 'pod_vendor_contacts'
        AND COLUMN_NAME = 'live_email_identity';

SET @pod_sql = IF(
    @pod_contact_email_identity_column = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD COLUMN `live_email_identity` VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 THEN NULLIF(LOWER(TRIM(`email`)), '''') ELSE NULL END) STORED',
    'SELECT 1'
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

SELECT IF(
    @pod_duplicate_live_contact_emails > 0,
    CONCAT(
        'WARNING: skipped uq_vendor_contacts_live_email; resolve ',
        @pod_duplicate_live_contact_emails,
        ' duplicate live contact email group(s) within the same CR, then rerun this script.'
    ),
    'Vendor contact emails are ready for per-CR uniqueness enforcement.'
) AS vendor_contact_email_uniqueness;

SELECT COUNT(*) INTO @pod_contact_email_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_email_identity'
  AND EXTRA LIKE '%GENERATED%';

SELECT COUNT(*) INTO @pod_contact_email_identity_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_live_email';

SET @pod_sql = IF(
    @pod_duplicate_live_contact_emails = 0
        AND @pod_contact_email_identity_column = 1
        AND @pod_contact_email_identity_index = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD UNIQUE INDEX `uq_vendor_contacts_live_email` (`vendor_id`, `live_email_identity`)',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- One live contact profile per user and CR. The nullable generated guard only
-- depends on deleted, not user_id. This remains compatible when the user FK
-- performs ON DELETE SET NULL on current MySQL/MariaDB releases.
SELECT COUNT(*) INTO @pod_contact_user_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_user_identity';

SELECT COUNT(*) INTO @pod_contact_user_identity_compatible
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_user_identity'
  AND LOWER(DATA_TYPE) = 'tinyint'
  AND UPPER(EXTRA) LIKE '%STORED%'
  AND UPPER(EXTRA) LIKE '%GENERATED%'
  AND LOWER(COALESCE(GENERATION_EXPRESSION, '')) LIKE '%deleted%'
  AND LOWER(COALESCE(GENERATION_EXPRESSION, '')) NOT LIKE '%user_id%';

-- Convert the earlier user_id-based generated-column design if this script
-- was already used on an older database server.
SELECT COUNT(*) INTO @pod_contact_user_identity_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_live_user';

SET @pod_sql = IF(
    @pod_contact_user_identity_column > 0
        AND @pod_contact_user_identity_compatible = 0
        AND @pod_contact_user_identity_index > 0,
    'ALTER TABLE `pod_vendor_contacts` DROP INDEX `uq_vendor_contacts_live_user`',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SET @pod_sql = IF(
    @pod_contact_user_identity_column > 0
        AND @pod_contact_user_identity_compatible = 0,
    'ALTER TABLE `pod_vendor_contacts` DROP COLUMN `live_user_identity`',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_contact_user_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_user_identity';

SET @pod_sql = IF(
    @pod_contact_user_identity_column = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD COLUMN `live_user_identity` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 THEN 1 ELSE NULL END) STORED',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_contact_user_identity_compatible
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'live_user_identity'
  AND LOWER(DATA_TYPE) = 'tinyint'
  AND UPPER(EXTRA) LIKE '%STORED%'
  AND UPPER(EXTRA) LIKE '%GENERATED%'
  AND LOWER(COALESCE(GENERATION_EXPRESSION, '')) LIKE '%deleted%'
  AND LOWER(COALESCE(GENERATION_EXPRESSION, '')) NOT LIKE '%user_id%';

-- Remove the old index only after the generated replacement column is proven
-- compatible. idx_vendor_contacts_user_id continues to support the user FK.
SELECT COUNT(*) INTO @pod_legacy_contact_membership_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_vendor_user';

SET @pod_sql = IF(
    @pod_contact_user_identity_compatible = 1 AND @pod_legacy_contact_membership_index > 0,
    'ALTER TABLE `pod_vendor_contacts` DROP INDEX `uq_vendor_contacts_vendor_user`',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_duplicate_live_contact_users
FROM (
    SELECT `vendor_id`, `user_id`
    FROM `pod_vendor_contacts`
    WHERE `deleted` = 0 AND `user_id` IS NOT NULL
    GROUP BY `vendor_id`, `user_id`
    HAVING COUNT(*) > 1
) duplicate_live_contact_users;

SELECT IF(
    @pod_duplicate_live_contact_users > 0,
    CONCAT(
        'WARNING: skipped uq_vendor_contacts_live_user; resolve ',
        @pod_duplicate_live_contact_users,
        ' duplicate live contact user group(s) within the same CR, then rerun this script.'
    ),
    'Vendor contact users are ready for live per-CR uniqueness enforcement.'
) AS vendor_contact_user_uniqueness;

SELECT COUNT(*) INTO @pod_contact_user_identity_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_live_user';

SELECT COUNT(*) INTO @pod_contact_user_identity_index_compatible
FROM (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @pod_schema
      AND TABLE_NAME = 'pod_vendor_contacts'
      AND INDEX_NAME = 'uq_vendor_contacts_live_user'
    GROUP BY INDEX_NAME, NON_UNIQUE
    HAVING NON_UNIQUE = 0
       AND COUNT(*) = 3
       AND SUM(CASE WHEN SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'vendor_id' THEN 1 ELSE 0 END) = 1
       AND SUM(CASE WHEN SEQ_IN_INDEX = 2 AND COLUMN_NAME = 'user_id' THEN 1 ELSE 0 END) = 1
       AND SUM(CASE WHEN SEQ_IN_INDEX = 3 AND COLUMN_NAME = 'live_user_identity' THEN 1 ELSE 0 END) = 1
) compatible_live_user_index;

SET @pod_sql = IF(
    @pod_contact_user_identity_index > 0
        AND @pod_contact_user_identity_index_compatible = 0,
    'ALTER TABLE `pod_vendor_contacts` DROP INDEX `uq_vendor_contacts_live_user`',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_contact_user_identity_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND INDEX_NAME = 'uq_vendor_contacts_live_user';

SET @pod_sql = IF(
    @pod_duplicate_live_contact_users = 0
        AND @pod_contact_user_identity_compatible = 1
        AND @pod_contact_user_identity_index = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD UNIQUE INDEX `uq_vendor_contacts_live_user` (`vendor_id`, `user_id`, `live_user_identity`)',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- The vendor-company email is descriptive, not a login identity. Multiple CRs
-- may therefore use it. Drop only unique indexes made solely from this column.
SELECT GROUP_CONCAT(CONCAT('DROP INDEX `', REPLACE(`INDEX_NAME`, '`', '``'), '`') SEPARATOR ', ')
INTO @pod_vendor_email_unique_indexes
FROM (
    SELECT `INDEX_NAME`
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @pod_schema
      AND TABLE_NAME = 'pod_vendors'
      AND NON_UNIQUE = 0
      AND INDEX_NAME <> 'PRIMARY'
    GROUP BY `INDEX_NAME`
    HAVING COUNT(*) = 1 AND MAX(`COLUMN_NAME`) = 'email'
) unique_email_indexes;

SET @pod_sql = IF(
    COALESCE(@pod_vendor_email_unique_indexes, '') <> '',
    CONCAT('ALTER TABLE `pod_vendors` ', @pod_vendor_email_unique_indexes),
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_vendor_email_lookup_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendors'
  AND COLUMN_NAME = 'email'
  AND SEQ_IN_INDEX = 1;

SET @pod_sql = IF(
    @pod_vendor_email_lookup_index = 0,
    'ALTER TABLE `pod_vendors` ADD INDEX `idx_vendors_email` (`email`)',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- Enforce unique nonblank CRs for live vendors only. Soft-deleted vendors and
-- blank CR values produce NULL, which a UNIQUE index permits multiple times.
SELECT COUNT(*) INTO @pod_duplicate_live_crs
FROM (
    SELECT UPPER(TRIM(`cr_number`)) AS normalized_cr
    FROM `pod_vendors`
    WHERE `deleted` = 0 AND NULLIF(TRIM(`cr_number`), '') IS NOT NULL
    GROUP BY UPPER(TRIM(`cr_number`))
    HAVING COUNT(*) > 1
) duplicate_live_crs;

SELECT COUNT(*) INTO @pod_cr_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendors'
  AND COLUMN_NAME = 'cr_number_identity';

SET @pod_sql = IF(
    @pod_duplicate_live_crs = 0 AND @pod_cr_identity_column = 0,
    'ALTER TABLE `pod_vendors` ADD COLUMN `cr_number_identity` VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN `deleted` = 0 THEN NULLIF(UPPER(TRIM(`cr_number`)), '''') ELSE NULL END) STORED',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_cr_identity_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendors'
  AND COLUMN_NAME = 'cr_number_identity'
  AND EXTRA LIKE '%GENERATED%';

SELECT COUNT(*) INTO @pod_cr_identity_index
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendors'
  AND INDEX_NAME = 'uq_vendors_cr_number_identity';

SET @pod_sql = IF(
    @pod_duplicate_live_crs = 0 AND @pod_cr_identity_column = 1 AND @pod_cr_identity_index = 0,
    'ALTER TABLE `pod_vendors` ADD UNIQUE INDEX `uq_vendors_cr_number_identity` (`cr_number_identity`)',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- Align vendor_users.user_id and invited_by with users.id when all current
-- values fit the target integer range. Existing foreign keys are respected.
SELECT COUNT(*) INTO @pod_vendor_user_orphans
FROM `pod_vendor_users` memberships
LEFT JOIN `pod_users` users ON users.`id` = memberships.`user_id`
WHERE memberships.`user_id` IS NOT NULL AND users.`id` IS NULL;

SELECT COUNT(*) INTO @pod_vendor_user_out_of_range
FROM `pod_vendor_users`
WHERE `user_id` IS NOT NULL
  AND (
      CAST(`user_id` AS DECIMAL(65, 0)) < CAST(@pod_users_id_min AS DECIMAL(65, 0))
      OR CAST(`user_id` AS DECIMAL(65, 0)) > CAST(@pod_users_id_max AS DECIMAL(65, 0))
  );

SELECT `COLUMN_TYPE` INTO @pod_vendor_user_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_users' AND COLUMN_NAME = 'user_id'
LIMIT 1;

SELECT COUNT(*) INTO @pod_vendor_user_column_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'user_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SET @pod_sql = IF(
    LOWER(REPLACE(@pod_vendor_user_type, ' ', '')) <> LOWER(REPLACE(@pod_users_id_type, ' ', ''))
        AND @pod_vendor_user_out_of_range = 0
        AND @pod_vendor_user_column_fk = 0,
    CONCAT('ALTER TABLE `pod_vendor_users` MODIFY COLUMN `user_id` ', @pod_users_id_type, ' NOT NULL'),
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT COUNT(*) INTO @pod_vendor_invited_by_column
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'invited_by';

SET @pod_vendor_invited_by_type = NULL;
SELECT `COLUMN_TYPE` INTO @pod_vendor_invited_by_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'invited_by'
LIMIT 1;

SELECT COUNT(*) INTO @pod_vendor_invited_by_column_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'invited_by'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SET @pod_vendor_invited_by_out_of_range = 0;
SET @pod_sql = IF(
    @pod_vendor_invited_by_column = 1,
    'SELECT COUNT(*) INTO @pod_vendor_invited_by_out_of_range
     FROM `pod_vendor_users`
     WHERE `invited_by` IS NOT NULL
       AND (
           CAST(`invited_by` AS DECIMAL(65, 0)) < CAST(@pod_users_id_min AS DECIMAL(65, 0))
           OR CAST(`invited_by` AS DECIMAL(65, 0)) > CAST(@pod_users_id_max AS DECIMAL(65, 0))
       )',
    'SELECT 0 INTO @pod_vendor_invited_by_out_of_range'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SET @pod_sql = IF(
    @pod_vendor_invited_by_column = 1
        AND LOWER(REPLACE(@pod_vendor_invited_by_type, ' ', '')) <> LOWER(REPLACE(@pod_users_id_type, ' ', ''))
        AND @pod_vendor_invited_by_out_of_range = 0
        AND @pod_vendor_invited_by_column_fk = 0,
    CONCAT('ALTER TABLE `pod_vendor_users` MODIFY COLUMN `invited_by` ', @pod_users_id_type, ' NULL DEFAULT NULL'),
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

-- Add user foreign keys only when the repaired types match and no orphan rows
-- exist. Existing differently named foreign keys are respected.
SELECT `COLUMN_TYPE` INTO @pod_contact_user_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_contacts' AND COLUMN_NAME = 'user_id'
LIMIT 1;

SELECT COUNT(*) INTO @pod_contact_user_column_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_contacts'
  AND COLUMN_NAME = 'user_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SET @pod_sql = IF(
    LOWER(REPLACE(@pod_contact_user_type, ' ', '')) = LOWER(REPLACE(@pod_users_id_type, ' ', ''))
        AND @pod_contact_user_orphans = 0
        AND @pod_contact_user_column_fk = 0,
    'ALTER TABLE `pod_vendor_contacts` ADD CONSTRAINT `fk_vendor_contacts_user_id` FOREIGN KEY (`user_id`) REFERENCES `pod_users` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SELECT `COLUMN_TYPE` INTO @pod_vendor_user_type
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @pod_schema AND TABLE_NAME = 'pod_vendor_users' AND COLUMN_NAME = 'user_id'
LIMIT 1;

SELECT COUNT(*) INTO @pod_vendor_user_column_fk
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @pod_schema
  AND TABLE_NAME = 'pod_vendor_users'
  AND COLUMN_NAME = 'user_id'
  AND REFERENCED_TABLE_NAME IS NOT NULL;

SET @pod_sql = IF(
    LOWER(REPLACE(@pod_vendor_user_type, ' ', '')) = LOWER(REPLACE(@pod_users_id_type, ' ', ''))
        AND @pod_vendor_user_orphans = 0
        AND @pod_vendor_user_column_fk = 0,
    'ALTER TABLE `pod_vendor_users` ADD CONSTRAINT `fk_vendor_users_user_id` FOREIGN KEY (`user_id`) REFERENCES `pod_users` (`id`) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE pod_stmt FROM @pod_sql;
EXECUTE pod_stmt;
DEALLOCATE PREPARE pod_stmt;

SET @pod_sql = NULL;

SET SESSION SQL_SAFE_UPDATES = @pod_vendor_identity_previous_sql_safe_updates;

SELECT 'COMPLETE 01/10 vendor_multi_cr_contact_identities_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 02/10 vendor_contact_credentials_readiness_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 02/10 vendor_contact_credentials_readiness_upgrade_pod.sql' AS `pod_upgrade_status`;

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

SELECT 'COMPLETE 02/10 vendor_contact_credentials_readiness_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 03/10 authentication_hardening_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 03/10 authentication_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- Apply once before deploying the Phase 2 authentication code.
-- Run the migration in normal deployments; this file is the equivalent
-- operator-reviewed SQL for environments that do not run CI migrations.

ALTER TABLE `pod_users`
    ADD COLUMN IF NOT EXISTS `auth_session_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `password`;

CREATE TABLE IF NOT EXISTS `pod_auth_login_security` (
    `identity_hash` CHAR(64) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `failed_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `first_failed_at` DATETIME NULL,
    `last_failed_at` DATETIME NULL,
    `locked_until` DATETIME NULL,
    `last_ip_hash` CHAR(64) NULL,
    `updated_at` DATETIME NOT NULL,
    PRIMARY KEY (`identity_hash`),
    KEY `idx_auth_login_user` (`user_id`),
    KEY `idx_auth_login_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pod_auth_password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `selector` CHAR(24) NOT NULL,
    `validator_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `request_ip_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_auth_reset_selector` (`selector`),
    KEY `idx_auth_reset_user_used` (`user_id`, `used_at`),
    KEY `idx_auth_reset_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pod_auth_mfa_challenges` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `challenge_id` CHAR(32) NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `purpose` VARCHAR(32) NOT NULL,
    `provider` VARCHAR(32) NOT NULL,
    `destination_hint` VARCHAR(190) NOT NULL,
    `code_hash` CHAR(64) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` INT UNSIGNED NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `consumed_at` DATETIME NULL,
    `request_ip_hash` CHAR(64) NOT NULL,
    `user_agent_hash` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_auth_mfa_challenge` (`challenge_id`),
    KEY `idx_auth_mfa_user_purpose` (`user_id`, `purpose`, `consumed_at`),
    KEY `idx_auth_mfa_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pod_auth_audit_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `event_type` VARCHAR(64) NOT NULL,
    `outcome` VARCHAR(32) NOT NULL,
    `identity_hash` CHAR(64) NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent_hash` CHAR(64) NOT NULL,
    `context_json` TEXT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_auth_audit_user_created` (`user_id`, `created_at`),
    KEY `idx_auth_audit_event_created` (`event_type`, `created_at`),
    KEY `idx_auth_audit_identity` (`identity_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT 'COMPLETE 03/10 authentication_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 04/10 gate_pass_scan_replay_protection_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 04/10 gate_pass_scan_replay_protection_upgrade_pod.sql' AS `pod_upgrade_status`;

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

SELECT 'COMPLETE 04/10 gate_pass_scan_replay_protection_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 05/10 vendor_portal_role_enforcement_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 05/10 vendor_portal_role_enforcement_upgrade_pod.sql' AS `pod_upgrade_status`;

-- Least-privilege vendor portal roles for the standard pod_ prefix.
-- Run during a maintenance window after taking a verified database backup.

SET @pod_vendor_roles_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

INSERT INTO `pod_vendor_roles` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Owner', 'OWNER', 'Full authority for the selected vendor CR, including contact administration.', 1, NOW(), NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM `pod_vendor_roles` WHERE UPPER(TRIM(`code`)) = 'OWNER');

INSERT INTO `pod_vendor_roles` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Editor', 'EDITOR', 'May maintain the selected CR profile and participate in tenders.', 1, NOW(), NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM `pod_vendor_roles` WHERE UPPER(TRIM(`code`)) = 'EDITOR');

INSERT INTO `pod_vendor_roles` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Bidder', 'BIDDER', 'May view the selected CR profile and participate in tenders.', 1, NOW(), NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM `pod_vendor_roles` WHERE UPPER(TRIM(`code`)) = 'BIDDER');

INSERT INTO `pod_vendor_roles` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Viewer', 'VIEWER', 'Read-only access to the selected CR profile and tenders.', 1, NOW(), NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM `pod_vendor_roles` WHERE UPPER(TRIM(`code`)) = 'VIEWER');

INSERT INTO `pod_vendor_roles` (`name`, `code`, `description`, `is_active`, `created_at`, `updated_at`, `deleted`)
SELECT 'Legacy Contact', 'CONTACT', 'Legacy role retained for compatibility and enforced as read-only.', 1, NOW(), NOW(), 0
WHERE NOT EXISTS (SELECT 1 FROM `pod_vendor_roles` WHERE UPPER(TRIM(`code`)) = 'CONTACT');

UPDATE `pod_vendor_roles`
SET `name` = 'Owner',
    `code` = 'OWNER',
    `description` = 'Full authority for the selected vendor CR, including contact administration.',
    `is_active` = 1,
    `deleted` = 0,
    `updated_at` = NOW()
WHERE UPPER(TRIM(`code`)) = 'OWNER';

UPDATE `pod_vendor_roles`
SET `name` = 'Editor',
    `code` = 'EDITOR',
    `description` = 'May maintain the selected CR profile and participate in tenders.',
    `is_active` = 1,
    `deleted` = 0,
    `updated_at` = NOW()
WHERE UPPER(TRIM(`code`)) = 'EDITOR';

UPDATE `pod_vendor_roles`
SET `name` = 'Bidder',
    `code` = 'BIDDER',
    `description` = 'May view the selected CR profile and participate in tenders.',
    `is_active` = 1,
    `deleted` = 0,
    `updated_at` = NOW()
WHERE UPPER(TRIM(`code`)) = 'BIDDER';

UPDATE `pod_vendor_roles`
SET `name` = 'Viewer',
    `code` = 'VIEWER',
    `description` = 'Read-only access to the selected CR profile and tenders.',
    `is_active` = 1,
    `deleted` = 0,
    `updated_at` = NOW()
WHERE UPPER(TRIM(`code`)) = 'VIEWER';

UPDATE `pod_vendor_roles`
SET `name` = 'Legacy Contact',
    `code` = 'CONTACT',
    `description` = 'Legacy role retained for compatibility and enforced as read-only.',
    `is_active` = 1,
    `deleted` = 0,
    `updated_at` = NOW()
WHERE UPPER(TRIM(`code`)) = 'CONTACT';

UPDATE `pod_vendor_users` AS `membership`
INNER JOIN `pod_vendor_roles` AS `legacy_role`
    ON `legacy_role`.`id` = `membership`.`vendor_role_id` AND UPPER(TRIM(`legacy_role`.`code`)) = 'CONTACT'
INNER JOIN `pod_vendor_roles` AS `viewer_role`
    ON UPPER(TRIM(`viewer_role`.`code`)) = 'VIEWER' AND `viewer_role`.`deleted` = 0
SET `membership`.`vendor_role_id` = `viewer_role`.`id`,
    `membership`.`updated_at` = NOW()
WHERE `membership`.`is_owner` = 0;

SET SESSION SQL_SAFE_UPDATES = @pod_vendor_roles_previous_sql_safe_updates;

SELECT 'COMPLETE 05/10 vendor_portal_role_enforcement_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 06/10 notification_processor_hardening_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 06/10 notification_processor_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- Notification processor replay protection for the standard pod_ prefix.
-- Creating this table does not enable SMS. It protects signed notification
-- processor requests from nonce reuse when that processor is used.

CREATE TABLE IF NOT EXISTS `pod_notification_processor_nonces` (
  `nonce_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`nonce_hash`),
  KEY `idx_notification_processor_nonce_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'COMPLETE 06/10 notification_processor_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 07/10 ptw_company_scope_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 07/10 ptw_company_scope_upgrade_pod.sql' AS `pod_upgrade_status`;

-- PTW company identity hardening for deployments using the standard pod_ prefix.
-- Run once during a maintenance window after taking a verified database backup.

SET @pod_ptw_scope_previous_sql_safe_updates := @@SESSION.SQL_SAFE_UPDATES;
SET SESSION SQL_SAFE_UPDATES = 0;

ALTER TABLE `pod_ptw_applications`
    ADD COLUMN IF NOT EXISTS `company_id` BIGINT UNSIGNED NULL AFTER `company_name`;

CREATE INDEX IF NOT EXISTS `idx_ptw_applications_company_id`
    ON `pod_ptw_applications` (`company_id`);

-- Only exact, case-sensitive names with one active company row are backfilled.
-- Ambiguous, missing, differently-cased, or inactive names deliberately remain
-- NULL and cannot be opened or reviewed by company-scoped reviewers.
UPDATE `pod_ptw_applications` AS `applications`
INNER JOIN (
    SELECT MIN(`id`) AS `company_id`, MIN(`name`) AS `company_name`
    FROM `pod_companies`
    WHERE `deleted` = 0 AND `name` IS NOT NULL AND `name` <> ''
    GROUP BY BINARY `name`
    HAVING COUNT(*) = 1
) AS `unique_companies`
    ON BINARY `unique_companies`.`company_name` = BINARY `applications`.`company_name`
SET `applications`.`company_id` = `unique_companies`.`company_id`
WHERE `applications`.`company_id` IS NULL
  AND `applications`.`company_name` IS NOT NULL
  AND `applications`.`company_name` <> '';

-- Review this count and resolve remaining records manually before reviewers
-- need to process them. Do not guess when a name is ambiguous.
SELECT COUNT(*) AS `unresolved_ptw_company_rows`
FROM `pod_ptw_applications`
WHERE `deleted` = 0 AND `company_id` IS NULL;

SET SESSION SQL_SAFE_UPDATES = @pod_ptw_scope_previous_sql_safe_updates;

SELECT 'COMPLETE 07/10 ptw_company_scope_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 08/10 tender_opening_secret_hardening_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 08/10 tender_opening_secret_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- tender_opening_secret_hardening_upgrade_pod.sql
-- Run once during a controlled deployment before enabling the hardened 3-key flow.
--
-- Application prerequisites (these are process environment secrets, not SQL values):
--   TENDER_OPENING_CODE_HMAC_KEY       = base64:<at least 32 random decoded bytes>
--   TENDER_OPENING_CODE_ENCRYPTION_KEY = base64:<a different 32 random decoded bytes>
-- Provision both through the production secret manager before deploying the code.
-- Do not store either key in this script, source control, logs, or database backups.
--
-- Existing plaintext sessions are deliberately expired, not converted. Committee
-- members must generate a fresh set after deployment.

SET @pod_previous_sql_safe_updates := @@SESSION.sql_safe_updates;
SET SESSION sql_safe_updates = 0;

ALTER TABLE `pod_tender_bid_openings`
  MODIFY COLUMN `status` varchar(50) NOT NULL DEFAULT 'codes_generated';

ALTER TABLE `pod_tender_bid_openings`
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `unlocked_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_path` varchar(255) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `manual_form_original_name` varchar(255) DEFAULT NULL AFTER `manual_form_path`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_by` bigint(20) UNSIGNED DEFAULT NULL AFTER `manual_form_original_name`,
  ADD COLUMN IF NOT EXISTS `manual_form_uploaded_at` datetime DEFAULT NULL AFTER `manual_form_uploaded_by`;

ALTER TABLE `pod_tender_bid_openings`
  ADD COLUMN IF NOT EXISTS `chairman_code_hash` char(64) DEFAULT NULL AFTER `member_code`,
  ADD COLUMN IF NOT EXISTS `secretary_code_hash` char(64) DEFAULT NULL AFTER `chairman_code_hash`,
  ADD COLUMN IF NOT EXISTS `member_code_hash` char(64) DEFAULT NULL AFTER `secretary_code_hash`,
  ADD COLUMN IF NOT EXISTS `chairman_code_ciphertext` varchar(255) DEFAULT NULL AFTER `member_code_hash`,
  ADD COLUMN IF NOT EXISTS `secretary_code_ciphertext` varchar(255) DEFAULT NULL AFTER `chairman_code_ciphertext`,
  ADD COLUMN IF NOT EXISTS `member_code_ciphertext` varchar(255) DEFAULT NULL AFTER `secretary_code_ciphertext`;

ALTER TABLE `pod_tender_bid_opening_entries`
  ADD COLUMN IF NOT EXISTS `signature_statement` text DEFAULT NULL AFTER `confirmed_at`,
  ADD COLUMN IF NOT EXISTS `signature_name` varchar(255) DEFAULT NULL AFTER `signature_statement`,
  ADD COLUMN IF NOT EXISTS `signature_image_path` varchar(500) DEFAULT NULL AFTER `signature_name`,
  ADD COLUMN IF NOT EXISTS `signed_at` datetime DEFAULT NULL AFTER `signature_image_path`,
  ADD COLUMN IF NOT EXISTS `signature_ip_address` varchar(45) DEFAULT NULL AFTER `signed_at`,
  ADD COLUMN IF NOT EXISTS `signature_user_agent` text DEFAULT NULL AFTER `signature_ip_address`;

SET @pod_opening_expiry_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_openings'
      AND INDEX_NAME = 'idx_tender_opening_secret_expiry'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_openings` ADD INDEX `idx_tender_opening_secret_expiry` (`status`, `deleted`, `expires_at`)'
);
PREPARE pod_opening_expiry_index_stmt FROM @pod_opening_expiry_index_sql;
EXECUTE pod_opening_expiry_index_stmt;
DEALLOCATE PREPARE pod_opening_expiry_index_stmt;

SET @pod_opening_user_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND INDEX_NAME = 'idx_tender_opening_failure_user'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_opening_entries` ADD INDEX `idx_tender_opening_failure_user` (`tender_bid_opening_id`, `is_valid`, `user_id`, `confirmed_at`)'
);
PREPARE pod_opening_user_index_stmt FROM @pod_opening_user_index_sql;
EXECUTE pod_opening_user_index_stmt;
DEALLOCATE PREPARE pod_opening_user_index_stmt;

SET @pod_opening_ip_index_sql := IF(
  EXISTS(
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND INDEX_NAME = 'idx_tender_opening_failure_ip'
  ),
  'SELECT 1',
  'ALTER TABLE `pod_tender_bid_opening_entries` ADD INDEX `idx_tender_opening_failure_ip` (`tender_bid_opening_id`, `is_valid`, `ip_address`, `confirmed_at`)'
);
PREPARE pod_opening_ip_index_stmt FROM @pod_opening_ip_index_sql;
EXECUTE pod_opening_ip_index_stmt;
DEALLOCATE PREPARE pod_opening_ip_index_stmt;

UPDATE `pod_tender_bid_openings`
SET `status` = 'expired',
    `updated_at` = NOW()
WHERE `deleted` = 0
  AND `status` = 'codes_generated'
  AND (
       `expires_at` IS NULL
       OR `expires_at` <= NOW()
       OR `chairman_code_hash` IS NULL
       OR `secretary_code_hash` IS NULL
       OR `member_code_hash` IS NULL
       OR `chairman_code_ciphertext` IS NULL
       OR `secretary_code_ciphertext` IS NULL
       OR `member_code_ciphertext` IS NULL
  );

UPDATE `pod_tender_bid_openings`
SET `chairman_code` = NULL,
    `secretary_code` = NULL,
    `member_code` = NULL;

UPDATE `pod_tender_bid_openings` AS older
INNER JOIN `pod_tender_bid_openings` AS newer
  ON newer.`tender_id` = older.`tender_id`
 AND newer.`stage` = older.`stage`
 AND newer.`deleted` = 0
 AND newer.`status` = 'codes_generated'
 AND newer.`id` > older.`id`
SET older.`status` = 'expired',
    older.`chairman_code_hash` = NULL,
    older.`secretary_code_hash` = NULL,
    older.`member_code_hash` = NULL,
    older.`chairman_code_ciphertext` = NULL,
    older.`secretary_code_ciphertext` = NULL,
    older.`member_code_ciphertext` = NULL,
    older.`updated_at` = NOW()
WHERE older.`deleted` = 0
  AND older.`status` = 'codes_generated';

UPDATE `pod_tender_bid_openings` AS generated
INNER JOIN `pod_tender_bid_openings` AS terminal
  ON terminal.`tender_id` = generated.`tender_id`
 AND terminal.`stage` = generated.`stage`
 AND terminal.`deleted` = 0
 AND terminal.`status` IN ('unlocked', 'signed', 'manual_accepted')
SET generated.`status` = 'expired',
    generated.`chairman_code_hash` = NULL,
    generated.`secretary_code_hash` = NULL,
    generated.`member_code_hash` = NULL,
    generated.`chairman_code_ciphertext` = NULL,
    generated.`secretary_code_ciphertext` = NULL,
    generated.`member_code_ciphertext` = NULL,
    generated.`updated_at` = NOW()
WHERE generated.`deleted` = 0
  AND generated.`status` = 'codes_generated';

UPDATE `pod_tender_bid_openings`
SET `chairman_code_hash` = NULL,
    `secretary_code_hash` = NULL,
    `member_code_hash` = NULL,
    `chairman_code_ciphertext` = NULL,
    `secretary_code_ciphertext` = NULL,
    `member_code_ciphertext` = NULL
WHERE `status` <> 'codes_generated'
   OR `deleted` <> 0;

SET @pod_clear_legacy_input_codes_sql := IF(
  (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pod_tender_bid_opening_entries'
      AND COLUMN_NAME IN ('input_chairman_code', 'input_secretary_code', 'input_member_code')
  ) = 3,
  'UPDATE `pod_tender_bid_opening_entries` SET `input_chairman_code` = NULL, `input_secretary_code` = NULL, `input_member_code` = NULL WHERE `input_chairman_code` IS NOT NULL OR `input_secretary_code` IS NOT NULL OR `input_member_code` IS NOT NULL',
  'SELECT ''Legacy tender input-code columns are absent; nothing to clear'' AS info'
);
PREPARE pod_clear_legacy_input_codes_stmt FROM @pod_clear_legacy_input_codes_sql;
EXECUTE pod_clear_legacy_input_codes_stmt;
DEALLOCATE PREPARE pod_clear_legacy_input_codes_stmt;

SET SESSION sql_safe_updates = @pod_previous_sql_safe_updates;

SELECT 'COMPLETE 08/10 tender_opening_secret_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 09/10 ptw_applicant_company_assignments_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 09/10 ptw_applicant_company_assignments_upgrade_pod.sql' AS `pod_upgrade_status`;

-- Run after a verified backup. Review the backfilled assignments before launch.
CREATE TABLE IF NOT EXISTS pod_ptw_applicant_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ptw_applicant_user_company (user_id, company_id),
    KEY idx_ptw_applicant_active_user (user_id, status, deleted),
    KEY idx_ptw_applicant_company (company_id, status, deleted)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pod_ptw_applicant_users
    (user_id, company_id, status, deleted, created_at)
SELECT DISTINCT applicant_user_id, company_id, 'active', 0, UTC_TIMESTAMP()
FROM pod_ptw_applications
WHERE deleted=0
  AND applicant_user_id IS NOT NULL
  AND applicant_user_id > 0
  AND company_id IS NOT NULL
  AND company_id > 0;

SELECT assignments.*, users.email, companies.name AS company_name
FROM pod_ptw_applicant_users assignments
JOIN pod_users users ON users.id=assignments.user_id
JOIN pod_companies companies ON companies.id=assignments.company_id
ORDER BY companies.name, users.email;

SELECT 'COMPLETE 09/10 ptw_applicant_company_assignments_upgrade_pod.sql' AS `pod_upgrade_status`;

-- ============================================================================
-- SECTION 10/10 runtime_schema_ownership_hardening_upgrade_pod.sql
-- ============================================================================
SELECT 'BEGIN 10/10 runtime_schema_ownership_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;

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

SELECT 'COMPLETE 10/10 runtime_schema_ownership_hardening_upgrade_pod.sql' AS `pod_upgrade_status`;


-- ============================================================================
-- COMBINED UPGRADE FOOTER
-- ============================================================================
SET SESSION SQL_SAFE_UPDATES = @pod_combined_previous_sql_safe_updates;
SELECT
  'Combined security database upgrade completed' AS `pod_upgrade_status`,
  @pod_combined_started_at AS `started_at`,
  NOW() AS `completed_at`,
  @@SESSION.SQL_SAFE_UPDATES AS `restored_sql_safe_updates`;
