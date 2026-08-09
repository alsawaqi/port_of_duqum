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
