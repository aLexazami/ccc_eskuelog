<?php
// src/Handlers/Admin/ajax/sub_concerns_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'msg_status' => false, 'msg_response' => 'Database connection unavailable.']);
    exit();
}

$action = $_POST['action'] ?? $_POST['actionSubmit'] ?? $_POST['actionUpdate'] ?? '';

try {
    switch ($action) {

        // ------------------------------------------------------------------
        // FETCH TRANSACTION TYPES BY OFFICE (FOR DYNAMIC MODAL DROPDOWNS)
        // ------------------------------------------------------------------
        case 'fetch_types_by_office':
            $office_id = (int)($_POST['office_id'] ?? 0);
            $whereClause = $office_id > 0 ? " WHERE office_id = {$office_id} AND flag_status = 0 " : " WHERE flag_status = 0 ";

            $stmt = $db_connect->query("SELECT id, transaction_name FROM transaction_types {$whereClause} ORDER BY transaction_name ASC");
            $types = [];
            while ($row = $stmt->fetch_assoc()) {
                $types[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $types]);
            exit();

        // ------------------------------------------------------------------
        // FETCH ALL SUB CONCERNS (Tabulator Standard)
        // ------------------------------------------------------------------
        case 'fetch_sub_concerns':
            $office_id = (int)($_POST['office_id'] ?? 0);
            $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);

            $conditions = [];
            if ($office_id > 0) {
                $conditions[] = "tt.office_id = {$office_id}";
            }
            if ($transaction_type_id > 0) {
                $conditions[] = "tsc.transaction_type_id = {$transaction_type_id}";
            }

            $whereClause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

            $sql = "
                SELECT 
                    tsc.id,
                    tsc.transaction_type_id,
                    tsc.sub_concern_name,
                    tsc.flag_status,
                    tt.transaction_name,
                    tt.office_id,
                    o.office_code,
                    o.office_name
                FROM transaction_sub_concerns tsc
                INNER JOIN transaction_types tt ON tsc.transaction_type_id = tt.id
                INNER JOIN office o ON tt.office_id = o.office_id
                {$whereClause}
                ORDER BY o.office_name ASC, tt.transaction_name ASC, tsc.sub_concern_name ASC
            ";

            $res = $db_connect->query($sql);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }

            echo json_encode(['success' => true, 'data' => $data]);
            exit();

        // ------------------------------------------------------------------
        // SUBMIT NEW SUB-CONCERN
        // ------------------------------------------------------------------
        case 'submitSubConcern':
            $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
            $sub_concern_name = trim((string)($_POST['sub_concern_name'] ?? ''));
            $flag_status = (int)($_POST['flag_status'] ?? 0);

            if ($transaction_type_id <= 0) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Please select a valid transaction type.']);
                exit();
            }
            if (empty($sub_concern_name)) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Sub-concern specification cannot be empty.']);
                exit();
            }

            $stmt = $db_connect->prepare("INSERT INTO transaction_sub_concerns (transaction_type_id, sub_concern_name, flag_status) VALUES (?, ?, ?)");
            $stmt->bind_param("isi", $transaction_type_id, $sub_concern_name, $flag_status);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['msg_status' => true, 'msg_response' => 'Sub-concern added successfully.']);
            exit();

        // ------------------------------------------------------------------
        // UPDATE SUB-CONCERN
        // ------------------------------------------------------------------
        case 'submitUpdateSubConcern':
            $sub_id = (int)($_POST['sub_id'] ?? 0);
            $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
            $sub_concern_name = trim((string)($_POST['sub_concern_name'] ?? ''));
            $flag_status = (int)($_POST['flag_status'] ?? 0);

            if ($sub_id <= 0 || $transaction_type_id <= 0 || empty($sub_concern_name)) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid sub-concern data provided.']);
                exit();
            }

            $stmt = $db_connect->prepare("UPDATE transaction_sub_concerns SET transaction_type_id = ?, sub_concern_name = ?, flag_status = ? WHERE id = ?");
            $stmt->bind_param("isii", $transaction_type_id, $sub_concern_name, $flag_status, $sub_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['msg_status' => true, 'msg_response' => 'Sub-concern updated successfully.']);
            exit();

        // ------------------------------------------------------------------
        // DELETE SUB-CONCERN
        // ------------------------------------------------------------------
        case 'delete_sub_concern':
            $sub_id = (int)($_POST['sub_id'] ?? 0);

            if ($sub_id <= 0) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid sub-concern ID.']);
                exit();
            }

            $stmt = $db_connect->prepare("DELETE FROM transaction_sub_concerns WHERE id = ?");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['msg_status' => true, 'msg_response' => 'Sub-concern deleted successfully.']);
            exit();

        default:
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid action request.']);
            exit();
    }
} catch (Exception $e) {
    echo json_encode(['msg_status' => false, 'msg_response' => 'Handler Error: ' . $e->getMessage()]);
    exit();
}