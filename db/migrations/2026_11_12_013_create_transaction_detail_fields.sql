-- Migration: Create Transaction Detail Fields Configuration Table
-- Date: 2026-11-12
-- Target Database: e_eskuelog

START TRANSACTION;


-- =========================================================
-- E-ESKUELOG: Transaction Detail Configuration
-- Three-table normalized structure
-- =========================================================

-- 1. CONFIGURATION HEADER
CREATE TABLE IF NOT EXISTS `transaction_detail_fields` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `transaction_type_id` INT(11) NOT NULL,
    `sub_concern_id` INT(11) DEFAULT NULL
        COMMENT 'NULL = attached directly to Primary Transaction Type',
    `flag_status` TINYINT(1) NOT NULL DEFAULT 0,
    `date_modify` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP()
        ON UPDATE CURRENT_TIMESTAMP(),

    PRIMARY KEY (`id`),
    KEY `idx_tdf_type` (`transaction_type_id`),
    KEY `idx_tdf_sub` (`sub_concern_id`),

    CONSTRAINT `fk_tdf_type`
        FOREIGN KEY (`transaction_type_id`)
        REFERENCES `transaction_types` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_tdf_sub`
        FOREIGN KEY (`sub_concern_id`)
        REFERENCES `transaction_sub_concerns` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;


-- 2. INDIVIDUAL DETAIL FIELD ITEMS
CREATE TABLE IF NOT EXISTS `transaction_detail_field_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `detail_field_id` INT(11) NOT NULL,
    `field_order` INT(11) UNSIGNED NOT NULL DEFAULT 1,

    `detail_mode` ENUM('INPUT', 'SELECT') NOT NULL,

    `input_data_type` VARCHAR(20) DEFAULT NULL
        COMMENT 'CHAR, INT, VAR; applicable to INPUT',

    `separator_style` VARCHAR(30) DEFAULT NULL
        COMMENT 'numeric, lower_alpha, upper_alpha; applicable to SELECT',

    `field_title` VARCHAR(100) NOT NULL,

    `flag_status` TINYINT(1) NOT NULL DEFAULT 0,
    `date_modify` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP()
        ON UPDATE CURRENT_TIMESTAMP(),

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_tdfi_order` (`detail_field_id`, `field_order`),
    KEY `idx_tdfi_header` (`detail_field_id`),

    CONSTRAINT `fk_tdfi_header`
        FOREIGN KEY (`detail_field_id`)
        REFERENCES `transaction_detail_fields` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;


-- 3. SELECT FIELD CHOICES
CREATE TABLE IF NOT EXISTS `transaction_detail_field_choices` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `field_item_id` INT(11) NOT NULL,
    `choice_order` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `choice_value` VARCHAR(100) NOT NULL,

    `date_modify` DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP()
        ON UPDATE CURRENT_TIMESTAMP(),

    PRIMARY KEY (`id`),

    UNIQUE KEY `uq_tdfc_order` (`field_item_id`, `choice_order`),
    UNIQUE KEY `uq_tdfc_value` (`field_item_id`, `choice_value`),
    KEY `idx_tdfc_item` (`field_item_id`),

    CONSTRAINT `fk_tdfc_item`
        FOREIGN KEY (`field_item_id`)
        REFERENCES `transaction_detail_field_items` (`id`)
        ON DELETE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;


COMMIT;

ALTER TABLE `transaction_sub_concerns` 
ADD COLUMN `estimated_minutes` INT(11) NOT NULL DEFAULT 5 AFTER `sub_concern_name`;

ALTER TABLE transaction_types
ADD COLUMN date_modify DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE transaction_types SET date_modify = created_at;