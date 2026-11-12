-- Migration: Create Transaction Detail Fields Configuration Table
-- Date: 2026-11-12
-- Target Database: e_eskuelog

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `transaction_detail_fields` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_type_id` INT(11) NOT NULL,
    `sub_concern_id` INT(11) DEFAULT NULL COMMENT 'NULL = attached directly to Primary Transaction Type',
    `detail_mode` ENUM('INPUT', 'SELECT') NOT NULL,
    `input_data_type` VARCHAR(20) DEFAULT NULL COMMENT 'CHAR, INT, VAR (for INPUT mode)',
    `separator_style` VARCHAR(30) DEFAULT NULL COMMENT 'numeric, lower_alpha, upper_alpha (for SELECT mode)',
    `field_count` INT(11) NOT NULL DEFAULT 1,
    `delimited_titles` LONGTEXT NOT NULL COMMENT 'Titles joined with :: via implode()',
    `detail_json` LONGTEXT NOT NULL COMMENT 'Full JSON configuration payload',
    `flag_status` INT(11) NOT NULL DEFAULT 0,
    `date_modify` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (`id`),
    KEY `idx_tdf_type` (`transaction_type_id`),
    KEY `idx_tdf_sub` (`sub_concern_id`),
    CONSTRAINT `fk_tdf_type` FOREIGN KEY (`transaction_type_id`) REFERENCES `transaction_types` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tdf_sub` FOREIGN KEY (`sub_concern_id`) REFERENCES `transaction_sub_concerns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

ALTER TABLE `transaction_sub_concerns` 
ADD COLUMN `estimated_minutes` INT(11) NOT NULL DEFAULT 5 AFTER `sub_concern_name`;