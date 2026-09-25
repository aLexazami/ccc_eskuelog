<?php
# ======================================================================
# NPUT SANITIZATION & CROSS-SITE SCRIPTING (XSS) FIREWALLS

function html($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function xml($string)
{
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function var_html($str)
{
    return htmlspecialchars(js_clean($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function array_html($str)
{
    if (!is_null($str) && is_array($str)) {
        foreach ($str as $index => $value) {
            if (empty($value)) {
                continue;
            }
            $str[$index] = htmlspecialchars(js_clean($value), ENT_QUOTES, "UTF-8");
        }
    }
    return $str;
}

function js_clean_array($array)
{
    $return = array();
    if (!empty($array) && is_array($array)) {
        foreach ($array as $index => $value) {
            $return[$index] = empty($value) ? $value : js_clean($value);
        }
    }
    return $return;
}

function js_clean($data)
{
    if (is_null($data) || !is_string($data)) {
        return $data;
    }

    // Remove any attribute starting with "on" or xmlns properties
    $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

    // Remove javascript: and vbscript: execution layer protocols
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
    $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

    // Strip expression layers targeting legacy engines
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
    $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

    $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

    do {
        $old_data = $data;
        $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
    } while ($old_data !== $data);

    return $data;
}

# ======================================================================
# FILE SYSTEM OPERATIONS & SYSTEM PATH SECURITY GUARDS
function del_file($file)
{
    // Path traversal block: Ensure file operations remain anchored safely
    if (strpos($file, '..') !== false) {
        return false;
    }
    if (file_exists($file) && is_file($file)) {
        unlink($file);
    }
}

function deleteDir($dirPath)
{
    if (empty($dirPath) || strpos($dirPath, '..') !== false) {
        return false;
    }
    if (!is_dir($dirPath)) {
        throw new InvalidArgumentException($dirPath . " must be a directory");
    }

    $dirPath = rtrim($dirPath, '/') . '/';
    if ($handle = opendir($dirPath)) {
        while (false !== ($sub = readdir($handle))) {
            if ($sub != "." && $sub != ".." && $sub != "Thumb.db") {
                $file = $dirPath . $sub;
                if (is_dir($file)) {
                    deleteDir($file);
                } else {
                    unlink($file);
                }
            }
        }
        closedir($handle);
    }
    rmdir($dirPath);
}

function get_filesize($filePath)
{
    if (!file_exists($filePath)) {
        return 0;
    }
    if (is_file($filePath)) {
        return filesize($filePath);
    }

    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filePath, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
        $size += $file->getSize();
    }
    return $size;
}

function summernote_image($content)
{
    if (empty($content)) return $content;
    if (strpos($content, '.tmp') !== false) {
        if (preg_match_all('/<img[^>]+src=["\']([^=]*)["\'][^>]*>/i', $content, $images)) {
            $images_srcs = $images[1];
            $collect = array();
            foreach ($images_srcs as $image_src) {
                if ($image_src == "" || strpos($image_src, '.tmp') === false) continue;

                $image_path = DOMAIN_PATH . '/' . str_replace(BASE_URL, '', $image_src);
                $new_src = rtrim($image_src, '.tmp');
                $new_path = DOMAIN_PATH . '/' . str_replace(BASE_URL, '', $new_src);

                if (file_exists($image_path)) {
                    rename($image_path, $new_path);
                    $collect[$image_src] = $new_src;
                }
            }
            $content = strtr($content, $collect);
        }
    }
    return $content;
}

# ======================================================================
# ENVIRONMENT LOGIC VALUATION & MAXIMUM LIMIT CALCULATORS
function parse_ini_size_to_bytes($setting)
{
    $val = trim(ini_get($setting));
    if (empty($val)) return 0;
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int)$val;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

function post_max_limit()
{
    return parse_ini_size_to_bytes('post_max_size');
}

function upload_max_limit()
{
    return parse_ini_size_to_bytes('upload_max_filesize');
}

function request_length()
{
    return (!empty($_POST) && isset($_SERVER['CONTENT_LENGTH'])) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
}

# ======================================================================
# DATA VALIDATORS & METRIC ROUTINES
function isValid_ColorInput($color)
{
    return (bool)preg_match('/^(\#[\da-f]{3}|\#[\da-f]{6}|rgba?\(((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*,\s*){2}((\d{1,2}|1\d\d|2([0-4]\d|5[0-5]))\s*)(,\s*(0\.\d+|1))?\)|hsla?\(\s*((\d{1,2}|[1-2]\d{2}|3([0-5]\d|60)))\s*,\s*((\d{1,2}|100)\s*%)\s*,\s*((\d{1,2}|100)\s*%)(,\s*(0\.\d+|1))?\))$/i', $color);
}

function is_base64_string($string)
{
    // Corrected to match absolute valid embedded data stream indicators
    return (bool)preg_match('/^data:(text|image|application)\/[a-z0-9\+\-\.]+;base64,/i', $string);
}

function mime2ext($mime)
{
    $all_mimes = [
        "png"  => ["image/png", "image/x-png"],
        "bmp"  => ["image/bmp", "image/x-bmp", "image/x-bitmap", "image/x-xbitmap", "image/x-win-bitmap", "image/x-windows-bmp", "image/ms-bmp", "image/x-ms-bmp"],
        "gif"  => ["image/gif"],
        "jpeg" => ["image/jpeg", "image/pjpeg"],
        "svg"  => ["image/svg+xml"],
        "jp2"  => ["image/jp2", "image/jpx", "image/jpm"],
        "tiff" => ["image/tiff"],
        "ico"  => ["image/x-icon", "image/x-ico", "image/vnd.microsoft.icon"]
    ];
    foreach ($all_mimes as $ext => $mimes) {
        if (in_array($mime, $mimes)) return $ext;
    }
    return false;
}

# ======================================================================
# HIGH-VALUE CRYPTOGRAPHIC ENGINE CORES
function encrypted_string($unencrypt)
{
    $g_key = "nomenclature";
    return encrypted_data($g_key, $unencrypt, 'aes-256-cbc', ["+", "/", "="], ["_PLUS_", "_SLASH_", "_EQUALS_"]);
}

function decrypted_string($encrypted_string)
{
    $g_key = "nomenclature";
    return decrypted_data($g_key, $encrypted_string, 'aes-256-cbc', ["+", "/", "="], ["_PLUS_", "_SLASH_", "_EQUALS_"]);
}

function custom_encrypted_string($g_key, $unencrypt, $g_cipher = "aes-256-cbc")
{
    return encrypted_data($g_key, $unencrypt, $g_cipher, ["+", "/", "="], ["_PLUS_", "_SLASH_", "_EQUALS_"]);
}

function custom_decrypted_string($g_key, $encrypted_string, $g_cipher = "aes-256-cbc")
{
    return decrypted_data($g_key, $encrypted_string, $g_cipher, ["+", "/", "="], ["_PLUS_", "_SLASH_", "_EQUALS_"]);
}

function encrypted_data($g_key, $unencrypt, $g_cipher = CRYPT_CIPHER, $dirty = CRYPT_DIRTY, $clean = CRYPT_CLEAN)
{
    $iv_len = openssl_cipher_iv_length($g_cipher);
    $iv = openssl_random_pseudo_bytes($iv_len);
    $encrypted = openssl_encrypt((string)$unencrypt, $g_cipher, $g_key, OPENSSL_RAW_DATA, $iv);
    // Combine raw ciphertext and IV securely before executing base64 conversions
    $ciphertext = base64_encode($encrypted . '::' . $iv);
    return str_replace($dirty, $clean, $ciphertext);
}

function decrypted_data($g_key, $encrypted_string, $g_cipher = CRYPT_CIPHER, $dirty = CRYPT_DIRTY, $clean = CRYPT_CLEAN)
{
    $garble = str_replace($clean, $dirty, $encrypted_string);
    $data_combined = base64_decode($garble);
    if (strpos($data_combined, '::') === false) {
        return false;
    }

    list($decoded, $iv) = explode('::', $data_combined, 2);
    return openssl_decrypt($decoded, $g_cipher, $g_key, OPENSSL_RAW_DATA, $iv);
}

# ======================================================================
# STRUCTURAL META CONVERTERS & TIME RECORDERS
function formatBytes($bytes, $precision = 2)
{
    $units = array('BYTES', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $bytes = max(0, (int)$bytes);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function convertDataUnit($value = 0, $unit_from = 'BYTES', $unit_to = 'BYTES', $base = 1024)
{
    $units = array('BYTES', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $result = ["value" => 0, "unit" => $unit_to];

    if (!is_digit($value) || $value <= 0) {
        return $result;
    }

    $from_id = array_search(strtoupper($unit_from), $units) ?: 0;
    $to_id   = array_search(strtoupper($unit_to), $units) ?: 0;

    if ($from_id == $to_id) {
        $result['value'] = $value;
        return $result;
    }

    if ($from_id < $to_id) {
        $value = $value / pow($base, ($to_id - $from_id));
    } else {
        $value = $value * pow($base, ($from_id - $to_id));
    }

    $result['value'] = $value;
    return $result;
}

function converToTz($time = "", $toTz = 'Asia/Manila', $fromTz = 'UTC', $format = 'Y-m-d H:i:s')
{
    $time = ($time == "") ? date('Y-m-d H:i:s') : $time;
    try {
        $date = new DateTime($time, new DateTimeZone($fromTz ?: 'UTC'));
        $date->setTimezone(new DateTimeZone($toTz ?: 'Asia/Manila'));
        return ($format == "DateTime::W3C") ? $date->format(DateTime::W3C) : $date->format($format);
    } catch (Exception $e) {
        return date($format);
    }
}

function timestamp_precise($time = '')
{
    if ($time == '') {
        $time = microtime(true);
    }
    return round($time * 1000);
}

function timestamp_php($time = '')
{
    if ($time == '') {
        $time = timestamp_precise();
    }
    return round($time / 1000, 3);
}

function get_ip()
{
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) return $_SERVER["HTTP_CLIENT_IP"];
    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $parts = explode(',', $_SERVER["HTTP_X_FORWARDED_FOR"]);
        return trim($parts[0]);
    }
    return $_SERVER["REMOTE_ADDR"] ?? '127.0.0.1';
}

function validateDate($date, $format = 'Y-m-d H:i:s', $iso = false)
{
    if (empty($date)) {
        return false;
    }
    if (isTimestampIsoValid($date)) {
        return true;
    }
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
}

function formatDate($date, $format = 'Y-m-d H:i:s', $iso = false)
{
    return date($format, empty($date) ? time() : strtotime($date));
}

function isTimestampIsoValid($timestamp)
{
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(Z|([-+]\d{2}:\d{2}))$/i', $timestamp)) {
        try {
            new DateTime($timestamp);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

function set_password($text)
{
    return (trim($text) !== "") ? sha1($text . SALT) : '';
}

function is_digit($digit)
{
    if (is_int($digit)) {
        return true;
    }
    return is_string($digit) && ctype_digit($digit);
}

function get_array_changes($a1, $a2)
{
    $array_diff  = array_diff_assoc($a2 ?? [], $a1 ?? []);
    $old_array = array();
    foreach ($array_diff as $index => $array_val) {
        $old_array[$index] = $a1[$index] ?? NULL;
    }
    return ['old_data' => $old_array, 'new_data' => $array_diff];
}

function DateDiffInterval($sDate1, $sDate2, $sUnit = 'H')
{
    $nInterval = strtotime($sDate2) - strtotime($sDate1);
    switch (strtoupper($sUnit)) {
        case 'D':
            return $nInterval / 86400;
        case 'M':
            return $nInterval / 60;
        case 'S':
            return $nInterval;
        case 'H':
        default:
            return $nInterval / 3600;
    }
}

# ======================================================================
# COMMUNICATIONS, SYSTEM TEXT SLUGS & MACROS GENERATORS
function is_shorten_url($Address)
{
    $Address = trim($Address ?? '');
    if (empty($Address)) return true;

    $parseUrl = parse_url($Address);
    $host = trim($parseUrl['host'] ?? explode('/', $parseUrl['path'] ?? '', 2)[0]);

    $shorten_links = array('bit.ly', 'tinyurl.com', 't.co', 'ow.ly', 'rb.gy', 'tiny.one', 'rotf.lol', 'to.short.cm', 'cutt.ly', 'bl.ink', 'short.fyi', 'mz.cm', 't.ly', 'is.gd', 'goo.gl', 'ctiny.me', 'www.seebot.run', 'tiny.cc');
    if (in_array(strtolower($host), $shorten_links)) return true;
    if (strlen($Address) > 30) return false;
    if (!empty($parseUrl["query"]) || !empty($parseUrl["fragment"])) return false;

    $pathParts = explode("/", trim($parseUrl["path"] ?? '', '/'));
    if (count($pathParts) > 1 || strlen($pathParts[0] ?? '') > 10) return false;
    return strlen($host) <= 10;
}

function is_google_drive($Address)
{
    $parseUrl = parse_url(trim($Address ?? ''));
    $host = strtolower($parseUrl['host'] ?? '');
    return in_array($host, ['docs.google.com', 'drive.google.com']);
}

function valid_submit_link($Address)
{
    $parseUrl = parse_url(trim($Address ?? ''));
    $host = strtolower($parseUrl['host'] ?? '');
    return in_array($host, ['docs.google.com', 'drive.google.com', 'www.youtube.com', 'youtube.com', 'youtu.be']);
}

function array_search_revision($needle, $haystack, $key_find)
{
    if (is_array($haystack) && !empty($key_find)) {
        foreach ($haystack as $key => $sub_array) {
            if (isset($sub_array[$key_find]) && $sub_array[$key_find] == $needle) return $key;
        }
    }
    return false;
}

function serial_number()
{
    $template = 'X9X9-XX9X-9X9X';
    $sernum = '';
    for ($i = 0; $i < strlen($template); $i++) {
        switch ($template[$i]) {
            case 'X':
                $sernum .= chr(rand(65, 90));
                break;
            case '9':
                $sernum .= rand(0, 9);
                break;
            case '-':
                $sernum .= '-';
                break;
        }
    }
    return $sernum;
}

function password_generate()
{
    $data = '0123456789abcdefghijklmnopqrstuvwxyz@#$%&_ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($data), 0, 10);
}

function msg_alert($response, $msg)
{
    $msg_response = [
        'msg_success' => ['class' => 'alert-success', 'icon' => 'bi bi-check-circle-fill'],
        'msg_error'   => ['class' => 'alert-danger', 'icon' => 'bi bi-exclamation-circle-fill'],
        'msg_info'    => ['class' => 'alert-info', 'icon' => 'bi bi-info-square-fill'],
        'msg_warning' => ['class' => 'alert-warning', 'icon' => 'bi bi-exclamation-triangle-fill'],
    ];
    if (!isset($msg_response[$response])) {
        $response = 'msg_info';
    }
    return '<div id="alert-message" class="alert ' . $msg_response[$response]['class'] . ' alert-dismissible fade show font-monospace" role="alert">
                <i class="' . $msg_response[$response]['icon'] . '"></i> ' . html($msg) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

function update_summary_logs($summary_file, $filename, $user_identification = "")
{
    // Block file appending vulnerabilities if traversal path triggers occur
    if (strpos($summary_file, '..') !== false) {
        return false;
    }
    $log = date("Y-m-d H:i:s") . "|" . $user_identification . "|" . $filename . "\r\n";
    file_put_contents($summary_file, $log, FILE_APPEND);
}

function tailCustom($filepath, $lines = 1, $adaptive = true)
{
    if (strpos($filepath, '..') !== false || !file_exists($filepath)) {
        return false;
    }
    $f = @fopen($filepath, "rb");
    if ($f === false) return false;

    $buffer = (!$adaptive) ? 4096 : ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
    fseek($f, -1, SEEK_END);
    if (fread($f, 1) != "\n") $lines -= 1;

    $output = '';
    while (ftell($f) > 0 && $lines >= 0) {
        $seek = min(ftell($f), $buffer);
        fseek($f, -$seek, SEEK_CUR);
        $output = ($chunk = fread($f, $seek)) . $output;
        fseek($f, -strlen($chunk), SEEK_CUR);
        $lines -= substr_count($chunk, "\n");
    }
    while ($lines++ < 0) {
        $output = substr($output, strpos($output, "\n") + 1);
    }
    fclose($f);
    return trim($output);
}

function customTrim($data)
{
    if (is_string($data)) return trim($data);
    if (is_array($data)) return array_map('customTrim', $data);
    if (is_object($data) && method_exists($data, '__toString')) return trim((string)$data);
    return $data;
}

function isArrayCompletelyEmpty($arr)
{
    if (!is_array($arr) || empty($arr)) {
        return true;
    }
    foreach ($arr as $value) {
        $value = customTrim($value);
        if (!empty($value) || $value === 0 || $value === '0') {
            return false;
        }
    }
    return true;
}

function customIsEmpty($data)
{
    if (is_string($data)) return empty(trim($data));
    if (is_array($data)) return isArrayCompletelyEmpty($data);
    if (is_object($data)) return empty((array)$data);
    if (is_numeric($data) || is_bool($data)) return false;
    return true;
}

function output($array)
{
    return json_encode($array ?? [], JSON_NUMERIC_CHECK);
}
