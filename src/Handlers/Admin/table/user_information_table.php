<?php
# Core Config Dependencies
$db_connect = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Server Execution Limits
set_time_limit(0);
ini_set('max_execution_time', '0');
ini_set('memory_limit', '1024M');

require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

// Helper to sanitize array values
function clean_array_null(array $array): array
{
    return array_map(function ($value) {
        return $value ?? "";
    }, $array);
}

$session_class->session_close();

// Guard against non-AJAX requests
if (!(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) {
    include HTTP_401;
    exit();
}

// Authentication Check
$system_auth_login = $session_class->getValue(SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['auth']);
if (!($g_user_role == "ADMIN") && !($system_auth_login == $g_public_key)) {
    echo json_encode(["last_page" => 1, "data" => [], "last_row" => 0]);
    exit();
}

// Default Query Setup
$table_name = "users AS tbl_u";
$sql_where_array = array();
$to_encode = array();

// Define Select Fields
$dbfield = array(
    'tbl_u.*',
    'CONCAT_WS(" ", tbl_u.first_name, NULLIF(tbl_u.middle_name, ""), tbl_u.last_name, NULLIF(tbl_u.suffix, "")) AS name'
);

// Map frontend filter/sort field names to database columns or expressions
$field_map = array(
    'name'           => 'CONCAT_WS(" ", tbl_u.first_name, NULLIF(tbl_u.middle_name, ""), tbl_u.last_name, NULLIF(tbl_u.suffix, ""))',
    'first_name'     => 'tbl_u.first_name',
    'middle_name'    => 'tbl_u.middle_name',
    'last_name'      => 'tbl_u.last_name',
    'suffix'         => 'tbl_u.suffix',
    'sex'            => 'tbl_u.sex',
    'birth_date'     => 'tbl_u.birth_date',
    'email'          => 'tbl_u.email',
    'personal_email' => 'tbl_u.personal_email',
    'date_modify'    => 'tbl_u.date_modify',
    'status'         => 'tbl_u.status'
);

// 1. HANDLE TABULATOR HEADER FILTERS ($_GET['filter'] or $_GET['filters'])
$header_filters = $_GET['filter'] ?? $_GET['filters'] ?? null;
if (!empty($header_filters) && is_array($header_filters)) {
    foreach ($header_filters as $filter) {
        if (isset($filter['field']) && isset($filter['value']) && $filter['value'] !== '') {
            $field = $filter['field'];
            $value = escape($db_connect, trim($filter['value']));

            if (array_key_exists($field, $field_map)) {
                $db_col = $field_map[$field];
                $sql_where_array[] = "{$db_col} LIKE '%{$value}%'";
            }
        }
    }
}

// 2. HANDLE GLOBAL SEARCH ($_GET['search'])
if (isset($_GET['search']) && trim($_GET['search']) !== '') {
    $search_val = escape($db_connect, trim($_GET['search']));
    $global_conditions = array(
        "tbl_u.first_name LIKE '%{$search_val}%'",
        "tbl_u.last_name LIKE '%{$search_val}%'",
        "tbl_u.email LIKE '%{$search_val}%'",
        "tbl_u.personal_email LIKE '%{$search_val}%'",
        "CONCAT_WS(' ', tbl_u.first_name, NULLIF(tbl_u.middle_name, ''), tbl_u.last_name, NULLIF(tbl_u.suffix, '')) LIKE '%{$search_val}%'"
    );
    $sql_where_array[] = "(" . implode(" OR ", $global_conditions) . ")";
}

// Build WHERE Clause
$sql_where = !empty($sql_where_array) ? "WHERE " . implode(" AND ", $sql_where_array) : "";

// 4. HANDLE SORTING ($_GET['sorters'] or $_GET['sort'])
$orderby = "tbl_u.id DESC";
$sorters = $_GET['sorters'] ?? $_GET['sort'] ?? null;
if (!empty($sorters) && is_array($sorters)) {
    $sort_field = $sorters[0]['field'] ?? null;
    $sort_dir   = strtolower($sorters[0]['dir'] ?? 'desc');

    if (in_array($sort_dir, ['asc', 'desc']) && array_key_exists($sort_field, $field_map)) {
        $orderby = $field_map[$sort_field] . " " . strtoupper($sort_dir);
    }
}

// 5. HANDLE PAGINATION & LIMITS
$query_limit = isset($_GET['size']) && is_numeric($_GET['size']) ? (int)$_GET['size'] : QUERY_LIMIT;
$page_no     = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page_no < 1) $page_no = 1;

// Get Total Records Count
$total_query = 0;
$count_query_sql = "SELECT COUNT(DISTINCT tbl_u.id) AS count FROM {$table_name} {$sql_where}";

if ($query = call_mysql_query($count_query_sql)) {
    if ($data = call_mysql_fetch_array($query)) {
        $total_query = (int)$data['count'];
    }
}

// Calculate Total Pages & Offset
$pages = ($total_query === 0) ? 1 : (int)ceil($total_query / $query_limit);
$start = ($page_no - 1) * $query_limit;
if ($start >= $total_query) {
    $start = max(0, $total_query - $query_limit);
}

// Fetch Paginated Results
$field_query_str = implode(', ', $dbfield);
$data_query_sql = "SELECT {$field_query_str} FROM {$table_name} {$sql_where} ORDER BY {$orderby} LIMIT {$start}, {$query_limit}";

if ($query = call_mysql_query($data_query_sql)) {
    if (call_mysql_num_rows($query) > 0) {
        while ($data = call_mysql_fetch_array($query)) {
            $data = clean_array_null($data);
            $data = array_html($data);
            $data['id'] = encrypted_string($data['id']);
            $to_encode[] = $data;
        }
        mysqli_free_result($query);
    }
}

// Send Tabulator-Compatible Response
echo json_encode([
    "last_page" => $pages,
    "data"      => $to_encode,
    "last_row"  => $total_query
]);
exit();
