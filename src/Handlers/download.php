<?php
# Core Config Dependencies
$db_connect = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

ignore_user_abort(true);
# Server Execution Limits [uncomment ONLY for long-running scripts like reports/imports]
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '1024M');

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;
# ===================================================================================

# Validate Access
$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
if (!($system_auth_login == $g_public_key)) {
    header("location: " . SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['link']['main']);
    exit();
}

$session_class->session_close();

$g_user_role = $g_user_role ?? '';
$db_connect = $db_connect ?? '';

## verify user access
if (!($g_user_role == "ADMIN")) {
    header("Location: " . BASE_URL); //balik sa login then sa login aalamain kung anung role at saang page landing dapat
    exit();
}

$id = isset($_GET['attach']) ? trim($_GET['attach']) : '';

if ($id != "") {
    $fullPath = "";
    // if ($id == "IMP_BLK_USRINF" && ($g_user_role == "ADMIN")) {
    //     $fullPath = UPLOAD_GUIDE_PATH . USER_INFORMATION_TEMPLATE;
    // } else if ($id == "IMP_BLK_EMPINF" && ($g_user_role == "ADMIN")) {
    //     $fullPath = UPLOAD_GUIDE_PATH . EMPLOYEE_INFORMATION_TEMPLATE;
    // } else if ($id == "IMP_BLK_EMPSYS" && ($g_user_role == "ADMIN")) {
    //     $fullPath = UPLOAD_GUIDE_PATH . EMPLOYEE_SYSTEM_ACCESS_TEMPLATE; 
    // } else {
    //     require_once HTTP_404;
    //     exit();
    // }

    if (file_exists($fullPath)) {
        if ($fd = fopen($fullPath, "r")) {
            $fsize = filesize($fullPath);
            $path_parts = pathinfo($fullPath);
            $ext = strtolower($path_parts["extension"]);
            switch ($ext) {
                case "pdf":
                    header("Content-type: application/pdf");
                    header("Content-Disposition: attachment; filename=\"" . $path_parts["basename"] . "\""); // use 'attachment' to force a file download
                    break;
                // add more headers for other content types here
                default;
                    header("Content-type: application/octet-stream");
                    header("Content-Disposition: filename=\"" . $path_parts["basename"] . "\"");
                    break;
            }
            header("Content-length: " . $fsize);
            header("Cache-control: private"); //use this to open files directly
            //session_write_close();
            while (!feof($fd)) {
                $buffer = fread($fd, 2048);
                echo $buffer;
            }
        }
        fclose($fd);
        exit();
    }
    require_once HTTP_404;
    exit();
}


require_once HTTP_404;
exit();
