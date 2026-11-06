<?php
// src/Handlers/Admin/ajax/office_counters_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

if (!$db_connect) {
    echo json_encode(['msg_status' => false, 'msg_response' => 'Database connection unavailable.']);
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

$action = $_REQUEST['action'] ?? $_REQUEST['actionSubmit'] ?? $_REQUEST['actionUpdate'] ?? '';

switch ($action) {

    case 'fetch_counters':
        $office_id = (int)($_REQUEST['office_id'] ?? 0);
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
                c.assigned_user_id,
                c.flag_status,
                o.office_name,
                o.office_code,
                CONCAT(u.first_name, ' ', IFNULL(u.last_name, '')) AS assigned_staff_name
            FROM counters c
            INNER JOIN office o ON c.office_id = o.office_id
            LEFT JOIN users u ON c.assigned_user_id = u.id
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

        if ($assigned_user_id > 0) {
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
            $stmt->close();
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

        if ($assigned_user_id > 0) {
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
            echo json_encode(['msg_status' => true, 'msg_response' => 'Service counter updated successfully.']);
            exit();
        }
        if ($stmt) $stmt->close();

        echo json_encode(['msg_status' => false, 'msg_response' => 'Failed to update service counter in database.']);
        exit();

    case 'toggle_counter_status':
        $counter_id  = (int)($_POST['counter_id'] ?? 0);
        $flag_status = (int)($_POST['flag_status'] ?? 0);

        if ($counter_id <= 0) {
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid Counter ID provided.']);
            exit();
        }

        $query = "UPDATE counters SET flag_status = ? WHERE id = ?";
        if ($stmt = $db_connect->prepare($query)) {
            $stmt->bind_param("ii", $flag_status, $counter_id);
            if ($stmt->execute()) {
                $stmt->close();
                echo json_encode(['msg_status' => true, 'msg_response' => 'Counter status updated successfully.']);
                exit();
            }
            $stmt->close();
        }

        echo json_encode(['msg_status' => false, 'msg_response' => 'Failed to update counter status in database.']);
        exit();

    default:
        echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid action specified.']);
        exit();
}