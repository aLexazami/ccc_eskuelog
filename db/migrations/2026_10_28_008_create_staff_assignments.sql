-- Migration: Create Staff Assignments Table
-- Date: 2026-10-28 (Updated: shared multi-staff windows AND multi-window staff, no shift status)
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `staff_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,                          -- References users.id (linked to employee.user_id)
    `office_id` INT(11) NOT NULL,                        -- References office.office_id
    `counter_id` INT(11) DEFAULT NULL,                   -- References counters.id (one row per staff + window)
    `flag_status` INT(11) NOT NULL DEFAULT 0,            -- 0 = Active, 1 = Inactive (mirrors a disabled window)
    `assigned_by_user_id` INT(11) DEFAULT NULL,          -- Admin/Staff user_id who made the assignment
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- A staff member can serve MANY windows and a window can have MANY staff,
    -- but the same staff member can't be listed twice on the same window.
    -- (Also serves as the index required by fk_sa_user.)
    UNIQUE KEY `uk_staff_counter` (`user_id`, `counter_id`),

    -- Indexes for fast login and shared window queue lookups
    KEY `fk_sa_counter` (`counter_id`),
    KEY `fk_sa_assigned_by` (`assigned_by_user_id`),
    KEY `idx_sa_lookup` (`office_id`, `counter_id`, `flag_status`),

    -- Foreign Key Constraints
    CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;