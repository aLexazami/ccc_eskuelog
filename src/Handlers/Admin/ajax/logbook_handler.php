<?php
// src/Handlers/Admin/ajax/logbook_handler.php

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

    // -------------------------------------------------------------------------
    // 1. FETCH STUDENT INFO (Auto-fill for Students)
    // -------------------------------------------------------------------------
    case 'fetch_student_info':
        $student_id = trim($_POST['student_id'] ?? '');

        if (empty($student_id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit();
        }

        $query = "
            SELECT 
                s.student_id,
                s.year_level,
                p.program_code,
                u.id AS user_id,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.suffix,
                u.post_nominal,
                u.sex,
                u.contact_no
            FROM student s
            INNER JOIN users u ON s.user_id = u.id
            LEFT JOIN programs p ON s.program_id = p.program_id
            WHERE s.student_id = ?
            LIMIT 1
        ";

        if ($stmt = $db_connect->prepare($query)) {
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $year_val = (string) $row['year_level'];
                $year_map = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
                $year_level = $year_map[$year_val] ?? $year_val;

                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'user_id'      => $row['user_id'],
                        'student_id'   => $row['student_id'],
                        'first_name'   => $row['first_name'] ?? '',
                        'middle_name'  => $row['middle_name'] ?? '',
                        'last_name'    => $row['last_name'] ?? '',
                        'suffix'       => $row['suffix'] ?? '',
                        'post_nominal' => $row['post_nominal'] ?? '',
                        'sex'          => !empty($row['sex']) ? ucfirst(strtolower($row['sex'])) : '',
                        'program_code' => $row['program_code'] ?? '',
                        'year_level'   => $year_level,
                        'contact_no'   => $row['contact_no'] ?? ''
                    ]
                ]);
                exit();
            }
        }

        echo json_encode(['success' => false, 'message' => 'No student record found.']);
        exit();

    // -------------------------------------------------------------------------
    // 2. FETCH EMPLOYEE INFO (Auto-fill for Employees)
    // -------------------------------------------------------------------------
    case 'fetch_employee_info':
        $employee_id = trim($_POST['employee_id'] ?? '');

        if (empty($employee_id)) {
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit();
        }

        $query = "
            SELECT 
                e.employee_id,
                e.department_id,
                e.office_id,
                u.id AS user_id,
                u.first_name,
                u.middle_name,
                u.last_name,
                u.suffix,
                u.post_nominal,
                u.sex,
                u.contact_no
            FROM employee e
            INNER JOIN users u ON e.user_id = u.id
            WHERE e.employee_id = ?
            LIMIT 1
        ";

        if ($stmt = $db_connect->prepare($query)) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'user_id'       => $row['user_id'],
                        'employee_id'   => $row['employee_id'],
                        'first_name'    => $row['first_name'] ?? '',
                        'middle_name'   => $row['middle_name'] ?? '',
                        'last_name'     => $row['last_name'] ?? '',
                        'suffix'        => $row['suffix'] ?? '',
                        'post_nominal'  => $row['post_nominal'] ?? '',
                        'department_id' => (int)($row['department_id'] ?? 0),
                        'sex'           => !empty($row['sex']) ? ucfirst(strtolower($row['sex'])) : '',
                        'contact_no'    => $row['contact_no'] ?? ''
                    ]
                ]);
                exit();
            }
        }

        echo json_encode(['success' => false, 'message' => 'No employee record found.']);
        exit();

    // -------------------------------------------------------------------------
    // 3. UNIFIED LOGBOOK CHECK-IN (Handles Student, Employee, AND Guest)
    // -------------------------------------------------------------------------
    case 'logbook_checkin':
        $visitor_type        = strtoupper(trim($_POST['visitor_type'] ?? 'GUEST')); // STUDENT, EMPLOYEE, or GUEST
        $reference_id        = trim($_POST['reference_id'] ?? '') ?: null;          // student_id/employee_id or null
        $user_id             = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        
        // Form Inputs
        $first_name          = trim($_POST['first_name'] ?? '');
        $middle_name         = trim($_POST['middle_name'] ?? '') ?: null;
        $last_name           = trim($_POST['last_name'] ?? '');
        $suffix              = trim($_POST['suffix'] ?? '') ?: null;
        $post_nominal        = trim($_POST['post_nominal'] ?? '') ?: null;
        $sex                 = trim($_POST['sex'] ?? '') ?: null;
        $contact_no          = trim($_POST['contact_no'] ?? '') ?: null;

        // Type-Specific Attributes
        $program_code        = trim($_POST['program_code'] ?? '') ?: null;
        $year_level          = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $department_id       = filter_var($_POST['department_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

        // Service Choice & Consent
        $office_id           = (int)($_POST['office_id'] ?? 0);
        $transaction_type_id = (int)($_POST['transaction_type_id'] ?? 0);
        $data_consent        = isset($_POST['data_consent']) && $_POST['data_consent'] === '1' ? 1 : 0;

        // Priority Feature Capture
        $is_priority         = isset($_POST['is_priority']) && $_POST['is_priority'] === '1' ? 1 : 0;
        $priority_type       = $is_priority ? (trim($_POST['priority_type'] ?? '') ?: 'OTHER') : null;

        // Validation
        if (empty($first_name) || empty($last_name) || $office_id === 0 || $transaction_type_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Please complete all required fields.']);
            exit();
        }

        if ($data_consent !== 1) {
            echo json_encode(['success' => false, 'message' => 'Data privacy consent is required.']);
            exit();
        }

        // Database Transaction for Atomic Write (logbooks + queue_tickets)
        $db_connect->begin_transaction();

        try {
            // Step A: Insert into logbooks table with priority fields
            $stmtLog = $db_connect->prepare("
                INSERT INTO logbooks (
                    visitor_type, reference_id, user_id, first_name, middle_name, last_name,
                    suffix, post_nominal, sex, contact_no, program_code, year_level,
                    department_id, office_id, transaction_type_id,
                    is_priority, priority_type, data_consent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtLog->bind_param(
                "ssissssssssiiiiiisi",
                $visitor_type, $reference_id, $user_id, $first_name, $middle_name, $last_name,
                $suffix, $post_nominal, $sex, $contact_no, $program_code, $year_level,
                $department_id, $office_id, $transaction_type_id,
                $is_priority, $priority_type, $data_consent
            );
            $stmtLog->execute();
            $logbook_id = $db_connect->insert_id;

            // Step B: Resolve office prefix_code & Daily Sequence Number
            $stmtSeq = $db_connect->prepare("
                SELECT 
                    COALESCE(NULLIF(o.prefix_code, ''), o.office_code) AS prefix,
                    COALESCE(MAX(q.queue_sequence), 0) + 1 AS next_seq
                FROM office o
                LEFT JOIN queue_tickets q ON o.office_id = q.office_id AND q.queue_date = CURDATE()
                WHERE o.office_id = ?
                GROUP BY o.office_id
            ");
            $stmtSeq->bind_param("i", $office_id);
            $stmtSeq->execute();
            $resSeq = $stmtSeq->get_result()->fetch_assoc();

            // Priority Ticket Prefixing Logic
            $base_prefix   = $resSeq['prefix'] ?? 'Q';
            $prefix        = $is_priority ? "P-" . $base_prefix : $base_prefix;
            $sequence      = (int)($resSeq['next_seq'] ?? 1);
            $ticket_number = sprintf("%s-%03d", $prefix, $sequence);
            $today         = date('Y-m-d');

            // Step C: Insert into queue_tickets with priority flag
            $stmtQueue = $db_connect->prepare("
                INSERT INTO queue_tickets (
                    logbook_id, ticket_number, queue_sequence, queue_date, office_id, status, is_priority
                ) VALUES (?, ?, ?, ?, ?, 'WAITING', ?)
            ");
            $stmtQueue->bind_param("isisii", $logbook_id, $ticket_number, $sequence, $today, $office_id, $is_priority);
            $stmtQueue->execute();

            $db_connect->commit();

            echo json_encode([
                'success'       => true,
                'message'       => 'Check-in successful!',
                'ticket_number' => $ticket_number,
                'sequence'      => $sequence,
                'is_priority'   => $is_priority
            ]);
            exit();

        } catch (Exception $e) {
            $db_connect->rollback();
            echo json_encode(['success' => false, 'message' => 'Check-in failed: ' . $e->getMessage()]);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action.']);
        exit();
}