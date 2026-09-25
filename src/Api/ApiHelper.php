<?php

namespace Src\Api;

use Exception;

class ApiHelper
{
    private $db;
    private $system_key;
    private $public_key;
    private $secret_key;
    private $cipher = 'aes-256-cbc';

    public function __construct($db, $default_system = null)
    {
        $this->db = $db;
        if ($default_system !== null) {
            $this->setKeys($default_system);
        } elseif (defined('SYSTEM_ACCESS_NAME')) {
            $this->setKeys(SYSTEM_ACCESS_NAME);
        }
    }

    /**
     * Private helper to log synchronization errors into the database.
     */
    private function logError(string $systemType, string $actionType, string $errorMessage, ?string $targetTable = null, ?string $recordIdentifier = null, $payload = null): void
    {
        $sql = "INSERT INTO sync_logs (system_type, action_type, target_table, record_identifier, error_message, payload) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->db, $sql);

        if ($stmt) {
            $payloadStr = is_string($payload) ? $payload : json_encode($payload);
            mysqli_stmt_bind_param($stmt, "ssssss", $systemType, $actionType, $targetTable, $recordIdentifier, $errorMessage, $payloadStr);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }

    /**
     * Fetch all active system types (flag_status = 0)
     *
     * @return array List of active system_type strings
     */
    public function getActiveTransferSystemTypes(): array
    {
        $activeSystems = [];
        $sql = "SELECT system_type FROM system_key WHERE flag_transfer = 1 AND flag_status = 0";
        $result = mysqli_query($this->db, $sql);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $activeSystems[] = $row['system_type'];
            }
            mysqli_free_result($result);
        }

        return $activeSystems;
    }

    public function setKeys($system = null)
    {
        if ($system === null) {
            $system = defined('SYSTEM_ACCESS_NAME') ? SYSTEM_ACCESS_NAME : '';
        }

        if (empty($system)) {
            throw new Exception("No system type provided for key retrieval.");
        }

        $sql = "SELECT system_key, public_key, secret_key FROM system_key WHERE (system_type = ? OR public_key = ?) AND flag_status = 0 LIMIT 1";
        $stmt = mysqli_prepare($this->db, $sql);

        if (!$stmt) {
            throw new Exception("DB prepare failed: " . mysqli_error($this->db));
        }

        mysqli_stmt_bind_param($stmt, "ss", $system, $system);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $system_key, $public_key, $secret_key);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!$public_key || !$secret_key) {
            throw new Exception("Invalid or inactive system keys for system: $system");
        }

        $this->system_key = $system_key;
        $this->public_key = $public_key;
        $this->secret_key = $secret_key;
    }

    public function get_system_key()
    {
        return $this->system_key;
    }

    public function get_public_key()
    {
        return $this->public_key;
    }

    public function get_secret_key()
    {
        return $this->secret_key;
    }

    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }

    public function encryptApiData($data)
    {
        if (empty($this->secret_key)) {
            return ["error" => "Secret key not set"];
        }

        $plaintext = "$data";
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $raw_encrypted = openssl_encrypt($plaintext, $this->cipher, $this->secret_key, OPENSSL_RAW_DATA, $iv);
        $encoded_ciphertext = base64_encode($raw_encrypted);
        $encoded_iv = base64_encode($iv);

        return $this->base64url_encode($encoded_ciphertext . '::' . $encoded_iv);
    }

    public function decryptApiData($data)
    {
        if (empty($this->secret_key)) {
            return ["error" => "Secret key not set"];
        }

        $decoded_raw = $this->base64url_decode($data);
        if (!$decoded_raw) {
            return ["error" => "Malformed base64 structural context"];
        }

        $parts = explode('::', $decoded_raw, 2);
        if (count($parts) < 2) {
            return ["error" => "Malformed encrypted payload data string structure"];
        }

        list($encoded_ciphertext, $encoded_iv) = $parts;
        $ciphertext = base64_decode($encoded_ciphertext);
        $iv = base64_decode($encoded_iv);

        return openssl_decrypt($ciphertext, $this->cipher, $this->secret_key, OPENSSL_RAW_DATA, $iv);
    }

    public function curlRequest($url, $payload, $token = '')
    {
        $ch = curl_init();
        $headers = [
            'Content-Type: application/json',
            'X-PUBLIC-KEY: ' . $this->public_key
        ];

        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $postData = (is_array($payload) || is_object($payload)) ? json_encode($payload) : $payload;

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return json_encode(['response_status' => 0, 'error_msg' => $err]);
        }

        curl_close($ch);
        return $response;
    }

    public function getRequestPublicKey()
    {
        $headers = getallheaders();
        return $headers['X-PUBLIC-KEY'] ?? $headers['X-Public-Key'] ?? null;
    }

    public function getJsonInput()
    {
        $input = file_get_contents("php://input");
        $decoded = json_decode($input, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $input;
    }

    public function syncAccountData(string $apiUrl, string $systemType, array $items, string $actionType = 'add_account', bool $autoUpdateRefId = false): array
    {
        try {
            if (empty($apiUrl) || empty($items)) {
                $errMsg = 'Target API URL or item queue cannot be empty.';
                $this->logError($systemType, $actionType, $errMsg, null, null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $this->setKeys($systemType);

            $encodedPayload = [
                'items'         => $items,
                'action'        => $actionType,
                'system_type'   => $systemType,
                'total_records' => isset($items['items']) ? count($items['items']) : 1
            ];

            $encryptedString = $this->encryptApiData(json_encode($encodedPayload));
            $payloadWrapper  = ['curl_response' => $encryptedString];

            $responseApi = $this->curlRequest($apiUrl, $payloadWrapper, $this->get_public_key());
            $resultJson  = json_decode($responseApi, true);

            if (json_last_error() !== JSON_ERROR_NONE || (isset($resultJson['response_status']) && (int)$resultJson['response_status'] === 0)) {
                $errMsg = $resultJson['msg_response'] ?? $resultJson['error_msg'] ?? 'Subsystem Rejection or JSON format breach.';
                $this->logError($systemType, $actionType, $errMsg, 'system_access', null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            if (!isset($resultJson['curl_response'])) {
                $errMsg = 'Missing expected encrypted payload in API response.';
                $this->logError($systemType, $actionType, $errMsg, 'system_access', null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $decryptedPayload = $this->decryptApiData($resultJson['curl_response']);
            $responseData     = json_decode($decryptedPayload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errMsg = 'Handshake Response Decryption Failure. Invalid JSON string returned.';
                $this->logError($systemType, $actionType, $errMsg, 'system_access', null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $processed_records = $responseData['data']['processed_records'] ?? [];
            $successCount = 0;

            if ($autoUpdateRefId && !empty($processed_records)) {
                $updateStmt = mysqli_prepare($this->db, "UPDATE system_access SET ref_id = ? WHERE user_id = ? AND system_type = ? AND system_role = ?");

                if ($updateStmt) {
                    $system_ref_id = null;
                    $user_id       = null;
                    $system_type   = null;
                    $system_role   = null;

                    if (mysqli_stmt_bind_param($updateStmt, "iisi", $system_ref_id, $user_id, $system_type, $system_role)) {
                        foreach ($processed_records as $item) {
                            $system_ref_id = $item['system_ref_id'] ?? null;
                            $user_id       = $item['user_id'] ?? null;
                            $system_type   = $item['system_type'] ?? null;
                            $system_role   = $item['system_role'] ?? null;

                            if (!empty($system_ref_id) && !empty($user_id) && $system_role !== null && $system_role !== '') {
                                if (mysqli_stmt_execute($updateStmt)) {
                                    $successCount++;
                                } else {
                                    $errMsg = "Failed updating system_access: " . mysqli_stmt_error($updateStmt);
                                    $this->logError($systemType, $actionType, $errMsg, 'system_access', (string)$user_id, $item);
                                }
                            }
                        }
                    } else {
                        $this->logError($systemType, $actionType, "Statement parameter binding failed.", 'system_access', null, $items);
                    }

                    mysqli_stmt_close($updateStmt);
                } else {
                    $this->logError($systemType, $actionType, "DB prepare statement failed: " . mysqli_error($this->db), 'system_access', null, $items);
                }
            }

            return [
                'success'         => true,
                'message'         => 'Synchronization completed successfully.',
                'updated_records' => $successCount,
                'data'            => $processed_records
            ];
        } catch (\Exception $e) {
            $this->logError($systemType, $actionType, 'Pipeline Execution Failure: ' . $e->getMessage(), null, null, $items);
            return ['success' => false, 'message' => 'Pipeline Execution Failure: ' . $e->getMessage()];
        }
    }

    public function syncInformationData(string $apiUrl, string $systemType, array $items, string $actionType = 'add_information', bool $autoUpdateRefId = false): array
    {
        try {
            if (empty($apiUrl) || empty($items)) {
                $errMsg = 'Target API URL or item queue cannot be empty.';
                $this->logError($systemType, $actionType, $errMsg, null, null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $this->setKeys($systemType);

            $encodedPayload = [
                'items'         => $items,
                'action'        => $actionType,
                'system_type'   => $systemType,
                'total_records' => isset($items['items']) ? count($items['items']) : 1
            ];

            $encryptedString = $this->encryptApiData(json_encode($encodedPayload));
            $payloadWrapper  = ['curl_response' => $encryptedString];

            $responseApi = $this->curlRequest($apiUrl, $payloadWrapper, $this->get_public_key());
            $resultJson  = json_decode($responseApi, true);

            if (json_last_error() !== JSON_ERROR_NONE || (isset($resultJson['response_status']) && (int)$resultJson['response_status'] === 0)) {
                $errMsg = $resultJson['msg_response'] ?? $resultJson['error_msg'] ?? 'Subsystem Rejection or JSON format breach.';
                $this->logError($systemType, $actionType, $errMsg, null, null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            if (!isset($resultJson['curl_response'])) {
                $errMsg = 'Missing expected encrypted payload in API response.';
                $this->logError($systemType, $actionType, $errMsg, null, null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $decryptedPayload = $this->decryptApiData($resultJson['curl_response']);
            $responseData     = json_decode($decryptedPayload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $errMsg = 'Handshake Response Decryption Failure. Invalid JSON string returned.';
                $this->logError($systemType, $actionType, $errMsg, null, null, $items);
                return ['success' => false, 'message' => $errMsg];
            }

            $processed_records = $responseData['data']['processed_records'] ?? [];
            $successCount = 0;

            if ($autoUpdateRefId && !empty($processed_records)) {
                foreach ($processed_records as $item) {
                    $tableName = $item['table'] ?? null;

                    if (empty($tableName) && is_array($items)) {
                        $tableName = key($items);
                    }

                    if (!empty($tableName) && isset($item['status']) && $item['status'] === true) {
                        $idColumn = null;
                        $idValue  = null;

                        if (isset($items[$tableName]) && is_array($items[$tableName])) {
                            foreach ($items[$tableName] as $col => $val) {
                                if (substr($col, -3) === '_id' || $col === 'id') {
                                    $idColumn = $col;
                                    $idValue  = $val;
                                    break;
                                }
                            }
                        }

                        if ($idColumn && $idValue !== null) {
                            $updateSql  = "UPDATE {$tableName} SET flag_update = 1 WHERE {$idColumn} = ?";
                            $updateStmt = mysqli_prepare($this->db, $updateSql);

                            if ($updateStmt) {
                                mysqli_stmt_bind_param($updateStmt, "s", $idValue);
                                if (mysqli_stmt_execute($updateStmt)) {
                                    $successCount++;
                                } else {
                                    $errMsg = "Failed to update flag_update in '{$tableName}': " . mysqli_stmt_error($updateStmt);
                                    $this->logError($systemType, $actionType, $errMsg, $tableName, (string)$idValue, $item);
                                }
                                mysqli_stmt_close($updateStmt);
                            } else {
                                $errMsg = "Failed to prepare update query for '{$tableName}': " . mysqli_error($this->db);
                                $this->logError($systemType, $actionType, $errMsg, $tableName, (string)$idValue, $item);
                            }
                        } else {
                            $errMsg = "Identifier column (_id/id) missing or empty for table '{$tableName}'.";
                            $this->logError($systemType, $actionType, $errMsg, $tableName, null, $item);
                        }
                    } else {
                        // Log failure returned from the subsystem endpoint for this record
                        $errMsg = $item['message'] ?? "Subsystem failed to process table '{$tableName}'.";
                        $this->logError($systemType, $actionType, $errMsg, $tableName, null, $item);
                    }
                }
            }

            return [
                'success'         => true,
                'message'         => 'Synchronization completed successfully.',
                'updated_records' => $successCount,
                'data'            => $processed_records
            ];
        } catch (\Exception $e) {
            $this->logError($systemType, $actionType, 'Pipeline Execution Failure: ' . $e->getMessage(), null, null, $items);
            return ['success' => false, 'message' => 'Pipeline Execution Failure: ' . $e->getMessage()];
        }
    }
}
