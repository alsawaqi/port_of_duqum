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

