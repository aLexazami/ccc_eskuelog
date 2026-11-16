
-- ======================================================================
-- MIGRATION: Insert Employee Profiles for Users 1 to 60
-- Users 1 & 2 assigned to Office ID 10 (DCI) / Department ID 3
-- Remaining users distributed across 12 Offices (5 employees per office)
-- Compatible with ' JOB ORDER' enum definition
-- ======================================================================

START TRANSACTION;

INSERT INTO `employee` (
    `user_id`, 
    `employee_id`, 
    `personnel_classification`, 
    `employment_status`, 
    `employment_basis`, 
    `position`, 
    `profile_pic`, 
    `cover_photo`, 
    `employment_date`, 
    `office_id`, 
    `department_id`, 
    `room_id`, 
    `service_status`, 
    `flag_update`, 
    `date_modify`
) VALUES 
-- Office 10 (DCI): Users 1 & 2 (plus users 46-50 later)
(1,  'CGC-123456', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),
(2,  'CGC-123457', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),

-- Office 1: OCP (office_id: 1, dept: 0) -> Users 3-5 (User 1 moved to DCI)
(3,  'CGC-123458', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Administrative Aide', NULL, NULL, '[\"\"]', 1, 0, 0, 0, 0, NOW()),
(4,  'CGC-123459', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor I', NULL, NULL, '[\"\"]', 1, 0, 0, 0, 0, NOW()),
(5,  'CGC-123460', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 1, 0, 0, 0, 0, NOW()),

-- Office 2: OVPAA (office_id: 2, dept: 0) -> Users 6-10
(6,  'CGC-123461', 'TEACHING PERSONNEL', 'CONTRACT OF SERVICE', 'PART TIME', 'Lecturer', NULL, NULL, '[\"\"]', 2, 0, 0, 0, 0, NOW()),
(7,  'CGC-123462', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Administrative Officer I', NULL, NULL, '[\"\"]', 2, 0, 0, 0, 0, NOW()),
(8,  'CGC-123463', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor I', NULL, NULL, '[\"\"]', 2, 0, 0, 0, 0, NOW()),
(9,  'CGC-123464', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 2, 0, 0, 0, 0, NOW()),
(10, 'CGC-123465', 'TEACHING PERSONNEL', ' JOB ORDER', 'PART TIME', 'Instructor I', NULL, NULL, '[\"\"]', 2, 0, 0, 0, 0, NOW()),

-- Office 3: OVPSDA (office_id: 3, dept: 0) -> Users 11-15
(11, 'CGC-123466', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Records Officer', NULL, NULL, '[\"\"]', 3, 0, 0, 0, 0, NOW()),
(12, 'CGC-123467', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Associate Professor', NULL, NULL, '[\"\"]', 3, 0, 0, 0, 0, NOW()),
(13, 'CGC-123468', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 3, 0, 0, 0, 0, NOW()),
(14, 'CGC-123469', 'TEACHING PERSONNEL', ' JOB ORDER', 'FULL TIME', 'Instructor II', NULL, NULL, '[\"\"]', 3, 0, 0, 0, 0, NOW()),
(15, 'CGC-123470', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 3, 0, 0, 0, 0, NOW()),

-- Office 4: OVPAF (office_id: 4, dept: 0) -> Users 16-20
(16, 'CGC-123471', 'TEACHING PERSONNEL', 'CONTRACT OF SERVICE', 'PART TIME', 'Lecturer', NULL, NULL, '[\"\"]', 4, 0, 0, 0, 0, NOW()),
(17, 'CGC-123472', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Staff Assistant', NULL, NULL, '[\"\"]', 4, 0, 0, 0, 0, NOW()),
(18, 'CGC-123473', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor II', NULL, NULL, '[\"\"]', 4, 0, 0, 0, 0, NOW()),
(19, 'CGC-123474', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 4, 0, 0, 0, 0, NOW()),
(20, 'CGC-123475', 'TEACHING PERSONNEL', ' JOB ORDER', 'PART TIME', 'Instructor I', NULL, NULL, '[\"\"]', 4, 0, 0, 0, 0, NOW()),

-- Office 5: OVPREPQA (office_id: 5, dept: 0) -> Users 21-25
(21, 'CGC-123476', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Administrative Aide III', NULL, NULL, '[\"\"]', 5, 0, 0, 0, 0, NOW()),
(22, 'CGC-123477', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor III', NULL, NULL, '[\"\"]', 5, 0, 0, 0, 0, NOW()),
(23, 'CGC-123478', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 5, 0, 0, 0, 0, NOW()),
(24, 'CGC-123479', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor I', NULL, NULL, '[\"\"]', 5, 0, 0, 0, 0, NOW()),
(25, 'CGC-123480', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 5, 0, 0, 0, 0, NOW()),

-- Office 6: OVPCAR (office_id: 6, dept: 0) -> Users 26-30
(26, 'CGC-123481', 'TEACHING PERSONNEL', 'CONTRACT OF SERVICE', 'PART TIME', 'Lecturer', NULL, NULL, '[\"\"]', 6, 0, 0, 0, 0, NOW()),
(27, 'CGC-123482', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Budget Officer', NULL, NULL, '[\"\"]', 6, 0, 0, 0, 0, NOW()),
(28, 'CGC-123483', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Associate Professor II', NULL, NULL, '[\"\"]', 6, 0, 0, 0, 0, NOW()),
(29, 'CGC-123484', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 6, 0, 0, 0, 0, NOW()),
(30, 'CGC-123485', 'TEACHING PERSONNEL', ' JOB ORDER', 'PART TIME', 'Instructor II', NULL, NULL, '[\"\"]', 6, 0, 0, 0, 0, NOW()),

-- Office 7: DTE (office_id: 7, dept: 4) -> Users 31-35
(31, 'CGC-123486', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Supply Officer', NULL, NULL, '[\"\"]', 7, 4, 0, 0, 0, NOW()),
(32, 'CGC-123487', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Professor I', NULL, NULL, '[\"\"]', 7, 4, 0, 0, 0, NOW()),
(33, 'CGC-123488', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 7, 4, 0, 0, 0, NOW()),
(34, 'CGC-123489', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor I', NULL, NULL, '[\"\"]', 7, 4, 0, 0, 0, NOW()),
(35, 'CGC-123490', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 7, 4, 0, 0, 0, NOW()),

-- Office 8: DAS (office_id: 8, dept: 1) -> Users 36-40
(36, 'CGC-123491', 'TEACHING PERSONNEL', 'CONTRACT OF SERVICE', 'PART TIME', 'Lecturer', NULL, NULL, '[\"\"]', 8, 1, 0, 0, 0, NOW()),
(37, 'CGC-123492', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Accountant I', NULL, NULL, '[\"\"]', 8, 1, 0, 0, 0, NOW()),
(38, 'CGC-123493', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor III', NULL, NULL, '[\"\"]', 8, 1, 0, 0, 0, NOW()),
(39, 'CGC-123494', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 8, 1, 0, 0, 0, NOW()),
(40, 'CGC-123495', 'TEACHING PERSONNEL', ' JOB ORDER', 'PART TIME', 'Instructor I', NULL, NULL, '[\"\"]', 8, 1, 0, 0, 0, NOW()),

-- Office 9: DBA (office_id: 9, dept: 2) -> Users 41-45
(41, 'CGC-123496', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'HR Assistant', NULL, NULL, '[\"\"]', 9, 2, 0, 0, 0, NOW()),
(42, 'CGC-123497', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Associate Professor I', NULL, NULL, '[\"\"]', 9, 2, 0, 0, 0, NOW()),
(43, 'CGC-123498', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 9, 2, 0, 0, 0, NOW()),
(44, 'CGC-123499', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor II', NULL, NULL, '[\"\"]', 9, 2, 0, 0, 0, NOW()),
(45, 'CGC-123500', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 9, 2, 0, 0, 0, NOW()),

-- Office 10: DCI (office_id: 10, dept: 3) -> Users 46-50 (Plus Users 1 & 2)
(46, 'CGC-123501', 'TEACHING PERSONNEL', 'CONTRACT OF SERVICE', 'PART TIME', 'Lecturer', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),
(47, 'CGC-123502', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'IT Officer I', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),
(48, 'CGC-123503', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor I', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),
(49, 'CGC-123504', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),
(50, 'CGC-123505', 'TEACHING PERSONNEL', ' JOB ORDER', 'PART TIME', 'Instructor I', NULL, NULL, '[\"\"]', 10, 3, 0, 0, 0, NOW()),

-- Office 11: LRIC (office_id: 11, dept: 0) -> Users 51-55
(51, 'CGC-123506', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Library Staff', NULL, NULL, '[\"\"]', 11, 0, 2, 0, 0, NOW()),
(52, 'CGC-123507', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor III', NULL, NULL, '[\"\"]', 11, 0, 2, 0, 0, NOW()),
(53, 'CGC-123508', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 11, 0, 2, 0, 0, NOW()),
(54, 'CGC-123509', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Assistant Professor II', NULL, NULL, '[\"\"]', 11, 0, 2, 0, 0, NOW()),
(55, 'CGC-123510', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 11, 0, 2, 0, 0, NOW()),

-- Office 12: MISD (office_id: 12, dept: 0) -> Users 56-60
(56, 'CGC-123511', 'NON-TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'MIS Programmer', NULL, NULL, '[\"\"]', 12, 0, 0, 0, 0, NOW()),
(57, 'CGC-123512', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Associate Professor III', NULL, NULL, '[\"\"]', 12, 0, 0, 0, 0, NOW()),
(58, 'CGC-123513', 'NON-TEACHING PERSONNEL', 'CONTRACT OF SERVICE', '', '', NULL, NULL, '[\"\"]', 12, 0, 0, 0, 0, NOW()),
(59, 'CGC-123514', 'TEACHING PERSONNEL', 'PERMANENT', 'FULL TIME', 'Instructor I', NULL, NULL, '[\"\"]', 12, 0, 0, 0, 0, NOW()),
(60, 'CGC-123515', 'NON-TEACHING PERSONNEL', ' JOB ORDER', '', '', NULL, NULL, '[\"\"]', 12, 0, 0, 0, 0, NOW())
ON DUPLICATE KEY UPDATE
    `employee_id`              = VALUES(`employee_id`),
    `personnel_classification` = VALUES(`personnel_classification`),
    `employment_status`        = VALUES(`employment_status`),
    `office_id`                = VALUES(`office_id`),
    `department_id`            = VALUES(`department_id`),
    `date_modify`              = NOW();

COMMIT;