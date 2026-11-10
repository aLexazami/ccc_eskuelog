<?php
// src/Handlers/Admin/ajax/logbook_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // -------------------------------------------------------------------------
    // 1. FETCH STUDENT INFO
    // -------------------------------------------------------------------------
    case 'fetch_student_info':
        $student_id = trim($_POST['student_id'] ?? '');

        if (empty($student_id)) {
            echo json_encode(['success' => false, 'message' => 'Student ID is required.']);
            exit();
        }

        try {
            $query = "
                SELECT 
                    s.student_id,
                    s.year_level,
                    s.department_id,
                    s.program_id,
                    p.program_code,
                    COALESCE(NULLIF(s.major, ''), p.major) AS major,
                    u.id AS user_id,
                    u.first_name,
                    u.middle_name,
                    u.last_name,
                    u.suffix,
                    u.post_nominal,
                    u.sex,
                    u.email,
                    u.contact_no
                FROM student s
                INNER JOIN users u ON s.user_id = u.id
                LEFT JOIN programs p ON s.program_id = p.program_id
                WHERE s.student_id = ?
                LIMIT 1
            ";

            $stmt = $db_connect->prepare($query);
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
                        'user_id'       => $row['user_id'],
                        'student_id'    => $row['student_id'],
                        'department_id' => (int)($row['department_id'] ?? 0),
                        'program_id'    => (int)($row['program_id'] ?? 0),
                        'program_code'  => $row['program_code'] ?? '',
                        'major'         => $row['major'] ?? '',
                        'first_name'    => $row['first_name'] ?? '',
                        'middle_name'   => $row['middle_name'] ?? '',
                        'last_name'     => $row['last_name'] ?? '',
                        'suffix'        => $row['suffix'] ?? '',
                        'post_nominal'  => $row['post_nominal'] ?? '',
                        'sex'           => !empty($row['sex']) ? ucfirst(strtolower($row['sex'])) : '',
                        'email'         => $row['email'] ?? '',
                        'year_level'    => $year_level,
                        'contact_no'    => $row['contact_no'] ?? ''
                    ]
                ]);
                exit();
            }

            echo json_encode(['success' => false, 'message' => 'No student record found.']);
            exit();

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Lookup failed: ' . $e->getMessage()]);
            exit();
        }

    // -------------------------------------------------------------------------
    // 2. UNIFIED LOGBOOK CHECK-IN
    // -------------------------------------------------------------------------
    case 'logbook_checkin':
        $visitor_type        = strtoupper(trim($_POST['visitor_type'] ?? 'STUDENT'));
        $reference_id        = trim($_POST['reference_id'] ?? '') ?: null;
        $user_id             = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        
        $first_name          = trim($_POST['first_name'] ?? '');
        $middle_name         = trim($_POST['middle_name'] ?? '') ?: null;
        $last_name           = trim($_POST['last_name'] ?? '');
        $suffix              = trim($_POST['suffix'] ?? '') ?: null;
        $post_nominal        = trim($_POST['post_nominal'] ?? '') ?: null;
        $sex                 = trim($_POST['sex'] ?? '') ?: null;
        $email               = trim($_POST['email'] ?? '') ?: null;
        $contact_no          = trim($_POST['contact_no'] ?? '') ?: null;

        $program_id          = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $program_code        = trim($_POST['program_code'] ?? '') ?: null;
        $major               = trim($_POST['major'] ?? '') ?: null;
        $year_level          = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $department_id       = filter_var($_POST['department_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

        $office_id           = (int)($_POST['office_id'] ?? 0);
        $raw_trans_id        = trim($_POST['transaction_type_id'] ?? '');
        $raw_sub_id          = trim($_POST['sub_concern_id'] ?? '');
        $other_concern_text  = trim($_POST['other_concern_text'] ?? '') ?: null;
        
        $data_consent        = isset($_POST['data_consent']) && $_POST['data_consent'] === '1' ? 1 : 0;
        $is_priority         = isset($_POST['is_priority']) && $_POST['is_priority'] === '1' ? 1 : 0;
        $priority_type       = $is_priority ? (trim($_POST['priority_type'] ?? '') ?: 'OTHER') : null;

        if (empty($first_name) || empty($last_name) || $office_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Please complete all required student details and office selection.']);
            exit();
        }

        if ($data_consent !== 1) {
            echo json_encode(['success' => false, 'message' => 'Data privacy authorization consent is required.']);
            exit();
        }

        // Parse array of selected numeric transaction IDs
        $trans_ids = array_filter(array_map('intval', explode(',', $raw_trans_id)), function($val) {
            return $val > 0;
        });

        // Parse sub-concerns (handles JSON map or comma-separated string)
        $sub_concerns_map = [];
        if (!empty($raw_sub_id)) {
            $decoded = json_decode($raw_sub_id, true);
            if (is_array($decoded)) {
                $sub_concerns_map = $decoded;
            } else {
                // Fallback for comma-separated IDs
                $raw_ids = array_filter(array_map('intval', explode(',', $raw_sub_id)), fn($v) => $v > 0);
                if (!empty($raw_ids) && !empty($trans_ids)) {
                    $sub_concerns_map[$trans_ids[0]] = $raw_ids;
                }
            }
        }

        // Fallback program_code from DB if not provided by form
        if (empty($program_code) && !empty($program_id)) {
            $stmtP = $db_connect->prepare("SELECT program_code, major FROM programs WHERE program_id = ? LIMIT 1");
            $stmtP->bind_param("i", $program_id);
            $stmtP->execute();
            $resP = $stmtP->get_result()->fetch_assoc();
            if ($resP) {
                $program_code = $resP['program_code'] ?? null;
                if (empty($major)) {
                    $major = $resP['major'] ?? null;
                }
            }
        }

        // Determine Primary Transaction Type ID for header snapshot
        $primary_trans_id = !empty($trans_ids) ? reset($trans_ids) : null;

        if (empty($primary_trans_id)) {
            $stmtFallback = $db_connect->prepare("SELECT id FROM transaction_types WHERE office_id = ? AND flag_status = 0 LIMIT 1");
            $stmtFallback->bind_param("i", $office_id);
            $stmtFallback->execute();
            $resFB = $stmtFallback->get_result()->fetch_assoc();
            $primary_trans_id = $resFB['id'] ?? null;
        }

        // Calculate Dynamic Cumulative Estimated Process Time
        $estimated_minutes = 0;
        if (!empty($trans_ids)) {
            $inClause = implode(',', array_map('intval', $trans_ids));
            $stmtMins = $db_connect->query("SELECT SUM(estimated_minutes) AS total_mins FROM transaction_types WHERE id IN ($inClause)");
            if ($stmtMins && $rowMins = $stmtMins->fetch_assoc()) {
                $estimated_minutes = (int)($rowMins['total_mins'] ?? 0);
            }
        }

        if (strpos($raw_trans_id, 'OTHERS') !== false || !empty($other_concern_text)) {
            $estimated_minutes += 10;
        }

        if ($estimated_minutes <= 0) {
            $estimated_minutes = 15;
        }

        $db_connect->begin_transaction();

        try {
            // STEP A: Insert primary header record into logbooks
            $stmtLog = $db_connect->prepare("
                INSERT INTO logbooks (
                    visitor_type, reference_id, user_id, first_name, middle_name, last_name,
                    suffix, post_nominal, sex, email, contact_no, program_id, program_code, major,
                    year_level, department_id, office_id, transaction_type_id, other_concern_text,
                    is_priority, priority_type, data_consent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtLog->bind_param(
                "ssissssssssissiiissisi",
                $visitor_type,
                $reference_id,
                $user_id,
                $first_name,
                $middle_name,
                $last_name,
                $suffix,
                $post_nominal,
                $sex,
                $email,
                $contact_no,
                $program_id,
                $program_code,
                $major,
                $year_level,
                $department_id,
                $office_id,
                $primary_trans_id,
                $other_concern_text,
                $is_priority,
                $priority_type,
                $data_consent
            );
            $stmtLog->execute();
            $logbook_id = $db_connect->insert_id;

            // STEP B: Populate Junction Table (logbook_transaction_items)
            $stmtItem = $db_connect->prepare("
                INSERT INTO logbook_transaction_items (logbook_id, transaction_type_id, sub_concern_id) 
                VALUES (?, ?, ?)
            ");

            if (!empty($trans_ids)) {
                foreach ($trans_ids as $t_id) {
                    $subsForTrans = $sub_concerns_map[$t_id] ?? [];
                    if (!empty($subsForTrans) && is_array($subsForTrans)) {
                        foreach ($subsForTrans as $sub_id) {
                            $s_id = (int)$sub_id;
                            $stmtItem->bind_param("iii", $logbook_id, $t_id, $s_id);
                            $stmtItem->execute();
                        }
                    } else {
                        $nullSub = null;
                        $stmtItem->bind_param("iii", $logbook_id, $t_id, $nullSub);
                        $stmtItem->execute();
                    }
                }
            } else {
                $nullTrans = null;
                $nullSub = null;
                $stmtItem->bind_param("iii", $logbook_id, $nullTrans, $nullSub);
                $stmtItem->execute();
            }

            // STEP C: Resolve office details, prefix code & daily ticket sequence
            $stmtSeq = $db_connect->prepare("
                SELECT 
                    o.office_name,
                    COALESCE(NULLIF(o.office_code, ''), 'Q') AS prefix,
                    COALESCE(MAX(q.queue_sequence), 0) + 1 AS next_seq
                FROM office o
                LEFT JOIN queue_tickets q ON o.office_id = q.office_id AND q.queue_date = CURDATE()
                WHERE o.office_id = ?
                GROUP BY o.office_id, o.office_name, o.office_code
            ");
            $stmtSeq->bind_param("i", $office_id);
            $stmtSeq->execute();
            $resSeq = $stmtSeq->get_result()->fetch_assoc();

            $office_name   = $resSeq['office_name'] ?? 'Office';
            $base_prefix   = !empty($resSeq['prefix']) ? $resSeq['prefix'] : 'Q';
            $prefix        = $is_priority ? "P-" . $base_prefix : $base_prefix;
            $sequence      = (int)($resSeq['next_seq'] ?? 1);
            $ticket_number = sprintf("%s-%03d", $prefix, $sequence);
            $today         = date('Y-m-d');

            // STEP D: Insert queue ticket
            $stmtQueue = $db_connect->prepare("
                INSERT INTO queue_tickets (
                    logbook_id, ticket_number, queue_sequence, queue_date, office_id, status, is_priority
                ) VALUES (?, ?, ?, ?, ?, 'WAITING', ?)
            ");
            $stmtQueue->bind_param("isisii", $logbook_id, $ticket_number, $sequence, $today, $office_id, $is_priority);
            $stmtQueue->execute();

            $db_connect->commit();

            echo json_encode([
                'success'           => true,
                'message'           => 'Check-in successful!',
                'ticket_number'     => $ticket_number,
                'sequence'          => $sequence,
                'office_name'       => $office_name,
                'is_priority'       => $is_priority,
                'estimated_minutes' => $estimated_minutes
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