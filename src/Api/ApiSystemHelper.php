<?php

namespace Src\Api;

use Exception;
use Src\Api\ApiHelper;
use Src\Common\dataHelper;

class ApiSystemHelper
{
    private $db;
    private $api;
    private $helper;
    private $data;

    public function __construct($db)
    {
        $this->db     = $db;
        $this->api    = new ApiHelper($this->db);
        $this->helper = new dataHelper($this->db);

        $clientPublicKey = $this->api->getRequestPublicKey();
        if (!$clientPublicKey) {
            $this->sendErrorResponse('Missing Public Key Header Parameters');
        }

        $this->api->setKeys($clientPublicKey);

        // Read input payload JSON stream container envelope
        $inputData        = $this->api->getJsonInput();
        $encryptedPayload = isset($inputData['curl_response']) ? $inputData['curl_response'] : null;

        if (!$encryptedPayload) {
            $this->sendErrorResponse('Structural Processing Error: Missing curl_response payload parameter wrapper');
        }

        // Run standalone decryption handler safely
        $decryptedData = $this->api->decryptApiData($encryptedPayload);
        if (is_array($decryptedData) && isset($decryptedData['error'])) {
            $this->sendErrorResponse($decryptedData['error'] ?? 'Invalid Cryptographic Data Execution');
        }

        // Map into array values representation cleanly
        $payloadArray = json_decode($decryptedData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendErrorResponse('Invalid JSON data decoding structure');
        }

        $this->data = (object)$payloadArray;
    }

    public function getPayloadData()
    {
        return $this->data;
    }

    /**
     * Private helper to check if a database table exists
     */
    private function tableExists(string $tableName): bool
    {
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND LOWER(TABLE_NAME) = LOWER(?)";
        $stmt = mysqli_prepare($this->db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $tableName);
            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                $exists = mysqli_stmt_num_rows($stmt) > 0;
                mysqli_stmt_close($stmt);
                return $exists;
            }
            mysqli_stmt_close($stmt);
        }

        return false;
    }

    /**
     * Action Pipeline: add_information
     * Dynamically processes incoming table-keyed payloads to insert or update table records.
     */
    public function addInformation()
    {
        $data       = $this->data;
        $db_connect = $this->db;

        // Extract items array from payload (supports nested items or direct object key structure)
        $items = isset($data->items) && is_array($data->items) ? $data->items : (array)$data;

        if (empty($items)) {
            $this->sendErrorResponse('Invalid or empty payload structure for information processing.');
        }

        $processed_records = [];

        foreach ($items as $table => $recordData) {
            // Trim table string to clean up any extra whitespace
            $table = is_string($table) ? trim($table) : $table;

            // Skip internal meta properties if standard object payload is passed directly
            if (!is_array($recordData) || empty($table) || in_array($table, ['action', 'system_type', 'total_records'])) {
                continue;
            }

            if (!$this->tableExists($table)) {
                $this->sendErrorResponse("Database error: Table '{$table}' does not exist.");
            }

            $primaryKeyCandidate = null;
            $primaryValue        = null;

            // Find primary key or foreign identifier candidate ending with _id or id
            foreach ($recordData as $col => $val) {
                if (substr($col, -3) === '_id' || $col === 'id') {
                    $primaryKeyCandidate = $col;
                    $primaryValue        = $val;
                    break;
                }
            }

            $recordExists = false;

            // Check if record exists in target table
            if ($primaryKeyCandidate !== null && !empty($primaryValue)) {
                $sqlCheck = "SELECT {$primaryKeyCandidate} FROM {$table} WHERE {$primaryKeyCandidate} = ? LIMIT 1";
                if ($stmt = mysqli_prepare($db_connect, $sqlCheck)) {
                    mysqli_stmt_bind_param($stmt, "s", $primaryValue);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_store_result($stmt);
                        if (mysqli_stmt_num_rows($stmt) > 0) {
                            $recordExists = true;
                        }
                    }
                    mysqli_stmt_close($stmt);
                }
            }

            // Perform atomic operation
            $db_connect->begin_transaction();
            try {
                if ($recordExists && $primaryKeyCandidate !== null) {
                    $where = [$primaryKeyCandidate => $primaryValue];
                    $updateStatus = $this->helper->updateData($table, $recordData, $where);

                    if (!$updateStatus) {
                        throw new Exception("Failed to update record in '{$table}'.");
                    }
                } else {
                    $insertId = $this->helper->insertData($table, $recordData);

                    if (!$insertId) {
                        throw new Exception("Failed to insert new record into '{$table}'.");
                    }
                }

                $db_connect->commit();
                $processed_records[] = [
                    'table'  => $table,
                    'action' => $recordExists ? 'updated' : 'inserted',
                    'status' => true
                ];
            } catch (Exception $e) {
                $db_connect->rollback();
                $this->sendErrorResponse($e->getMessage());
            }
        }

        $this->sendSuccessResponse(['processed_records' => $processed_records]);
    }

    /**
     * Action Pipeline: add_account
     * CRUD Create/Update Engine handling Bulk Multi-Dimensional Payloads
     */
    public function addAccount()
    {
        $data       = $this->data;
        $db_connect = $this->db;

        // Check if items exists and is an array
        $items = isset($data->items) && is_array($data->items) ? $data->items : [];

        if (empty($items)) {
            $this->sendErrorResponse('Invalid or empty payload structure, please contact the system administrator.');
        }

        $processed_ids = [];
        foreach ($items as $item) {
            # Extracted Information for each row
            $ref_id        = isset($item['ref_id']) ? (int)$item['ref_id'] : 0;
            $users         = isset($item['users']) ? (array)$item['users'] : [];
            $employee      = isset($item['employee']) ? (array)$item['employee'] : [];
            $student       = isset($item['student']) ? (array)$item['student'] : [];
            $system_access = isset($item['system_access']) ? (array)$item['system_access'] : [];
            $login         = isset($item['login']) ? (array)$item['login'] : [];

            # DATA VALIDATION CHECKS
            if (($ref_id === '' || !is_digit($ref_id)) || empty($system_access['system_type']) || empty($system_access['system_role']) || empty($system_access['access_tag'])) {
                $this->sendErrorResponse('Invalid or empty payload structure, please contact the system administrator.');
            }

            $get_id     = 0;
            $db_id      = 0;
            $exist      = false;
            $sql_select = "SELECT id FROM users WHERE ref_id = ? LIMIT 1";
            if ($stmt = mysqli_prepare($db_connect, $sql_select)) {
                mysqli_stmt_bind_param($stmt, "i", $ref_id);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_store_result($stmt);
                    mysqli_stmt_bind_result($stmt, $get_id);
                    if (mysqli_stmt_fetch($stmt)) {
                        $exist = true;
                        $db_id = (int)$get_id;
                    }
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_log = "Database error: " . mysqli_error($db_connect);
                error_log($error_log);
                $this->sendErrorResponse($error_log);
            }

            if ($exist) {
                # UPDATE EXISTING USER ACCOUNT
                $db_connect->begin_transaction();

                try {
                    // User Information [UPDATE]
                    $users['ref_id'] = $ref_id;
                    $user_where = ['id' => $db_id];
                    $user_update = $this->helper->updateData('users', $users, $user_where);
                    if (!$user_update) {
                        throw new Exception("An error occurred while saving the core user demographic profile.");
                    }

                    // Employee Information [UPDATE]
                    if (!empty($employee)) {
                        $employee['user_id'] = $db_id;
                        $emp_where = ['user_id' => $db_id];
                        $employee_update = $this->helper->updateData('employee', $employee, $emp_where);
                        if (!$employee_update) {
                            throw new Exception("An error occurred while saving the employee profile.");
                        }
                    }

                    $access_exist = false;
                    $sql_select = "SELECT id FROM system_access WHERE ref_id = ? AND system_type = ? AND system_role = ? LIMIT 1";
                    if ($stmt = mysqli_prepare($db_connect, $sql_select)) {
                        mysqli_stmt_bind_param($stmt, "isi", $ref_id, $system_access['system_type'], $system_access['system_role']);
                        if (mysqli_stmt_execute($stmt)) {
                            mysqli_stmt_store_result($stmt);
                            mysqli_stmt_bind_result($stmt, $get_id);
                            if (mysqli_stmt_fetch($stmt)) {
                                $access_exist = true;
                            }
                        }
                        mysqli_stmt_close($stmt);
                    }

                    // System Access [INSERT]
                    if (!empty($system_access) && !$access_exist) {
                        $system_access['user_id'] = $db_id;
                        $system_access['ref_id']  = $ref_id;
                        $system_access_insert     = $this->helper->insertData('system_access', $system_access);
                        if (!$system_access_insert) {
                            throw new Exception("An error occurred while saving system access details.");
                        }
                    }

                    // Login Information [UPDATE]
                    if (!empty($login)) {
                        $login['user_id'] = $db_id;
                        $login_where      = ['user_id' => $db_id];
                        $login_update     = $this->helper->updateData('login', $login, $login_where);
                        if (!$login_update) {
                            throw new Exception("An error occurred while saving login information.");
                        }
                    }

                    $db_connect->commit();
                    $processed_ids[] = ['system_ref_id' => $db_id, 'user_id' => $ref_id, 'system_type' => $system_access['system_type'], 'system_role' => $system_access['system_role']];
                } catch (Exception $exception) {
                    $db_connect->rollback();
                    $this->sendErrorResponse($exception->getMessage());
                }
            } else {
                # INSERT NEW USER ACCOUNT
                $db_connect->begin_transaction();

                try {
                    ## Users Table [INSERT]
                    $users['ref_id']  = $ref_id;
                    $user_id_last = $this->helper->insertData('users', $users);
                    if (!$user_id_last) {
                        throw new Exception("An error occurred while saving the core user demographic profile.");
                    }

                    // Employee Table [INSERT]
                    if (!empty($employee)) {
                        $employee['user_id'] = $user_id_last;
                        $employee_insert     = $this->helper->insertData('employee', $employee);
                        if (!$employee_insert) {
                            throw new Exception("An error occurred while saving the employee profile.");
                        }
                    }

                    // System Access Table [INSERT]
                    if (!empty($system_access)) {
                        $system_access['user_id'] = $user_id_last;
                        $system_access['ref_id']  = $ref_id;
                        $system_access_insert     = $this->helper->insertData('system_access', $system_access);
                        if (!$system_access_insert) {
                            throw new Exception("An error occurred while saving system access details.");
                        }
                    }

                    // Login Table [INSERT]
                    if (!empty($login)) {
                        $login['user_id'] = $user_id_last;
                        $login_insert     = $this->helper->insertData('login', $login);
                        if (!$login_insert) {
                            throw new Exception("An error occurred while saving login details.");
                        }
                    }

                    $db_connect->commit();
                    $processed_ids[] = ['system_ref_id' => $user_id_last, 'user_id' => $ref_id, 'system_type' => $system_access['system_type'], 'system_role' => $system_access['system_role']];
                } catch (Exception $exception) {
                    $db_connect->rollback();
                    $this->sendErrorResponse($exception->getMessage());
                }
            }
        }

        // Return processed results
        $this->sendSuccessResponse(['processed_records' => $processed_ids]);
    }

    /**
     * Action Pipeline: verify_login
     * Safe Parameter-Bound Identity Verification
     */
    public function verifyLogin()
    {
        $data     = $this->data;
        $username = isset($data->username) ? trim($data->username) : '';
        $password = isset($data->password) ? trim($data->password) : '';

        if (empty($username) || empty($password)) {
            $this->sendErrorResponse('Missing requisite auth parameters credentials');
        }

        $sql_auth = "SELECT user_id, password, user_role, f_name, l_name FROM users WHERE username = ? LIMIT 1";
        $stmt     = mysqli_prepare($this->db, $sql_auth);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $user_id, $db_hashed_password, $user_role, $f_name, $l_name);

            if (mysqli_stmt_fetch($stmt)) {
                if (password_verify($password, (string)$db_hashed_password)) {
                    mysqli_stmt_close($stmt);

                    $this->sendSuccessResponse([
                        'authenticated' => true,
                        'user_data'     => [
                            'system_id' => $user_id,
                            'name'      => $f_name . ' ' . $l_name,
                            'roles'     => json_decode((string)$user_role)
                        ]
                    ]);
                }
            }
            mysqli_stmt_close($stmt);
        }

        $this->sendErrorResponse('InvalidCredentialsVerificationParametersMatchFailed');
    }

    /**
     * Action Pipeline: delete_account
     * Safe Account Removal
     */
    public function deleteAccount()
    {
        $general_id = isset($this->data->general_id) ? trim($this->data->general_id) : '';

        if (empty($general_id)) {
            $this->sendErrorResponse('MissingTargetGeneralIDParameter');
        }

        $sql_delete = "DELETE FROM users WHERE general_id = ?";
        $stmt       = mysqli_prepare($this->db, $sql_delete);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $general_id);
            $execution = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($execution) {
                $this->sendSuccessResponse(['deleted_id' => $general_id]);
            }
        }

        $this->sendErrorResponse('DeletionActionFailedInterfaceAbort');
    }

    private function sendSuccessResponse($dataPayload)
    {
        $payload = [
            'status'   => 1,
            'response' => 1,
            'data'     => $dataPayload
        ];

        echo json_encode([
            'response_status' => 1,
            'msg_response'    => 'success',
            'curl_response'   => $this->api->encryptApiData(json_encode($payload))
        ]);
        exit();
    }

    private function sendErrorResponse($message)
    {
        echo json_encode([
            'response_status' => 0,
            'msg_response'    => $message
        ]);
        exit();
    }
}
