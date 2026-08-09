-- Legacy invoice payment hardening upgrade.
-- Run during a controlled deployment before enabling Stripe, PayPal or Paytm
-- for invoice payments. Existing in-flight invoice checkouts must be restarted;
-- Stripe subscription setup rows are intentionally retained.

CREATE TABLE IF NOT EXISTS `pod_legacy_invoice_payment_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(32) NOT NULL,
  `provider` varchar(24) NOT NULL,
  `invoice_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `contact_user_id` bigint unsigned NOT NULL,
  `payment_method_id` bigint unsigned NOT NULL,
  `invoice_verification_code` char(10) DEFAULT NULL,
  `expected_amount` decimal(15,3) NOT NULL,
  `expected_amount_minor` bigint unsigned NOT NULL,
  `currency` char(3) NOT NULL,
  `minor_unit_exponent` tinyint unsigned NOT NULL,
  `provider_reference` varchar(191) DEFAULT NULL,
  `provider_transaction_id` varchar(191) DEFAULT NULL,
  `invoice_payment_id` bigint unsigned DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `failure_code` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legacy_invoice_payment_public` (`public_id`),
  UNIQUE KEY `uq_legacy_invoice_payment_provider_reference` (`provider`,`provider_reference`),
  UNIQUE KEY `uq_legacy_invoice_payment_provider_transaction` (`provider`,`provider_transaction_id`),
  KEY `idx_legacy_invoice_payment_invoice` (`invoice_id`,`status`),
  KEY `idx_legacy_invoice_payment_expiry` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refuse legacy invoice redirects after deployment. Subscription setup rows
-- remain because subscription_id is non-zero and use a separate callback.
UPDATE `pod_stripe_ipn`
SET `deleted` = 1
WHERE `deleted` = 0 AND COALESCE(`invoice_id`, 0) > 0 AND COALESCE(`subscription_id`, 0) = 0;

UPDATE `pod_paypal_ipn`
SET `deleted` = 1
WHERE `deleted` = 0 AND COALESCE(`invoice_id`, 0) > 0;
