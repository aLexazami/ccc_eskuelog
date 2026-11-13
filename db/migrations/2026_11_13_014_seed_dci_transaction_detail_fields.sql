-- Migration: Seed DCI (office_id = 10) Primary & Sub-Transaction Detail Fields
-- Date: 2026-11-13
-- Target Database: e_eskuelog

START TRANSACTION;

-- 1. Resolve Primary Transaction IDs under DCI (office_id = 10)
SET @adv_type_id = (
    SELECT `id` FROM `transaction_types`
    WHERE `office_id` = 10 AND `transaction_name` = 'Academic Advising & Subject Consultation'
    LIMIT 1
);

SET @cap_type_id = (
    SELECT `id` FROM `transaction_types`
    WHERE `office_id` = 10 AND `transaction_name` = 'Capstone / Thesis Concern & Adviser Assignment'
    LIMIT 1
);

-- 2. Resolve the 3 Existing Sub-Transaction IDs under Capstone / Thesis Concern
SET @sub1_id = (
    SELECT `id` FROM `transaction_sub_concerns`
    WHERE `transaction_type_id` = @cap_type_id AND `sub_concern_name` = 'Capstone Proposal Submission'
    LIMIT 1
);

SET @sub2_id = (
    SELECT `id` FROM `transaction_sub_concerns`
    WHERE `transaction_type_id` = @cap_type_id AND `sub_concern_name` = 'Adviser Re-assignment Request'
    LIMIT 1
);

SET @sub3_id = (
    SELECT `id` FROM `transaction_sub_concerns`
    WHERE `transaction_type_id` = @cap_type_id AND `sub_concern_name` = 'Defense Panel Schedule & Requirements'
    LIMIT 1
);

-- Ensure sub-transactions have realistic estimated durations
UPDATE `transaction_sub_concerns` SET `estimated_minutes` = 15 WHERE `id` = @sub1_id;
UPDATE `transaction_sub_concerns` SET `estimated_minutes` = 10 WHERE `id` = @sub2_id;
UPDATE `transaction_sub_concerns` SET `estimated_minutes` = 20 WHERE `id` = @sub3_id;

-- 3. Clean existing detail configs for these two DCI transactions (idempotent)
DELETE FROM `transaction_detail_fields`
WHERE `transaction_type_id` IN (@adv_type_id, @cap_type_id);

-- =========================================================================
-- PART A: PRIMARY TRANSACTION WITH DIRECT DETAILS (sub_concern_id = NULL)
-- Service: Academic Advising & Subject Consultation
-- =========================================================================
INSERT INTO `transaction_detail_fields` (`transaction_type_id`, `sub_concern_id`, `flag_status`)
VALUES (@adv_type_id, NULL, 0);
SET @tdf_adv = LAST_INSERT_ID();

-- Field 1: INPUT - VAR
INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_adv, 1, 'INPUT', 'VAR', NULL, 'Course / Subject Code', 0);

-- Field 2: INPUT - INT
INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_adv, 2, 'INPUT', 'INT', NULL, 'Total Units to Enroll', 0);

-- Field 3: SELECT - numeric (1., 2., 3.)
INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_adv, 3, 'SELECT', NULL, 'numeric', 'Consultation Purpose', 0);
SET @item_adv_3 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_choices` (`field_item_id`, `choice_order`, `choice_value`) VALUES
    (@item_adv_3, 1, 'Overload Request'),
    (@item_adv_3, 2, 'Prerequisite Waiver'),
    (@item_adv_3, 3, 'Curriculum Checklist Review');

-- =========================================================================
-- PART B: PRIMARY TRANSACTION WITH 3 SUB-TRANSACTIONS + DETAILS
-- Service: Capstone / Thesis Concern & Adviser Assignment
-- =========================================================================

-- --- Sub-Transaction 1: Capstone Proposal Submission (INPUT CHAR + SELECT lower_alpha) ---
INSERT INTO `transaction_detail_fields` (`transaction_type_id`, `sub_concern_id`, `flag_status`)
VALUES (@cap_type_id, @sub1_id, 0);
SET @tdf_sub1 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub1, 1, 'INPUT', 'CHAR', NULL, 'Proposed Capstone Title', 0);

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub1, 2, 'SELECT', NULL, 'lower_alpha', 'Capstone Track / Specialization', 0);
SET @item_sub1_2 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_choices` (`field_item_id`, `choice_order`, `choice_value`) VALUES
    (@item_sub1_2, 1, 'Web & Mobile Application Development'),
    (@item_sub1_2, 2, 'Data Science & Artificial Intelligence'),
    (@item_sub1_2, 3, 'Network & Cybersecurity');

-- --- Sub-Transaction 2: Adviser Re-assignment Request (INPUT VAR + SELECT upper_alpha) ---
INSERT INTO `transaction_detail_fields` (`transaction_type_id`, `sub_concern_id`, `flag_status`)
VALUES (@cap_type_id, @sub2_id, 0);
SET @tdf_sub2 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub2, 1, 'INPUT', 'VAR', NULL, 'Group Reference Code', 0);

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub2, 2, 'SELECT', NULL, 'upper_alpha', 'Reason for Re-assignment', 0);
SET @item_sub2_2 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_choices` (`field_item_id`, `choice_order`, `choice_value`) VALUES
    (@item_sub2_2, 1, 'Topic Scope Change'),
    (@item_sub2_2, 2, 'Adviser Schedule Conflict'),
    (@item_sub2_2, 3, 'Adviser Endorsement');

-- --- Sub-Transaction 3: Defense Panel Schedule & Requirements (INPUT INT + SELECT numeric) ---
INSERT INTO `transaction_detail_fields` (`transaction_type_id`, `sub_concern_id`, `flag_status`)
VALUES (@cap_type_id, @sub3_id, 0);
SET @tdf_sub3 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub3, 1, 'INPUT', 'INT', NULL, 'Number of Group Members', 0);

INSERT INTO `transaction_detail_field_items`
    (`detail_field_id`, `field_order`, `detail_mode`, `input_data_type`, `separator_style`, `field_title`, `flag_status`)
VALUES
    (@tdf_sub3, 2, 'SELECT', NULL, 'numeric', 'Defense Stage', 0);
SET @item_sub3_2 = LAST_INSERT_ID();

INSERT INTO `transaction_detail_field_choices` (`field_item_id`, `choice_order`, `choice_value`) VALUES
    (@item_sub3_2, 1, 'Title Defense'),
    (@item_sub3_2, 2, 'Pre-Oral Defense'),
    (@item_sub3_2, 3, 'Final Oral Defense');

COMMIT;