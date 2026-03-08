ALTER TABLE `pod_ptw_applications`
  ADD COLUMN `work_supervisor_name` varchar(255) DEFAULT NULL AFTER `exact_location`,
  ADD COLUMN `supervisor_contact_details` varchar(255) DEFAULT NULL AFTER `work_supervisor_name`,
  ADD COLUMN `total_workers` int(10) UNSIGNED DEFAULT NULL AFTER `supervisor_contact_details`,
  ADD COLUMN `location_sector_name` varchar(255) DEFAULT NULL AFTER `location_lng`,
  ADD COLUMN `location_description` varchar(500) DEFAULT NULL AFTER `location_sector_name`,

  ADD COLUMN `declaration_agreed` tinyint(1) NOT NULL DEFAULT 0 AFTER `final_pdf_path`,
  ADD COLUMN `declaration_responsible_name` varchar(255) DEFAULT NULL AFTER `declaration_agreed`,
  ADD COLUMN `declaration_function` varchar(255) DEFAULT NULL AFTER `declaration_responsible_name`,
  ADD COLUMN `declaration_date` datetime DEFAULT NULL AFTER `declaration_function`,

  ADD COLUMN `signature_file_name` varchar(255) DEFAULT NULL AFTER `declaration_date`,
  ADD COLUMN `signature_file_path` varchar(255) DEFAULT NULL AFTER `signature_file_name`,
  ADD COLUMN `signature_file_type` varchar(100) DEFAULT NULL AFTER `signature_file_path`,
  ADD COLUMN `signature_file_size` int(11) DEFAULT NULL AFTER `signature_file_type`;





ALTER TABLE `pod_ptw_applications`
  ADD UNIQUE KEY `pod_ptw_applications_reference_unique` (`reference`),
  ADD KEY `pod_ptw_applications_stage_status_deleted_idx` (`stage`, `status`, `deleted`),
  ADD KEY `pod_ptw_applications_applicant_user_id_idx` (`applicant_user_id`),
  ADD KEY `pod_ptw_applications_work_from_work_to_idx` (`work_from`, `work_to`),
  ADD KEY `pod_ptw_applications_deleted_idx` (`deleted`);





ALTER TABLE `pod_ptw_requirement_definitions`
  ADD COLUMN `group_key` varchar(100) DEFAULT NULL AFTER `category`,
  ADD COLUMN `has_text_input` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_mandatory`,
  ADD COLUMN `text_label` varchar(255) DEFAULT NULL AFTER `has_text_input`,
  ADD COLUMN `allowed_extensions` varchar(255) DEFAULT NULL AFTER `text_label`,
  ADD COLUMN `help_text` varchar(255) DEFAULT NULL AFTER `allowed_extensions`;



  ALTER TABLE `pod_ptw_requirement_definitions`
  ADD UNIQUE KEY `pod_ptw_requirement_definitions_code_unique` (`code`),
  ADD KEY `pod_ptw_requirement_definitions_group_sort_idx` (`category`, `group_key`, `sort_order`),
  ADD KEY `pod_ptw_requirement_definitions_active_deleted_idx` (`is_active`, `deleted`);



ALTER TABLE `pod_ptw_requirement_responses`
  MODIFY COLUMN `value_text` text DEFAULT NULL;



ALTER TABLE `pod_ptw_requirement_responses`
  ADD KEY `pod_ptw_requirement_responses_req_def_id_idx` (`ptw_requirement_definition_id`),
  ADD KEY `pod_ptw_requirement_responses_deleted_idx` (`deleted`),
  ADD UNIQUE KEY `pod_ptw_requirement_responses_app_req_unique` (`ptw_application_id`, `ptw_requirement_definition_id`);  






ALTER TABLE `pod_ptw_requirement_responses`
  ADD CONSTRAINT `pod_ptw_requirement_responses_req_def_fk`
    FOREIGN KEY (`ptw_requirement_definition_id`)
    REFERENCES `pod_ptw_requirement_definitions` (`id`)
    ON DELETE CASCADE;




    ALTER TABLE `pod_ptw_attachments`
  ADD COLUMN `ptw_application_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `ptw_requirement_id`,
  ADD COLUMN `ptw_requirement_response_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `ptw_application_id`,
  ADD COLUMN `uploaded_by` int(11) DEFAULT NULL AFTER `file_size`;




  ALTER TABLE `pod_ptw_attachments`
  ADD KEY `pod_ptw_attachments_ptw_application_id_idx` (`ptw_application_id`),
  ADD KEY `pod_ptw_attachments_ptw_requirement_response_id_idx` (`ptw_requirement_response_id`),
  ADD KEY `pod_ptw_attachments_uploaded_by_idx` (`uploaded_by`);




  ALTER TABLE `pod_ptw_attachments`
  ADD CONSTRAINT `pod_ptw_attachments_ptw_application_fk`
    FOREIGN KEY (`ptw_application_id`)
    REFERENCES `pod_ptw_applications` (`id`)
    ON DELETE CASCADE,
  ADD CONSTRAINT `pod_ptw_attachments_ptw_requirement_response_fk`
    FOREIGN KEY (`ptw_requirement_response_id`)
    REFERENCES `pod_ptw_requirement_responses` (`id`)
    ON DELETE CASCADE;




    ALTER TABLE `pod_ptw_reviews`
  ADD COLUMN `received_at` datetime DEFAULT NULL AFTER `reviewer_id`,
  ADD COLUMN `completed_at` datetime DEFAULT NULL AFTER `received_at`,
  ADD COLUMN `status_change_reason` text DEFAULT NULL AFTER `remarks`;




  ALTER TABLE `pod_ptw_reviews`
  ADD KEY `pod_ptw_reviews_stage_decision_deleted_idx` (`stage`, `decision`, `deleted`),
  ADD KEY `pod_ptw_reviews_reviewer_id_idx` (`reviewer_id`),
  ADD UNIQUE KEY `pod_ptw_reviews_app_stage_revision_unique` (`ptw_application_id`, `stage`, `revision_no`);




  ALTER TABLE `pod_ptw_audit_logs`
  ADD KEY `pod_ptw_audit_logs_user_id_idx` (`user_id`),
  ADD KEY `pod_ptw_audit_logs_ptw_created_idx` (`ptw_application_id`, `created_at`),
  ADD KEY `pod_ptw_audit_logs_deleted_idx` (`deleted`);



  CREATE TABLE `pod_ptw_hsse_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pod_ptw_hsse_users_user_company_unique` (`user_id`, `company_id`),
  KEY `pod_ptw_hsse_users_status_deleted_idx` (`status`, `deleted`),
  KEY `pod_ptw_hsse_users_company_id_idx` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE `pod_ptw_hmo_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pod_ptw_hmo_users_user_company_unique` (`user_id`, `company_id`),
  KEY `pod_ptw_hmo_users_status_deleted_idx` (`status`, `deleted`),
  KEY `pod_ptw_hmo_users_company_id_idx` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE `pod_ptw_terminal_users` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pod_ptw_terminal_users_user_company_unique` (`user_id`, `company_id`),
  KEY `pod_ptw_terminal_users_status_deleted_idx` (`status`, `deleted`),
  KEY `pod_ptw_terminal_users_company_id_idx` (`company_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




CREATE TABLE `pod_ptw_reasons` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `stage` enum('hsse','hmo','terminal','any') NOT NULL DEFAULT 'any',
  `reason_type` enum('reject','revise') NOT NULL,
  `title` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pod_ptw_reasons_stage_type_active_idx` (`stage`, `reason_type`, `is_active`),
  KEY `pod_ptw_reasons_deleted_idx` (`deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



SELECT `reference`, COUNT(*) c
FROM `pod_ptw_applications`
GROUP BY `reference`
HAVING c > 1;



SELECT `ptw_application_id`, `ptw_requirement_definition_id`, COUNT(*) c
FROM `pod_ptw_requirement_responses`
GROUP BY `ptw_application_id`, `ptw_requirement_definition_id`
HAVING c > 1;