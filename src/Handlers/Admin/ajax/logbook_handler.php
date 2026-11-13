<?php
// src/Handlers/Admin/ajax/logbook_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json; charset=utf-8');

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
                    COALESCE(NULLIF(s.department_id, 0), p.department_id, 0) AS department_id,
                    s.program_id,
                    p.program_code,
                    p.program_name,
                    COALESCE(NULLIF(s.major, ''), p.major) AS major,
                    u.id AS user_id,
                    u.first_name,
                    u.middle_name,
                    u.middle_initial,
                    u.last_name,
                    u.suffix,
                    u.post_nominal,
                    u.sex,
                    u.email,
                    COALESCE(NULLIF(NULLIF(s.profile_pic, ''), 'default-profile.jpg'), u.profile_pic) AS profile_pic
                FROM student s
                INNER JOIN users u ON s.user_id = u.id
                LEFT JOIN programs p ON s.program_id = p.program_id
                WHERE BINARY s.student_id = ? AND s.flag_status = 0 AND u.flag_status = 0
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
                        'user_id'        => (int)$row['user_id'],
                        'student_id'     => trim($row['student_id']),
                        'department_id'  => (int)($row['department_id'] ?? 0),
                        'program_id'     => (int)($row['program_id'] ?? 0),
                        'program_code'   => $row['program_code'] ?? '',
                        'program_name'   => $row['program_name'] ?? '',
                        'major'          => $row['major'] ?? '',
                        'first_name'     => $row['first_name'] ?? '',
                        'middle_name'    => $row['middle_name'] ?? '',
                        'middle_initial' => $row['middle_initial'] ?? '',
                        'last_name'      => $row['last_name'] ?? '',
                        'suffix'         => $row['suffix'] ?? '',
                        'post_nominal'   => $row['post_nominal'] ?? '',
                        'sex'            => !empty($row['sex']) ? ucfirst(strtolower($row['sex'])) : '',
                        'email'          => $row['email'] ?? '',
                        'year_level'     => $year_level,
                        'profile_pic'    => $row['profile_pic'] ?? ''
                    ]
                ]);
                exit();
            }

            echo json_encode(['success' => false, 'message' => 'No active student record found.']);
            exit();

        } catch (Exception $e) {
            error_log('fetch_student_info error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Student lookup failed. Please try again.']);
            exit();
        }

    // -------------------------------------------------------------------------
    // 1.5. FETCH EMPLOYEE INFO
    // -------------------------------------------------------------------------
    case 'fetch_employee_info':
        $employee_id = trim($_POST['employee_id'] ?? '');

        if (empty($employee_id)) {
            echo json_encode(['success' => false, 'message' => 'Employee ID is required.']);
            exit();
        }

        try {
            $query = "
                SELECT 
                    e.employee_id,
                    e.department_id,
                    e.office_id,
                    COALESCE(e.position, '') AS position,
                    u.id AS user_id,
                    u.first_name,
                    u.middle_name,
                    u.middle_initial,
                    u.last_name,
                    u.suffix,
                    u.post_nominal,
                    u.sex,
                    u.email,
                    COALESCE(NULLIF(e.profile_pic, ''), u.profile_pic) AS profile_pic
                FROM employee e
                INNER JOIN users u ON e.user_id = u.id
                WHERE BINARY e.employee_id = ? AND e.service_status = 0 AND u.flag_status = 0
                LIMIT 1
            ";

            $stmt = $db_connect->prepare($query);
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'data'    => [
                        'user_id'        => (int)$row['user_id'],
                        'employee_id'    => trim($row['employee_id']),
                        'department_id'  => (int)($row['department_id'] ?? 0),
                        'office_id'      => (int)($row['office_id'] ?? 0),
                        'position'       => $row['position'] ?? '',
                        'first_name'     => $row['first_name'] ?? '',
                        'middle_name'    => $row['middle_name'] ?? '',
                        'middle_initial' => $row['middle_initial'] ?? '',
                        'last_name'      => $row['last_name'] ?? '',
                        'suffix'         => $row['suffix'] ?? '',
                        'post_nominal'   => $row['post_nominal'] ?? '',
                        'sex'            => !empty($row['sex']) ? ucfirst(strtolower($row['sex'])) : '',
                        'email'          => $row['email'] ?? '',
                        'profile_pic'    => $row['profile_pic'] ?? ''
                    ]
                ]);
                exit();
            }

            echo json_encode(['success' => false, 'message' => 'No active employee record found.']);
            exit();

        } catch (Exception $e) {
            error_log('fetch_employee_info error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Employee lookup failed. Please try again.']);
            exit();
        }

    // -------------------------------------------------------------------------
    // 2. UNIFIED LOGBOOK CHECK-IN
    // -------------------------------------------------------------------------
    case 'logbook_checkin':
        $allowed_visitor_types = ['STUDENT', 'EMPLOYEE', 'GUEST'];
        $visitor_type = strtoupper(trim($_POST['visitor_type'] ?? 'STUDENT'));
        if (!in_array($visitor_type, $allowed_visitor_types, true)) {
            $visitor_type = 'GUEST';
        }

        $reference_id        = trim($_POST['reference_id'] ?? '') ?: null;
        $user_id             = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT) ?: null;

        $first_name          = trim($_POST['first_name'] ?? '');
        $middle_name         = trim($_POST['middle_name'] ?? '') ?: null;
        $last_name           = trim($_POST['last_name'] ?? '');
        $suffix              = trim($_POST['suffix'] ?? '') ?: null;
        $post_nominal        = trim($_POST['post_nominal'] ?? '') ?: null;
        $sex                 = trim($_POST['sex'] ?? '') ?: null;
        $email               = trim($_POST['email'] ?? '') ?: null;

        $program_id          = filter_var($_POST['program_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $program_code        = trim($_POST['program_code'] ?? '') ?: null;
        $major               = trim($_POST['major'] ?? '') ?: null;
        $year_level          = filter_var($_POST['year_level'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $department_id       = filter_var($_POST['department_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;

        $office_id           = (int)($_POST['office_id'] ?? 0);
        $raw_trans_id        = trim($_POST['transaction_type_id'] ?? '');
        $raw_sub_id          = trim($_POST['sub_concern_id'] ?? '');
        $other_concern_text  = trim($_POST['other_concern_text'] ?? '') ?: null;
        $submitted_details   = $_POST['transaction_details'] ?? [];

        $data_consent        = (isset($_POST['data_consent']) && $_POST['data_consent'] === '1') ? 1 : 0;
        $is_priority         = (isset($_POST['is_priority']) && $_POST['is_priority'] === '1') ? 1 : 0;
        $priority_type       = $is_priority ? (strtoupper(trim($_POST['priority_type'] ?? '')) ?: 'OTHER') : null;

        if (empty($first_name) || empty($last_name) || empty($sex) || empty($email) || $office_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please complete all required personal details and office selection.']);
            exit();
        }

        // Category-specific required validation
        if ($visitor_type === 'STUDENT') {
            if (empty($program_id) || empty($year_level)) {
                echo json_encode(['success' => false, 'message' => 'Course / Program and Year Level are required for Student visitors.']);
                exit();
            }
        } elseif ($visitor_type === 'EMPLOYEE') {
            if ($department_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Department is required for Employee visitors.']);
                exit();
            }
        }

        if ($data_consent !== 1) {
            echo json_encode(['success' => false, 'message' => 'Data privacy authorization consent is required.']);
            exit();
        }

        // Parse candidate transaction IDs & check for "OTHERS"
        $raw_trans_ids = array_values(array_unique(array_filter(
            array_map('intval', explode(',', $raw_trans_id)),
            fn($val) => $val > 0
        )));
        $has_others = (strpos($raw_trans_id, 'OTHERS') !== false || !empty($other_concern_text));

        // Parse sub-concerns map
        $sub_concerns_map = [];
        if (!empty($raw_sub_id)) {
            $decoded = json_decode($raw_sub_id, true);
            if (is_array($decoded)) {
                $sub_concerns_map = $decoded;
            } else {
                $raw_ids = array_filter(array_map('intval', explode(',', $raw_sub_id)), fn($v) => $v > 0);
                if (!empty($raw_ids) && !empty($raw_trans_ids)) {
                    $sub_concerns_map[$raw_trans_ids[0]] = $raw_ids;
                }
            }
        }

        // Validate selected transaction types against the target office
        $trans_ids = [];
        $validTransLookup = [];

        if (!empty($raw_trans_ids)) {
            $inClause = implode(',', $raw_trans_ids);
            $stmtValidTrans = $db_connect->prepare("
                SELECT id, transaction_name, estimated_minutes 
                FROM transaction_types 
                WHERE office_id = ? AND flag_status = 0 AND id IN ($inClause)
            ");
            $stmtValidTrans->bind_param("i", $office_id);
            $stmtValidTrans->execute();
            $resValidTrans = $stmtValidTrans->get_result();

            while ($rowT = $resValidTrans->fetch_assoc()) {
                $tid = (int)$rowT['id'];
                $validTransLookup[$tid] = [
                    'name'              => $rowT['transaction_name'],
                    'estimated_minutes' => (int)($rowT['estimated_minutes'] ?? 5)
                ];
            }

            foreach ($raw_trans_ids as $tid) {
                if (isset($validTransLookup[$tid])) {
                    $trans_ids[] = $tid;
                }
            }
        }

        if (empty($trans_ids) && !$has_others) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one valid transaction concern or specify your concern under Others.']);
            exit();
        }

        // Fetch active sub-concerns & their individual estimated_minutes
        $validated_sub_map = [];
        $transHasActiveSubs = [];
        $validSubInfo = [];

        if (!empty($trans_ids)) {
            $inTrans = implode(',', $trans_ids);
            $stmtValidSubs = $db_connect->query("
                SELECT id, transaction_type_id, sub_concern_name, estimated_minutes 
                FROM transaction_sub_concerns 
                WHERE flag_status = 0 AND transaction_type_id IN ($inTrans)
            ");

            while ($rowS = $stmtValidSubs->fetch_assoc()) {
                $tId = (int)$rowS['transaction_type_id'];
                $sId = (int)$rowS['id'];
                $transHasActiveSubs[$tId] = true;
                $validSubInfo[$tId][$sId] = [
                    'name'              => $rowS['sub_concern_name'],
                    'estimated_minutes' => (int)($rowS['estimated_minutes'] ?? 5)
                ];
            }

            foreach ($trans_ids as $tid) {
                $submittedSubs = $sub_concerns_map[$tid] ?? ($sub_concerns_map[(string)$tid] ?? []);
                if (is_array($submittedSubs)) {
                    foreach ($submittedSubs as $sid) {
                        $sid = (int)$sid;
                        if ($sid > 0 && isset($validSubInfo[$tid][$sid])) {
                            $validated_sub_map[$tid][] = $sid;
                        }
                    }
                    if (!empty($validated_sub_map[$tid])) {
                        $validated_sub_map[$tid] = array_values(array_unique($validated_sub_map[$tid]));
                    }
                }

                // Enforce selecting at least one sub-concern if the primary transaction has sub-concerns
                if (!empty($transHasActiveSubs[$tid]) && empty($validated_sub_map[$tid])) {
                    $tName = $validTransLookup[$tid]['name'] ?? 'Selected service';
                    echo json_encode([
                        'success' => false,
                        'message' => "Please select at least one sub-transaction under \"{$tName}\"."
                    ]);
                    exit();
                }
            }
        }

        // Calculate total estimated minutes:
        // - If transaction has sub-concerns: sum selected sub-concerns' estimated_minutes
        // - If standalone transaction: use transaction_types.estimated_minutes
        $estimated_minutes = 0;
        foreach ($trans_ids as $tid) {
            if (!empty($transHasActiveSubs[$tid])) {
                foreach ($validated_sub_map[$tid] as $sid) {
                    $estimated_minutes += $validSubInfo[$tid][$sid]['estimated_minutes'];
                }
            } else {
                $estimated_minutes += $validTransLookup[$tid]['estimated_minutes'];
            }
        }

        if ($has_others) {
            $estimated_minutes += 10;
        }
        if ($estimated_minutes <= 0) {
            $estimated_minutes = 5;
        }

        // Fetch configured dynamic detail fields for selected transactions / sub-concerns
        $requiredDetailItems = [];
        if (!empty($trans_ids)) {
            $inTrans = implode(',', $trans_ids);
            $detailSql = "
                SELECT
                    h.transaction_type_id,
                    h.sub_concern_id,
                    i.id AS item_id,
                    i.field_title,
                    i.detail_mode,
                    i.input_data_type
                FROM transaction_detail_fields h
                INNER JOIN transaction_detail_field_items i
                    ON i.detail_field_id = h.id
                   AND i.flag_status = 0
                WHERE h.transaction_type_id IN ({$inTrans})
                  AND h.flag_status = 0
                ORDER BY h.id DESC, i.field_order ASC
            ";
            $resDetails = $db_connect->query($detailSql);
            while ($dRow = $resDetails->fetch_assoc()) {
                $tId = (int)$dRow['transaction_type_id'];
                $sId = $dRow['sub_concern_id'] !== null ? (int)$dRow['sub_concern_id'] : 0;

                if ($sId === 0 && empty($transHasActiveSubs[$tId])) {
                    $scopeKey = "type_{$tId}";
                    $requiredDetailItems[$scopeKey][] = $dRow;
                } elseif ($sId > 0 && in_array($sId, $validated_sub_map[$tId] ?? [], true)) {
                    $scopeKey = "sub_{$sId}";
                    $requiredDetailItems[$scopeKey][] = $dRow;
                }
            }
            $resDetails->free();
        }

        // Validate submitted detail field answers
        $validatedDetailAnswers = [];
        foreach ($requiredDetailItems as $scopeKey => $fieldsList) {
            $submittedScope = is_array($submitted_details[$scopeKey] ?? null) ? $submitted_details[$scopeKey] : [];
            foreach ($fieldsList as $fieldMeta) {
                $itemId = (int)$fieldMeta['item_id'];
                $title  = (string)$fieldMeta['field_title'];
                $mode   = strtoupper((string)$fieldMeta['detail_mode']);
                $fmt    = strtoupper((string)($fieldMeta['input_data_type'] ?? 'CHAR'));

                $rawEntry = $submittedScope[$itemId] ?? null;
                $ansVal   = is_array($rawEntry) ? trim((string)($rawEntry['value'] ?? '')) : trim((string)$rawEntry);
                $choiceId = is_array($rawEntry) ? (filter_var($rawEntry['choice_id'] ?? null, FILTER_VALIDATE_INT) ?: null) : null;

                if ($ansVal === '') {
                    echo json_encode([
                        'success' => false,
                        'message' => "Please fill out the required detail field: \"{$title}\"."
                    ]);
                    exit();
                }

                if ($mode === 'INPUT' && $fmt === 'INT' && !preg_match('/^-?\d+$/', $ansVal)) {
                    echo json_encode([
                        'success' => false,
                        'message' => "\"{$title}\" must be a valid whole number."
                    ]);
                    exit();
                }

                $validatedDetailAnswers[$scopeKey][] = [
                    'field_item_id' => $itemId,
                    'field_title'   => $title,
                    'detail_mode'   => in_array($mode, ['INPUT', 'SELECT'], true) ? $mode : 'INPUT',
                    'choice_id'     => $choiceId,
                    'answer_value'  => $ansVal
                ];
            }
        }

        // Normalize visitor metadata by visitor_type
        if ($visitor_type === 'STUDENT') {
            $post_nominal = null;
            if (!empty($program_id)) {
                $stmtP = $db_connect->prepare("
                    SELECT department_id, program_code, major 
                    FROM programs 
                    WHERE program_id = ? AND flag_status = 0
                    LIMIT 1
                ");
                $stmtP->bind_param("i", $program_id);
                $stmtP->execute();
                $resP = $stmtP->get_result()->fetch_assoc();
                if ($resP) {
                    if (empty($department_id)) {
                        $department_id = (int)($resP['department_id'] ?? 0);
                    }
                    if (empty($program_code)) {
                        $program_code = $resP['program_code'] ?? null;
                    }
                    if (empty($major)) {
                        $major = $resP['major'] ?? null;
                    }
                } else {
                    $program_id = null;
                }
            }
        } elseif ($visitor_type === 'EMPLOYEE') {
            $program_id   = null;
            $program_code = null;
            $major        = null;
            $year_level   = null;
        } else { // GUEST
            $user_id       = null;
            $reference_id  = null;
            $post_nominal  = null;
            $department_id = 0;
            $program_id    = null;
            $program_code  = null;
            $major         = null;
            $year_level    = null;
        }

        $primary_trans_id = !empty($trans_ids) ? $trans_ids[0] : null;

        $db_connect->begin_transaction();

        try {
            // STEP A: Lock office row first to verify active office & serialize daily ticket sequence
            $today = date('Y-m-d');

            $stmtOffice = $db_connect->prepare("
                SELECT office_name, COALESCE(NULLIF(office_code, ''), 'Q') AS prefix
                FROM office
                WHERE office_id = ? AND flag_status = 0
                FOR UPDATE
            ");
            $stmtOffice->bind_param("i", $office_id);
            $stmtOffice->execute();
            $resOffice = $stmtOffice->get_result()->fetch_assoc();

            if (!$resOffice) {
                $db_connect->rollback();
                echo json_encode(['success' => false, 'message' => 'Selected office is unavailable or inactive.']);
                exit();
            }

            // STEP B: Insert primary header record into logbooks
            $stmtLog = $db_connect->prepare("
                INSERT INTO logbooks (
                    visitor_type, reference_id, user_id, first_name, middle_name, last_name,
                    suffix, post_nominal, sex, email, program_id, program_code, major,
                    year_level, department_id, office_id, transaction_type_id, other_concern_text,
                    is_priority, priority_type, data_consent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtLog->bind_param(
                "ssisssssssissiiiisisi",
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

            // STEP C: Populate Junction Table (logbook_transaction_items) & Dynamic Detail Answers
            $stmtItem = $db_connect->prepare("
                INSERT INTO logbook_transaction_items (logbook_id, transaction_type_id, sub_concern_id) 
                VALUES (?, ?, ?)
            ");

            $stmtItemDetail = $db_connect->prepare("
                INSERT INTO logbook_transaction_item_details (
                    logbook_item_id, field_item_id, field_title, detail_mode, choice_id, answer_value
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            if (!empty($trans_ids)) {
                foreach ($trans_ids as $t_id) {
                    $validSubs = $validated_sub_map[$t_id] ?? [];

                    if (!empty($validSubs)) {
                        foreach ($validSubs as $s_id) {
                            $stmtItem->bind_param("iii", $logbook_id, $t_id, $s_id);
                            $stmtItem->execute();
                            $logbook_item_id = (int)$db_connect->insert_id;

                            foreach ($validatedDetailAnswers["sub_{$s_id}"] ?? [] as $ans) {
                                $stmtItemDetail->bind_param(
                                    "iissis",
                                    $logbook_item_id,
                                    $ans['field_item_id'],
                                    $ans['field_title'],
                                    $ans['detail_mode'],
                                    $ans['choice_id'],
                                    $ans['answer_value']
                                );
                                $stmtItemDetail->execute();
                            }
                        }
                    } else {
                        $nullSub = null;
                        $stmtItem->bind_param("iii", $logbook_id, $t_id, $nullSub);
                        $stmtItem->execute();
                        $logbook_item_id = (int)$db_connect->insert_id;

                        foreach ($validatedDetailAnswers["type_{$t_id}"] ?? [] as $ans) {
                            $stmtItemDetail->bind_param(
                                "iissis",
                                $logbook_item_id,
                                $ans['field_item_id'],
                                $ans['field_title'],
                                $ans['detail_mode'],
                                $ans['choice_id'],
                                $ans['answer_value']
                            );
                            $stmtItemDetail->execute();
                        }
                    }
                }
            } else {
                $nullTrans = null;
                $nullSub = null;
                $stmtItem->bind_param("iii", $logbook_id, $nullTrans, $nullSub);
                $stmtItem->execute();
            }

            // STEP D: Resolve daily sequence & insert queue ticket
            $stmtSeq = $db_connect->prepare("
                SELECT COALESCE(MAX(queue_sequence), 0) + 1 AS next_seq
                FROM queue_tickets
                WHERE office_id = ? AND queue_date = ?
            ");
            $stmtSeq->bind_param("is", $office_id, $today);
            $stmtSeq->execute();
            $resSeq = $stmtSeq->get_result()->fetch_assoc();

            $office_name   = $resOffice['office_name'] ?? 'Office';
            $base_prefix   = !empty($resOffice['prefix']) ? $resOffice['prefix'] : 'Q';
            $prefix        = $is_priority ? "P-" . $base_prefix : $base_prefix;
            $sequence      = (int)($resSeq['next_seq'] ?? 1);
            $ticket_number = sprintf("%s-%03d", $prefix, $sequence);

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
            error_log('logbook_checkin error: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Check-in failed. Please try again or ask staff for assistance.']);
            exit();
        }

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid or missing action.']);
        exit();
}