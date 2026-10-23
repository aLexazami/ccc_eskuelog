-- Migration: Add Priority Support for Senior Citizens, PWDs, and Pregnant Visitors
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Add priority tracking columns to logbooks
ALTER TABLE `logbooks`
ADD COLUMN IF NOT EXISTS `is_priority` TINYINT(1) NOT NULL DEFAULT 0 AFTER `data_consent`,
ADD COLUMN IF NOT EXISTS `priority_type` VARCHAR(50) DEFAULT NULL AFTER `is_priority`;
-- Note on priority_type: Expected values include 'SENIOR', 'PWD', 'PREGNANT', 'OTHER'

-- 2. Add priority flag & compound index to queue_tickets for high-performance priority calling
ALTER TABLE `queue_tickets`
ADD COLUMN IF NOT EXISTS `is_priority` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
ADD INDEX `idx_queue_call` (`office_id`, `queue_date`, `status`, `is_priority`, `queue_sequence`);

COMMIT;