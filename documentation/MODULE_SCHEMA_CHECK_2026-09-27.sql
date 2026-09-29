-- READ ONLY: select the application database in Workbench, then run.
-- Expected columns for the four modules and their shared login/payment/SMS tables.
-- An empty missing_columns result means no listed columns are missing. This is a schema
-- check, not a test of credentials, permissions, network providers or user data.
SET SESSION group_concat_max_len=16384;
SELECT DATABASE() AS selected_database;
SELECT expected.table_name, GROUP_CONCAT(expected.column_name ORDER BY expected.column_name SEPARATOR ', ') AS missing_columns
FROM (
SELECT 'pod_activity_logs' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_type' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_type_title' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_type_id' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'changes' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_for' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_for_id' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_for2' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'log_for_id2' AS column_name
UNION ALL
SELECT 'pod_activity_logs' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'event_type' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'outcome' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'identity_hash' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'user_agent_hash' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'context_json' AS column_name
UNION ALL
SELECT 'pod_auth_audit_events' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'identity_hash' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'failed_attempts' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'first_failed_at' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'last_failed_at' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'locked_until' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'last_ip_hash' AS column_name
UNION ALL
SELECT 'pod_auth_login_security' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'challenge_id' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'purpose' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'provider' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'destination_hint' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'code_hash' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'attempts' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'max_attempts' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'consumed_at' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'request_ip_hash' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'user_agent_hash' AS column_name
UNION ALL
SELECT 'pod_auth_mfa_challenges' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'selector' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'validator_hash' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'used_at' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'request_ip_hash' AS column_name
UNION ALL
SELECT 'pod_auth_password_reset_tokens' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'regions_id' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_cities' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_companies' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_country' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_departments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'public_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'subject_type' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'subject_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'amount' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'amount_minor' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'provider' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'idempotency_key' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'provider_checkout_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'checkout_url' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'provider_payment_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'metadata' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'initiated_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'paid_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'failed_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'failure_code' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'active_subject_key' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'gateway_merchant_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'bank_reference' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'response_json' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'status_response_json' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'verification_issues' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'verified_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'returned_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'handed_off_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'last_status_check_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payments' AS table_name, 'settlement_status' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'payment_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'provider' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'provider_event_id' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'event_type' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'payload_sha256' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'received_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'processed_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'response_json' AS column_name
UNION ALL
SELECT 'pod_eservice_payment_events' AS table_name, 'verification_issues' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'gate_pass_request_visitor_id' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'gate_pass_no' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'qr_token' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'valid_from' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'valid_to' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'issued_by' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'issued_at' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'printed_at' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'meta' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_passes' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'id_number' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'normalized_id_number' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'id_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'visitor_name' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'nationality' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'visitor_company' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'source_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'source_visitor_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'blocked_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'blocked_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'unblocked_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'unblocked_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'unblock_reason' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'last_action_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'last_action_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'blocked_visitor_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'action_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'action_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_gate_pass_blocked_visitor_logs' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_commercial_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_department_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'visit_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'vehicle_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'gate_pass_purpose_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'amount' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'rate_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'min_days' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'max_days' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'active_from' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'active_to' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'is_waivable' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_fee_rules' AS table_name, 'induction_amount' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'event_key' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'visitor_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'recipient_user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'channel' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'destination' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'subject' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'message' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'is_preview' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'claimed_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_notification_outbox' AS table_name, 'processed_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'category' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_purposes' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'description' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_reasons' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'reference' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'requester_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'site_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'stage_updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'gate_pass_purpose_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'visitor_coordinator_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'visit_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'request_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'vehicle_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'visit_from' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'visit_to' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'purpose_notes' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'supporting_letter_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_amount' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_is_waived' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_waived_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_waived_reason' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'payment_transaction_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'submitted_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'issued_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'issued_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_waived_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_waiver_requested' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_waiver_commercial_status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_requests' AS table_name, 'fee_breakdown' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'decision' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'reason_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'comment' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'decided_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'decided_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_approvals' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'actor_user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'details' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_audit_log' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'vehicle_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'plate_no' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'is_international_plate' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'plate_country' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'international_plate_no' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'make' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'model' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'color' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'vehicle_registration_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_vehicles' AS table_name, 'mulkiyah_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'person_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'visa_type_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'role' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'full_name' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'id_type' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'id_number' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'nationality' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'phone' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'visitor_company' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'id_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'visa_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'photo_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'driving_license_attachment_path' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'is_primary' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'is_blocked' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'block_reason' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'blocked_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'blocked_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_request_visitors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_rop_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'gate_pass_request_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'gate_pass_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'gate_pass_request_visitor_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'security_user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'note' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'recorded_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'performed_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_gate_pass_scan_log' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_security_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'username' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'otp_channel' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'invited_by' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'registered_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_gate_pass_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applicant_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'reference' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'applicant_user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'company_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'applicant_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'applicant_position' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'contact_phone' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'contact_email' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'work_description' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'exact_location' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'work_supervisor_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'supervisor_contact_details' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'total_workers' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'location_lat' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'location_lng' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'location_sector_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'location_description' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'work_from' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'work_to' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'submitted_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'completed_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'terminal_approval_required' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'final_pdf_path' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'declaration_agreed' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'declaration_responsible_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'declaration_function' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'declaration_date' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'signature_file_name' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'signature_file_path' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'signature_file_type' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'signature_file_size' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_applications' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'ptw_requirement_id' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'ptw_application_id' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'ptw_requirement_response_id' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'file_name' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'file_path' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'file_type' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'file_size' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'uploaded_by' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_attachments' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'ptw_application_id' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'meta' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_audit_logs' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_hmo_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_hsse_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'reason_type' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reasons' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'category' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'group_key' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'label' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'requires_attachment' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'is_mandatory' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'has_text_input' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'text_label' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'allowed_extensions' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'help_text' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_definitions' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'ptw_application_id' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'ptw_requirement_definition_id' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'is_checked' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'value_text' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'attachment_path' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_requirement_responses' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'ptw_application_id' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'revision_no' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'reviewer_id' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'received_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'completed_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'decision' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'remarks' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'status_change_reason' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'reviewed_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_ptw_reviews' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_ptw_terminal_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'country_id' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_regions' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_roles' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_roles' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'pod_roles' AS table_name, 'permissions' AS column_name
UNION ALL
SELECT 'pod_roles' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_settings' AS table_name, 'setting_name' AS column_name
UNION ALL
SELECT 'pod_settings' AS table_name, 'setting_value' AS column_name
UNION ALL
SELECT 'pod_settings' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_settings' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'event_key' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'module' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'subject_id' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'source_table' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'source_id' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'reference' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'recipient_user_id' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'recipient_name' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'mobile' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'message' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'language' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'is_preview' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'provider_code' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'claimed_at' AS column_name
UNION ALL
SELECT 'pod_sms_outbox' AS table_name, 'processed_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'tender_request_id' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'reference' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'brief_description' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'tender_fee' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'tender_type' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'evaluation_method' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'technical_weight' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'commercial_weight' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'workflow_stage' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_status' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_submitted_by' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_submitted_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_reviewed_by' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_reviewed_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_comment' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_payload' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'procurement_manager_action' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'release_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'document_purchase_deadline' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'site_visit_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'site_visit_location' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'site_visit_instructions' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'site_visit_mandatory' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'clarification_deadline' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'published_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'closing_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'bid_opening_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'technical_eval_deadline' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'commercial_eval_deadline' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'technical_start_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'technical_end_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'technical_locked_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'committee_3key_start_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'committee_3key_end_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'commercial_start_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'commercial_end_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'commercial_unlocked_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'award_ready_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'award_vendor_id' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'loa_reference' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'loa_issued_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tenders' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'submitted_at' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'total_amount' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bids' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'section' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'disk' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'path' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'original_name' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'mime_type' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'size_bytes' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'submitted_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_documents' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'tender_rfq_item_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'qty' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'unit_price' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'line_total' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_item_prices' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'chairman_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'secretary_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'member_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'chairman_code_hash' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'secretary_code_hash' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'member_code_hash' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'chairman_code_ciphertext' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'secretary_code_ciphertext' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'member_code_ciphertext' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'generated_by' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'generated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'unlocked_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'signed_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'manual_form_path' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'manual_form_original_name' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'manual_form_uploaded_by' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'manual_form_uploaded_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_openings' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'tender_bid_opening_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'role' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'input_chairman_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'input_secretary_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'input_member_code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'is_valid' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'confirmed_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signature_statement' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signature_name' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signature_image_path' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signed_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signature_ip_address' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'signature_user_agent' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_opening_entries' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'label' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'is_required' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_bid_requirements' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_commercial_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_committee_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'parent_id' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'clarification_scope' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'internal_audience' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'subject' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'message' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'sent_to_all' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'is_vendor_visible' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'published_at' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_communications' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'communication_id' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'disk' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'path' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'original_name' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'mime_type' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'size_bytes' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'uploaded_by' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_communication_attachments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'weight' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_criteria' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_department_manager_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_department_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'doc_type' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'title' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'disk' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'path' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'original_name' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'mime_type' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'size_bytes' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'time_limited' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'expires_in_hours' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'uploaded_by' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_documents' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'evaluator_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'decision' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'total_score' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'comments' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'review_started_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'review_duration_seconds' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'deadline_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'submitted_after_deadline' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'late_review_status' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'late_reviewed_by' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'late_reviewed_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'late_review_comment' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'submitted_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluations' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'tender_evaluation_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'disk' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'path' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'original_name' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'mime_type' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'size_bytes' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'uploaded_by' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_attachments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'tender_evaluation_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'tender_criterion_id' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'score' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'comment' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_evaluation_scores' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'milestone_code' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'old_close_at' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'new_close_at' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'approved_by' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'approved_at' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_extensions' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'amount' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'payment_reference' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'paid_at' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_fee_payments' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_finance_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'invite_status' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'invited_by' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'invited_at' AS column_name
UNION ALL
SELECT 'pod_tender_invited_vendors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'tender_opening_session_id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'payload' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'success' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'attempted_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_attempts' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'tender_opening_session_id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'code_hash' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'code_issued_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'code_confirmed_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'is_confirmed' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_members' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'required_members' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'started_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_opening_sessions' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_manager_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_procurement_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'reference' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'department_id' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'department_manager_user_id' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'department_manager_title' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'department_manager_signed_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'department_manager_reject_comment' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'requester_id' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'request_date' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'budget_omr' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'tender_fee' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'finance_verified_by' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'finance_verified_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'finance_reject_comment' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'committee_approved_by' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'committee_approved_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'committee_reject_comment' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'estimated_previous_amount' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'estimated_previous_notes' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'subject' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'brief_description' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'announcement' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'tender_type' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'evaluation_method' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'technical_weight' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'commercial_weight' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_requests' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'tender_request_id' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'stage' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'decided_by' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'decision' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'comment' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'decided_at' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'ip_address' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'user_agent' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_request_approvals' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'tender_request_id' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'team_role' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_request_team_members' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'tender_request_id' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_request_vendors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'rfq_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'rfq_date' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'pr_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'delivery_location' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'incoterm' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'material_required_on' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'terms_reference' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'notes' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'enclosures' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'rfq_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'rfq_date' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'pr_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'delivery_location' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'incoterm' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'material_required_on' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'terms_reference' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'notes' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'enclosures' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_details_orphan_20260722' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'sr_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'description' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'uom' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'qty' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'unit_price' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'brand' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'sr_no' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'description' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'uom' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'qty' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'unit_price' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'brand' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'sort_order' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_rfq_items_orphan_20260722' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'vendor_category_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'vendor_sub_category_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'vendor_grade_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_target_specialties' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_target_vendors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'team_role' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_team_members' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'company_id' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_technical_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'tender_bid_id' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'evaluation_stage' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'round' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'overall_score' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'score_breakdown' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'notes' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'evaluated_by' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'evaluated_at' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_tender_vendor_performance_evaluations' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'action_type' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'from_status' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'to_status' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'from_stage' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'to_stage' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'open_until' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'details' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'action_type' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'from_status' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'to_status' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'from_stage' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'to_stage' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'open_until' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'details' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_tender_workflow_history_orphan_20260722' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'first_name' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'last_name' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'user_type' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'is_admin' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'role_id' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'email' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'password' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'image' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'message_checked_at' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'client_id' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'notification_checked_at' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'is_primary_contact' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'job_title' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'disable_login' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'note' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'address' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'alternative_address' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'phone' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'alternative_phone' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'dob' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'ssn' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'gender' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'sticky_note' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'skype' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'language' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'enable_web_notification' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'enable_email_notification' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'last_online' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'requested_account_removal' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'client_permissions' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'auth_session_version' AS column_name
UNION ALL
SELECT 'pod_users' AS table_name, 'otp_delivery_channel' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'vendor_grade_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'vendor_name' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'email' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'cr_number' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'phone' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'phone_country_code' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'contact_person' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'contact_designation' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'legal_type_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'country_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'region_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'city_id' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'address' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'po_box' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'postal_code' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'payment_terms' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'registration_valid_from' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'registration_valid_to' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'notes' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'blocked_reason' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'blocked_by' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'blocked_at' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'updated_by' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendors' AS table_name, 'cr_number_identity' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'bank_name' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'bank_branch' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'bank_account_no' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'bank_swift_code' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'iban' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'letter_head_path' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_bank_accounts' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'address' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'country_id' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'region_id' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'city_id' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'phone' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'email' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'is_main' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_branches' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_categories' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'contacts_name' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'phone' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'fax' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'designation' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'email' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'email_2' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'mobile' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'role' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'is_primary' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'live_email_identity' AS column_name
UNION ALL
SELECT 'pod_vendor_contacts' AS table_name, 'live_user_identity' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'type' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'number' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'issue_date' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'expiry_date' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'notes' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_credentials' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'vendor_document_type_id' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'disk' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'path' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'original_name' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'mime_type' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'size_bytes' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'issued_at' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'expires_at' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'uploaded_by' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_documents' AS table_name, 'reviewed_by' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'is_required' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_document_types' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'fee_type' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'period_key' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'fee_id' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'amount' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'prior_valid_until' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'validity_days' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'payment_id' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'review_status' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'requested_by' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'reviewed_by' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'reviewed_at' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_fee_requests' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'description' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'sort' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_grades' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'requires_riyada' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'default_validity_days' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_groups' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'vendor_group_id' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'fee_type' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'currency' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'amount' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'active_from' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'active_to' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'created_by' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_group_fees' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'tender_id' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'category' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'score' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'comment' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'scored_by' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'scored_at' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_performance_scores' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'description' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_roles' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'vendor_category_id' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'vendor_sub_category_id' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'specialty_type' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'specialty_name' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'specialty_description' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_specialties' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'from_status' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'to_status' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'action' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'reason' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'action_by' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'action_at' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_status_histories' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'vendor_category_id' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'name' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'code' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'is_active' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_sub_categories' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'requested_by' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'changes' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'reviewed_by' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'reviewed_at' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'review_comment' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_update_requests' AS table_name, 'deleted' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'id' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'vendor_id' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'user_id' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'invited_by' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'vendor_role_id' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'is_owner' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'status' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'invited_at' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'credentials_ready_at' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'created_at' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'updated_at' AS column_name
UNION ALL
SELECT 'pod_vendor_users' AS table_name, 'deleted' AS column_name
) expected LEFT JOIN information_schema.COLUMNS actual
 ON actual.TABLE_SCHEMA=DATABASE() AND actual.TABLE_NAME=expected.table_name AND actual.COLUMN_NAME=expected.column_name
WHERE actual.COLUMN_NAME IS NULL
GROUP BY expected.table_name ORDER BY expected.table_name;

-- Both PTW definition fields must say YES under IS_NULLABLE.
SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE,COLUMN_TYPE
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
 AND ((TABLE_NAME='pod_ptw_requirement_responses' AND COLUMN_NAME='ptw_requirement_definition_id')
 OR (TABLE_NAME='pod_ptw_attachments' AND COLUMN_NAME='ptw_requirement_id'));
-- The decision enum must include fee_waiver_rejected.
SHOW COLUMNS FROM pod_gate_pass_request_approvals LIKE 'decision';
-- These provider/accounting tables must retain their unique and lookup indexes.
SHOW INDEX FROM pod_eservice_payments;
SHOW INDEX FROM pod_gate_pass_notification_outbox;

-- Review active tariffs; this audit does not replace or reset any charges.
-- The current per-person calculator expects flat rules and separate induction.
SELECT id,min_days,max_days,rate_type,amount,induction_amount,currency,is_active
FROM pod_gate_pass_fee_rules WHERE deleted=0 AND is_active=1
ORDER BY currency,min_days,max_days;
