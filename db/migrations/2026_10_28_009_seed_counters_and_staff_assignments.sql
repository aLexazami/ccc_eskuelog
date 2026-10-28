-- Migration: Seed Counters and Initial Staff Assignment
-- Date: 2026-10-28
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Seed Service Counters / Windows for Campus Offices
INSERT INTO `counters` (`office_id`, `counter_name`, `assigned_user_id`, `flag_status`) VALUES
-- OCP (office_id = 1)
(1, 'Window 1', NULL, 0),
(1, 'Window 2', NULL, 0),

-- OVPAA (office_id = 2)
(2, 'Window 1', NULL, 0),
(2, 'Window 2', NULL, 0),

-- OVPSDA (office_id = 3)
(3, 'Window 1', NULL, 0),
(3, 'Window 2', NULL, 0),

-- OVPAF (office_id = 4)
(4, 'Window 1', NULL, 0),
(4, 'Window 2', NULL, 0),

-- OVPREPQA (office_id = 5)
(5, 'Window 1', NULL, 0),

-- OVPCAR (office_id = 6)
(6, 'Window 1', NULL, 0),

-- DTE (office_id = 7)
(7, 'Window 1', NULL, 0),

-- DAS (office_id = 8)
(8, 'Window 1', NULL, 0),

-- DBA (office_id = 9)
(9, 'Window 1', NULL, 0),

-- DCI (office_id = 10)
(10, 'Window 1', 1, 0),
(10, 'Window 2', NULL, 0),

-- LRIC (office_id = 11)
(11, 'Window 1', NULL, 0),
(11, 'Window 2', NULL, 0);

-- 2. Seed Initial Staff Assignment for Staff Member Marlon Reolo (user_id = 1)
-- Assigned to DCI (office_id = 10) at Window 1
INSERT INTO `staff_assignments` (
    `user_id`,
    `office_id`,
    `counter_id`,
    `shift_status`,
    `flag_status`,
    `assigned_by_user_id`
) VALUES (
    1,                                                                      -- user_id = 1 (Marlon Reolo)
    10,                                                                     -- office_id = 10 (DCI)
    (SELECT `id` FROM `counters` WHERE `office_id` = 10 AND `counter_name` = 'Window 1' LIMIT 1), -- Window 1 counter_id
    'ON_DUTY',                                                              -- Shift status
    0,                                                                      -- 0 = Active
    1                                                                       -- Self/Admin assigner ID
) ON DUPLICATE KEY UPDATE 
    `office_id` = VALUES(`office_id`),
    `counter_id` = VALUES(`counter_id`),
    `shift_status` = VALUES(`shift_status`),
    `flag_status` = VALUES(`flag_status`);

COMMIT;