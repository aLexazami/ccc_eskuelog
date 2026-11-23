-- Migration: Seed Counters and Initial Staff Assignment
-- Date: 2026-10-28 (Updated for Shared Multi-Staff Windows without Shift Status)
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Seed Service Counters / Windows for Campus Offices (Window Setup Only)
INSERT INTO `counters` (`office_id`, `counter_name`, `flag_status`) VALUES
-- OCP (office_id = 1)
(1, 'Window 1', 0),
(1, 'Window 2', 0),

-- OVPAA (office_id = 2)
(2, 'Window 1', 0),
(2, 'Window 2', 0),

-- OVPSDA (office_id = 3)
(3, 'Window 1', 0),
(3, 'Window 2', 0),

-- OVPAF (office_id = 4)
(4, 'Window 1', 0),
(4, 'Window 2', 0),

-- OVPREPQA (office_id = 5)
(5, 'Window 1', 0),

-- OVPCAR (office_id = 6)
(6, 'Window 1', 0),

-- DTE (office_id = 7)
(7, 'Window 1', 0),

-- DAS (office_id = 8)
(8, 'Window 1', 0),

-- DBA (office_id = 9)
(9, 'Window 1', 0),

-- DCI (office_id = 10)
(10, 'Window 1', 0),
(10, 'Window 2', 0),

-- LIRC (office_id = 11)
(11, 'Window 1', 0),
(11, 'Window 2', 0),

-- MISD (office_id = 12)
(12, 'Window 1', 0),
(12, 'Window 2', 0)
ON DUPLICATE KEY UPDATE
    `flag_status` = VALUES(`flag_status`);

-- Step 6: Seed Staff Assignments for MISD (office_id = 12) at Window 1 for user_id = 1 and user_id = 2
INSERT INTO `staff_assignments` (
    `user_id`,
    `office_id`,
    `counter_id`,
    `flag_status`,
    `assigned_by_user_id`
) VALUES 
(
    1,                                                                                         -- user_id = 1
    12,                                                                                        -- office_id = 12 (MISD)
    (SELECT `id` FROM `counters` WHERE `office_id` = 12 AND `counter_name` = 'Window 1' LIMIT 1),
    0,                                                                                         -- 0 = Active Assignment
    1                                                                                          -- Self/Admin assigner user_id
),
(
    2,                                                                                         -- user_id = 2
    12,                                                                                        -- office_id = 12 (MISD)
    (SELECT `id` FROM `counters` WHERE `office_id` = 12 AND `counter_name` = 'Window 1' LIMIT 1),
    0,                                                                                         -- 0 = Active Assignment
    1                                                                                          -- Admin assigner user_id
)
ON DUPLICATE KEY UPDATE
    `office_id` = VALUES(`office_id`),
    `counter_id` = VALUES(`counter_id`),
    `flag_status` = VALUES(`flag_status`),
    `assigned_by_user_id` = VALUES(`assigned_by_user_id`);

COMMIT;