<?php
# ===================================================================================
# Core Config Dependencies
# Included [DOMAIN_PATH, CONFIG, GLOBAL_FUNC, CL_SESSION_PATH, CONNECT_PATH]

# Database Connection Fallback
$db_connect = $db_connect ?? null;

# Check Access Login state [if not login, return to homepage]
require_once ISLOGIN;

$targer_link = SYSTEM_ACCESS[SYSTEM_FALLBACK]['link']['main'];

$log_status = false;
try {
    $log_status = system_user_log(array("ID_USER" => $g_user_id, "IP" => $g_ip, "TOKEN" => $g_token_id, "ACTION" => "LOGOUT", "AGENTS" => $g_device, "SUMMARY" => "", "USER_ROLE" => $g_system_role));
    if ($log_status === true) {
        $session_class->destroy();
        header('Location: ' . $targer_link); ## redirect to e-Guro++
        exit();
    }
} catch (mysqli_sql_exception $e) {
    $response = base64_encode("Systemic Storage Fault: " . $e->getMessage());
    header('Location: ' . BASE_URL . '/Admin/login-access');
    exit();
}

