-- PORT OF DUQM: RESET DEFAULT GATE PASS TARIFFS - 21 September 2026
-- Source: Gate-Passes-Tariff.pdf, with the previously approved boundaries:
-- exactly 7 days is OMR 3; a month is 30 days; 181-365 days is OMR 50;
-- add OMR 5 induction per person to every pass lasting 15 days or longer.
-- Requires the updated per-person tariff application code (September 19/20 package).
-- Select the application database in MySQL Workbench and back up before running.
-- Run during a maintenance window. Table prefix: pod_. No migration command.
-- Old rules are removed from the active list using deleted=1, preserving their IDs
-- for history. Existing requests, paid amounts and bank transactions are untouched.
-- Rerunning replaces the six visible rules again; it never duplicates active bands.
-- Every amount is charged ONCE PER PERSON for the matching inclusive day range.
-- These are duration ranges, not fixed calendar dates or monthly multipliers.

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

-- Remove every existing tariff from the current rules screen/calculation.
UPDATE pod_gate_pass_fee_rules
SET is_active = 0, deleted = 1
WHERE deleted = 0;

-- Install the official default charges. Commercial can still approve a waiver.
INSERT INTO pod_gate_pass_fee_rules
    (rate_type, min_days, max_days, currency, amount, induction_amount,
     is_waivable, is_active, deleted)
VALUES
    ('flat',   1,   1, 'OMR',  2.000, 0.000, 1, 1, 0),
    ('flat',   2,   7, 'OMR',  3.000, 0.000, 1, 1, 0),
    ('flat',   8,  14, 'OMR',  5.000, 0.000, 1, 1, 0),
    ('flat',  15,  90, 'OMR', 20.000, 5.000, 1, 1, 0),
    ('flat',  91, 180, 'OMR', 35.000, 5.000, 1, 1, 0),
    ('flat', 181, 365, 'OMR', 50.000, 5.000, 1, 1, 0);

COMMIT;

-- Expected result: exactly these six rows, with totals 2, 3, 5, 25, 40, 55 OMR.
SELECT min_days, max_days,
       amount AS tariff_per_person,
       induction_amount AS induction_per_person,
       amount + induction_amount AS total_per_person,
       currency, is_active
FROM pod_gate_pass_fee_rules
WHERE deleted = 0 AND is_active = 1
ORDER BY min_days;
