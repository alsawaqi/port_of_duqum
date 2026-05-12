ALTER TABLE `pod_tenders`
    ADD COLUMN IF NOT EXISTS `tender_fee` DECIMAL(15,3) DEFAULT NULL AFTER `brief_description`;
