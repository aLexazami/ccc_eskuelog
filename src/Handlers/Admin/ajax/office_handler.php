<?php
// src/Handlers/Admin/ajax/office_handler.php

/** @var \mysqli $db_connect */
global $db_connect;
$db_connect = $db_connect ?? null;

header('Content-Type: application/json; charset=utf-8');

if (!$db_connect) {
    echo json_encode(['success' => false, 'message' => 'Database connection unavailable.']);
    exit();
}

/**
 * Normalize stored detail format.
 */
function officeNormalizeDetailFormat(string $mode, $format): string
{
    if ($mode === 'SELECT') {
        $format = strtolower(trim((string)$format));
        return in_array($format, ['numeric', 'lower_alpha', 'upper_alpha'], true) ? $format : 'numeric';
    }
    $format = strtoupper(trim((string)$format));
    return in_array($format, ['CHAR', 'INT', 'VAR'], true) ? $format : 'CHAR';
}

/**
 * Fetch normalized detail fields for all active transactions in an office.
 */
function fetchOfficeNormalizedDetailMaps(mysqli $db, array $typeIds): array
{
    if (empty($typeIds)) {
        return ['direct' => [], 'sub' => []];
    }

    $inList = implode(',', array_map('intval', $typeIds));

    $sql = "
        SELECT
            h.id AS header_id,
            h.transaction_type_id,
            h.sub_concern_id,
            i.id AS item_id,
            i.field_order,
            i.detail_mode,
            i.input_data_type,
            i.separator_style,
            i.field_title,
            c.id AS choice_id,
            c.choice_order,
            c.choice_value
        FROM transaction_detail_fields h
        LEFT JOIN transaction_detail_field_items i
            ON i.detail_field_id = h.id
           AND i.flag_status = 0
        LEFT JOIN transaction_detail_field_choices c
            ON c.field_item_id = i.id
        WHERE h.transaction_type_id IN ({$inList})
          AND h.flag_status = 0
        ORDER BY
            h.id DESC,
            i.field_order ASC,
            c.choice_order ASC
    ";

    $result = $db->query($sql);
    if (!$result) {
        return ['direct' => [], 'sub' => []];
    }

    $headers = [];
    $selectedHeaders = [];

    while ($row = $result->fetch_assoc()) {
        $typeId   = (int)$row['transaction_type_id'];
        $subId    = $row['sub_concern_id'] !== null ? (int)$row['sub_concern_id'] : null;
        $scopeKey = $subId === null ? "type:{$typeId}" : "sub:{$subId}";
        $headerId = (int)$row['header_id'];

        if (!isset($selectedHeaders[$scopeKey])) {
            $selectedHeaders[$scopeKey] = $headerId;
            $headers[$headerId] = [
                'type_id' => $typeId,
                'sub_id'  => $subId,
                'fields'  => []
            ];
        }

        if ($selectedHeaders[$scopeKey] !== $headerId || $row['item_id'] === null) {
            continue;
        }

        $itemId = (int)$row['item_id'];
        if (!isset($headers[$headerId]['fields'][$itemId])) {
            $mode = strtoupper((string)$row['detail_mode']);
            if (!in_array($mode, ['INPUT', 'SELECT'], true)) {
                $mode = 'INPUT';
            }
            $rawFormat = $mode === 'SELECT' ? $row['separator_style'] : $row['input_data_type'];

            $headers[$headerId]['fields'][$itemId] = [
                'id'      => $itemId,
                'order'   => (int)$row['field_order'],
                'mode'    => $mode,
                'format'  => officeNormalizeDetailFormat($mode, $rawFormat),
                'title'   => (string)$row['field_title'],
                'choices' => []
            ];
        }

        if ($row['choice_id'] !== null) {
            $headers[$headerId]['fields'][$itemId]['choices'][] = [
                'id'    => (int)$row['choice_id'],
                'value' => (string)$row['choice_value']
            ];
        }
    }
    $result->free();

    $directMap = [];
    $subMap    = [];

    foreach ($headers as $headerId => $header) {
        $fields = array_values($header['fields']);
        usort($fields, static fn($a, $b) => $a['order'] <=> $b['order']);

        foreach ($fields as &$f) {
            unset($f['order']);
        }
        unset($f);

        if (empty($fields)) {
            continue;
        }

        $payload = [
            'id'          => (int)$headerId,
            'has_details' => true,
            'count'       => count($fields),
            'fields'      => $fields
        ];

        if ($header['sub_id'] === null) {
            $directMap[$header['type_id']] = $payload;
        } else {
            $subMap[$header['sub_id']] = $payload;
        }
    }

    return ['direct' => $directMap, 'sub' => $subMap];
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'fetch_office_transactions':
        $office_id = (int)($_POST['office_id'] ?? 0);

        if ($office_id === 0) {
            echo json_encode(['success' => false, 'message' => 'Office ID is required.']);
            exit();
        }

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

            $transactions = [];
            $typeIds = [];

            while ($row = $result->fetch_assoc()) {
                $tid = (int)$row['id'];
                $row['id']                = $tid;
                $row['estimated_minutes'] = (int)($row['estimated_minutes'] ?? 0);
                $row['direct_details']    = null;
                $row['sub_concerns']      = [];
                $transactions[$tid]       = $row;
                $typeIds[]                = $tid;
            }
            $stmt->close();

            if (!empty($typeIds)) {
                $detailMaps = fetchOfficeNormalizedDetailMaps($db_connect, $typeIds);

                foreach ($detailMaps['direct'] as $tid => $details) {
                    if (isset($transactions[$tid])) {
                        $transactions[$tid]['direct_details'] = $details;
                    }
                }

                $inList = implode(',', $typeIds);
                $sub_query = "
                    SELECT id, transaction_type_id, sub_concern_name, estimated_minutes 
                    FROM transaction_sub_concerns 
                    WHERE transaction_type_id IN ({$inList}) AND flag_status = 0 
                    ORDER BY id ASC
                ";
                if ($sub_result = $db_connect->query($sub_query)) {
                    while ($sub_row = $sub_result->fetch_assoc()) {
                        $tid = (int)$sub_row['transaction_type_id'];
                        $sid = (int)$sub_row['id'];
                        if (!isset($transactions[$tid])) {
                            continue;
                        }
                        $transactions[$tid]['sub_concerns'][] = [
                            'id'                => $sid,
                            'sub_concern_name'  => $sub_row['sub_concern_name'],
                            'estimated_minutes' => (int)($sub_row['estimated_minutes'] ?? 5),
                            'parsed_details'    => $detailMaps['sub'][$sid] ?? null
                        ];
                    }
                    $sub_result->free();
                }
            }

            echo json_encode([
                'success' => true,
                'data'    => array_values($transactions)
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Failed to retrieve transactions.']);
        exit();

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit();
}