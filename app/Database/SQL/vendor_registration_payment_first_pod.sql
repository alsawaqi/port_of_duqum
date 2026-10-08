-- Point 1: run against the application's pod_ database before uploading the PHP files.
-- Additive and repeatable. Existing registrations, fee requests and payments are retained.
CREATE TABLE IF NOT EXISTS `pod_vendor_registration_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `vendor_id` BIGINT UNSIGNED NOT NULL,
  `fee_request_id` BIGINT UNSIGNED NOT NULL,
  `owner_contact_id` BIGINT UNSIGNED NOT NULL,
  `riyada_document_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(24) NOT NULL,
  `initial_amount` DECIMAL(15,3) NOT NULL,
  `review_note` TEXT NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vendor_registration_vendor` (`vendor_id`),
  UNIQUE KEY `uq_vendor_registration_fee` (`fee_request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riyadha is a fixed upload for a waiver. General group document rules are a separate change.
-- Reuse an existing non-deleted type, including one previously marked inactive.
UPDATE `pod_vendor_document_types` SET `is_active`=1
WHERE deleted=0 AND UPPER(code) IN ('RIYADHA','RIYADA');

INSERT INTO `pod_vendor_document_types` (`name`,`code`,`vendor_group_id`,`is_required`,`is_active`,`deleted`)
SELECT 'Riyadha Certificate','RIYADHA',NULL,0,1,0
WHERE NOT EXISTS (
  SELECT 1 FROM `pod_vendor_document_types`
  WHERE deleted=0 AND is_active=1 AND UPPER(code) IN ('RIYADHA','RIYADA')
);
