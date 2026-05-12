USE `pod`;

CREATE TABLE IF NOT EXISTS `pod_tender_bid_item_prices` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tender_bid_id` BIGINT(20) UNSIGNED NOT NULL,
  `tender_id` BIGINT(20) UNSIGNED NOT NULL,
  `vendor_id` BIGINT(20) UNSIGNED NOT NULL,
  `tender_rfq_item_id` BIGINT(20) UNSIGNED NOT NULL,
  `qty` DECIMAL(18,3) DEFAULT NULL,
  `unit_price` DECIMAL(18,3) NOT NULL,
  `line_total` DECIMAL(18,3) DEFAULT NULL,
  `created_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tender_bid_item_prices_bid` (`tender_bid_id`),
  KEY `idx_tender_bid_item_prices_tender_vendor` (`tender_id`, `vendor_id`),
  KEY `idx_tender_bid_item_prices_rfq_item` (`tender_rfq_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
