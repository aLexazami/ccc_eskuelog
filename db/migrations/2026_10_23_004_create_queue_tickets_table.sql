-- Migration: Create Queue Tickets Table linked to Logbooks
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `queue_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `logbook_id` INT NOT NULL UNIQUE,                 -- 1:1 Foreign Key to logbooks.id
    
    -- Queue Specifics
    `ticket_number` VARCHAR(30) NOT NULL,             -- e.g., OAA-001, P-DCI-015
    `queue_sequence` INT NOT NULL,                    -- Daily incremental number per office
    `queue_date` DATE NOT NULL,                        -- Date of issuance
    `office_id` INT NOT NULL,                          -- Indexed for fast office queue filtering
    
    -- Queue Lifecycle State
    `status` ENUM('WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'SKIPPED', 'CANCELLED') DEFAULT 'WAITING',
    `is_priority` TINYINT(1) NOT NULL DEFAULT 0,       -- 0 = Regular, 1 = Priority Ticket
    `counter_id` INT DEFAULT NULL,                    -- References counters.id
    `served_by_user_id` INT DEFAULT NULL,              -- Staff who called ticket
    
    -- Queue Timestamps
    `called_at` DATETIME DEFAULT NULL,
    `served_at` DATETIME DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    
    -- Constraints
    CONSTRAINT `fk_queue_logbook` FOREIGN KEY (`logbook_id`) REFERENCES `logbooks`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_queue_office_ref` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`),
    CONSTRAINT `fk_queue_counter_ref` FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`),
    
    -- Indexes
    INDEX `idx_queue_ticket` (`ticket_number`),
    INDEX `idx_queue_call` (`office_id`, `queue_date`, `status`, `is_priority`, `queue_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;