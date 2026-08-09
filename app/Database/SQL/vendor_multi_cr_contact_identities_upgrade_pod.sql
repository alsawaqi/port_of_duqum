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
