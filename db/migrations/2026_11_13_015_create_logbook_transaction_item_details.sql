-- Migration: Store Visitor Responses for Dynamic Transaction Detail Fields
-- Date: 2026-11-13
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `logbook_transaction_item_details` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `logbook_item_id` INT(11) NOT NULL,
    `field_item_id` INT(11) DEFAULT NULL,
    `field_title` VARCHAR(100) NOT NULL,
    `detail_mode` ENUM('INPUT', 'SELECT') NOT NULL DEFAULT 'INPUT',
    `choice_id` INT(11) DEFAULT NULL,
    `answer_value` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    KEY `idx_ltid_item` (`logbook_item_id`),
    KEY `idx_ltid_field` (`field_item_id`),
    CONSTRAINT `fk_ltid_item`
        FOREIGN KEY (`logbook_item_id`)
        REFERENCES `logbook_transaction_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_ltid_field`
        FOREIGN KEY (`field_item_id`)
        REFERENCES `transaction_detail_field_items` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;