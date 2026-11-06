<?php
// src/Handlers/Admin/ajax/office_prefix_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$action = $_REQUEST['action'] ?? '';

switch ($action) {

    case 'fetch_offices':
        $offices = [];
        $query = "
            SELECT office_id, office_code, prefix_code, office_name, date_modify 
            FROM office 
            WHERE flag_status = 0 
            ORDER BY office_name ASC
        ";
        if ($result = $db_connect->query($query)) {
            while ($row = $result->fetch_assoc()) {
                $offices[] = $row;
            }
            $result->free();
            echo json_encode(['success' => true, 'data' => $offices]);
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Failed to fetch office records.']);
        exit();

    case 'update_prefix_code':
        $office_id   = (int)($_POST['office_id'] ?? 0);
        $prefix_code = strtoupper(trim($_POST['prefix_code'] ?? ''));

        if ($office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Office ID provided.']);
            exit();
        }

        // Sanitize prefix code (alphanumeric, hyphens, and underscores only)
        $prefix_code = preg_replace('/[^A-Z0-9_-]/', '', $prefix_code);

        $query = "
            UPDATE office 
            SET prefix_code = ?, date_modify = NOW() 
            WHERE office_id = ? AND flag_status = 0
        ";

        if ($stmt = $db_connect->prepare($query)) {
            $stmt->bind_param("si", $prefix_code, $office_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                echo json_encode([
                    'success' => true,
                    'message' => 'Office prefix code updated successfully.'
                ]);
                exit();
            }
            $stmt->close();
        }

        echo json_encode(['success' => false, 'message' => 'Failed to update office prefix code in database.']);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}