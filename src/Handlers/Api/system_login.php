<?php
defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 3));
require_once DOMAIN_PATH . '/config/config.php';
require_once GLOBAL_FUNC;
require_once CL_SESSION_PATH;
require_once CONNECT_PATH;
require_once HELPER;
require_once API_CONNECT;

# Safely target fallback route context
$targerLink = SYSTEM_ACCESS[SYSTEM_FALLBACK]['link']['main'];

if (isset($_GET['token']) && trim($_GET['token']) !== '') {
    $system_token = trim($_GET['token']);

    # Initialize the matching ApiHelper class context on the consumer site
    $api = new \Src\Api\ApiHelper($db_connect);

    # Set matching crypto key context based on local structural identity configuration
    $api->setKeys();

    $decoded_data = "";
    try {
        $decoded_data = $api->decryptApiData($system_token);
    } catch (Exception $e) {
        $response = base64_encode("Invalid Token Encryption Scheme. Please log in again.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    $data = explode(',', $decoded_data);
    if (count($data) < 7) {
        $response = base64_encode("Malformed payload parameters.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    $ref_id      = trim($data[0]);
    $system_role = trim($data[1]);
    $token_date  = trim($data[2]);
    $token_auth  = trim($data[3]);
    $token_id    = trim($data[4]);
    $device      = base64_decode($data[5]);
    $ip          = base64_decode($data[6]);

    if (empty($ref_id) || empty($system_role) || empty($token_date) || empty($token_auth) || empty($token_id)) {
        $response = base64_encode("Invalid Token Data Elements. Please try again.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    if (!validateDate($token_date, "Y-m-d H:i:s") || DATE_TIME > $token_date) {
        $response = base64_encode("Token validation time context expired.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    if ($token_auth !== SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']) {
        $response = base64_encode("Invalid Subsystem Authority Verification Code.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    # select data
    $_data = [];
    $query = "SELECT tbl_user.*, 
                --  login
                tbl_login.username, tbl_login.password, tbl_login.recovery_email, tbl_login.status, tbl_login.locked,
                -- system access
                tbl_access.ref_id, tbl_access.system_type, tbl_access.system_role, tbl_access.access_tag, tbl_access.employee_update, tbl_access.student_update, tbl_access.flag_access,
                -- employee
                tbl_employee.employee_id, tbl_employee.personnel_classification, tbl_employee.employment_status, tbl_employee.employment_basis, tbl_employee.position, tbl_employee.employment_date, tbl_employee.office_id, tbl_employee.department_id, tbl_employee.room_id, tbl_employee.service_status

                FROM users AS tbl_user
                LEFT JOIN login AS tbl_login ON tbl_login.user_id = tbl_user.id 
                LEFT JOIN system_access AS tbl_access ON tbl_access.user_id = tbl_user.id 
                LEFT JOIN employee AS tbl_employee ON tbl_employee.user_id = tbl_user.id 
                WHERE tbl_user.id = ? AND tbl_access.system_role = ? LIMIT 1";
    if ($stmt = mysqli_prepare($db_connect, $query)) {
        mysqli_stmt_bind_param($stmt, "ii", $ref_id, $system_role);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($data = mysqli_fetch_assoc($result)) {
            $_data = $data;
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($_data)) {
        $response = base64_encode("No matching systemic record assignment found locally.");
        header('Location: ' . $targerLink . '?token-response=' . $response);
        exit();
    }

    $log_status = false;
    try {
        $log_status = system_user_log(array("ID_USER" => $ref_id, "IP" => $ip, "TOKEN" => $token_id, "ACTION" => "LOGIN", "AGENTS" => $device, "SUMMARY" => "", "USER_ROLE" => $system_role));
        if ($log_status === false) {
            $response = base64_encode("Pipeline Log Registration Fault.");
            header('Location: ' . $targerLink . '?token-response=' . $response);
            exit();
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $response = base64_encode("Token replay intercepted. Authentication vector already consumed.");
            header('Location: ' . $targerLink . '?token-response=' . $response);
            exit();
        } else {
            $response = base64_encode("Systemic Storage Fault: " . $e->getMessage());
            header('Location: ' . $targerLink . '?token-response=' . $response);
            exit();
        }
    }

    if ($log_status) {
        // Regenerate session ID ONCE safely upon successful login
        $session_class->regenerate();

        $db_system_type = $_data['system_type'];
        $db_system_role = $_data['system_role'];

        $session_class->setValue('token_id', $token_id);
        $session_class->setValue('device', $device);
        $session_class->setValue('ip', $ip);

        $session_class->setValue('login', 'success');
        $session_class->setValue('user_id', $_data['id']);

        $system_role_name = SYSTEM_ACCESS[$db_system_type]['role'][$db_system_role];

        $session_class->setValue('system_role', $_data['system_role']);
        $session_class->setValue('user_role_id', $db_system_role);
        $session_class->setValue('user_role', $system_role_name);

        $session_class->setValue('employee_id', $_data['employee_id']);
        $session_class->setValue('fullname', trim($_data['first_name'] . ' ' . $_data['last_name']));

        $fingerprint = $session_class->getValue('fingerprint');
        $session_class->setValue('browser_fingerprint', $fingerprint);

        // Commit and release session write lock before header redirect
        $session_class->session_close();
        
        header("Location: " . BASE_URL . "admin/login-access");
        exit();
    }

    $response = base64_encode("Request processing vector denied.");
    header('Location: ' . $targerLink . '?token-response=' . $response);
    exit();
} else {
    $response = base64_encode("Authentication access denied. Token context empty.");
    header('Location: ' . $targerLink . '?token-response=' . $response);
    exit();
}
