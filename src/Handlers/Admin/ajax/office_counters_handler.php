<?php
// src/Handlers/Admin/ajax/office_counters_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!$db_connect) {
    echo json_encode(['msg_status' => false, 'msg_response' => 'Database connection unavailable.']);
    exit();
}

// Resolve logged-in Admin User ID for `assigned_by_user_id` tracking
$admin_user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $admin_user_id = (int)($session_class->getValue('user_id') ?? $session_class->getValue('id') ?? $session_class->getValue('sys_user_id') ?? 0);
}
if ($admin_user_id <= 0) {
    $admin_user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

// Decode raw JSON payload if sent via fetch/Tabulator JSON body stream
$rawInput = file_get_contents('php://input');
if (!empty($rawInput)) {
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $_POST = array_merge($_POST, $jsonData);
        $_REQUEST = array_merge($_REQUEST, $jsonData);
    }
}

/**
 * Check if a staff member is already assigned to another counter.
 * Returns an associative array with office_name and counter_name if busy, or null if available.
 */
function findExistingStaffAssignment(mysqli $db, int $userId, int $excludeCounterId = 0): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $sql = "
        SELECT c.id AS counter_id, c.counter_name, o.office_name, o.office_code
        FROM counters c
        INNER JOIN office o ON o.office_id = c.office_id
        WHERE c.assigned_user_id = ?
          AND c.id != ?
        LIMIT 1
    ";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param("ii", $userId, $excludeCounterId);
    $stmt->execute();
    $conflict = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $conflict ?: null;
}

$action = $_REQUEST['action'] ?? $_REQUEST['actionSubmit'] ?? $_REQUEST['actionUpdate'] ?? '';

switch ($action) {

    case 'fetch_counters':
        $office_id = (int)($_REQUEST['office_id'] ?? 0);
        $counters  = [];

        $whereClause = "WHERE o.flag_status = 0";
        if ($office_id > 0) {
            $whereClause .= " AND c.office_id = " . $office_id;
        }

        // Only join active staff_assignments (sa.flag_status = 0) and match c.assigned_user_id
        // so deactivated/stale rows never ghost into the UI
        $query = "
            SELECT 
                c.id AS counter_id,
                c.office_id,
                c.counter_name,
                c.flag_status,
                o.office_name,
                o.office_code,
                COALESCE(c.assigned_user_id, 0) AS assigned_user_id,
                CONCAT(u.first_name, ' ', IFNULL(u.last_name, '')) AS assigned_staff_name,
                sa.shift_status
            FROM counters c
            INNER JOIN office o ON c.office_id = o.office_id
            LEFT JOIN users u ON u.id = c.assigned_user_id
            LEFT JOIN staff_assignments sa 
                ON sa.counter_id = c.id 
               AND sa.user_id = c.assigned_user_id 
               AND sa.flag_status = 0
            {$whereClause}
            ORDER BY o.office_name ASC, c.counter_name ASC
        ";

        if ($result = $db_connect->query($query)) {
            while ($row = $result->fetch_assoc()) {
                $row['counter_id']          = (int)$row['counter_id'];
                $row['office_id']           = (int)$row['office_id'];
                $row['assigned_user_id']    = (int)($row['assigned_user_id'] ?? 0);
                $row['flag_status']         = (int)$row['flag_status'];
                $row['assigned_staff_name'] = $row['assigned_user_id'] > 0 ? trim($row['assigned_staff_name'] ?? '') : '';
                $counters[] = $row;
            }
            $result->free();
            echo json_encode(['success' => true, 'data' => $counters]);
            exit();
        }

        echo json_encode(['success' => false, 'data' => [], 'message' => 'Failed to fetch service counter records.']);
        exit();

    case 'submitCounter':
    case 'save_counter':
        $office_id        = (int)($_POST['office_id'] ?? 0);
        $counter_name     = trim($_POST['counter_name'] ?? '');
        $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
        $flag_status      = (int)($_POST['flag_status'] ?? 0);

        if ($office_id <= 0 || empty($counter_name)) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Office location and counter name are required.']);
            exit();
        }

        // 1. Check for duplicate counter name in the same office
        $stmtCheck = $db_connect->prepare("SELECT id FROM counters WHERE office_id = ? AND LOWER(counter_name) = LOWER(?) LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("is", $office_id, $counter_name);
            $stmtCheck->execute();
            $existing = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if ($existing) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'A service counter named "' . htmlspecialchars($counter_name) . '" already exists for this office.'
                ]);
                exit();
            }
        }

        // 2. Prevent assigning a staff member who is already assigned to another counter
        if ($assigned_user_id > 0) {
            $conflict = findExistingStaffAssignment($db_connect, $assigned_user_id, 0);
            if ($conflict) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'This staff member is already assigned to ' . htmlspecialchars($conflict['office_name']) . ' (' . htmlspecialchars($conflict['counter_name']) . '). Please unassign them there first.'
                ]);
                exit();
            }
        }

        $db_connect->begin_transaction();

        try {
            if ($assigned_user_id > 0) {
                $stmt = $db_connect->prepare("INSERT INTO counters (office_id, counter_name, assigned_user_id, flag_status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isii", $office_id, $counter_name, $assigned_user_id, $flag_status);
            } else {
                $stmt = $db_connect->prepare("INSERT INTO counters (office_id, counter_name, assigned_user_id, flag_status) VALUES (?, ?, NULL, ?)");
                $stmt->bind_param("isi", $office_id, $counter_name, $flag_status);
            }

            if (!$stmt || !$stmt->execute()) {
                throw new Exception('Failed to create service counter in database.');
            }
            $new_counter_id = (int)$stmt->insert_id;
            $stmt->close();

            if ($assigned_user_id > 0 && $new_counter_id > 0) {
                $sa_flag_status  = ($flag_status === 0) ? 0 : 1;
                $sa_shift_status = ($flag_status === 0) ? 'ON_DUTY' : 'OFF_DUTY';
                $assigner        = $admin_user_id > 0 ? $admin_user_id : null;

                $stmtSa = $db_connect->prepare("
                    INSERT INTO staff_assignments (user_id, office_id, counter_id, shift_status, flag_status, assigned_by_user_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        office_id = VALUES(office_id),
                        counter_id = VALUES(counter_id),
                        shift_status = VALUES(shift_status),
                        flag_status = VALUES(flag_status),
                        assigned_by_user_id = VALUES(assigned_by_user_id)
                ");
                $stmtSa->bind_param("iiisii", $assigned_user_id, $office_id, $new_counter_id, $sa_shift_status, $sa_flag_status, $assigner);
                if (!$stmtSa->execute()) {
                    throw new Exception('Failed to synchronize staff assignment.');
                }
                $stmtSa->close();
            }

            $db_connect->commit();
            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter created successfully.']);
            exit();
        } catch (Throwable $e) {
            $db_connect->rollback();
            echo json_encode(['msg_status' => false, 'msg_response' => $e->getMessage()]);
            exit();
        }

    case 'submitUpdateCounter':
    case 'update_counter':
        $counter_id       = (int)($_POST['counter_id'] ?? 0);
        $office_id        = (int)($_POST['office_id'] ?? 0);
        $counter_name     = trim($_POST['counter_name'] ?? '');
        $assigned_user_id = (int)($_POST['assigned_user_id'] ?? 0);
        $flag_status      = (int)($_POST['flag_status'] ?? 0);

        if ($counter_id <= 0) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid Counter ID provided.']);
            exit();
        }

        if ($office_id <= 0 || empty($counter_name)) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Office location and counter name are required.']);
            exit();
        }

        // 1. Check for duplicate counter name in the same office (excluding current counter)
        $stmtCheck = $db_connect->prepare("SELECT id FROM counters WHERE office_id = ? AND LOWER(counter_name) = LOWER(?) AND id != ? LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("isi", $office_id, $counter_name, $counter_id);
            $stmtCheck->execute();
            $existing = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if ($existing) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'Another service counter named "' . htmlspecialchars($counter_name) . '" already exists in this office.'
                ]);
                exit();
            }
        }

        // 2. Prevent assigning a staff member who is already assigned to another counter
        if ($assigned_user_id > 0) {
            $conflict = findExistingStaffAssignment($db_connect, $assigned_user_id, $counter_id);
            if ($conflict) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'This staff member is already assigned to ' . htmlspecialchars($conflict['office_name']) . ' (' . htmlspecialchars($conflict['counter_name']) . '). Please unassign them there first.'
                ]);
                exit();
            }
        }

        $db_connect->begin_transaction();

        try {
            if ($assigned_user_id > 0) {
                $stmt = $db_connect->prepare("UPDATE counters SET office_id = ?, counter_name = ?, assigned_user_id = ?, flag_status = ? WHERE id = ?");
                $stmt->bind_param("isiii", $office_id, $counter_name, $assigned_user_id, $flag_status, $counter_id);
            } else {
                $stmt = $db_connect->prepare("UPDATE counters SET office_id = ?, counter_name = ?, assigned_user_id = NULL, flag_status = ? WHERE id = ?");
                $stmt->bind_param("isii", $office_id, $counter_name, $flag_status, $counter_id);
            }

            if (!$stmt || !$stmt->execute()) {
                throw new Exception('Failed to update service counter in database.');
            }
            $stmt->close();

            if ($assigned_user_id > 0) {
                // Clear any previous user that was assigned to this counter
                $stmtClearOther = $db_connect->prepare("UPDATE staff_assignments SET flag_status = 1, shift_status = 'OFF_DUTY', counter_id = NULL WHERE counter_id = ? AND user_id != ?");
                $stmtClearOther->bind_param("ii", $counter_id, $assigned_user_id);
                $stmtClearOther->execute();
                $stmtClearOther->close();

                $sa_flag_status  = ($flag_status === 0) ? 0 : 1;
                $sa_shift_status = ($flag_status === 0) ? 'ON_DUTY' : 'OFF_DUTY';
                $assigner        = $admin_user_id > 0 ? $admin_user_id : null;

                $stmtSa = $db_connect->prepare("
                    INSERT INTO staff_assignments (user_id, office_id, counter_id, shift_status, flag_status, assigned_by_user_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        office_id = VALUES(office_id),
                        counter_id = VALUES(counter_id),
                        shift_status = VALUES(shift_status),
                        flag_status = VALUES(flag_status),
                        assigned_by_user_id = VALUES(assigned_by_user_id)
                ");
                $stmtSa->bind_param("iiisii", $assigned_user_id, $office_id, $counter_id, $sa_shift_status, $sa_flag_status, $assigner);
                if (!$stmtSa->execute()) {
                    throw new Exception('Failed to synchronize staff assignment.');
                }
                $stmtSa->close();
            } else {
                // Set to Unassigned (0): clear counter_id and mark OFF_DUTY
                $stmtUnassign = $db_connect->prepare("UPDATE staff_assignments SET flag_status = 1, shift_status = 'OFF_DUTY', counter_id = NULL WHERE counter_id = ?");
                $stmtUnassign->bind_param("i", $counter_id);
                $stmtUnassign->execute();
                $stmtUnassign->close();
            }

            $db_connect->commit();
            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter updated successfully.']);
            exit();
        } catch (Throwable $e) {
            $db_connect->rollback();
            echo json_encode(['msg_status' => false, 'msg_response' => $e->getMessage()]);
            exit();
        }

    case 'delete_counter':
    case 'deleteCounter':
        $counter_id = (int)($_POST['counter_id'] ?? 0);

        if ($counter_id <= 0) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid Counter ID provided.']);
            exit();
        }

        $db_connect->begin_transaction();

        try {
            $stmtSaClean = $db_connect->prepare("DELETE FROM staff_assignments WHERE counter_id = ?");
            $stmtSaClean->bind_param("i", $counter_id);
            $stmtSaClean->execute();
            $stmtSaClean->close();

            $stmtDel = $db_connect->prepare("DELETE FROM counters WHERE id = ?");
            $stmtDel->bind_param("i", $counter_id);
            if (!$stmtDel->execute()) {
                throw new Exception('Failed to delete service counter from database.');
            }
            $stmtDel->close();

            $db_connect->commit();
            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter deleted successfully.']);
            exit();
        } catch (Throwable $e) {
            $db_connect->rollback();
            echo json_encode(['msg_status' => false, 'msg_response' => $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid action specified.']);
        exit();
}