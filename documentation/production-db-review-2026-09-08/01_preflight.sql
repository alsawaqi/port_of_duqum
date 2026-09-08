-- Read-only checks against the supplied production schema.
-- Export the result grids if you want the production data checks reviewed.
-- No passwords, encryption keys, SMS credentials, or customer phone values are selected.
USE `bedotscpanel_poderp`;

SELECT VERSION() AS server_version, DATABASE() AS selected_database;

-- These four tables were absent in the export. If they now exist, inspect them before patching.
SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
('pod_eservice_payments','pod_eservice_payment_events','pod_vendor_fee_requests','pod_sms_outbox');

-- This is the legacy migration ledger found in the export, not CodeIgniter's pod_migrations.
SELECT migration, batch FROM migrations ORDER BY id;

-- Schema-only exports cannot confirm the state of these settings.
-- Report presence only; never export the SMS password or encrypted credential value.
SELECT setting_name, type,
       CASE WHEN setting_value IS NULL OR setting_value='' THEN 'EMPTY' ELSE 'PRESENT' END AS configuration_presence
FROM pod_settings WHERE deleted=0 AND setting_name IN
('ismartsms_enabled','ismartsms_user_id','ismartsms_password','ismartsms_header',
 'sms_login_otp_required','sms_notifications_enabled','sms_live_notifications',
 'sms_vendor_enabled','sms_gate_pass_enabled','sms_ptw_enabled','sms_tender_enabled');

SELECT COUNT(*) AS active_login_accounts,
       SUM(CASE WHEN TRIM(COALESCE(phone,''))='' THEN 1 ELSE 0 END) AS missing_mobile_numbers
FROM pod_users WHERE deleted=0 AND status='active' AND disable_login=0;
-- This counts empty mobiles only. Settings > SMS applies the full Oman-mobile validation.

SELECT code, is_active, deleted, COUNT(*) AS role_records
FROM pod_vendor_roles GROUP BY code,is_active,deleted;

SELECT fee_type,currency,is_active,COUNT(*) AS fee_rules,
       MIN(amount) AS minimum_amount,MAX(amount) AS maximum_amount
FROM pod_vendor_group_fees WHERE deleted=0 GROUP BY fee_type,currency,is_active;

-- Pending legacy records need a cutover decision; an empty new ledger proves no historical payment.
SELECT status,COUNT(*) AS vendor_records
FROM pod_vendors WHERE deleted=0 GROUP BY status;

SELECT stage,status,COUNT(*) AS gate_pass_requests,
       SUM(CASE WHEN fee_amount>0 AND COALESCE(fee_is_waived,0)=0 THEN 1 ELSE 0 END) AS positive_unwaived_fees
FROM pod_gate_pass_requests WHERE deleted=0 GROUP BY stage,status;

SELECT status,COUNT(*) AS legacy_tender_fee_records
FROM pod_tender_fee_payments WHERE deleted=0 GROUP BY status;

SELECT status,COUNT(*) AS tender_records,
       SUM(CASE WHEN tender_fee IS NULL THEN 1 ELSE 0 END) AS unset_tender_fees
FROM pod_tenders WHERE deleted=0 GROUP BY status;

SELECT COUNT(*) AS active_ptw_applications_without_company_id
FROM pod_ptw_applications WHERE deleted=0 AND company_id IS NULL;

SELECT COUNT(*) AS approved_vendor_contacts_without_login_link
FROM pod_vendor_contacts WHERE deleted=0 AND is_active=1 AND status='approved' AND user_id IS NULL;
