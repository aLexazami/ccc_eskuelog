-- Migration: Window (counter) assignment for Transaction Services
-- Date: 2026-11-15
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Primary transaction service -> Window (only used when the service has NO sub-transactions)
ALTER TABLE `transaction_types`
    ADD COLUMN IF NOT EXISTS `counter_id` INT DEFAULT NULL AFTER `estimated_minutes`,
    ADD INDEX `idx_trans_counter` (`counter_id`),
    ADD CONSTRAINT `fk_trans_counter` FOREIGN KEY (`counter_id`)
        REFERENCES `counters`(`id`) ON DELETE SET NULL;

-- 2. Sub-transaction -> Window (each sub-transaction has its own)
ALTER TABLE `transaction_sub_concerns`
    ADD COLUMN IF NOT EXISTS `counter_id` INT DEFAULT NULL AFTER `estimated_minutes`,
    ADD INDEX `idx_sub_counter` (`counter_id`),
    ADD CONSTRAINT `fk_sub_counter` FOREIGN KEY (`counter_id`)
        REFERENCES `counters`(`id`) ON DELETE SET NULL;

COMMIT;