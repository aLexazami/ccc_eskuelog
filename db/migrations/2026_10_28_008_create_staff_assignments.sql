-- Migration: Create Staff Assignments Table
-- Date: 2026-10-28 (Updated for Shared Multi-Staff Windows without Shift Status)
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `staff_assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,                          -- References users.id (linked to employee.user_id)
    `office_id` INT(11) NOT NULL,                        -- References office.office_id
    `counter_id` INT(11) DEFAULT NULL,                   -- References counters.id (shared across multiple staff in the same office)
    `flag_status` INT(11) NOT NULL DEFAULT 0,            -- 0 = Active Assignment, 1 = Inactive / Unassigned
    `assigned_by_user_id` INT(11) DEFAULT NULL,          -- Admin/Staff user_id who made the assignment
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Ensures 1 staff member is assigned to at most 1 window at a time,
    -- while allowing multiple staff rows to share the same counter_id
    UNIQUE KEY `uk_active_staff` (`user_id`),

    -- Indexes for fast login and shared window queue lookups
    KEY `fk_sa_counter` (`counter_id`),
    KEY `fk_sa_assigned_by` (`assigned_by_user_id`),
    KEY `idx_sa_lookup` (`office_id`, `counter_id`, `flag_status`),

    -- Foreign Key Constraints
    CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sa_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;