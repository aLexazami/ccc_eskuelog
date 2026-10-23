<?php
// src/Handlers/Admin/ajax/office_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'fetch_office_transactions':
        $office_id = (int)($_POST['office_id'] ?? 0);

        if ($office_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Office ID is required.']);
            exit();
        }

        // Updated SELECT query without requires_custom_input
        $query = "
            SELECT id, transaction_name, estimated_minutes 
            FROM transaction_types 
            WHERE office_id = ? AND flag_status = 0 
            ORDER BY transaction_name ASC
        ";

        if ($stmt = $db_connect->prepare($query)) {
            $stmt->bind_param("i", $office_id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Prepare statement for associated sub-concerns
            $sub_query = "
                SELECT id, sub_concern_name 
                FROM transaction_sub_concerns 
                WHERE transaction_type_id = ? AND flag_status = 0 
                ORDER BY id ASC
            ";
            $sub_stmt = $db_connect->prepare($sub_query);

            $transactions = [];
            while ($row = $result->fetch_assoc()) {
                $sub_concerns = [];
                if ($sub_stmt) {
                    $sub_stmt->bind_param("i", $row['id']);
                    $sub_stmt->execute();
                    $sub_result = $sub_stmt->get_result();
                    while ($sub_row = $sub_result->fetch_assoc()) {
                        $sub_concerns[] = [
                            'id'               => (int)$sub_row['id'],
                            'sub_concern_name' => $sub_row['sub_concern_name']
                        ];
                    }
                }
                $row['sub_concerns'] = $sub_concerns;
                $transactions[] = $row;
            }

            if ($sub_stmt) {
                $sub_stmt->close();
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'data'    => $transactions
            ]);
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Failed to retrieve transactions.']);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}