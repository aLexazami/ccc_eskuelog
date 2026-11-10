-- Migration: Create Logbook Transaction Items Junction Table (Multi-Concern Support)
-- Date: 2026-11-10
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `logbook_transaction_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logbook_id` INT(11) NOT NULL,
    `transaction_type_id` INT(11) DEFAULT NULL,
    `sub_concern_id` INT(11) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    KEY `idx_lti_logbook` (`logbook_id`),
    KEY `idx_lti_trans` (`transaction_type_id`),
    KEY `idx_lti_sub` (`sub_concern_id`),
    CONSTRAINT `fk_lti_logbook` FOREIGN KEY (`logbook_id`) REFERENCES `logbooks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_lti_trans` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_lti_sub` FOREIGN KEY (`sub_concern_id`) REFERENCES `transaction_sub_concerns` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;