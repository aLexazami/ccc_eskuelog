<?php
// ======================================================================
// [City College of Calamba] CENTRAL ENTRYPOINT PIPELINE HANDLER FOR API
// Location: /src/Handlers/api/system_access.php
// ======================================================================

defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 3));
require_once DOMAIN_PATH . '/config/config.php';
require_once GLOBAL_FUNC;
require_once CL_SESSION_PATH;
require_once CONNECT_PATH;         // Instantiates your global $db_connect variable
require_once HELPER;
require_once API_CONNECT;          // Loads your ApiHelper context setup code
require_once API_SYSTEM_CONNECT;  // Loads your ApiSystemHelper ($api_controller) setup code


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['response_status' => 0, 'msg_response' => 'Method Not Allowed']);
    exit();
}

try {
    # Fetch the decrypted payload values directly from the controller instance state
    # The ApiSystemHelper constructor has already decoded the inner fields into an object
    $decryptedPayload = $api_controller->getPayloadData();
    $action = isset($decryptedPayload->action) ? trim($decryptedPayload->action) : '';

    # Delegate execution securely based on the decrypted text properties
    switch ($action) {
        case 'add_account':
            $api_controller->addAccount();
            break;

        case 'update_account':
            $api_controller->addAccount();
            break;

        case 'delete_account':
            $api_controller->deleteAccount();
            break;

        default:
            echo json_encode(['response_status' => 0, 'msg_response' => 'Action Engine Method Variant Not Recognized']);
            exit();
    }
} catch (mysqli_sql_exception $e) {
    echo json_encode([
        'response_status' => 0,
        'msg_response' => ($e->getCode() == 1062) ? 'Duplicated Unique Key Data Constraint Fault' : $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode(['response_status' => 0, 'msg_response' => 'Fatal Subsystem Engine Core Failure Exception: ' . $e->getMessage()]);
}
exit();
