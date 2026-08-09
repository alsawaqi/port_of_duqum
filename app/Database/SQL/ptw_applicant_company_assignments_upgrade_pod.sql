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
