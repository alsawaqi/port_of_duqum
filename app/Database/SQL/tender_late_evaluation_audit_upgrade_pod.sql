USE `pod`;

ALTER TABLE `pod_tender_evaluations`
  ADD COLUMN IF NOT EXISTS `review_started_at` DATETIME DEFAULT NULL AFTER `comments`,
  ADD COLUMN IF NOT EXISTS `review_duration_seconds` INT(11) DEFAULT NULL AFTER `review_started_at`,
  ADD COLUMN IF NOT EXISTS `deadline_at` DATETIME DEFAULT NULL AFTER `review_duration_seconds`,
  ADD COLUMN IF NOT EXISTS `submitted_after_deadline` TINYINT(1) NOT NULL DEFAULT 0 AFTER `deadline_at`,
  ADD COLUMN IF NOT EXISTS `late_review_status` ENUM('pending','accepted','rejected') DEFAULT NULL AFTER `submitted_after_deadline`,
  ADD COLUMN IF NOT EXISTS `late_reviewed_by` BIGINT(20) UNSIGNED DEFAULT NULL AFTER `late_review_status`,
  ADD COLUMN IF NOT EXISTS `late_reviewed_at` DATETIME DEFAULT NULL AFTER `late_reviewed_by`,
  ADD COLUMN IF NOT EXISTS `late_review_comment` TEXT DEFAULT NULL AFTER `late_reviewed_at`;

ALTER TABLE `pod_tender_evaluations`
  ADD INDEX IF NOT EXISTS `idx_tender_eval_late_status` (`tender_id`, `type`, `submitted_after_deadline`, `late_review_status`, `deleted`);
