-- Migration: Add Sub Concern ID Foreign Key to Logbooks Table
-- Date: 2026-10-28
-- Target Database: e_eskuelog

START TRANSACTION;

-- Add sub_concern_id column to logbooks table referencing transaction_sub_concerns
ALTER TABLE `logbooks`
ADD COLUMN IF NOT EXISTS `sub_concern_id` INT DEFAULT NULL AFTER `transaction_type_id`,
ADD CONSTRAINT `fk_log_sub_trans` FOREIGN KEY (`sub_concern_id`) REFERENCES `transaction_sub_concerns`(`id`) ON DELETE SET NULL;

COMMIT;