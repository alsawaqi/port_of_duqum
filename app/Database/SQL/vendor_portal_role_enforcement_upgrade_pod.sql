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
