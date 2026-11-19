<?php
// src/Common/activity_logger.php

if (!function_exists('record_activity_log')) {
    /**
     * Inserts an audit trail record into `activity_log`.
     *
     * @param \mysqli $db_connect
     * @param int     $user_id
     * @param string  $action_text
     * @param string  $user_level  e.g., ADMIN, ADMIN_STAFF, STATION_ADMIN, USER, SYSTEM
     * @param int     $system_id   Default 5 (E-SKUELOG)
     * @param string  $session_id  Defaults to current PHP session_id()
     */
    function record_activity_log(
        \mysqli $db_connect,
        int $user_id,
        string $action_text,
        string $user_level = 'USER',
        int $system_id = 5,
        string $session_id = ''
    ): void {
        try {
            if ($session_id === '') {
                $session_id = session_id() ?: 'NO_SESSION';
            }
            $user_level = strtoupper(trim($user_level)) ?: 'USER';

            $stmt = $db_connect->prepare("
                INSERT INTO activity_log (user_id, date_log, action, session_id, user_level, system_id)
                VALUES (?, NOW(), ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param("isssi", $user_id, $action_text, $session_id, $user_level, $system_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (\Throwable $e) {
            // Silently log to php-error.log so audit logging never interrupts queue operations
            error_log('[ActivityLog Error] ' . $e->getMessage());
        }
    }
}