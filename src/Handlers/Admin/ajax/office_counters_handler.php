<?php
// src/Handlers/Admin/ajax/office_counters_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

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

$action = $_REQUEST['action'] ?? $_REQUEST['actionSubmit'] ?? $_REQUEST['actionUpdate'] ?? '';

switch ($action) {

    case 'fetch_counters':
        $office_id = (int)($_REQUEST['office_id'] ?? 0);
        $counters  = [];

        $whereClause = "WHERE o.flag_status = 0";
        if ($office_id > 0) {
            $whereClause .= " AND c.office_id = " . $office_id;
        }

        // Priority lookup for assigned staff: Join staff_assignments first, fallback to counters.assigned_user_id
        $query = "
            SELECT 
                c.id AS counter_id,
                c.office_id,
                c.counter_name,
                c.flag_status,
                o.office_name,
                o.office_code,
                COALESCE(sa.user_id, c.assigned_user_id, 0) AS assigned_user_id,
                CONCAT(u.first_name, ' ', IFNULL(u.last_name, '')) AS assigned_staff_name,
                sa.shift_status
            FROM counters c
            INNER JOIN office o ON c.office_id = o.office_id
            LEFT JOIN staff_assignments sa ON sa.counter_id = c.id AND sa.flag_status = 0
            LEFT JOIN users u ON u.id = COALESCE(sa.user_id, c.assigned_user_id)
            {$whereClause}
            ORDER BY o.office_name ASC, c.counter_name ASC
        ";

        if ($result = $db_connect->query($query)) {
            while ($row = $result->fetch_assoc()) {
                $row['counter_id']          = (int)$row['counter_id'];
                $row['office_id']           = (int)$row['office_id'];
                $row['assigned_user_id']    = (int)($row['assigned_user_id'] ?? 0);
                $row['flag_status']         = (int)$row['flag_status'];
                $row['assigned_staff_name'] = trim($row['assigned_staff_name'] ?? '');
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

        // CHECK FOR DUPLICATE COUNTER NAME IN THE SAME OFFICE
        $stmtCheck = $db_connect->prepare("SELECT id FROM counters WHERE office_id = ? AND LOWER(counter_name) = LOWER(?) LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("is", $office_id, $counter_name);
            $stmtCheck->execute();
            $existing = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();
            
            if ($existing) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'A service counter named "' . htmlspecialchars($counter_name) . '" already exists for this office. Please update the existing counter instead.']);
                exit();
            }
        }

        // 1. If assigned_user_id is selected, unassign this user from any previous counter
        if ($assigned_user_id > 0) {
            $stmtClearPrev = $db_connect->prepare("UPDATE counters SET assigned_user_id = NULL WHERE assigned_user_id = ?");
            if ($stmtClearPrev) {
                $stmtClearPrev->bind_param("i", $assigned_user_id);
                $stmtClearPrev->execute();
                $stmtClearPrev->close();
            }

            $query = "INSERT INTO counters (office_id, counter_name, assigned_user_id, flag_status) VALUES (?, ?, ?, ?)";
            $stmt  = $db_connect->prepare($query);
            if ($stmt) {
                $stmt->bind_param("isii", $office_id, $counter_name, $assigned_user_id, $flag_status);
            }
        } else {
            $query = "INSERT INTO counters (office_id, counter_name, assigned_user_id, flag_status) VALUES (?, ?, NULL, ?)";
            $stmt  = $db_connect->prepare($query);
            if ($stmt) {
                $stmt->bind_param("isi", $office_id, $counter_name, $flag_status);
            }
        }

        if ($stmt && $stmt->execute()) {
            $new_counter_id = $stmt->insert_id;
            $stmt->close();

            // 2. Synchronize with staff_assignments table
            if ($assigned_user_id > 0 && $new_counter_id > 0) {
                $stmtSa = $db_connect->prepare("
                    INSERT INTO staff_assignments (user_id, office_id, counter_id, shift_status, flag_status, assigned_by_user_id)
                    VALUES (?, ?, ?, 'ON_DUTY', 0, ?)
                    ON DUPLICATE KEY UPDATE 
                        office_id = VALUES(office_id),
                        counter_id = VALUES(counter_id),
                        shift_status = VALUES(shift_status),
                        flag_status = VALUES(flag_status),
                        assigned_by_user_id = VALUES(assigned_by_user_id)
                ");
                if ($stmtSa) {
                    $assigner = $admin_user_id > 0 ? $admin_user_id : null;
                    $stmtSa->bind_param("iiii", $assigned_user_id, $office_id, $new_counter_id, $assigner);
                    $stmtSa->execute();
                    $stmtSa->close();
                }
            }

            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter created successfully.']);
            exit();
        }
        if ($stmt) $stmt->close();

        echo json_encode(['msg_status' => false, 'msg_response' => 'Failed to create service counter in database.']);
        exit();

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

        // CHECK FOR DUPLICATE COUNTER NAME IN THE SAME OFFICE (EXCLUDING CURRENT COUNTER)
        $stmtCheck = $db_connect->prepare("SELECT id FROM counters WHERE office_id = ? AND LOWER(counter_name) = LOWER(?) AND id != ? LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("isi", $office_id, $counter_name, $counter_id);
            $stmtCheck->execute();
            $existing = $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if ($existing) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Another service counter named "' . htmlspecialchars($counter_name) . '" already exists in this office.']);
                exit();
            }
        }

        // 1. Update counters table
        if ($assigned_user_id > 0) {
            // Unassign this user from any other counter first so there are no duplicate active assignments
            $stmtClearPrev = $db_connect->prepare("UPDATE counters SET assigned_user_id = NULL WHERE assigned_user_id = ? AND id != ?");
            if ($stmtClearPrev) {
                $stmtClearPrev->bind_param("ii", $assigned_user_id, $counter_id);
                $stmtClearPrev->execute();
                $stmtClearPrev->close();
            }

            $query = "UPDATE counters SET office_id = ?, counter_name = ?, assigned_user_id = ?, flag_status = ? WHERE id = ?";
            $stmt  = $db_connect->prepare($query);
            if ($stmt) {
                $stmt->bind_param("isiii", $office_id, $counter_name, $assigned_user_id, $flag_status, $counter_id);
            }
        } else {
            $query = "UPDATE counters SET office_id = ?, counter_name = ?, assigned_user_id = NULL, flag_status = ? WHERE id = ?";
            $stmt  = $db_connect->prepare($query);
            if ($stmt) {
                $stmt->bind_param("isii", $office_id, $counter_name, $flag_status, $counter_id);
            }
        }

        if ($stmt && $stmt->execute()) {
            $stmt->close();

            // 2. Synchronize with staff_assignments table
            if ($assigned_user_id > 0) {
                // Clear any other user's active assignment on this specific counter
                $stmtClearOther = $db_connect->prepare("UPDATE staff_assignments SET flag_status = 1 WHERE counter_id = ? AND user_id != ?");
                if ($stmtClearOther) {
                    $stmtClearOther->bind_param("ii", $counter_id, $assigned_user_id);
                    $stmtClearOther->execute();
                    $stmtClearOther->close();
                }

                // UPSERT staff_assignments for the assigned staff user
                $stmtSa = $db_connect->prepare("
                    INSERT INTO staff_assignments (user_id, office_id, counter_id, shift_status, flag_status, assigned_by_user_id)
                    VALUES (?, ?, ?, 'ON_DUTY', 0, ?)
                    ON DUPLICATE KEY UPDATE 
                        office_id = VALUES(office_id),
                        counter_id = VALUES(counter_id),
                        shift_status = 'ON_DUTY',
                        flag_status = 0,
                        assigned_by_user_id = VALUES(assigned_by_user_id)
                ");
                if ($stmtSa) {
                    $assigner = $admin_user_id > 0 ? $admin_user_id : null;
                    $stmtSa->bind_param("iiii", $assigned_user_id, $office_id, $counter_id, $assigner);
                    $stmtSa->execute();
                    $stmtSa->close();
                }
            } else {
                // If set to Unassigned (0), deactivate any staff_assignment record for this counter
                $stmtUnassign = $db_connect->prepare("UPDATE staff_assignments SET flag_status = 1 WHERE counter_id = ?");
                if ($stmtUnassign) {
                    $stmtUnassign->bind_param("i", $counter_id);
                    $stmtUnassign->execute();
                    $stmtUnassign->close();
                }
            }

            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter updated successfully.']);
            exit();
        }
        if ($stmt) $stmt->close();

        echo json_encode(['msg_status' => false, 'msg_response' => 'Failed to update service counter in database.']);
        exit();

    case 'delete_counter':
    case 'deleteCounter':
        $counter_id = (int)($_POST['counter_id'] ?? 0);

        if ($counter_id <= 0) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid Counter ID provided.']);
            exit();
        }

        // 1. Clean up active staff assignments linked to this counter first
        $stmtSaClean = $db_connect->prepare("DELETE FROM staff_assignments WHERE counter_id = ?");
        if ($stmtSaClean) {
            $stmtSaClean->bind_param("i", $counter_id);
            $stmtSaClean->execute();
            $stmtSaClean->close();
        }

        // 2. Delete counter record
        $stmtDel = $db_connect->prepare("DELETE FROM counters WHERE id = ?");
        if ($stmtDel) {
            $stmtDel->bind_param("i", $counter_id);
            if ($stmtDel->execute()) {
                $stmtDel->close();
                echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter deleted successfully.']);
                exit();
            }
            $stmtDel->close();
        }

        echo json_encode(['msg_status' => false, 'msg_response' => 'Failed to delete service counter from database.']);
        exit();

    default:
        echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid action specified.']);
        exit();
}