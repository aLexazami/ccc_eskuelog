<?php
// src/Handlers/Admin/ajax/transaction_types_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ------------------------------------------------------------------
        // FETCH ACTIVE OFFICES FOR DROPDOWNS & FILTERS
        // ------------------------------------------------------------------
        case 'fetch_offices':
            $stmt = $db_connect->query("SELECT office_id, office_code, office_name FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
            $offices = [];
            while ($row = $stmt->fetch_assoc()) {
                $offices[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $offices]);
            exit();

        // ------------------------------------------------------------------
        // FETCH TRANSACTION TYPES WITH SUB-CONCERN COUNT
        // ------------------------------------------------------------------
        case 'fetch_transaction_types':
            $office_id = (int)($_POST['office_id'] ?? 0);
            $whereClause = "";
            $params = [];
            $types = "i";

            if ($office_id > 0) {
                $whereClause = " WHERE tt.office_id = ? ";
                $params[] = $office_id;
            }

            $sql = "
                SELECT 
                    tt.id,
                    tt.office_id,
                    o.office_code,
                    o.office_name,
                    tt.transaction_name,
                    tt.estimated_minutes,
                    tt.flag_status,
                    tt.date_modify,
                    COUNT(tsc.id) AS sub_concerns_count
                FROM transaction_types tt
                INNER JOIN office o ON tt.office_id = o.office_id
                LEFT JOIN transaction_sub_concerns tsc ON tt.id = tsc.transaction_type_id AND tsc.flag_status = 0
                {$whereClause}
                GROUP BY tt.id
                ORDER BY o.office_name ASC, tt.transaction_name ASC
            ";

            $stmt = $db_connect->prepare($sql);
            if ($office_id > 0) {
                $stmt->bind_param("i", $office_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();

            echo json_encode(['success' => true, 'data' => $data]);
            exit();

        // ------------------------------------------------------------------
        // CREATE / UPDATE TRANSACTION TYPE
        // ------------------------------------------------------------------
        case 'save_transaction_type':
            $id = (int)($_POST['id'] ?? 0);
            $office_id = (int)($_POST['office_id'] ?? 0);
            $transaction_name = trim((string)($_POST['transaction_name'] ?? ''));
            $estimated_minutes = (int)($_POST['estimated_minutes'] ?? 5);

            if ($office_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Please select a valid office.']);
                exit();
            }
            if (empty($transaction_name)) {
                echo json_encode(['success' => false, 'message' => 'Transaction name cannot be empty.']);
                exit();
            }

            if ($id > 0) {
                // Update
                $stmt = $db_connect->prepare("UPDATE transaction_types SET office_id = ?, transaction_name = ?, estimated_minutes = ? WHERE id = ?");
                $stmt->bind_param("isii", $office_id, $transaction_name, $estimated_minutes, $id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Transaction type updated successfully.';
            } else {
                // Insert
                $stmt = $db_connect->prepare("INSERT INTO transaction_types (office_id, transaction_name, estimated_minutes, flag_status) VALUES (?, ?, ?, 0)");
                $stmt->bind_param("isi", $office_id, $transaction_name, $estimated_minutes);
                $stmt->execute();
                $stmt->close();
                $msg = 'Transaction type added successfully.';
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            exit();

        // ------------------------------------------------------------------
        // TOGGLE TRANSACTION TYPE STATUS (ACTIVE / INACTIVE)
        // ------------------------------------------------------------------
        case 'toggle_type_status':
            $id = (int)($_POST['id'] ?? 0);
            $current_status = (int)($_POST['status'] ?? 0);
            $new_status = ($current_status === 0) ? 1 : 0;

            $stmt = $db_connect->prepare("UPDATE transaction_types SET flag_status = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'new_status' => $new_status]);
            exit();

        // ------------------------------------------------------------------
        // DELETE TRANSACTION TYPE
        // ------------------------------------------------------------------
        case 'delete_transaction_type':
            $id = (int)($_POST['id'] ?? 0);

            $stmt = $db_connect->prepare("DELETE FROM transaction_types WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Transaction type deleted successfully.']);
            exit();

        // ------------------------------------------------------------------
        // FETCH SUB-CONCERNS FOR A TRANSACTION TYPE
        // ------------------------------------------------------------------
        case 'fetch_sub_concerns':
            $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);

            $stmt = $db_connect->prepare("
                SELECT id, transaction_type_id, sub_concern_name, flag_status 
                FROM transaction_sub_concerns 
                WHERE transaction_type_id = ? 
                ORDER BY sub_concern_name ASC
            ");
            $stmt->bind_param("i", $transaction_type_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $subs = [];
            while ($row = $result->fetch_assoc()) {
                $subs[] = $row;
            }
            $stmt->close();

            echo json_encode(['success' => true, 'data' => $subs]);
            exit();

        // ------------------------------------------------------------------
        // SAVE SUB-CONCERN (ADD / UPDATE)
        // ------------------------------------------------------------------
        case 'save_sub_concern':
            $sub_id = (int)($_POST['sub_id'] ?? 0);
            $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
            $sub_concern_name = trim((string)($_POST['sub_concern_name'] ?? ''));

            if ($transaction_type_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid transaction type selection.']);
                exit();
            }
            if (empty($sub_concern_name)) {
                echo json_encode(['success' => false, 'message' => 'Sub-concern name cannot be empty.']);
                exit();
            }

            if ($sub_id > 0) {
                $stmt = $db_connect->prepare("UPDATE transaction_sub_concerns SET sub_concern_name = ? WHERE id = ?");
                $stmt->bind_param("si", $sub_concern_name, $sub_id);
                $stmt->execute();
                $stmt->close();
                $msg = 'Sub-concern updated successfully.';
            } else {
                $stmt = $db_connect->prepare("INSERT INTO transaction_sub_concerns (transaction_type_id, sub_concern_name, flag_status) VALUES (?, ?, 0)");
                $stmt->bind_param("is", $transaction_type_id, $sub_concern_name);
                $stmt->execute();
                $stmt->close();
                $msg = 'Sub-concern added successfully.';
            }

            echo json_encode(['success' => true, 'message' => $msg]);
            exit();

        // ------------------------------------------------------------------
        // TOGGLE SUB-CONCERN STATUS
        // ------------------------------------------------------------------
        case 'toggle_sub_status':
            $sub_id = (int)($_POST['sub_id'] ?? 0);
            $current_status = (int)($_POST['status'] ?? 0);
            $new_status = ($current_status === 0) ? 1 : 0;

            $stmt = $db_connect->prepare("UPDATE transaction_sub_concerns SET flag_status = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $sub_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Sub-concern status updated.', 'new_status' => $new_status]);
            exit();

        // ------------------------------------------------------------------
        // DELETE SUB-CONCERN
        // ------------------------------------------------------------------
        case 'delete_sub_concern':
            $sub_id = (int)($_POST['sub_id'] ?? 0);

            $stmt = $db_connect->prepare("DELETE FROM transaction_sub_concerns WHERE id = ?");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Sub-concern deleted successfully.']);
            exit();

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action request.']);
            exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Handler Error: ' . $e->getMessage()]);
    exit();
}