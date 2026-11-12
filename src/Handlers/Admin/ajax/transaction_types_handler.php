<?php
// src/Handlers/Admin/ajax/transaction_types_handler.php

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

/**
 * Normalize & encode detail payload using implode('::') + json_encode()
 */
function prepareDetailRecord(?array $detailPayload): ?array
{
    if (empty($detailPayload) || ($detailPayload['enabled'] ?? '0') !== '1') {
        return null;
    }

    $mode = strtoupper(trim((string)($detailPayload['mode'] ?? 'INPUT')));
    $mode = ($mode === 'SELECT') ? 'SELECT' : 'INPUT';

    $cleanTitles = [];
    foreach ((array)($detailPayload['titles'] ?? []) as $t) {
        $val = trim(str_replace('::', ' ', (string)$t));
        if ($val !== '') {
            $cleanTitles[] = $val;
        }
    }

    if (empty($cleanTitles)) {
        return null;
    }

    // Force double colon (::) separator via implode
    $delimitedTitles = implode('::', $cleanTitles);
    $inputDataType   = ($mode === 'INPUT') ? strtoupper(trim((string)($detailPayload['input_data_type'] ?? 'CHAR'))) : null;
    $separatorStyle  = ($mode === 'SELECT') ? trim((string)($detailPayload['separator_style'] ?? 'numeric')) : null;
    $count           = count($cleanTitles);

    $jsonPayload = json_encode([
        'has_details'      => true,
        'detail_mode'      => $mode,
        'input_data_type'  => $inputDataType,
        'separator_style'  => $separatorStyle,
        'delimiter'        => '::',
        'count'            => $count,
        'delimited_titles' => $delimitedTitles
    ], JSON_UNESCAPED_UNICODE);

    return [
        'detail_mode'      => $mode,
        'input_data_type'  => $inputDataType,
        'separator_style'  => $separatorStyle,
        'field_count'      => $count,
        'delimited_titles' => $delimitedTitles,
        'detail_json'      => $jsonPayload
    ];
}

/**
 * Save or replace a detail row in `transaction_detail_fields`
 */
function syncDetailFieldRow(mysqli $db, int $typeId, ?int $subConcernId, ?array $detailPayload): void
{
    if ($subConcernId === null) {
        $del = $db->prepare("DELETE FROM transaction_detail_fields WHERE transaction_type_id = ? AND sub_concern_id IS NULL");
        $del->bind_param("i", $typeId);
        $del->execute();
        $del->close();
    } else {
        $del = $db->prepare("DELETE FROM transaction_detail_fields WHERE transaction_type_id = ? AND sub_concern_id = ?");
        $del->bind_param("ii", $typeId, $subConcernId);
        $del->execute();
        $del->close();
    }

    $record = prepareDetailRecord($detailPayload);
    if ($record === null) {
        return;
    }

    $ins = $db->prepare("
        INSERT INTO transaction_detail_fields
            (transaction_type_id, sub_concern_id, detail_mode, input_data_type, separator_style, field_count, delimited_titles, detail_json, flag_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
    ");
    $ins->bind_param(
        "iisssiss",
        $typeId,
        $subConcernId,
        $record['detail_mode'],
        $record['input_data_type'],
        $record['separator_style'],
        $record['field_count'],
        $record['delimited_titles'],
        $record['detail_json']
    );
    $ins->execute();
    $ins->close();
}

/**
 * Decode JSON + explode('::') back into formatted items for UI & Kiosk
 */
function parseDetailRow(array $row): array
{
    $decoded = json_decode((string)($row['detail_json'] ?? '{}'), true);
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $delimited = (string)($decoded['delimited_titles'] ?? $row['delimited_titles'] ?? '');
    $titles    = ($delimited !== '') ? explode('::', $delimited) : [];
    $mode      = strtoupper((string)($decoded['detail_mode'] ?? $row['detail_mode'] ?? 'INPUT'));
    $style     = (string)($decoded['separator_style'] ?? $row['separator_style'] ?? 'numeric');

    $formattedItems = [];
    foreach ($titles as $idx => $title) {
        $prefix = '';
        if ($mode === 'SELECT') {
            if ($style === 'lower_alpha') {
                $prefix = chr(97 + ($idx % 26)) . '. ';
            } elseif ($style === 'upper_alpha') {
                $prefix = chr(65 + ($idx % 26)) . '. ';
            } else {
                $prefix = ($idx + 1) . '. ';
            }
        }
        $formattedItems[] = [
            'index'   => $idx + 1,
            'prefix'  => $prefix,
            'title'   => $title,
            'display' => $prefix . $title
        ];
    }

    return [
        'id'               => (int)$row['id'],
        'has_details'      => true,
        'detail_mode'      => $mode,
        'input_data_type'  => $decoded['input_data_type'] ?? $row['input_data_type'],
        'separator_style'  => $style,
        'delimiter'        => '::',
        'count'            => count($titles),
        'delimited_titles' => $delimited,
        'titles'           => $titles,
        'formatted_items'  => $formattedItems
    ];
}

try {
    switch ($action) {

        // ------------------------------------------------------------------
        // FETCH UNIFIED SERVICES + DIRECT DETAILS + SUB-TRANSACTIONS
        // ------------------------------------------------------------------
        case 'fetch_transaction_types':
            $office_id = (int)($_POST['office_id'] ?? 0);
            $whereClause = $office_id > 0 ? " WHERE tt.office_id = ? " : "";

            $sql = "
                SELECT
                    tt.id,
                    tt.office_id,
                    o.office_code,
                    o.office_name,
                    tt.transaction_name,
                    tt.estimated_minutes,
                    tt.flag_status,
                    tt.date_modify
                FROM transaction_types tt
                INNER JOIN office o ON tt.office_id = o.office_id
                {$whereClause}
                ORDER BY o.office_name ASC, tt.transaction_name ASC
            ";

            $stmt = $db_connect->prepare($sql);
            if ($office_id > 0) {
                $stmt->bind_param("i", $office_id);
            }
            $stmt->execute();
            $res = $stmt->get_result();

            $types = [];
            $typeIds = [];
            while ($row = $res->fetch_assoc()) {
                $tid = (int)$row['id'];
                $row['direct_details'] = null; // Details attached directly to primary service
                $row['sub_concerns'] = [];
                $row['sub_concerns_count'] = 0;
                $types[$tid] = $row;
                $typeIds[] = $tid;
            }
            $stmt->close();

            if (!empty($typeIds)) {
                $inList = implode(',', $typeIds);

                // 1. Fetch all detail configs from `transaction_detail_fields`
                $directDetailsMap = [];
                $subDetailsMap    = [];
                $detRes = $db_connect->query("
                    SELECT * FROM transaction_detail_fields
                    WHERE transaction_type_id IN ({$inList}) AND flag_status = 0
                ");
                while ($dRow = $detRes->fetch_assoc()) {
                    $parsed = parseDetailRow($dRow);
                    if ($dRow['sub_concern_id'] === null) {
                        $directDetailsMap[(int)$dRow['transaction_type_id']] = $parsed;
                    } else {
                        $subDetailsMap[(int)$dRow['sub_concern_id']] = $parsed;
                    }
                }

                // Attach direct details to primary services
                foreach ($directDetailsMap as $tid => $parsedDet) {
                    if (isset($types[$tid])) {
                        $types[$tid]['direct_details'] = $parsedDet;
                    }
                }

                // 2. Fetch all sub-transactions and attach their detail configs and estimated minutes
                $subRes = $db_connect->query("
                    SELECT id, transaction_type_id, sub_concern_name, estimated_minutes, flag_status
                    FROM transaction_sub_concerns
                    WHERE transaction_type_id IN ({$inList})
                    ORDER BY id ASC
                ");
                while ($sub = $subRes->fetch_assoc()) {
                    $tid = (int)$sub['transaction_type_id'];
                    $sid = (int)$sub['id'];
                    if (isset($types[$tid])) {
                        $sub['estimated_minutes'] = (int)($sub['estimated_minutes'] ?? 5);
                        $sub['parsed_details'] = $subDetailsMap[$sid] ?? null;
                        $types[$tid]['sub_concerns'][] = $sub;
                        $types[$tid]['sub_concerns_count']++;
                    }
                }
            }

            echo json_encode(['success' => true, 'data' => array_values($types)]);
            exit();

        // ------------------------------------------------------------------
        // CREATE UNIFIED TRANSACTION TYPE + DIRECT DETAILS OR SUB-TRANSACTIONS
        // ------------------------------------------------------------------
        case 'submitUnifiedType':
            $office_id         = (int)($_POST['office_id'] ?? 0);
            $transaction_name  = trim((string)($_POST['transaction_name'] ?? ''));
            $has_sub_concerns  = (string)($_POST['has_sub_concerns'] ?? 'no');
            $estimated_minutes = $has_sub_concerns === 'yes' ? 0 : (int)($_POST['estimated_minutes'] ?? 5);
            $flag_status       = (int)($_POST['flag_status'] ?? 0);
            $direct_details    = $_POST['direct_details'] ?? null;
            $sub_concerns      = $_POST['sub_concerns'] ?? [];

            if ($office_id <= 0 || $transaction_name === '') {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Please select an office and enter a transaction service name.']);
                exit();
            }

            $db_connect->begin_transaction();

            $stmtType = $db_connect->prepare("
                INSERT INTO transaction_types (office_id, transaction_name, estimated_minutes, flag_status)
                VALUES (?, ?, ?, ?)
            ");
            $stmtType->bind_param("isii", $office_id, $transaction_name, $estimated_minutes, $flag_status);
            $stmtType->execute();
            $newTypeId = (int)$stmtType->insert_id;
            $stmtType->close();

            if ($has_sub_concerns === 'yes' && is_array($sub_concerns)) {
                $stmtSub = $db_connect->prepare("
                    INSERT INTO transaction_sub_concerns (transaction_type_id, sub_concern_name, estimated_minutes, flag_status)
                    VALUES (?, ?, ?, 0)
                ");
                foreach ($sub_concerns as $subItem) {
                    $subName   = trim((string)($subItem['name'] ?? ''));
                    $subEstMin = (int)($subItem['estimated_minutes'] ?? 5);
                    if ($subName === '') continue;

                    $stmtSub->bind_param("isi", $newTypeId, $subName, $subEstMin);
                    $stmtSub->execute();
                    $newSubId = (int)$stmtSub->insert_id;

                    syncDetailFieldRow($db_connect, $newTypeId, $newSubId, $subItem['details'] ?? null);
                }
                $stmtSub->close();
            } else {
                // Standalone Primary Service -> save optional direct details (sub_concern_id = NULL)
                syncDetailFieldRow($db_connect, $newTypeId, null, $direct_details);
            }

            $db_connect->commit();
            echo json_encode(['msg_status' => true, 'msg_response' => 'Transaction service configuration saved successfully.']);
            exit();

        // ------------------------------------------------------------------
        // UPDATE UNIFIED TRANSACTION TYPE + SYNC DETAILS & SUB-TRANSACTIONS
        // ------------------------------------------------------------------
        case 'submitUpdateUnifiedType':
            $type_id           = (int)($_POST['type_id'] ?? 0);
            $office_id         = (int)($_POST['office_id'] ?? 0);
            $transaction_name  = trim((string)($_POST['transaction_name'] ?? ''));
            $flag_status       = (int)($_POST['flag_status'] ?? 0);
            $has_sub_concerns  = (string)($_POST['has_sub_concerns'] ?? 'no');
            $estimated_minutes = $has_sub_concerns === 'yes' ? 0 : (int)($_POST['estimated_minutes'] ?? 5);
            $direct_details    = $_POST['direct_details'] ?? null;
            $sub_concerns      = $_POST['sub_concerns'] ?? [];

            if ($type_id <= 0 || $office_id <= 0 || $transaction_name === '') {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid transaction service data provided.']);
                exit();
            }

            $db_connect->begin_transaction();

            $stmtUpd = $db_connect->prepare("
                UPDATE transaction_types
                SET office_id = ?, transaction_name = ?, estimated_minutes = ?, flag_status = ?, date_modify = NOW()
                WHERE id = ?
            ");
            $stmtUpd->bind_param("isiii", $office_id, $transaction_name, $estimated_minutes, $flag_status, $type_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            $existingIds = [];
            $resEx = $db_connect->query("SELECT id FROM transaction_sub_concerns WHERE transaction_type_id = {$type_id}");
            while ($r = $resEx->fetch_assoc()) {
                $existingIds[] = (int)$r['id'];
            }

            $keptIds = [];
            if ($has_sub_concerns === 'yes' && is_array($sub_concerns)) {
                // Clear any standalone direct details since service now uses sub-transactions
                syncDetailFieldRow($db_connect, $type_id, null, null);

                $stmtSubIns = $db_connect->prepare("INSERT INTO transaction_sub_concerns (transaction_type_id, sub_concern_name, estimated_minutes, flag_status) VALUES (?, ?, ?, 0)");
                $stmtSubUpd = $db_connect->prepare("UPDATE transaction_sub_concerns SET sub_concern_name = ?, estimated_minutes = ? WHERE id = ? AND transaction_type_id = ?");

                foreach ($sub_concerns as $subItem) {
                    $subId     = (int)($subItem['id'] ?? 0);
                    $subName   = trim((string)($subItem['name'] ?? ''));
                    $subEstMin = (int)($subItem['estimated_minutes'] ?? 5);
                    if ($subName === '') continue;

                    if ($subId > 0 && in_array($subId, $existingIds, true)) {
                        $stmtSubUpd->bind_param("siii", $subName, $subEstMin, $subId, $type_id);
                        $stmtSubUpd->execute();
                        $targetSubId = $subId;
                    } else {
                        $stmtSubIns->bind_param("isi", $type_id, $subName, $subEstMin);
                        $stmtSubIns->execute();
                        $targetSubId = (int)$stmtSubIns->insert_id;
                    }

                    $keptIds[] = $targetSubId;
                    syncDetailFieldRow($db_connect, $type_id, $targetSubId, $subItem['details'] ?? null);
                }
                $stmtSubIns->close();
                $stmtSubUpd->close();
            } else {
                // Standalone Primary Service -> save direct details
                syncDetailFieldRow($db_connect, $type_id, null, $direct_details);
            }

            $toDelete = array_diff($existingIds, $keptIds);
            if (!empty($toDelete)) {
                $delList = implode(',', array_map('intval', $toDelete));
                $db_connect->query("DELETE FROM transaction_sub_concerns WHERE id IN ({$delList}) AND transaction_type_id = {$type_id}");
            }

            $db_connect->commit();
            echo json_encode(['msg_status' => true, 'msg_response' => 'Transaction service and details updated successfully.']);
            exit();

        // ------------------------------------------------------------------
        // DELETE TRANSACTION TYPE (CASCADES SUB-TRANSACTIONS & DETAIL FIELDS)
        // ------------------------------------------------------------------
        case 'delete_transaction_type':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid transaction type ID.']);
                exit();
            }

            $stmt = $db_connect->prepare("DELETE FROM transaction_types WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            echo json_encode(['msg_status' => true, 'msg_response' => 'Transaction service deleted successfully.']);
            exit();

        default:
            echo json_encode(['msg_status' => false, 'msg_response' => 'Invalid action request.']);
            exit();
    }
} catch (Exception $e) {
    if ($db_connect && $db_connect->errno === 0) {
        @$db_connect->rollback();
    }
    echo json_encode(['msg_status' => false, 'msg_response' => 'Handler Error: ' . $e->getMessage()]);
    exit();
}