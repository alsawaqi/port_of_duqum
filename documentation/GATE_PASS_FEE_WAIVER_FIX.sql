-- Gate Pass fee-waiver history fix.
-- Run in MySQL Workbench against the application's database after a backup.
-- Double-click the correct schema first so it is the default database.
-- No CodeIgniter migration command is required.
-- This definition matches the production_schema.sql supplied on September 8.
-- If SHOW COLUMNS lists any additional enum values, retain those too in ALTER.

SELECT DATABASE() AS selected_database;
SHOW COLUMNS FROM `pod_gate_pass_request_approvals` LIKE 'decision';

ALTER TABLE `pod_gate_pass_request_approvals`
    MODIFY COLUMN `decision`
    ENUM('approved', 'rejected', 'returned', 'fee_waiver_rejected')
    NULL DEFAULT NULL;

SHOW COLUMNS FROM `pod_gate_pass_request_approvals` LIKE 'decision';
