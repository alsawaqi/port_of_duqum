-- Gate Pass Tariff upgrade, 19 September 2026. MySQL 5.7+/MariaDB.
-- BACK UP the database first. Select the destination application database in Workbench.
-- Run during a maintenance window together with the matching PHP files.
-- This installs the approved OMR tariffs. It does NOT reprice existing requests/payments.
-- Rerunning restores these six tariffs (including any administrator edits to them).
SELECT DATABASE() AS selected_database;

SET @pod_tariff_ddl = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='pod_gate_pass_requests' AND COLUMN_NAME='fee_breakdown')=0,
  'ALTER TABLE pod_gate_pass_requests ADD COLUMN fee_breakdown LONGTEXT NULL', 'SELECT 1');
PREPARE pod_tariff_stmt FROM @pod_tariff_ddl;
EXECUTE pod_tariff_stmt;
DEALLOCATE PREPARE pod_tariff_stmt;

SET @pod_tariff_ddl = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='pod_gate_pass_fee_rules' AND COLUMN_NAME='induction_amount')=0,
  'ALTER TABLE pod_gate_pass_fee_rules ADD COLUMN induction_amount DECIMAL(12,3) NOT NULL DEFAULT 0.000', 'SELECT 1');
PREPARE pod_tariff_stmt FROM @pod_tariff_ddl;
EXECUTE pod_tariff_stmt;
DEALLOCATE PREPARE pod_tariff_stmt;

START TRANSACTION;
-- Retain old rules for reference. Only the new six OMR rules will be active.
UPDATE pod_gate_pass_fee_rules SET is_active=0 WHERE currency='OMR' AND deleted=0;

INSERT INTO pod_gate_pass_fee_rules
 (rate_type,min_days,max_days,currency,amount,induction_amount,is_waivable,is_active,deleted)
SELECT 'flat', t.min_days,t.max_days,'OMR',t.amount,t.induction_amount,1,0,0
FROM (
 SELECT 1 AS min_days,1 AS max_days,2.000 AS amount,0.000 AS induction_amount
 UNION ALL SELECT 2,7,3.000,0.000
 UNION ALL SELECT 8,14,5.000,0.000
 UNION ALL SELECT 15,90,20.000,5.000
 UNION ALL SELECT 91,180,35.000,5.000
 UNION ALL SELECT 181,365,50.000,5.000
) AS t
WHERE NOT EXISTS (SELECT 1 FROM pod_gate_pass_fee_rules r WHERE r.currency='OMR'
 AND r.rate_type='flat' AND r.deleted=0 AND r.min_days=t.min_days AND r.max_days=t.max_days
 AND r.amount=t.amount AND r.induction_amount=t.induction_amount);

-- Select one rule per band, even if duplicate legacy rows exist.
UPDATE pod_gate_pass_fee_rules r
JOIN (
 SELECT MIN(id) AS id FROM pod_gate_pass_fee_rules
 WHERE currency='OMR' AND rate_type='flat' AND deleted=0 AND (
 (min_days=1 AND max_days=1 AND amount=2 AND induction_amount=0) OR
 (min_days=2 AND max_days=7 AND amount=3 AND induction_amount=0) OR
 (min_days=8 AND max_days=14 AND amount=5 AND induction_amount=0) OR
 (min_days=15 AND max_days=90 AND amount=20 AND induction_amount=5) OR
 (min_days=91 AND max_days=180 AND amount=35 AND induction_amount=5) OR
 (min_days=181 AND max_days=365 AND amount=50 AND induction_amount=5))
 GROUP BY min_days,max_days
) chosen ON chosen.id=r.id SET r.is_active=1;
COMMIT;

SELECT min_days,max_days,amount AS tariff_per_person,induction_amount AS induction_per_person,
 amount+induction_amount AS total_per_person,currency
FROM pod_gate_pass_fee_rules WHERE currency='OMR' AND deleted=0 AND is_active=1 ORDER BY min_days;
