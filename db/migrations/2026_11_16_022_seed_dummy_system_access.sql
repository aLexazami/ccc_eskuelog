-- ======================================================================
-- Migration: Seed System Access Roles for Dummy Users 3 to 60 (E-SKUELOG - EMPLOYEE)
-- Date: 2026-11-16
-- Target Database: e_eskuelog
-- ======================================================================

START TRANSACTION;

-- Generating roles 1 to 4 for every user_id from 3 to 60 using a cross join approach
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
) 
SELECT 
    u.id AS user_id,
    u.id AS ref_id,
    'E-SKUELOG' AS system_type,
    r.role_id AS system_role,
    'EMPLOYEE' AS access_tag,
    0 AS employee_update,
    0 AS student_update,
    0 AS flag_access,
    CURRENT_TIMESTAMP() AS date_modify
FROM `users` u
CROSS JOIN (
    SELECT 1 AS role_id UNION ALL
    SELECT 2 UNION ALL
    SELECT 3 UNION ALL
    SELECT 4
) r
WHERE u.id BETWEEN 3 AND 60
ON DUPLICATE KEY UPDATE 
    `ref_id`      = VALUES(`ref_id`),
    `access_tag`  = VALUES(`access_tag`),
    `flag_access` = VALUES(`flag_access`),
    `date_modify` = VALUES(`date_modify`);

COMMIT;