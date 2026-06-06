ALTER TABLE `pod_gate_pass_request_vehicles`
  ADD COLUMN IF NOT EXISTS `is_international_plate` TINYINT(1) NOT NULL DEFAULT 0 AFTER `plate_no`,
  ADD COLUMN IF NOT EXISTS `plate_country` VARCHAR(120) DEFAULT NULL AFTER `is_international_plate`,
  ADD COLUMN IF NOT EXISTS `international_plate_no` VARCHAR(120) DEFAULT NULL AFTER `plate_country`;
