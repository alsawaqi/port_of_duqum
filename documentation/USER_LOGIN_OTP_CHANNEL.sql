-- Select the application database in MySQL Workbench before running.
-- Safe to rerun. Existing accounts retain the current system OTP policy.
SET @pod_otp_ddl = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='pod_users'
       AND COLUMN_NAME='otp_delivery_channel') = 0,
    'ALTER TABLE `pod_users` ADD COLUMN `otp_delivery_channel` VARCHAR(16) NOT NULL DEFAULT ''''',
    'SELECT ''OTP delivery column already exists'' AS result'
);
PREPARE pod_otp_stmt FROM @pod_otp_ddl;
EXECUTE pod_otp_stmt;
DEALLOCATE PREPARE pod_otp_stmt;
