
<?php
// src/Handlers/Admin/ajax/transaction_types_handler.php

/** @var mysqli|null $db_connect */
global $db_connect;

header('Content-Type: application/json; charset=utf-8');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$db_connect = $db_connect ?? null;
$transactionOpen = false;

const DETAIL_INPUT_FORMATS  = ['CHAR', 'INT', 'VAR'];
const DETAIL_SELECT_FORMATS = ['numeric', 'lower_alpha', 'upper_alpha'];
const DETAIL_MAX_FIELDS     = 20;
const DETAIL_MAX_TITLE_LEN  = 100;
const DETAIL_MAX_CHOICES    = 50;
const DETAIL_MAX_CHOICE_LEN = 100;

/**
 * Safe JSON response helper.
 */
function transactionTypesJsonResponse(array $response): void
{
    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit();
}

/**
 * Validation exception for user input.
 */
class TransactionTypesValidationException extends Exception
{
}

/**
 * Normalize a stored field format.
 */
function normalizeDetailFormat(string $mode, $format): string
{
    if ($mode === 'SELECT') {
        $format = strtolower(trim((string)$format));

        return in_array($format, DETAIL_SELECT_FORMATS, true)
            ? $format
            : 'numeric';
    }

    $format = strtoupper(trim((string)$format));

    return in_array($format, DETAIL_INPUT_FORMATS, true)
        ? $format
        : 'CHAR';
}

/**
 * Validate and normalize a detail configuration.
 *
 * Existing field payload:
 *
 * [
 *     'id' => 10,
 *     'mode' => 'SELECT',
 *     'format' => 'numeric',
 *     'title' => 'Category',
 *     'choices' => [
 *         ['id' => 20, 'value' => 'Option A'],
 *         ['id' => 21, 'value' => 'Option B']
 *     ]
 * ]
 *
 * New fields and choices may omit their IDs.
 *
 * Returns null when details are disabled.
 */
function prepareDetailRecord(
    $detailPayload,
    string $label = 'Details'
): ?array {
    if (
        !is_array($detailPayload) ||
        (string)($detailPayload['enabled'] ?? '0') !== '1'
    ) {
        return null;
    }

    $rawFields = $detailPayload['fields'] ?? null;

    if (!is_array($rawFields) || count($rawFields) === 0) {
        throw new TransactionTypesValidationException(
            "{$label}: add at least one detail field, or remove the details."
        );
    }

    if (count($rawFields) > DETAIL_MAX_FIELDS) {
        throw new TransactionTypesValidationException(
            "{$label}: a maximum of " . DETAIL_MAX_FIELDS . " detail fields is allowed."
        );
    }

    $cleanFields = [];
    $submittedFieldIds = [];

    foreach (array_values($rawFields) as $index => $field) {
        $fieldNumber = $index + 1;

        if (!is_array($field)) {
            throw new TransactionTypesValidationException(
                "{$label}: detail field #{$fieldNumber} is invalid."
            );
        }

        $fieldId = isset($field['id']) && (int)$field['id'] > 0
            ? (int)$field['id']
            : null;

        if ($fieldId !== null) {
            if (in_array($fieldId, $submittedFieldIds, true)) {
                throw new TransactionTypesValidationException(
                    "{$label}: a detail field ID was submitted more than once."
                );
            }

            $submittedFieldIds[] = $fieldId;
        }

        $mode = strtoupper(trim((string)($field['mode'] ?? '')));

        if (!in_array($mode, ['INPUT', 'SELECT'], true)) {
            throw new TransactionTypesValidationException(
                "{$label}: detail field #{$fieldNumber} has an invalid Data Type Option."
            );
        }

        $format = trim((string)($field['format'] ?? ''));

        if ($mode === 'INPUT') {
            $format = strtoupper($format);

            if (!in_array($format, DETAIL_INPUT_FORMATS, true)) {
                throw new TransactionTypesValidationException(
                    "{$label}: detail field #{$fieldNumber} has an invalid Input Format Type."
                );
            }
        } else {
            $format = strtolower($format);

            if (!in_array($format, DETAIL_SELECT_FORMATS, true)) {
                throw new TransactionTypesValidationException(
                    "{$label}: detail field #{$fieldNumber} has an invalid Option style."
                );
            }
        }

        $title = trim(
            (string)preg_replace(
                '/\s+/u',
                ' ',
                (string)($field['title'] ?? '')
            )
        );

        if ($title === '') {
            throw new TransactionTypesValidationException(
                "{$label}: detail field #{$fieldNumber} needs a title name."
            );
        }

        if (mb_strlen($title) > DETAIL_MAX_TITLE_LEN) {
            throw new TransactionTypesValidationException(
                "{$label}: the title of detail field #{$fieldNumber} must not exceed "
                . DETAIL_MAX_TITLE_LEN . " characters."
            );
        }

        $choices = [];
        $choiceIds = [];

        if ($mode === 'SELECT') {
            /*
             * Prefer choices because they can contain existing choice IDs.
             * choices_text remains supported for older payload formats.
             */
            $rawChoices = $field['choices']
                ?? ($field['choices_text'] ?? []);

            if (is_string($rawChoices)) {
                $rawChoices = preg_split(
                    '/\r\n|\r|\n/',
                    $rawChoices
                );
            }

            if (!is_array($rawChoices)) {
                throw new TransactionTypesValidationException(
                    "{$label}: Select field #{$fieldNumber} must contain a valid choices list."
                );
            }

            $submittedChoiceIds = [];

            foreach ($rawChoices as $rawChoice) {
                $choiceId = 0;

                /*
                 * Existing choice:
                 * ['id' => 20, 'value' => 'Option A']
                 *
                 * New choice:
                 * ['value' => 'Option C']
                 *
                 * Legacy choice:
                 * 'Option A'
                 */
                if (is_array($rawChoice)) {
                    $choiceId = (int)($rawChoice['id'] ?? 0);

                    $rawChoice = $rawChoice['value'] ?? '';
                }

                $choice = trim((string)$rawChoice);

                if ($choice === '') {
                    continue;
                }

                if (mb_strlen($choice) > DETAIL_MAX_CHOICE_LEN) {
                    throw new TransactionTypesValidationException(
                        "{$label}: each choice in Select field #{$fieldNumber} must not exceed "
                        . DETAIL_MAX_CHOICE_LEN . " characters."
                    );
                }

                if ($choiceId > 0) {
                    if (in_array($choiceId, $submittedChoiceIds, true)) {
                        throw new TransactionTypesValidationException(
                            "{$label}: a choice ID was submitted more than once in field #{$fieldNumber}."
                        );
                    }

                    $submittedChoiceIds[] = $choiceId;
                }

                $choices[] = $choice;
                $choiceIds[] = $choiceId;
            }

            if (
                count($choices) < 2 ||
                count($choices) > DETAIL_MAX_CHOICES
            ) {
                throw new TransactionTypesValidationException(
                    "{$label}: Select field #{$fieldNumber} needs between 2 and "
                    . DETAIL_MAX_CHOICES . " choices."
                );
            }

            $normalizedChoices = array_map(
                static function ($value) {
                    return mb_strtolower($value, 'UTF-8');
                },
                $choices
            );

            if (
                count(array_unique($normalizedChoices)) !== count($choices)
            ) {
                throw new TransactionTypesValidationException(
                    "{$label}: Select field #{$fieldNumber} contains duplicate choices."
                );
            }
        }

        $cleanFields[] = [
            'id'         => $fieldId,
            'mode'       => $mode,
            'format'     => $format,
            'title'      => $title,
            'choices'    => $choices,
            'choice_ids' => $choiceIds
        ];
    }

    return $cleanFields;
}

/**
 * Validate the complete service payload before writing to the database.
 */
function validateServiceInput(
    string $name,
    string $hasSub,
    int $minutes,
    int $flagStatus,
    $directDetails,
    $subConcerns
): void {
    if ($name === '') {
        throw new TransactionTypesValidationException(
            'Transaction service name is required.'
        );
    }

    if (mb_strlen($name) > 255) {
        throw new TransactionTypesValidationException(
            'Transaction service name must not exceed 255 characters.'
        );
    }

    if (!in_array($flagStatus, [0, 1], true)) {
        throw new TransactionTypesValidationException(
            'Invalid service status.'
        );
    }

    if (!in_array($hasSub, ['yes', 'no'], true)) {
        throw new TransactionTypesValidationException(
            'Invalid sub-transaction configuration.'
        );
    }

    if ($hasSub === 'yes') {
        if (!is_array($subConcerns)) {
            throw new TransactionTypesValidationException(
                'Invalid sub-transaction data.'
            );
        }

        $validCount = 0;

        foreach ($subConcerns as $sub) {
            $sub = is_array($sub) ? $sub : [];

            $subName = trim((string)($sub['name'] ?? ''));

            if ($subName === '') {
                continue;
            }

            $validCount++;

            if (mb_strlen($subName) > 150) {
                throw new TransactionTypesValidationException(
                    "Sub-transaction \"{$subName}\" must not exceed 150 characters."
                );
            }

            $subMinutes = (int)($sub['estimated_minutes'] ?? 5);

            if ($subMinutes < 1 || $subMinutes > 180) {
                throw new TransactionTypesValidationException(
                    "Sub-transaction \"{$subName}\": estimated duration must be between 1 and 180 minutes."
                );
            }

            prepareDetailRecord(
                $sub['details'] ?? null,
                "Sub-transaction \"{$subName}\""
            );
        }

        if ($validCount === 0) {
            throw new TransactionTypesValidationException(
                'Add at least one sub-transaction, or switch to Standalone Service.'
            );
        }
    } else {
        if ($minutes < 1 || $minutes > 180) {
            throw new TransactionTypesValidationException(
                'Estimated duration must be between 1 and 180 minutes.'
            );
        }

        prepareDetailRecord(
            $directDetails,
            'Primary service details'
        );
    }
}

/**
 * Delete an existing detail header.
 *
 * Its field items and choices are automatically deleted by the
 * configured ON DELETE CASCADE foreign keys.
 */
function deleteDetailFieldRow(
    mysqli $db,
    int $typeId,
    ?int $subConcernId
): void {
    if ($subConcernId === null) {
        $stmt = $db->prepare("
            DELETE FROM transaction_detail_fields
            WHERE transaction_type_id = ?
              AND sub_concern_id IS NULL
        ");

        $stmt->bind_param('i', $typeId);
    } else {
        $stmt = $db->prepare("
            DELETE FROM transaction_detail_fields
            WHERE transaction_type_id = ?
              AND sub_concern_id = ?
        ");

        $stmt->bind_param('ii', $typeId, $subConcernId);
    }

    $stmt->execute();
    $stmt->close();
}

/**
 * Synchronize a detail configuration without unnecessarily
 * deleting and recreating existing records.
 *
 * Existing header IDs, field item IDs, and choice IDs are preserved
 * whenever their IDs are submitted and belong to the current parent.
 */
function syncDetailFieldRow(
    mysqli $db,
    int $typeId,
    ?int $subConcernId,
    $detailPayload,
    string $label = 'Details'
): void {
    $fields = prepareDetailRecord($detailPayload, $label);

    /*
     * Lock and locate the existing header.
     */
    if ($subConcernId === null) {
        $headerStmt = $db->prepare("
            SELECT id
            FROM transaction_detail_fields
            WHERE transaction_type_id = ?
              AND sub_concern_id IS NULL
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $headerStmt->bind_param('i', $typeId);
    } else {
        $headerStmt = $db->prepare("
            SELECT id
            FROM transaction_detail_fields
            WHERE transaction_type_id = ?
              AND sub_concern_id = ?
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $headerStmt->bind_param('ii', $typeId, $subConcernId);
    }

    $headerStmt->execute();

    $existingHeader = $headerStmt->get_result()->fetch_assoc();

    $headerStmt->close();

    /*
     * Details disabled: remove the existing configuration.
     */
    if ($fields === null) {
        deleteDetailFieldRow(
            $db,
            $typeId,
            $subConcernId
        );

        return;
    }

    /*
     * Preserve the existing header.
     * Insert only when no header exists.
     *
     * Use a separate INSERT for NULL sub_concern_id.
     */
    if ($existingHeader) {
        $detailFieldId = (int)$existingHeader['id'];
    } else {
        if ($subConcernId === null) {
            $headerInsert = $db->prepare("
                INSERT INTO transaction_detail_fields
                    (
                        transaction_type_id,
                        sub_concern_id,
                        flag_status
                    )
                VALUES (?, NULL, 0)
            ");

            $headerInsert->bind_param('i', $typeId);
        } else {
            $headerInsert = $db->prepare("
                INSERT INTO transaction_detail_fields
                    (
                        transaction_type_id,
                        sub_concern_id,
                        flag_status
                    )
                VALUES (?, ?, 0)
            ");

            $headerInsert->bind_param(
                'ii',
                $typeId,
                $subConcernId
            );
        }

        $headerInsert->execute();

        $detailFieldId = (int)$headerInsert->insert_id;

        $headerInsert->close();
    }

    /*
     * Fetch existing field item IDs belonging to this header.
     */
    $existingItems = [];

    $itemFetch = $db->prepare("
        SELECT id
        FROM transaction_detail_field_items
        WHERE detail_field_id = ?
        FOR UPDATE
    ");

    $itemFetch->bind_param('i', $detailFieldId);

    $itemFetch->execute();

    $itemResult = $itemFetch->get_result();

    while ($row = $itemResult->fetch_assoc()) {
        $existingItems[] = (int)$row['id'];
    }

    $itemFetch->close();

    $keptItemIds = [];

    /*
     * Field operations.
     */
    $itemUpdate = $db->prepare("
        UPDATE transaction_detail_field_items
        SET
            field_order = ?,
            detail_mode = ?,
            input_data_type = ?,
            separator_style = ?,
            field_title = ?
        WHERE id = ?
          AND detail_field_id = ?
    ");

    $itemInsert = $db->prepare("
        INSERT INTO transaction_detail_field_items
            (
                detail_field_id,
                field_order,
                detail_mode,
                input_data_type,
                separator_style,
                field_title,
                flag_status
            )
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ");

    /*
     * Choice operations.
     */
    $choiceFetch = $db->prepare("
        SELECT id, choice_value
        FROM transaction_detail_field_choices
        WHERE field_item_id = ?
        ORDER BY choice_order ASC
        FOR UPDATE
    ");

    $choiceUpdate = $db->prepare("
        UPDATE transaction_detail_field_choices
        SET
            choice_order = ?,
            choice_value = ?
        WHERE id = ?
          AND field_item_id = ?
    ");

    $choiceInsert = $db->prepare("
        INSERT INTO transaction_detail_field_choices
            (
                field_item_id,
                choice_order,
                choice_value
            )
        VALUES (?, ?, ?)
    ");

    $choiceDelete = $db->prepare("
        DELETE FROM transaction_detail_field_choices
        WHERE id = ?
          AND field_item_id = ?
    ");

    foreach ($fields as $index => $field) {
        $fieldOrder = $index + 1;
        $mode = $field['mode'];
        $title = $field['title'];

        $inputDataType = $mode === 'INPUT'
            ? $field['format']
            : null;

        $separatorStyle = $mode === 'SELECT'
            ? $field['format']
            : null;

        $requestedItemId = (int)($field['id'] ?? 0);

        /*
         * Verify ownership before updating an existing item.
         */
        if (
            $requestedItemId > 0 &&
            in_array($requestedItemId, $existingItems, true)
        ) {
            $itemId = $requestedItemId;

            $itemUpdate->bind_param(
                'issssii',
                $fieldOrder,
                $mode,
                $inputDataType,
                $separatorStyle,
                $title,
                $itemId,
                $detailFieldId
            );

            $itemUpdate->execute();
        } else {
            /*
             * Unknown or missing IDs are never trusted.
             * Insert a new field instead.
             */
            $itemInsert->bind_param(
                'iissss',
                $detailFieldId,
                $fieldOrder,
                $mode,
                $inputDataType,
                $separatorStyle,
                $title
            );

            $itemInsert->execute();

            $itemId = (int)$itemInsert->insert_id;
        }

        $keptItemIds[] = $itemId;

        /*
         * Fetch existing choices for this field.
         */
        $existingChoices = [];

        $choiceFetch->bind_param('i', $itemId);

        $choiceFetch->execute();

        $choiceResult = $choiceFetch->get_result();

        while ($choiceRow = $choiceResult->fetch_assoc()) {
            $existingChoices[(int)$choiceRow['id']] =
                (string)$choiceRow['choice_value'];
        }

        $keptChoiceIds = [];

        /*
         * Synchronize SELECT choices.
         */
        if ($mode === 'SELECT') {
            foreach ($field['choices'] as $choiceIndex => $choiceValue) {
                $choiceOrder = $choiceIndex + 1;

                /*
                 * The IDs are kept in a parallel array by
                 * prepareDetailRecord().
                 */
                $choiceId = (int)(
                    $field['choice_ids'][$choiceIndex] ?? 0
                );

                if ($choiceValue === '') {
                    continue;
                }

                /*
                 * Update only choices that belong to this field.
                 */
                if (
                    $choiceId > 0 &&
                    array_key_exists($choiceId, $existingChoices)
                ) {
                    $choiceUpdate->bind_param(
                        'isii',
                        $choiceOrder,
                        $choiceValue,
                        $choiceId,
                        $itemId
                    );

                    $choiceUpdate->execute();

                    $keptChoiceIds[] = $choiceId;
                } else {
                    /*
                     * New choice or unrecognized choice ID.
                     */
                    $choiceInsert->bind_param(
                        'iis',
                        $itemId,
                        $choiceOrder,
                        $choiceValue
                    );

                    $choiceInsert->execute();

                    $keptChoiceIds[] = (int)$choiceInsert->insert_id;
                }
            }
        }

        /*
         * Delete removed choices only.
         * Switching SELECT to INPUT removes its choices.
         */
        foreach (array_keys($existingChoices) as $existingChoiceId) {
            if (!in_array($existingChoiceId, $keptChoiceIds, true)) {
                $choiceDelete->bind_param(
                    'ii',
                    $existingChoiceId,
                    $itemId
                );

                $choiceDelete->execute();
            }
        }
    }

    /*
     * Delete removed field items only.
     * Their choices are removed through ON DELETE CASCADE.
     */
    $itemDelete = $db->prepare("
        DELETE FROM transaction_detail_field_items
        WHERE id = ?
          AND detail_field_id = ?
    ");

    foreach ($existingItems as $existingItemId) {
        if (!in_array($existingItemId, $keptItemIds, true)) {
            $itemDelete->bind_param(
                'ii',
                $existingItemId,
                $detailFieldId
            );

            $itemDelete->execute();
        }
    }

    $itemDelete->close();

    $itemUpdate->close();
    $itemInsert->close();

    $choiceFetch->close();
    $choiceUpdate->close();
    $choiceInsert->close();
    $choiceDelete->close();
}

/**
 * Build the frontend-compatible parsed detail structure.
 *
 * Field IDs and choice IDs are included so the frontend can submit
 * them again during an update.
 */
function buildParsedDetail(
    int $headerId,
    array $fields
): array {
    $counters = [];
    $items = [];

    foreach ($fields as $index => $field) {
        $mode = $field['mode'];
        $format = $field['format'];
        $title = $field['title'];
        $choices = $field['choices'] ?? [];

        $prefix = '';

        if ($mode === 'SELECT') {
            $counter = $counters[$format] ?? 0;
            $counters[$format] = $counter + 1;

            if ($format === 'lower_alpha') {
                $prefix = chr(97 + ($counter % 26)) . '. ';
            } elseif ($format === 'upper_alpha') {
                $prefix = chr(65 + ($counter % 26)) . '. ';
            } else {
                $prefix = ($counter + 1) . '. ';
            }
        }

        $choicePrefixes = [];

        if ($mode === 'SELECT') {
            foreach ($choices as $choiceIndex => $_choice) {
                if ($format === 'lower_alpha') {
                    $choicePrefixes[] =
                        chr(97 + ($choiceIndex % 26)) . '. ';
                } elseif ($format === 'upper_alpha') {
                    $choicePrefixes[] =
                        chr(65 + ($choiceIndex % 26)) . '. ';
                } else {
                    $choicePrefixes[] = ($choiceIndex + 1) . '. ';
                }
            }
        }

        $items[] = [
            'id'              => $field['id'] ?? null,
            'index'           => $index + 1,
            'prefix'          => $prefix,
            'title'           => $title,
            'mode'            => $mode,
            'format'          => $format,
            'choices'         => $choices,
            'choice_ids'      => $field['choice_ids'] ?? [],
            'choice_prefixes' => $choicePrefixes,
            'display'         => $prefix . $title
        ];
    }

    $titles = array_column($fields, 'title');

    $modes = array_values(
        array_unique(array_column($fields, 'mode'))
    );

    $inputFormat = null;
    $selectFormat = null;

    foreach ($fields as $field) {
        if (
            $field['mode'] === 'INPUT' &&
            $inputFormat === null
        ) {
            $inputFormat = $field['format'];
        }

        if (
            $field['mode'] === 'SELECT' &&
            $selectFormat === null
        ) {
            $selectFormat = $field['format'];
        }
    }

    return [
        'id'               => $headerId,
        'has_details'      => true,
        'detail_mode'      => count($modes) > 1
            ? 'MIXED'
            : ($modes[0] ?? 'INPUT'),
        'input_data_type'  => $inputFormat,
        'separator_style'  => $selectFormat,
        'delimiter'        => '::',
        'count'            => count($fields),
        'delimited_titles' => implode('::', $titles),
        'titles'           => $titles,
        'fields'           => $fields,
        'formatted_items'  => $items
    ];
}

/**
 * Fetch normalized detail configurations for a list of transaction types.
 *
 * Returns:
 * [
 *     'direct' => [transaction_type_id => parsed details],
 *     'sub'    => [sub_concern_id => parsed details]
 * ]
 */
function fetchNormalizedDetailMaps(
    mysqli $db,
    array $typeIds
): array {
    if (empty($typeIds)) {
        return [
            'direct' => [],
            'sub' => []
        ];
    }

    // IDs originate from database results and are cast to integers.
    $inList = implode(
        ',',
        array_map('intval', $typeIds)
    );

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

    $headers = [];
    $selectedHeaders = [];

    while ($row = $result->fetch_assoc()) {
        $typeId = (int)$row['transaction_type_id'];

        $subId = $row['sub_concern_id'] !== null
            ? (int)$row['sub_concern_id']
            : null;

        $scopeKey = $subId === null
            ? "type:{$typeId}"
            : "sub:{$subId}";

        $headerId = (int)$row['header_id'];

        /*
         * If duplicate headers exist for the same service/sub-transaction,
         * use the newest header (highest ID).
         */
        if (!isset($selectedHeaders[$scopeKey])) {
            $selectedHeaders[$scopeKey] = $headerId;

            $headers[$headerId] = [
                'type_id' => $typeId,
                'sub_id' => $subId,
                'fields' => []
            ];
        }

        // Ignore older duplicate headers.
        if ($selectedHeaders[$scopeKey] !== $headerId) {
            continue;
        }

        if ($row['item_id'] === null) {
            continue;
        }

        $itemId = (int)$row['item_id'];

        if (!isset($headers[$headerId]['fields'][$itemId])) {
            $mode = strtoupper(
                (string)$row['detail_mode']
            );

            if (!in_array($mode, ['INPUT', 'SELECT'], true)) {
                $mode = 'INPUT';
            }

            $rawFormat = $mode === 'SELECT'
                ? $row['separator_style']
                : $row['input_data_type'];

            $headers[$headerId]['fields'][$itemId] = [
                'id'         => $itemId,
                'order'      => (int)$row['field_order'],
                'mode'       => $mode,
                'format'     => normalizeDetailFormat($mode, $rawFormat),
                'title'      => (string)$row['field_title'],
                'choices'    => [],
                'choice_ids' => []
            ];
        }

        if ($row['choice_id'] !== null) {
            $headers[$headerId]['fields'][$itemId]['choices'][] =
                (string)$row['choice_value'];

            $headers[$headerId]['fields'][$itemId]['choice_ids'][] =
                (int)$row['choice_id'];
        }
    }

    $result->free();

    $directMap = [];
    $subMap = [];

    foreach ($headers as $headerId => $header) {
        $fields = array_values($header['fields']);

        usort(
            $fields,
            static function ($a, $b) {
                return $a['order'] <=> $b['order'];
            }
        );

        // Remove the internal ordering value.
        foreach ($fields as &$field) {
            unset($field['order']);
        }
        unset($field);

        if (empty($fields)) {
            continue;
        }

        $parsed = buildParsedDetail(
            (int)$headerId,
            $fields
        );

        if ($header['sub_id'] === null) {
            $directMap[$header['type_id']] = $parsed;
        } else {
            $subMap[$header['sub_id']] = $parsed;
        }
    }

    return [
        'direct' => $directMap,
        'sub' => $subMap
    ];
}

/**
 * Delete sub-transactions not retained by the update request.
 */
function deleteRemovedSubConcerns(
    mysqli $db,
    int $typeId,
    array $existingIds,
    array $keptIds
): void {
    $toDelete = array_values(
        array_diff($existingIds, $keptIds)
    );

    if (empty($toDelete)) {
        return;
    }

    $stmt = $db->prepare("
        DELETE FROM transaction_sub_concerns
        WHERE id = ?
          AND transaction_type_id = ?
    ");

    foreach ($toDelete as $subId) {
        $subId = (int)$subId;

        $stmt->bind_param(
            'ii',
            $subId,
            $typeId
        );

        $stmt->execute();
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (!$db_connect instanceof mysqli) {
    transactionTypesJsonResponse([
        'success' => false,
        'msg_status' => false,
        'msg_response' => 'Database connection unavailable.'
    ]);
}

$action = $_POST['action']
    ?? $_POST['actionSubmit']
    ?? $_POST['actionUpdate']
    ?? '';

try {
    switch ($action) {

        /*
        |--------------------------------------------------------------------------
        | FETCH TRANSACTION TYPES
        |--------------------------------------------------------------------------
        */

        case 'fetch_transaction_types':

            $officeId = (int)($_POST['office_id'] ?? 0);

            $whereClause = $officeId > 0
                ? ' WHERE tt.office_id = ? '
                : '';

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

                INNER JOIN office o
                    ON tt.office_id = o.office_id

                {$whereClause}

                ORDER BY
                    o.office_name ASC,
                    tt.transaction_name ASC
            ";

            $stmt = $db_connect->prepare($sql);

            if ($officeId > 0) {
                $stmt->bind_param('i', $officeId);
            }

            $stmt->execute();

            $result = $stmt->get_result();

            $types = [];
            $typeIds = [];

            while ($row = $result->fetch_assoc()) {
                $typeId = (int)$row['id'];

                $row['direct_details'] = null;
                $row['sub_concerns'] = [];
                $row['sub_concerns_count'] = 0;

                $types[$typeId] = $row;
                $typeIds[] = $typeId;
            }

            $stmt->close();

            if (!empty($typeIds)) {
                // Fetch all normalized detail configurations.
                $detailMaps = fetchNormalizedDetailMaps(
                    $db_connect,
                    $typeIds
                );

                // Attach direct details.
                foreach ($detailMaps['direct'] as $typeId => $details) {
                    if (isset($types[$typeId])) {
                        $types[$typeId]['direct_details'] = $details;
                    }
                }

                // Fetch sub-transactions.
                $inList = implode(
                    ',',
                    array_map('intval', $typeIds)
                );

                $subResult = $db_connect->query("
                    SELECT
                        id,
                        transaction_type_id,
                        sub_concern_name,
                        estimated_minutes,
                        flag_status

                    FROM transaction_sub_concerns

                    WHERE transaction_type_id IN ({$inList})

                    ORDER BY id ASC
                ");

                while ($sub = $subResult->fetch_assoc()) {
                    $typeId = (int)$sub['transaction_type_id'];
                    $subId = (int)$sub['id'];

                    if (!isset($types[$typeId])) {
                        continue;
                    }

                    $sub['id'] = $subId;
                    $sub['transaction_type_id'] = $typeId;
                    $sub['estimated_minutes'] = (int)(
                        $sub['estimated_minutes'] ?? 5
                    );

                    $sub['parsed_details'] =
                        $detailMaps['sub'][$subId] ?? null;

                    $types[$typeId]['sub_concerns'][] = $sub;
                    $types[$typeId]['sub_concerns_count']++;
                }

                $subResult->free();
            }

            transactionTypesJsonResponse([
                'success' => true,
                'data' => array_values($types)
            ]);

        /*
        |--------------------------------------------------------------------------
        | CREATE TRANSACTION TYPE
        |--------------------------------------------------------------------------
        */

        case 'submitUnifiedType':

            $officeId = (int)($_POST['office_id'] ?? 0);

            $transactionName = trim(
                (string)($_POST['transaction_name'] ?? '')
            );

            $hasSubConcerns = (string)(
                $_POST['has_sub_concerns'] ?? 'no'
            );

            $requestedMinutes = (int)(
                $_POST['estimated_minutes'] ?? 5
            );

            $estimatedMinutes = $hasSubConcerns === 'yes'
                ? 0
                : $requestedMinutes;

            $flagStatus = (int)($_POST['flag_status'] ?? 0);

            $directDetails = $_POST['direct_details'] ?? null;
            $subConcerns = $_POST['sub_concerns'] ?? [];

            if ($officeId <= 0 || $transactionName === '') {
                transactionTypesJsonResponse([
                    'msg_status' => false,
                    'msg_response' =>
                        'Please select an office and enter a transaction service name.'
                ]);
            }

            validateServiceInput(
                $transactionName,
                $hasSubConcerns,
                $requestedMinutes,
                $flagStatus,
                $directDetails,
                $subConcerns
            );

            $db_connect->begin_transaction();
            $transactionOpen = true;

            // Create the primary transaction type.
            $stmtType = $db_connect->prepare("
                INSERT INTO transaction_types
                    (
                        office_id,
                        transaction_name,
                        estimated_minutes,
                        flag_status
                    )
                VALUES (?, ?, ?, ?)
            ");

            $stmtType->bind_param(
                'isii',
                $officeId,
                $transactionName,
                $estimatedMinutes,
                $flagStatus
            );

            $stmtType->execute();

            $newTypeId = (int)$stmtType->insert_id;

            $stmtType->close();

            if ($hasSubConcerns === 'yes') {
                $stmtSub = $db_connect->prepare("
                    INSERT INTO transaction_sub_concerns
                        (
                            transaction_type_id,
                            sub_concern_name,
                            estimated_minutes,
                            flag_status
                        )
                    VALUES (?, ?, ?, 0)
                ");

                foreach ($subConcerns as $subItem) {
                    $subName = trim(
                        (string)($subItem['name'] ?? '')
                    );

                    if ($subName === '') {
                        continue;
                    }

                    $subMinutes = (int)(
                        $subItem['estimated_minutes'] ?? 5
                    );

                    $stmtSub->bind_param(
                        'isi',
                        $newTypeId,
                        $subName,
                        $subMinutes
                    );

                    $stmtSub->execute();

                    $newSubId = (int)$stmtSub->insert_id;

                    syncDetailFieldRow(
                        $db_connect,
                        $newTypeId,
                        $newSubId,
                        $subItem['details'] ?? null,
                        "Sub-transaction \"{$subName}\""
                    );
                }

                $stmtSub->close();
            } else {
                // Standalone service: details belong directly to the type.
                syncDetailFieldRow(
                    $db_connect,
                    $newTypeId,
                    null,
                    $directDetails,
                    'Primary service details'
                );
            }

            $db_connect->commit();
            $transactionOpen = false;

            transactionTypesJsonResponse([
                'msg_status' => true,
                'msg_response' =>
                    'Transaction service configuration saved successfully.'
            ]);

        /*
        |--------------------------------------------------------------------------
        | UPDATE TRANSACTION TYPE
        |--------------------------------------------------------------------------
        */

        case 'submitUpdateUnifiedType':

            $typeId = (int)($_POST['type_id'] ?? 0);
            $officeId = (int)($_POST['office_id'] ?? 0);

            $transactionName = trim(
                (string)($_POST['transaction_name'] ?? '')
            );

            $flagStatus = (int)($_POST['flag_status'] ?? 0);

            $hasSubConcerns = (string)(
                $_POST['has_sub_concerns'] ?? 'no'
            );

            $requestedMinutes = (int)(
                $_POST['estimated_minutes'] ?? 5
            );

            $estimatedMinutes = $hasSubConcerns === 'yes'
                ? 0
                : $requestedMinutes;

            $directDetails = $_POST['direct_details'] ?? null;
            $subConcerns = $_POST['sub_concerns'] ?? [];

            if (
                $typeId <= 0 ||
                $officeId <= 0 ||
                $transactionName === ''
            ) {
                transactionTypesJsonResponse([
                    'msg_status' => false,
                    'msg_response' =>
                        'Invalid transaction service data provided.'
                ]);
            }

            validateServiceInput(
                $transactionName,
                $hasSubConcerns,
                $requestedMinutes,
                $flagStatus,
                $directDetails,
                $subConcerns
            );

            $db_connect->begin_transaction();
            $transactionOpen = true;

            // Lock and verify the target transaction type.
            $checkType = $db_connect->prepare("
                SELECT id
                FROM transaction_types
                WHERE id = ?
                FOR UPDATE
            ");

            $checkType->bind_param('i', $typeId);
            $checkType->execute();

            $typeExists = $checkType->get_result()->fetch_assoc();

            $checkType->close();

            if (!$typeExists) {
                throw new TransactionTypesValidationException(
                    'The selected transaction service no longer exists.'
                );
            }

            // Update the primary transaction type.
            $stmtUpdate = $db_connect->prepare("
                UPDATE transaction_types
                SET
                    office_id = ?,
                    transaction_name = ?,
                    estimated_minutes = ?,
                    flag_status = ?,
                    date_modify = NOW()
                WHERE id = ?
            ");

            $stmtUpdate->bind_param(
                'isiii',
                $officeId,
                $transactionName,
                $estimatedMinutes,
                $flagStatus,
                $typeId
            );

            $stmtUpdate->execute();
            $stmtUpdate->close();

            // Fetch existing sub-transaction IDs belonging to this type.
            $existingIds = [];

            $stmtExisting = $db_connect->prepare("
                SELECT id
                FROM transaction_sub_concerns
                WHERE transaction_type_id = ?
                FOR UPDATE
            ");

            $stmtExisting->bind_param('i', $typeId);
            $stmtExisting->execute();

            $existingResult = $stmtExisting->get_result();

            while ($existing = $existingResult->fetch_assoc()) {
                $existingIds[] = (int)$existing['id'];
            }

            $stmtExisting->close();

            $keptIds = [];

            if ($hasSubConcerns === 'yes') {
                /*
                 * Remove direct details because this service now uses
                 * sub-transaction-specific detail configurations.
                 */
                syncDetailFieldRow(
                    $db_connect,
                    $typeId,
                    null,
                    null
                );

                $stmtSubInsert = $db_connect->prepare("
                    INSERT INTO transaction_sub_concerns
                        (
                            transaction_type_id,
                            sub_concern_name,
                            estimated_minutes,
                            flag_status
                        )
                    VALUES (?, ?, ?, 0)
                ");

                $stmtSubUpdate = $db_connect->prepare("
                    UPDATE transaction_sub_concerns
                    SET
                        sub_concern_name = ?,
                        estimated_minutes = ?
                    WHERE id = ?
                      AND transaction_type_id = ?
                ");

                foreach ($subConcerns as $subItem) {
                    $subId = (int)($subItem['id'] ?? 0);

                    $subName = trim(
                        (string)($subItem['name'] ?? '')
                    );

                    if ($subName === '') {
                        continue;
                    }

                    $subMinutes = (int)(
                        $subItem['estimated_minutes'] ?? 5
                    );

                    /*
                     * Only update IDs that belong to this transaction type.
                     * An unknown or foreign ID is treated as a new sub-transaction.
                     */
                    if (
                        $subId > 0 &&
                        in_array($subId, $existingIds, true)
                    ) {
                        $stmtSubUpdate->bind_param(
                            'siii',
                            $subName,
                            $subMinutes,
                            $subId,
                            $typeId
                        );

                        $stmtSubUpdate->execute();

                        $targetSubId = $subId;
                    } else {
                        $stmtSubInsert->bind_param(
                            'isi',
                            $typeId,
                            $subName,
                            $subMinutes
                        );

                        $stmtSubInsert->execute();

                        $targetSubId = (int)$stmtSubInsert->insert_id;
                    }

                    $keptIds[] = $targetSubId;

                    // Synchronize this sub-transaction's details.
                    syncDetailFieldRow(
                        $db_connect,
                        $typeId,
                        $targetSubId,
                        $subItem['details'] ?? null,
                        "Sub-transaction \"{$subName}\""
                    );
                }

                $stmtSubInsert->close();
                $stmtSubUpdate->close();

                // Remove sub-transactions omitted from the update payload.
                // Their detail records are removed through FK cascades.
                deleteRemovedSubConcerns(
                    $db_connect,
                    $typeId,
                    $existingIds,
                    $keptIds
                );
            } else {
                /*
                 * Switching to standalone service:
                 * remove all previous sub-transactions and their details.
                 */
                $stmtDeleteSubs = $db_connect->prepare("
                    DELETE FROM transaction_sub_concerns
                    WHERE transaction_type_id = ?
                ");

                $stmtDeleteSubs->bind_param('i', $typeId);
                $stmtDeleteSubs->execute();
                $stmtDeleteSubs->close();

                // Synchronize optional direct details.
                syncDetailFieldRow(
                    $db_connect,
                    $typeId,
                    null,
                    $directDetails,
                    'Primary service details'
                );
            }

            $db_connect->commit();
            $transactionOpen = false;

            transactionTypesJsonResponse([
                'msg_status' => true,
                'msg_response' =>
                    'Transaction service and details updated successfully.'
            ]);

        /*
        |--------------------------------------------------------------------------
        | DELETE TRANSACTION TYPE
        |--------------------------------------------------------------------------
        */

        case 'delete_transaction_type':

            $id = (int)($_POST['id'] ?? 0);

            if ($id <= 0) {
                transactionTypesJsonResponse([
                    'msg_status' => false,
                    'msg_response' => 'Invalid transaction type ID.'
                ]);
            }

            $stmtDelete = $db_connect->prepare("
                DELETE FROM transaction_types
                WHERE id = ?
            ");

            $stmtDelete->bind_param('i', $id);
            $stmtDelete->execute();

            $deletedRows = $stmtDelete->affected_rows;

            $stmtDelete->close();

            if ($deletedRows === 0) {
                transactionTypesJsonResponse([
                    'msg_status' => false,
                    'msg_response' =>
                        'Transaction service was not found or has already been deleted.'
                ]);
            }

            transactionTypesJsonResponse([
                'msg_status' => true,
                'msg_response' =>
                    'Transaction service deleted successfully.'
            ]);

        default:

            transactionTypesJsonResponse([
                'msg_status' => false,
                'msg_response' => 'Invalid action request.'
            ]);
    }
} catch (TransactionTypesValidationException $e) {
    if ($transactionOpen) {
        $db_connect->rollback();
        $transactionOpen = false;
    }

    transactionTypesJsonResponse([
        'msg_status' => false,
        'msg_response' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    if ($transactionOpen) {
        try {
            $db_connect->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                'Transaction type rollback error: ' .
                $rollbackError->getMessage()
            );
        }

        $transactionOpen = false;
    }

    // Keep database and internal exception details out of the response.
    error_log(
        'Transaction types handler error: ' .
        $e->getMessage()
    );

    transactionTypesJsonResponse([
        'success' => false,
        'msg_status' => false,
        'msg_response' =>
            'An unexpected error occurred while processing the transaction service.'
    ]);
}
