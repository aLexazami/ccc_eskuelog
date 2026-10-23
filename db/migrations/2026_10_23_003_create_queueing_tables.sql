-- Migration: Create Queueing and Digital Logbook Module Tables
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Transaction Types / Services per Office
CREATE TABLE IF NOT EXISTS `transaction_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_id` INT NOT NULL,                          -- References office.office_id
    `transaction_name` VARCHAR(150) NOT NULL,          -- e.g., 'Transcript Request', 'ID Validation'
    `estimated_minutes` INT DEFAULT 5,                 -- For queue wait time estimation
    `requires_custom_input` TINYINT(1) DEFAULT 0,      -- 1 = prompt visitor for specific details
    `flag_status` INT NOT NULL DEFAULT 0,              -- 0 = active, 1 = disabled
    `date_modify` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_trans_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    INDEX `idx_office_trans` (`office_id`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Service Counters / Windows per Office
CREATE TABLE IF NOT EXISTS `counters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_id` INT NOT NULL,                          -- References office.office_id
    `counter_name` VARCHAR(100) NOT NULL,              -- e.g., 'Window 1', 'Counter A', 'Desk 2'
    `assigned_user_id` INT DEFAULT NULL,               -- FK to users.id (staff currently logged in)
    `flag_status` INT NOT NULL DEFAULT 0,              -- 0 = active, 1 = inactive
    
    CONSTRAINT `fk_counter_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    INDEX `idx_office_counter` (`office_id`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Master Queue Tickets & Digital Logbook Record
CREATE TABLE IF NOT EXISTS `queue_tickets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Ticket Identification
    `ticket_number` VARCHAR(30) NOT NULL,             -- Formatted number (e.g., OAA-001, DCI-015)
    `queue_sequence` INT NOT NULL,                    -- Daily incremental number per office (1, 2, 3...)
    `queue_date` DATE NOT NULL,                        -- Date of queue issuance
    
    -- Visitor Information Snapshot
    `visitor_type` ENUM('STUDENT', 'EMPLOYEE', 'GUEST') NOT NULL,
    `reference_id` VARCHAR(50) DEFAULT NULL,          -- student_id or employee_id
    `user_id` INT DEFAULT NULL,                        -- FK to users.id if registered user
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `suffix` VARCHAR(10) DEFAULT NULL,
    `sex` VARCHAR(20) DEFAULT NULL,
    `contact_no` VARCHAR(50) DEFAULT NULL,
    
    -- Client-Specific Metadata Snapshot
    `program_code` VARCHAR(20) DEFAULT NULL,          -- Student specific
    `year_level` INT DEFAULT NULL,                    -- Student specific
    `department_id` INT DEFAULT 0,                    -- Employee / Student specific
    `position` VARCHAR(100) DEFAULT NULL,              -- Employee specific
    
    -- Relational Service Selection
    `office_id` INT NOT NULL,                          -- References office.office_id
    `transaction_type_id` INT NOT NULL,                -- References transaction_types.id
    `purpose_details` VARCHAR(255) DEFAULT NULL,      -- Additional details entered at kiosk
    `data_consent` TINYINT(1) NOT NULL DEFAULT 0,      -- Privacy compliance
    
    -- Queue Lifecycle State
    `status` ENUM('WAITING', 'CALLED', 'SERVING', 'COMPLETED', 'SKIPPED', 'CANCELLED') DEFAULT 'WAITING',
    `counter_id` INT DEFAULT NULL,                    -- Counter where ticket is served
    `served_by_user_id` INT DEFAULT NULL,              -- Staff member who handled the queue
    
    -- Lifecycle Timestamps
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Check-in / Ticket issuance time
    `called_at` DATETIME DEFAULT NULL,                        -- When ticket was called
    `served_at` DATETIME DEFAULT NULL,                        -- When transaction started
    `completed_at` DATETIME DEFAULT NULL,                      -- When transaction finished
    
    -- Foreign Keys
    CONSTRAINT `fk_queue_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`),
    CONSTRAINT `fk_queue_trans` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types`(`id`),
    CONSTRAINT `fk_queue_counter` FOREIGN KEY (`counter_id`) REFERENCES `counters`(`id`),
    
    -- High-Performance Indexes
    INDEX `idx_daily_queue` (`office_id`, `queue_date`, `status`),
    INDEX `idx_ticket_lookup` (`ticket_number`),
    INDEX `idx_visitor_ref` (`visitor_type`, `reference_id`),
    INDEX `idx_date_status` (`created_at`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;