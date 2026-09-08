-- Read-only verification after 02_add_missing_tables.sql.
USE `bedotscpanel_poderp`;
SELECT VERSION() AS server_version, DATABASE() AS selected_database;
SELECT wanted.table_name, wanted.expected_columns, COUNT(c.COLUMN_NAME) AS actual_columns,
       CASE WHEN COUNT(c.COLUMN_NAME)=wanted.expected_columns THEN 'COUNT OK - review definitions below' ELSE 'MISSING OR INCOMPLETE' END AS result
FROM (
SELECT 'pod_eservice_payments' AS table_name, 35 AS expected_columns
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 12 AS expected_columns
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 18 AS expected_columns
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 19 AS expected_columns
) wanted LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=wanted.table_name
GROUP BY wanted.table_name,wanted.expected_columns;

SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,GENERATION_EXPRESSION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('pod_eservice_payments','pod_eservice_payment_events','pod_vendor_fee_requests','pod_sms_outbox') ORDER BY TABLE_NAME,ORDINAL_POSITION;

SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_in_order FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('pod_eservice_payments','pod_eservice_payment_events','pod_vendor_fee_requests','pod_sms_outbox') GROUP BY TABLE_NAME,INDEX_NAME,NON_UNIQUE ORDER BY TABLE_NAME,INDEX_NAME;
