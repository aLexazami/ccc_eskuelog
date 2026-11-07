-- Migration: Create Transaction Types, Counters, and Digital Logbooks
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Transaction Types / Services per Office
CREATE TABLE IF NOT EXISTS `transaction_types` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_id` INT NOT NULL,                          -- References office.office_id
    `transaction_name` VARCHAR(150) NOT NULL,          -- e.g., 'Transcript Request', 'ID Validation'
    `estimated_minutes` INT DEFAULT 5,                 -- For queue wait time estimation
    `flag_status` INT NOT NULL DEFAULT 0,              -- 0 = active, 1 = disabled
    `date_modify` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT `fk_trans_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    INDEX `idx_office_trans` (`office_id`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Service Counters / Windows per Office
CREATE TABLE IF NOT EXISTS `counters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `office_id` INT NOT NULL,                          -- References office.office_id
    `counter_name` VARCHAR(100) NOT NULL,              -- e.g., 'Window 1', 'Counter A', 'Desk 2'
    `assigned_user_id` INT DEFAULT NULL,               -- FK to users.id (staff currently logged in)
    `flag_status` INT NOT NULL DEFAULT 0,              -- 0 = active, 1 = inactive
    
    -- Foreign Key Constraints
    CONSTRAINT `fk_counter_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`) ON DELETE CASCADE,
    
    -- Unique Constraint (Prevents duplicate counter names per office)
    UNIQUE KEY `uk_office_counter_name` (`office_id`, `counter_name`),
    
    -- Indexes
    INDEX `idx_office_counter` (`office_id`, `flag_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Digital Logbook Master Table
CREATE TABLE IF NOT EXISTS `logbooks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Visitor Classification
    `visitor_type` ENUM('STUDENT', 'EMPLOYEE', 'GUEST') NOT NULL,
    `reference_id` VARCHAR(50) DEFAULT NULL,          -- student_id or employee_id
    `user_id` INT DEFAULT NULL,                        -- FK to users.id
    
    -- Personal Snapshot
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `suffix` VARCHAR(10) DEFAULT NULL,
    `post_nominal` VARCHAR(255) DEFAULT NULL,
    `sex` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(150) DEFAULT NULL,
    `contact_no` VARCHAR(50) DEFAULT NULL,
    
    -- Academic / Metadata Snapshot
    `program_id` INT DEFAULT NULL,                     -- References programs.program_id / student.program_id
    `program_code` VARCHAR(20) DEFAULT NULL,           -- Snapshot code (e.g., BSIT)
    `major` VARCHAR(100) DEFAULT NULL,                 -- Snapshot major (e.g., Web Development)
    `year_level` INT DEFAULT NULL,                     -- Student specific
    `department_id` INT DEFAULT 0,                     -- Employee / Student specific
    
    -- Service Selection & Concerns
    `office_id` INT NOT NULL,                          -- FK to office.office_id
    `transaction_type_id` INT NOT NULL,                -- FK to transaction_types.id
    `sub_concern_id` INT DEFAULT NULL,                 -- Optional specific concern detail ID
    
    -- Priority / Special Assistance
    `is_priority` TINYINT(1) NOT NULL DEFAULT 0,       -- 0 = Regular, 1 = Priority Ticket
    `priority_type` VARCHAR(50) DEFAULT NULL,          -- SENIOR, PWD, PREGNANT, OTHER
    
    -- Consent & Timestamps
    `data_consent` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Constraints
    CONSTRAINT `fk_log_office` FOREIGN KEY (`office_id`) REFERENCES `office`(`office_id`),
    CONSTRAINT `fk_log_trans` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types`(`id`),
    
    -- Indexes
    INDEX `idx_log_visitor` (`visitor_type`, `reference_id`),
    INDEX `idx_log_program` (`program_id`),
    INDEX `idx_log_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;