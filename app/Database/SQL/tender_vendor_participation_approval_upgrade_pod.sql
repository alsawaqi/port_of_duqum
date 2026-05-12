ALTER TABLE `pod_tender_invited_vendors`
    MODIFY COLUMN `invite_status` ENUM('sent','delivered','opened','declined','pending_approval','approved','rejected') NOT NULL DEFAULT 'sent';
