-- Migration: Insert Student Record for Alex Flores
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Insert Base User Profile
INSERT INTO `users` (
    `ref_id`, `first_name`, `middle_name`, `middle_initial`, `last_name`, 
    `suffix`, `post_nominal`, `birth_date`, `sex`, `email`, `personal_email`, 
    `civil_status`, `nationality`, `birth_place`, `contact_no`, `brgy`, `city`, 
    `province`, `home_address`, `profile_pic`, `cover_photo`, `e_name`, 
    `e_relationship`, `e_contact`, `e_address`, `flag_status`, `flag_update`, `date_modify`
) VALUES (
    2, 'ALEX', 'CARBELLIDO', 'C', 'FLORES', 
    '', NULL, '2003-04-08', 'male', 'acflores@ccc.edu.ph', '', 
    'single', 'Filipino', 'Sta. Cruz, Laguna', '09750920427', 'Canlubang', 'Calamba City', 
    'Laguna', 'Mars St, MCDC', 'profile-img.png', 'default-cover.jpg', '', 
    '', '', '', 0, 0, NOW()
);

-- 2. Fetch the dynamically generated ID and insert Student Profile
SET @last_user_id = LAST_INSERT_ID();

INSERT INTO `student` (
    `user_id`, 
    `student_id`, 
    `year_level`, 
    `program_id`
) VALUES (
    @last_user_id, 
    '2023-10544', 
    4, 
    5
);

-- 3. Log execution into activity_log
INSERT INTO `activity_log` (
    `user_id`, `action`, `session_id`, `user_level`, `system_id`
) VALUES (
    @last_user_id, 'MIGRATION_EXECUTE: Insert Student 2023-10544', 'CLI_MIGRATION', 'SYSTEM', 5
);

COMMIT;