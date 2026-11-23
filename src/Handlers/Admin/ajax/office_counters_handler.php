<?php
// src/Handlers/Admin/ajax/office_counters_handler.php

/** @var \mysqli $db_connect */
global $db_connect, $session_class, $g_user_role, $g_user_id;
$db_connect = $db_connect ?? null;

require_once ACTIVITY_LOGGER;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!$db_connect) {
    echo json_encode(['msg_status' => false, 'msg_response' => 'Database connection unavailable.']);
    exit();
}

// Resolve logged-in user ID & role
$admin_user_id = (int)($g_user_id ?? 0);
$currentRole   = strtoupper(trim((string)($g_user_role ?? '')));

if (isset($session_class) && method_exists($session_class, 'getValue')) {
    if ($admin_user_id <= 0) {
        $admin_user_id = (int)($session_class->getValue('user_id') ?? $session_class->getValue('id') ?? $session_class->getValue('sys_user_id') ?? 0);
    }
    if ($currentRole === '') {
        $currentRole = strtoupper(trim((string)($session_class->getValue('user_role') ?? $session_class->getValue('role') ?? '')));
    }
}
if ($admin_user_id <= 0) {
    $admin_user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}
if ($currentRole === '') {
    $currentRole = strtoupper(trim((string)($_SESSION['user_role'] ?? $_SESSION['role'] ?? '')));
}

if (!in_array($currentRole, ['ADMIN', 'ADMIN_STAFF'], true)) {
    echo json_encode(['success' => false, 'msg_status' => false, 'msg_response' => 'Unauthorized access.']);
    exit();
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

$isAdminStaff   = ($currentRole === 'ADMIN_STAFF');
$lockedOfficeId = 0;

if ($isAdminStaff) {
    if ($admin_user_id > 0) {
        $stmtAssign = $db_connect->prepare("SELECT office_id FROM staff_assignments WHERE user_id = ? AND flag_status = 0 LIMIT 1");
        if ($stmtAssign) {
            $stmtAssign->bind_param("i", $admin_user_id);
            $stmtAssign->execute();
            $resAssign = $stmtAssign->get_result()->fetch_assoc();
            $lockedOfficeId = (int)($resAssign['office_id'] ?? 0);
            $stmtAssign->close();
        }
        if ($lockedOfficeId === 0) {
            $stmtEmp = $db_connect->prepare("SELECT office_id FROM employee WHERE user_id = ? LIMIT 1");
            if ($stmtEmp) {
                $stmtEmp->bind_param("i", $admin_user_id);
                $stmtEmp->execute();
                $resEmp = $stmtEmp->get_result()->fetch_assoc();
                $lockedOfficeId = (int)($resEmp['office_id'] ?? 0);
                $stmtEmp->close();
            }
        }
    }

    if ($lockedOfficeId <= 0) {
        echo json_encode(['success' => false, 'msg_status' => false, 'msg_response' => 'No office assignment found for your account.', 'data' => []]);
        exit();
    }

    // Force office_id on all incoming requests
    $_POST['office_id']    = $lockedOfficeId;
    $_REQUEST['office_id'] = $lockedOfficeId;
}

/**
 * Extract and sanitize an array of user IDs from request payload.
 * Supports both `assigned_user_ids[]` (multi-select) and legacy `assigned_user_id`.
 */
function parseAssignedUserIds(array $source): array
{
    $rawIds = [];

    if (isset($source['assigned_user_ids'])) {
        if (is_array($source['assigned_user_ids'])) {
            $rawIds = $source['assigned_user_ids'];
        } elseif (is_string($source['assigned_user_ids']) && trim($source['assigned_user_ids']) !== '') {
            $rawIds = explode(',', $source['assigned_user_ids']);
        }
    } elseif (isset($source['assigned_user_id']) && (int)$source['assigned_user_id'] > 0) {
        $rawIds = [(int)$source['assigned_user_id']];
    }

    return array_values(array_unique(array_filter(array_map('intval', $rawIds), fn(int $id) => $id > 0)));
}

/**
 * Verify that an active employee belongs to the target office.
 */
function isStaffInOffice(mysqli $db, int $userId, int $officeId): bool
{
    if ($userId <= 0) {
        return true;
    }
    $stmt = $db->prepare("
        SELECT u.id
        FROM users u
        INNER JOIN employee e ON e.user_id = u.id AND e.service_status = 0
        LEFT JOIN staff_assignments sa ON sa.user_id = u.id AND sa.flag_status = 0
        WHERE u.id = ?
          AND u.flag_status = 0
          AND (sa.office_id = ? OR e.office_id = ?)
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iii", $userId, $officeId, $officeId);
    $stmt->execute();
    $found = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $found;
}

/**
 * Synchronize the staff members of ONE counter. Only this counter's rows are touched,
 * so a staff member's assignments to other windows are left alone.
 */
function syncCounterStaffAssignments(mysqli $db, int $counterId, int $officeId, array $userIds, int $counterFlagStatus, ?int $assignerId): void
{
    // 1. Remove the rows of staff no longer on this window (other windows are untouched)
    if (empty($userIds)) {
        $stmtRemove = $db->prepare("DELETE FROM staff_assignments WHERE counter_id = ?");
        $stmtRemove->bind_param("i", $counterId);
        $stmtRemove->execute();
        $stmtRemove->close();
        return;
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmtRemove = $db->prepare("
        DELETE FROM staff_assignments
        WHERE counter_id = ? AND user_id NOT IN ({$placeholders})
    ");
    $stmtRemove->bind_param('i' . str_repeat('i', count($userIds)), $counterId, ...$userIds);
    $stmtRemove->execute();
    $stmtRemove->close();

    // 2. One row per (staff, window): UPDATE when it exists, INSERT otherwise.
    $saFlagStatus = ($counterFlagStatus === 0) ? 0 : 1;

    $stmtFind = $db->prepare("SELECT id FROM staff_assignments WHERE user_id = ? AND counter_id = ? LIMIT 1");
    $stmtUpd  = $db->prepare("
        UPDATE staff_assignments
        SET office_id = ?, flag_status = ?, assigned_by_user_id = ?
        WHERE id = ?
    ");
    $stmtIns  = $db->prepare("
        INSERT INTO staff_assignments (user_id, office_id, counter_id, flag_status, assigned_by_user_id)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($userIds as $uid) {
        $stmtFind->bind_param("ii", $uid, $counterId);
        $stmtFind->execute();
        $existing = $stmtFind->get_result()->fetch_assoc();

        if ($existing) {
            $rowId = (int) $existing['id'];
            $stmtUpd->bind_param("iiii", $officeId, $saFlagStatus, $assignerId, $rowId);
            $ok = $stmtUpd->execute();
        } else {
            $stmtIns->bind_param("iiiii", $uid, $officeId, $counterId, $saFlagStatus, $assignerId);
            $ok = $stmtIns->execute();
        }

        if (!$ok) {
            throw new Exception('Failed to synchronize staff assignment for user ID ' . $uid . '.');
        }
    }

    $stmtFind->close();
    $stmtUpd->close();
    $stmtIns->close();
}

/**
 * Build a human-readable office & assigned staff summary for activity logging.
 */
function buildCounterLogSummary(mysqli $db, int $officeId, array $userIds): string
{
    $officeLabel = "Office #{$officeId}";
    $stmtOff = $db->prepare("SELECT office_code, office_name FROM office WHERE office_id = ? LIMIT 1");
    if ($stmtOff) {
        $stmtOff->bind_param("i", $officeId);
        $stmtOff->execute();
        if ($off = $stmtOff->get_result()->fetch_assoc()) {
            $officeLabel = trim(($off['office_code'] ?? '') . ' - ' . ($off['office_name'] ?? ''), ' -');
        }
        $stmtOff->close();
    }

    if (empty($userIds)) {
        return "{$officeLabel} | Staff: Unassigned";
    }

    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmtStaff = $db->prepare("
        SELECT TRIM(CONCAT(first_name, ' ', IFNULL(last_name, ''))) AS full_name
        FROM users
        WHERE id IN ({$placeholders})
        ORDER BY last_name ASC, first_name ASC
    ");
    $names = [];
    if ($stmtStaff) {
        $stmtStaff->bind_param(str_repeat('i', count($userIds)), ...$userIds);
        $stmtStaff->execute();
        $res = $stmtStaff->get_result();
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['full_name'])) {
                $names[] = $r['full_name'];
            }
        }
        $stmtStaff->close();
    }

    $staffText = !empty($names) ? implode(', ', $names) : 'Unassigned';
    return "{$officeLabel} | Staff: {$staffText}";
}

$action = $_REQUEST['action'] ?? $_REQUEST['actionSubmit'] ?? $_REQUEST['actionUpdate'] ?? '';

switch ($action) {

    case 'fetch_counters':
        $office_id = $isAdminStaff ? $lockedOfficeId : (int)($_REQUEST['office_id'] ?? 0);
        $counters  = [];

        $whereClause = "WHERE o.flag_status = 0";
        if ($office_id > 0) {
            $whereClause .= " AND c.office_id = " . $office_id;
        }

        $query = "
            SELECT 
                c.id AS counter_id,
                c.office_id,
                c.counter_name,
                c.flag_status,
                c.created_at,
                c.updated_at,
                o.office_name,
                o.office_code,
                GROUP_CONCAT(sa.user_id ORDER BY u.last_name ASC, u.first_name ASC SEPARATOR ',') AS assigned_user_ids_csv,
                GROUP_CONCAT(
                    TRIM(CONCAT(u.first_name, ' ', IFNULL(u.last_name, '')))
                    ORDER BY u.last_name ASC, u.first_name ASC SEPARATOR ', '
                ) AS assigned_staff_names,
                COUNT(sa.user_id) AS staff_count
            FROM counters c
            INNER JOIN office o ON c.office_id = o.office_id
            LEFT JOIN staff_assignments sa 
                ON sa.counter_id = c.id 
               AND sa.office_id = c.office_id 
               AND sa.flag_status = 0
            LEFT JOIN users u ON u.id = sa.user_id AND u.flag_status = 0
            {$whereClause}
            GROUP BY c.id, c.office_id, c.counter_name, c.flag_status, c.created_at, c.updated_at, o.office_name, o.office_code
            ORDER BY c.updated_at DESC, c.created_at DESC, c.id DESC
        ";

        if ($result = $db_connect->query($query)) {
            while ($row = $result->fetch_assoc()) {
                $idsCsv = (string)($row['assigned_user_ids_csv'] ?? '');
                $idsArray = $idsCsv !== ''
                    ? array_values(array_filter(array_map('intval', explode(',', $idsCsv)), fn(int $id) => $id > 0))
                    : [];

                $row['counter_id']           = (int)$row['counter_id'];
                $row['office_id']            = (int)$row['office_id'];
                $row['flag_status']          = (int)$row['flag_status'];
                $row['staff_count']          = (int)$row['staff_count'];
                $row['created_at']           = (string)($row['created_at'] ?? '');
                $row['updated_at']           = (string)($row['updated_at'] ?? '');
                $row['assigned_user_ids']    = $idsArray;
                $row['assigned_staff_names'] = trim((string)($row['assigned_staff_names'] ?? ''));
                unset($row['assigned_user_ids_csv']);

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
        $office_id         = $isAdminStaff ? $lockedOfficeId : (int)($_POST['office_id'] ?? 0);
        $counter_name      = trim($_POST['counter_name'] ?? '');
        $assigned_user_ids = parseAssignedUserIds($_POST);
        $flag_status       = (int)($_POST['flag_status'] ?? 0);

        if ($office_id <= 0 || empty($counter_name)) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Office location and counter name are required.']);
            exit();
        }

        // Validate every selected staff member belongs to the target office (a staff member may serve several windows)
        foreach ($assigned_user_ids as $uid) {
            if (!isStaffInOffice($db_connect, $uid, $office_id)) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'One or more selected staff members do not belong to the selected office.'
                ]);
                exit();
            }
        }

        // Check for duplicate counter name in the same office
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

        $db_connect->begin_transaction();

        try {
            $stmt = $db_connect->prepare("INSERT INTO counters (office_id, counter_name, flag_status) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $office_id, $counter_name, $flag_status);

            if (!$stmt || !$stmt->execute()) {
                throw new Exception('Failed to create service counter in database.');
            }
            $new_counter_id = (int)$stmt->insert_id;
            $stmt->close();

            $assigner = $admin_user_id > 0 ? $admin_user_id : null;
            syncCounterStaffAssignments($db_connect, $new_counter_id, $office_id, $assigned_user_ids, $flag_status, $assigner);

            $db_connect->commit();

            $statusText = ($flag_status === 0) ? 'Active' : 'Disabled';
            $summary    = buildCounterLogSummary($db_connect, $office_id, $assigned_user_ids);
            record_activity_log(
                $db_connect,
                $admin_user_id,
                "Created service counter \"{$counter_name}\" (#{$new_counter_id}) [{$statusText}] — {$summary}",
                $currentRole
            );

            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter created successfully.']);
            exit();
        } catch (Throwable $e) {
            $db_connect->rollback();
            echo json_encode(['msg_status' => false, 'msg_response' => $e->getMessage()]);
            exit();
        }

    case 'submitUpdateCounter':
    case 'update_counter':
        $counter_id        = (int)($_POST['counter_id'] ?? 0);
        $office_id         = $isAdminStaff ? $lockedOfficeId : (int)($_POST['office_id'] ?? 0);
        $counter_name      = trim($_POST['counter_name'] ?? '');
        $assigned_user_ids = parseAssignedUserIds($_POST);
        $flag_status       = (int)($_POST['flag_status'] ?? 0);

        if ($counter_id <= 0) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid Counter ID provided.']);
            exit();
        }

        if ($office_id <= 0 || empty($counter_name)) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Office location and counter name are required.']);
            exit();
        }

        if ($isAdminStaff) {
            $stmtOwner = $db_connect->prepare("SELECT office_id FROM counters WHERE id = ? LIMIT 1");
            $stmtOwner->bind_param("i", $counter_id);
            $stmtOwner->execute();
            $ownerRow = $stmtOwner->get_result()->fetch_assoc();
            $stmtOwner->close();

            if (!$ownerRow || (int)$ownerRow['office_id'] !== $lockedOfficeId) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'You are not authorized to modify counters from another office.']);
                exit();
            }
        }

        // Validate every selected staff member belongs to the target office (a staff member may serve several windows)
        foreach ($assigned_user_ids as $uid) {
            if (!isStaffInOffice($db_connect, $uid, $office_id)) {
                echo json_encode([
                    'msg_status'   => false,
                    'msg_response' => 'One or more selected staff members do not belong to the selected office.'
                ]);
                exit();
            }
        }

        // Check for duplicate counter name in the same office (excluding current counter)
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

        $db_connect->begin_transaction();

        try {
            $stmt = $db_connect->prepare("
                UPDATE counters
                SET office_id = ?, counter_name = ?, flag_status = ?, updated_at = CURRENT_TIMESTAMP()
                WHERE id = ?
            ");
            $stmt->bind_param("isii", $office_id, $counter_name, $flag_status, $counter_id);

            if (!$stmt || !$stmt->execute()) {
                throw new Exception('Failed to update service counter in database.');
            }
            $stmt->close();

            $assigner = $admin_user_id > 0 ? $admin_user_id : null;
            syncCounterStaffAssignments($db_connect, $counter_id, $office_id, $assigned_user_ids, $flag_status, $assigner);

            $db_connect->commit();

            $statusText = ($flag_status === 0) ? 'Active' : 'Disabled';
            $summary    = buildCounterLogSummary($db_connect, $office_id, $assigned_user_ids);
            record_activity_log(
                $db_connect,
                $admin_user_id,
                "Updated service counter \"{$counter_name}\" (#{$counter_id}) [{$statusText}] — {$summary}",
                $currentRole
            );

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

        // Fetch counter metadata before deletion for authorization and audit logging
        $stmtInfo = $db_connect->prepare("
            SELECT c.counter_name, c.office_id, o.office_code, o.office_name
            FROM counters c
            LEFT JOIN office o ON o.office_id = c.office_id
            WHERE c.id = ?
            LIMIT 1
        ");
        $stmtInfo->bind_param("i", $counter_id);
        $stmtInfo->execute();
        $counterInfo = $stmtInfo->get_result()->fetch_assoc();
        $stmtInfo->close();

        if (!$counterInfo) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Service counter not found.']);
            exit();
        }

        if ($isAdminStaff && (int)$counterInfo['office_id'] !== $lockedOfficeId) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'You are not authorized to delete counters from another office.']);
            exit();
        }

        $db_connect->begin_transaction();

        try {
            // Remove the staff assignment rows of this window before deleting it
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

            $delName   = $counterInfo['counter_name'] ?? "Counter #{$counter_id}";
            $delOffice = trim(($counterInfo['office_code'] ?? '') . ' - ' . ($counterInfo['office_name'] ?? ''), ' -');
            record_activity_log(
                $db_connect,
                $admin_user_id,
                "Deleted service counter \"{$delName}\" (#{$counter_id}) from {$delOffice}",
                $currentRole
            );

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