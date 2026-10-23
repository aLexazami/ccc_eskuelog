-- Migration: Add prefix_code column to office table and seed all office records
-- Date: 2026-10-23
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Structural Change: Add column safely if it doesn't exist
ALTER TABLE `office`
ADD COLUMN IF NOT EXISTS `prefix_code` VARCHAR(10) DEFAULT NULL AFTER `office_code`;

-- 2. Data Updates: Populate prefix_code for all existing offices
UPDATE `office` SET `prefix_code` = 'OCP'   WHERE `office_code` = 'OCP';
UPDATE `office` SET `prefix_code` = 'OAA'  WHERE `office_code` = 'OVPAA';
UPDATE `office` SET `prefix_code` = 'OSA'   WHERE `office_code` = 'OVPSDA';
UPDATE `office` SET `prefix_code` = 'OAF'  WHERE `office_code` = 'OVPAF';
UPDATE `office` SET `prefix_code` = 'ORE'    WHERE `office_code` = 'OVPREPQA';
UPDATE `office` SET `prefix_code` = 'CAR'   WHERE `office_code` = 'OVPCAR';
UPDATE `office` SET `prefix_code` = 'DTE'   WHERE `office_code` = 'DTE';
UPDATE `office` SET `prefix_code` = 'DAS'   WHERE `office_code` = 'DAS';
UPDATE `office` SET `prefix_code` = 'DBA'   WHERE `office_code` = 'DBA';
UPDATE `office` SET `prefix_code` = 'DCI'   WHERE `office_code` = 'DCI';
UPDATE `office` SET `prefix_code` = 'LRI'  WHERE `office_code` = 'LRIC';

COMMIT;