-- Migration: Create Staff Assignments Table
-- Date: 2026-10-28
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `staff_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,                               -- References users.id
    `office_id` INT NOT NULL,                             -- References office.office_id
    `counter_id` INT DEFAULT NULL,                        -- References counters.id
    `shift_status` ENUM('OFF_DUTY', 'ON_DUTY', 'ON_BREAK') NOT NULL DEFAULT 'ON_DUTY',
    `flag_status` INT NOT NULL DEFAULT 0,                -- 0 = Active Assignment, 1 = Disabled
    `assigned_by_user_id` INT DEFAULT NULL,              -- Admin who made the assignment
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign Key Constraints
    CONSTRAINT `fk_sa_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sa_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_sa_assigned_by` FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,

    -- Indexes for fast login and queue execution queries
    UNIQUE KEY `uk_active_staff` (`user_id`),
    INDEX `idx_sa_lookup` (`office_id`, `counter_id`, `shift_status`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;