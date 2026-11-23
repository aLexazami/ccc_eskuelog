-- ======================================================================
-- Migration: Add Composite Index for Active Staff Ticket Lookups
-- Date: 2026-11-23
-- Target Database: e_eskuelog
-- ======================================================================

START TRANSACTION;

ALTER TABLE `queue_tickets`
ADD INDEX `idx_queue_staff_active` (`office_id`, `counter_id`, `served_by_user_id`, `queue_date`, `status`);

COMMIT;