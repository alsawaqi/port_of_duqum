ALTER TABLE `pod_tenders`
    ADD COLUMN IF NOT EXISTS `evaluation_method` ENUM('separate','combined') NOT NULL DEFAULT 'separate' AFTER `tender_type`,
    ADD COLUMN IF NOT EXISTS `technical_weight` TINYINT(3) UNSIGNED NOT NULL DEFAULT 70 AFTER `evaluation_method`,
    ADD COLUMN IF NOT EXISTS `commercial_weight` TINYINT(3) UNSIGNED NOT NULL DEFAULT 30 AFTER `technical_weight`;

UPDATE `pod_tenders` t
INNER JOIN `pod_tender_requests` req
    ON req.`id` = t.`tender_request_id`
   AND req.`deleted` = 0
SET t.`evaluation_method` = COALESCE(req.`evaluation_method`, t.`evaluation_method`),
    t.`technical_weight` = COALESCE(req.`technical_weight`, t.`technical_weight`),
    t.`commercial_weight` = COALESCE(req.`commercial_weight`, t.`commercial_weight`)
WHERE t.`deleted` = 0;
