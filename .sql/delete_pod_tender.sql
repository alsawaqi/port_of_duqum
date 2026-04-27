USE `bedotscpanel_poderp`;

SET SESSION sql_safe_updates = 0;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `pod_tender_bid_opening_entries`;
DELETE FROM `pod_tender_evaluation_scores`;
DELETE FROM `pod_tender_vendor_performance_evaluations`;
DELETE FROM `pod_tender_bid_documents`;
DELETE FROM `pod_tender_evaluations`;
DELETE FROM `pod_tender_bid_openings`;

DELETE FROM `pod_tender_opening_attempts`;
DELETE FROM `pod_tender_opening_members`;
DELETE FROM `pod_tender_opening_sessions`;

DELETE FROM `pod_tender_communications`;
DELETE FROM `pod_tender_extensions`;
DELETE FROM `pod_tender_documents`;
DELETE FROM `pod_tender_criteria`;
DELETE FROM `pod_tender_invited_vendors`;
DELETE FROM `pod_tender_target_specialties`;
DELETE FROM `pod_tender_team_members`;
DELETE FROM `pod_tender_bid_requirements`;
DELETE FROM `pod_vendor_performance_scores`;
DELETE FROM `pod_tender_bids`;

DELETE FROM `pod_tender_request_approvals`;
DELETE FROM `pod_tender_request_team_members`;
DELETE FROM `pod_tender_request_vendors`;

DELETE FROM `pod_tenders`;
DELETE FROM `pod_tender_requests`;

SET FOREIGN_KEY_CHECKS = 1;
SET SESSION sql_safe_updates = 1;
