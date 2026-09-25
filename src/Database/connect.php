<?php
defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 2));

# ======================================================================
# DATABASE ENVIRONMENT CREDENTIALS RESOLUTION
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_name = getenv('DB_NAME') ?: 'e_eskuelog';
$db_pass = getenv('DB_PASS') ?: '';

$db_connect = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (mysqli_connect_errno()) {
    $error = "Failed to connect to Database: " . mysqli_connect_error();
    error_log($error);
    if (defined('SYSTEM_FLAG') && SYSTEM_FLAG === 'DEV') {
        echo $error;
    } else {
        echo "An internal configuration database error occurred.";
    }
    exit();
}

# ======================================================================
# LEGACY COMPATIBILITY WRAPPERS
function escape($con = "", $str = "")
{
    global $db_connect;
    return mysqli_real_escape_string($db_connect, $str ?? '');
}

function db_close()
{
    global $db_connect;
    mysqli_close($db_connect);
}

function call_mysql_query($query, $connect = '')
{
    global $db_connect;
    $connect = empty($connect) ? $db_connect : $connect;
    if (empty($query)) return false;
    return mysqli_query($connect, $query);
}

function call_mysql_fetch_array($query, $resulttype = MYSQLI_ASSOC, $connect = '')
{
    return mysqli_fetch_array($query, $resulttype);
}

function call_mysql_num_rows($query)
{
    return $query ? mysqli_num_rows($query) : 0;
}

function call_mysql_affected_rows($connect = '')
{
    global $db_connect;
    $connect = empty($connect) ? $db_connect : $connect;
    return mysqli_affected_rows($connect);
}

function mysqli_query_return($sql_query, $connect = "")
{
    global $db_connect;
    $connect = empty($connect) ? $db_connect : $connect;
    $rdata = array();
    if (empty($sql_query)) return $rdata;

    if ($query = mysqli_query($connect, $sql_query)) {
        while ($data = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
            array_push($rdata, $data);
        }
    }
    return $rdata;
}

function mysqliquery_return($sql_query, $connect = "", $type = MYSQLI_ASSOC)
{
    global $db_connect;
    $connect = (empty($connect)) ? $db_connect : $connect;
    $rdata = array();

    if ($query = mysqli_query($connect, $sql_query)) {
        while ($data = mysqli_fetch_array($query, $type)) {
            $rdata[] = $data;
        }
    }
    return $rdata;
}

# ======================================================================
# HARDENED AUDIT SYSTEMS
function activity_log_new($action)
{
    global $db_connect, $session_class;

    if (!$db_connect || !$session_class) return false;

    $date_now = date('Y-m-d H:i:s');
    $user_id = $session_class->getValue('user_id');
    $role_txt = $session_class->getValue('user_role');
    $fingerprint = $session_class->getValue('browser_fingerprint');

    $role_map = ["ADMIN" => 1, "REGISTRAR" => 2, "VPAA" => 3, "OFFICIAL" => 4, "FACULTY" => 5, "STUDENT" => 6];
    $role_id = $role_map[$role_txt] ?? 0;

    if (!empty($user_id) && trim((string)$action) !== "") {
        $stmt = mysqli_prepare($db_connect, "INSERT INTO activity_log (user_id, action, date_log, session_id, user_level) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssi", $user_id, $action, $date_now, $fingerprint, $role_id);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
    }
    return false;
}

function data_log_new($action, $user_id, $system_access = "", $jwt_data = "", $process_flag = "")
{
    global $db_connect;
    if (!$db_connect) return false;

    $date_now = DATE_TIME;
    $action = trim((string)$action);

    if (!empty($user_id) && $action !== '') {
        if ($action === 'INSERT_DATA' && !empty($jwt_data) && $process_flag !== '' && !empty($system_access)) {
            $stmt = mysqli_prepare($db_connect, "INSERT INTO log (user_id, system_access, data_log, process_flag, action_flag, date_log) VALUES (?, ?, ?, ?, '0', ?)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "sssss", $user_id, $system_access, $jwt_data, $process_flag, $date_now);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $success;
            }
        } elseif ($action === 'UPDATE_DATA') {
            $stmt = mysqli_prepare($db_connect, "UPDATE log SET action_flag = '1' WHERE log_id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $user_id);
                $success = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $success;
            }
        }
    }
    return false;
}

function user_log($action, $agents = array())
{
    global $db_connect, $session_class;

    if (!$db_connect || !$session_class) return false;

    $date_now = date('Y-m-d H:i:s');
    $g_user_id = $session_class->getValue('user_id');
    $fingerprint = $session_class->getValue('browser_fingerprint');
    $ip = function_exists('get_ip') ? get_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');
    $device = json_encode($agents);
    $action = strtoupper(trim((string)$action));

    if ($action === "LOGIN" && !empty($g_user_id)) {
        $stmt = mysqli_prepare($db_connect, "INSERT INTO user_log (login_date, action, user_id, session_id, ip_address, device) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ssssss", $date_now, $action, $g_user_id, $fingerprint, $ip, $device);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
    } elseif ($action === 'LOGOUT' && !empty($fingerprint)) {
        $stmt = mysqli_prepare($db_connect, "UPDATE user_log SET logout_date = ?, action = CONCAT(action, '::LOGOUT') WHERE session_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ss", $date_now, $fingerprint);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success;
        }
    }
    return false;
}

function system_user_log($data = array())
{
    global $db_connect;

    if (!$db_connect) {
        trigger_error("system_user_log: Database connection error - " . mysqli_connect_error(), E_USER_WARNING);
        return false;
    }

    $input = array_merge([
        "ID_USER"   => "",
        "IP"        => "",
        "TOKEN"     => "",
        "ACTION"    => "",
        "AGENTS"    => "",
        "SUMMARY"   => "",
        "USER_ROLE" => ""
    ], $data);

    $token_id = trim((string)$input['TOKEN']);
    if (empty($input['ID_USER'])) return false;

    $action    = strtoupper(trim((string)$input['ACTION']));
    $userId    = trim((string)$input['ID_USER']);
    $ip        = trim((string)$input['IP']);
    $agents    = trim((string)$input['AGENTS']);
    $userRole  = trim((string)$input['USER_ROLE']);

    $user_login_id   = '';
    $duplicate_token = false;

    // Fetch existing log entry for today using Prepared Statements
    $select = "SELECT user_log_id, token_id FROM user_log WHERE DATE_FORMAT(login_date,'%Y-%m-%d') = ? AND user_id = ? LIMIT 1";
    if ($stmt = mysqli_prepare($db_connect, $select)) {
        $dateNow = DATE_NOW;
        mysqli_stmt_bind_param($stmt, "ss", $dateNow, $userId);

        if (mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $user_login_id = $row['user_log_id'];

                $token_array = json_decode((string)$row['token_id'], true);
                if (!is_array($token_array)) {
                    $token_array = [];
                }
                if (in_array($token_id, $token_array, true)) {
                    $duplicate_token = true;
                }
            }
        } else {
            trigger_error("Select Execute Error: " . mysqli_stmt_error($stmt), E_USER_WARNING);
        }
        mysqli_stmt_close($stmt);
    } else {
        trigger_error("Select Prepare Error: " . mysqli_error($db_connect), E_USER_WARNING);
    }

    // No log record found for today yet (Insert New Row)
    if (empty($user_login_id)) {
        $dateTime = DATE_TIME;
        $json_summary = json_encode([[$dateTime, $action, $ip]]);
        $json_token   = json_encode([$token_id]);

        if ($action === 'LOGIN') {
            $sql = "INSERT INTO user_log (login_date, logout_date, action, user_id, session_id, ip_address, device, token_id, login_flag, user_level) 
                    VALUES (?, NULL, ?, ?, ?, ?, ?, ?, '1', ?)";

            if ($stmt = mysqli_prepare($db_connect, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssss", $dateTime, $action, $userId, $json_summary, $ip, $agents, $json_token, $userRole);
                $success = mysqli_stmt_execute($stmt);
                if (!$success) {
                    trigger_error("INSERT LOGIN Execute Error: " . mysqli_stmt_error($stmt), E_USER_WARNING);
                }
                mysqli_stmt_close($stmt);
                return $success;
            } else {
                trigger_error("INSERT LOGIN Prepare Error: " . mysqli_error($db_connect), E_USER_WARNING);
            }
        } else if ($action === 'LOGOUT') {
            $sql = "INSERT INTO user_log (login_date, logout_date, action, user_id, session_id, ip_address, device, token_id, login_flag, user_level) 
                    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, '0', ?)";

            if ($stmt = mysqli_prepare($db_connect, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssssssss", $dateTime, $action, $userId, $json_summary, $ip, $agents, $json_token, $userRole);
                $success = mysqli_stmt_execute($stmt);
                if (!$success) {
                    trigger_error("INSERT LOGOUT Execute Error: " . mysqli_stmt_error($stmt), E_USER_WARNING);
                }
                mysqli_stmt_close($stmt);
                return $success;
            } else {
                trigger_error("INSERT LOGOUT Prepare Error: " . mysqli_error($db_connect), E_USER_WARNING);
            }
        }
    } else {
        if ($action === 'LOGIN') {
            if ($duplicate_token) return 'duplicate';
            $sql = "UPDATE user_log 
                    SET session_id = JSON_ARRAY_APPEND(session_id, '$', JSON_ARRAY(?, ?, ?)), 
                        token_id = JSON_ARRAY_APPEND(COALESCE(token_id, JSON_ARRAY()), '$', ?), 
                        login_flag = '1', 
                        user_level = ? 
                    WHERE user_log_id = ?";

            if ($stmt = mysqli_prepare($db_connect, $sql)) {
                $dateTime = DATE_TIME;
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssss",
                    $dateTime,      // JSON_ARRAY arg 1
                    $action,        // JSON_ARRAY arg 2
                    $ip,            // JSON_ARRAY arg 3
                    $token_id,      // token_id append value
                    $userRole,      // user_level
                    $user_login_id  // WHERE clause user_log_id
                );
                $success = mysqli_stmt_execute($stmt);
                if (!$success) {
                    trigger_error("UPDATE LOGIN Execute Error: " . mysqli_stmt_error($stmt), E_USER_WARNING);
                }
                mysqli_stmt_close($stmt);
                return $success;
            } else {
                trigger_error("UPDATE LOGIN Prepare Error: " . mysqli_error($db_connect), E_USER_WARNING);
            }
        } else if ($action === 'LOGOUT') {
            $sql = "UPDATE user_log 
                    SET logout_date = ?, 
                        session_id = JSON_ARRAY_APPEND(session_id, '$', JSON_ARRAY(?, ?, ?)), 
                        login_flag = '0', 
                        user_level = ? 
                    WHERE user_log_id = ?";

            if ($stmt = mysqli_prepare($db_connect, $sql)) {
                $dateTime = DATE_TIME;
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssss",
                    $dateTime,      // logout_date
                    $dateTime,      // JSON_ARRAY arg 1
                    $action,        // JSON_ARRAY arg 2
                    $ip,            // SON_ARRAY arg 3
                    $userRole,      // user_level
                    $user_login_id  // WHERE clause user_log_id
                );
                $success = mysqli_stmt_execute($stmt);
                if (!$success) {
                    trigger_error("UPDATE LOGOUT Execute Error: " . mysqli_stmt_error($stmt), E_USER_WARNING);
                }
                mysqli_stmt_close($stmt);
                return $success;
            } else {
                trigger_error("UPDATE LOGOUT Prepare Error: " . mysqli_error($db_connect), E_USER_WARNING);
            }
        }
    }

    return false;
}

function get_profile_pic($user_id, $field, $table)
{
    global $db_connect;
    $path = "";
    if (trim($user_id ?? '') == "" || trim($field ?? '') == "" || trim($table ?? '') == "") return "";

    $field = preg_replace('/[^a-zA-Z0-9_]/', '', $field);
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

    $query = "SELECT location FROM {$table} WHERE {$field} = ? LIMIT 1";
    if ($stmt = mysqli_prepare($db_connect, $query)) {
        mysqli_stmt_bind_param($stmt, "s", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($data = mysqli_fetch_assoc($result)) {
            $path = empty($data['location']) ? "" : BASE_URL . $data['location'];
        }
        mysqli_stmt_close($stmt);
    }
    return $path;
}

function isduplicate_where($table_name, $select_column, $sql_where)
{
    global $db_connect;
    $table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
    $select_column = preg_replace('/[^a-zA-Z0-9_]/', '', $select_column);

    $default_query = "SELECT {$select_column} FROM {$table_name} WHERE {$sql_where} LIMIT 1";
    if ($query = mysqli_query($db_connect, $default_query)) {
        return mysqli_num_rows($query) > 0;
    }
    return false;
}
