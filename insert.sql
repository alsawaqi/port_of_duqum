-- =========================================================
-- STEP 2: PTW Requirement Definitions Seed (BRD Section 8.4)
-- Assumes Step 1 patch already applied:
-- - added group_key, has_text_input, text_label, allowed_extensions, help_text
-- - added UNIQUE KEY on `code`
-- =========================================================

INSERT INTO `pod_ptw_requirement_definitions`
(
  `category`,
  `group_key`,
  `code`,
  `label`,
  `requires_attachment`,
  `is_mandatory`,
  `has_text_input`,
  `text_label`,
  `allowed_extensions`,
  `help_text`,
  `sort_order`,
  `is_active`,
  `created_at`,
  `updated_at`,
  `deleted`
)
VALUES

-- =========================================================
-- SECTION 3: Hazards & Attachments (Documents)
-- =========================================================

('hazard_document','hazard_docs','haz_risk_assessment','Risk Assessment',1,1,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Mandatory document upload',10,1,NOW(),NOW(),0),
('hazard_document','hazard_docs','haz_scope_of_work_doc','Scope of Work',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',20,1,NOW(),NOW(),0),
('hazard_document','hazard_docs','haz_job_safety_analysis','Job Safety Analyses (JSA)',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',30,1,NOW(),NOW(),0),
('hazard_document','hazard_docs','haz_msds','MSDS',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',40,1,NOW(),NOW(),0),
('hazard_document','hazard_docs','haz_method_statement','Method Statement',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',50,1,NOW(),NOW(),0),
('hazard_document','hazard_docs','haz_base_cold_work_permit','Base (Cold) Work Permit',1,1,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Mandatory permit upload',60,1,NOW(),NOW(),0),

('hazard_document','hazard_permits','haz_hot_work_permit','Hot Work Permit',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',70,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_confined_space_entry','Confined Space Entry Permit',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',80,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_excavation_certificate','Excavation Certificate',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',90,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_working_at_height_cert','Working at Height Certificate',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',100,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_diving_log','Diving Log',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',110,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_elec_mech_isolation','Electrical / Mechanical Isolation',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',120,1,NOW(),NOW(),0),
('hazard_document','hazard_permits','haz_toolbox_talk','Toolbox Talk',1,0,0,NULL,'pdf,docx,jpg,jpeg,png,webp','Upload if applicable',130,1,NOW(),NOW(),0),

('hazard_document','hazard_permits','haz_other_document','Other',1,0,1,'Specify other document','pdf,docx,jpg,jpeg,png,webp','Use text field + optional upload if checked',140,1,NOW(),NOW(),0),

-- =========================================================
-- SECTION 4: Proposed PPE (Checklist)
-- =========================================================

-- Mandatory PPE
('ppe','ppe_mandatory','ppe_helmet','Helmet',0,1,0,NULL,NULL,'Mandatory PPE item',210,1,NOW(),NOW(),0),
('ppe','ppe_mandatory','ppe_safety_shoes','Safety Shoes',0,1,0,NULL,NULL,'Mandatory PPE item',220,1,NOW(),NOW(),0),
('ppe','ppe_mandatory','ppe_safety_glasses','Safety Glasses',0,1,0,NULL,NULL,'Mandatory PPE item',230,1,NOW(),NOW(),0),

-- Optional PPE (common industrial/PTW items)
('ppe','ppe_optional','ppe_gloves','Gloves',0,0,0,NULL,NULL,NULL,240,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_coverall','Coverall / Protective Clothing',0,0,0,NULL,NULL,NULL,250,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_face_shield','Face Shield',0,0,0,NULL,NULL,NULL,260,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_ear_protection','Ear Protection',0,0,0,NULL,NULL,NULL,270,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_respirator','Respirator / Dust Mask',0,0,0,NULL,NULL,NULL,280,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_harness','Fall Arrest Harness',0,0,0,NULL,NULL,NULL,290,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_life_jacket','Life Jacket',0,0,0,NULL,NULL,NULL,300,1,NOW(),NOW(),0),
('ppe','ppe_optional','ppe_welding_shield','Welding Shield / Goggles',0,0,0,NULL,NULL,NULL,310,1,NOW(),NOW(),0),

('ppe','ppe_optional','ppe_other','Other',0,0,1,'Specify other PPE',NULL,'Use text field when checked',320,1,NOW(),NOW(),0),

-- =========================================================
-- SECTION 5: Work Area Preparations (Checklist & Text)
-- =========================================================

-- General preparatory precautions
('preparation','prep_general','prep_loto','Lock-out / Tag-out (LOTO) applied',0,0,0,NULL,NULL,NULL,410,1,NOW(),NOW(),0),
('preparation','prep_general','prep_warning_signs','Warning signs placed',0,0,0,NULL,NULL,NULL,420,1,NOW(),NOW(),0),
('preparation','prep_general','prep_area_barricaded','Area barricaded',0,0,0,NULL,NULL,NULL,430,1,NOW(),NOW(),0),
('preparation','prep_general','prep_permit_displayed','Permit displayed at work area',0,0,0,NULL,NULL,NULL,440,1,NOW(),NOW(),0),
('preparation','prep_general','prep_emergency_contacts','Emergency contacts available',0,0,0,NULL,NULL,NULL,450,1,NOW(),NOW(),0),
('preparation','prep_general','prep_lighting_adequate','Adequate lighting provided',0,0,0,NULL,NULL,NULL,460,1,NOW(),NOW(),0),
('preparation','prep_general','prep_ventilation_adequate','Adequate ventilation provided',0,0,0,NULL,NULL,NULL,470,1,NOW(),NOW(),0),
('preparation','prep_general','prep_access_clear','Safe access / egress clear',0,0,0,NULL,NULL,NULL,480,1,NOW(),NOW(),0),

-- Hot work / specific precautions
('preparation','prep_hot_work','prep_hot_no_flammable','Area free of flammable materials',0,0,0,NULL,NULL,'Hot work precaution',510,1,NOW(),NOW(),0),
('preparation','prep_hot_work','prep_hot_fire_hose_standby','Fire hose standby',0,0,0,NULL,NULL,'Hot work precaution',520,1,NOW(),NOW(),0),
('preparation','prep_hot_work','prep_hot_gas_monitoring','Gas monitoring in place',0,0,0,NULL,NULL,'Hot work precaution',530,1,NOW(),NOW(),0),
('preparation','prep_hot_work','prep_hot_fire_watch','Fire watch assigned',0,0,0,NULL,NULL,'Hot work precaution',540,1,NOW(),NOW(),0),
('preparation','prep_hot_work','prep_hot_spark_containment','Spark containment / shielding provided',0,0,0,NULL,NULL,'Hot work precaution',550,1,NOW(),NOW(),0),

-- Text-enabled fields required by BRD wording ("Specify medium", "Other")
('preparation','prep_hot_work','prep_hot_specify_medium','Specify medium',0,0,1,'Specify medium',NULL,'Text field for medium details',560,1,NOW(),NOW(),0),
('preparation','prep_hot_work','prep_other','Other',0,0,1,'Specify other preparation',NULL,'Use text field when checked',570,1,NOW(),NOW(),0)

ON DUPLICATE KEY UPDATE
  `category` = VALUES(`category`),
  `group_key` = VALUES(`group_key`),
  `label` = VALUES(`label`),
  `requires_attachment` = VALUES(`requires_attachment`),
  `is_mandatory` = VALUES(`is_mandatory`),
  `has_text_input` = VALUES(`has_text_input`),
  `text_label` = VALUES(`text_label`),
  `allowed_extensions` = VALUES(`allowed_extensions`),
  `help_text` = VALUES(`help_text`),
  `sort_order` = VALUES(`sort_order`),
  `is_active` = VALUES(`is_active`),
  `updated_at` = NOW(),
  `deleted` = 0;




  -- Check all seeded PTW definitions
SELECT
  id, category, group_key, code, label,
  requires_attachment, is_mandatory, has_text_input, sort_order, is_active
FROM pod_ptw_requirement_definitions
WHERE deleted = 0
ORDER BY category, sort_order, id;




-- Count by category (should show hazard_document / ppe / preparation)
SELECT category, COUNT(*) AS total
FROM pod_ptw_requirement_definitions
WHERE deleted = 0 AND is_active = 1
GROUP BY category;