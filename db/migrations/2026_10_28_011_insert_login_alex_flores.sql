-- Migration: Seed Login Credentials, System Access Roles, and Employee Profile for Alex Flores (user_id = 2)
-- Date: 2026-10-28
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Insert/Update Login Credentials
INSERT INTO `login` (
    `user_id`, 
    `username`, 
    `password`, 
    `recovery_email`, 
    `status`, 
    `locked`, 
    `flag_update`, 
    `flag_validity`, 
    `date_modify`, 
    `date_username`, 
    `date_password`
) VALUES (
    '2', 
    'acflores@ccc.edu.ph', 
    '$2y$10$RxM0TZaAhhjaTNOKiw5ti.IIAb8bdidNtqlkmlva6de5FmlUeSV6i', -- Hashed 'acflores@ccc.edu.ph'
    '', 
    0, 
    0, 
    0, 
    0, 
    CURRENT_TIMESTAMP(), 
    CURRENT_DATE(), 
    CURRENT_DATE()
) ON DUPLICATE KEY UPDATE 
    `username` = VALUES(`username`),
    `password` = VALUES(`password`),
    `status`   = VALUES(`status`),
    `locked`   = VALUES(`locked`);

-- 2. Insert/Update System Access Roles
INSERT INTO `system_access` (
    `user_id`, 
    `ref_id`, 
    `system_type`, 
    `system_role`, 
    `access_tag`, 
    `employee_update`, 
    `student_update`, 
    `flag_access`, 
    `date_modify`
) VALUES 
    (2, 2, 'E-SKUELOG', 1, 'EMPLOYEE', 0, 0, 0, CURRENT_TIMESTAMP()),
    (2, 2, 'E-SKUELOG', 2, 'EMPLOYEE', 0, 0, 0, CURRENT_TIMESTAMP()),
    (2, 2, 'E-SKUELOG', 3, 'EMPLOYEE', 0, 0, 0, CURRENT_TIMESTAMP()),
    (2, 2, 'E-SKUELOG', 4, 'EMPLOYEE', 0, 0, 0, CURRENT_TIMESTAMP())
ON DUPLICATE KEY UPDATE 
    `ref_id`      = VALUES(`ref_id`),
    `access_tag`  = VALUES(`access_tag`),
    `flag_access` = VALUES(`flag_access`),
    `date_modify` = VALUES(`date_modify`);

-- 3. Insert/Update Employee Profile
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
) VALUES (
    2,
    'CGC-123457',                  -- Employee ID Code
    'NON-TEACHING PERSONNEL',       -- Options: 'TEACHING PERSONNEL', 'NON-TEACHING PERSONNEL'
    'CONTRACT OF SERVICE',          -- Options: 'PERMANENT', 'CONTRACT OF SERVICE', ' JOB ORDER'
    '',                             -- Options: 'FULL TIME', 'PART TIME', ''
    '',                             -- Position / Title
    NULL,
    NULL,
    '[""]',
    0,
    0,
    0,
    0,
    0,
    CURRENT_TIMESTAMP()
) ON DUPLICATE KEY UPDATE 
    `employee_id`              = VALUES(`employee_id`),
    `personnel_classification` = VALUES(`personnel_classification`),
    `employment_status`        = VALUES(`employment_status`),
    `date_modify`              = VALUES(`date_modify`);

COMMIT;