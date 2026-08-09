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
