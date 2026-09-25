<?php
// ======================================================================
// [Admin] COMPLETE SYSTEM FRONT CONTROLLER
// Location: /public/admin/index.php
// ======================================================================

defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 2));
require_once DOMAIN_PATH . '/config/config.php';
require_once CL_SESSION_PATH;
require_once CONNECT_PATH;
require_once GLOBAL_FUNC;
require_once HELPER;
require_once ISLOGIN;
require_once API_CONNECT;

# Grab session configurations
$g_system_role     = $session_class->getValue('system_role');
$g_system_role_id  = $session_class->getValue('user_role_id');
$g_user_role       = $session_class->getValue('user_role');

# Standardize user role to UPPERCASE for ACL comparisons
$g_user_role_upper = !empty($g_user_role) ? strtoupper(trim($g_user_role)) : '';

$request_uri = $_SERVER['REQUEST_URI'];
$clean_path  = explode('?', $request_uri)[0];

# Automatically parse out directory structure
$script_base = parse_url(BASE_URL, PHP_URL_PATH);

if ($script_base !== '/' && strpos($clean_path, $script_base) === 0) {
    $clean_path = substr($clean_path, strlen($script_base));
}

$route = '/' . trim($clean_path, '/');
$route = strtolower($route); // Standardize to lowercase

# Structural folder isolation shield (Blocks direct access to hidden/system dirs)
if (preg_match('#^/(config|src|db|storage|env)(?:/|$)#i', $route)) {
    http_response_code(404);
    echo "403 Forbidden: Direct directory access denied.";
    exit();
}

# Static Explicit Handlers
switch ($route) {
    case '/admin':
    case '/admin/home':
    case '/admin/index':
    case '/admin/login':
    case '/admin/login-access':
    case '/admin/index.php':
    case '/admin/login.php':
    case '/admin/login-access.php':
        if ($g_user_role_upper === "ADMIN") {
            header("Location: " . BASE_URL . "admin/user-information");
            exit();
        } else if ($g_user_role_upper === "ADMIN_STAFF") {
            header("Location: " . BASE_URL . "admin/user-information");
            exit();
        } else if ($g_user_role_upper === "STATION_ADMIN") {
            header("Location: " . BASE_URL . "admin/user-information");
            exit();
        } else if ($g_user_role_upper === "USER") {
            header("Location: " . BASE_URL . "admin/user-information");
            exit();
        } else {
            header("Location: " . API_URL);
            exit();
        }
    default:
        # Pass-through for static assets (CSS, JS, Images)
        if (file_exists(__DIR__ . $route) && is_file(__DIR__ . $route)) {
            return false;
        }

        # Role-based routes table
        $custom_routes = [
            # Sign Out
            '/admin/sign-out' => ['file'  => SRC_PATH . '/Handlers/Admin/sign_out.php','roles' => ["ADMIN", "ADMIN_STAFF"]],

            # ADMIN Views
            '/admin/user-information' => ['file'  => SRC_PATH . '/Views/Admin/user_information.php', 'roles' => ["ADMIN", "ADMIN_STAFF", "STATION_ADMIN", "USER"]],

            # TABLE Handlers
            '/admin/table/user-information' => ['file'  => SRC_PATH . '/Handlers/Admin/table/user_information_table.php', 'roles' => ["ADMIN", "ADMIN_STAFF", "STATION_ADMIN", "USER"]],

            # AJAX Handlers
            '/admin/ajax/process' => ['file'  => SRC_PATH . '/Handlers/Admin/ajax/process.php', 'roles' => ["ADMIN", "ADMIN_STAFF", "STATION_ADMIN", "USER"]],

        ];

        # Dynamic Route Matching Engine
        $matched_route = null;
        $route_params  = [];

        foreach ($custom_routes as $defined_route => $info) {
            $defined_route_lower = strtolower($defined_route);

            # Convert placeholders like '/news/{slug}' to regex pattern
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $defined_route_lower);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $route, $matches)) {
                $matched_route = $info;

                # Extract route parameter values
                preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $defined_route_lower, $param_names);

                foreach ($param_names[1] as $index => $name) {
                    if (isset($matches[$index + 1])) {
                        $route_params[$name] = $matches[$index + 1];
                    }
                }
                break;
            }
        }

        # Route Execution Engine
        if ($matched_route) {
            # ACCESS CONTROL CHECK
            if (!empty($matched_route['roles'])) {
                $has_role_access = !empty($g_user_role_upper) && in_array($g_user_role_upper, $matched_route['roles']);
                $has_key_access  = isset($system_auth_login, $g_public_key) && ($system_auth_login === $g_public_key);

                if (!$has_role_access && !$has_key_access) {
                    http_response_code(403);

                    $is_ajax = (strpos($route, '/ajax/') !== false) || (strpos($route, '/api/') !== false) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient privileges.']);
                    } else {
                        require HTTP_404;
                    }
                    exit();
                }
            }

            $_ROUTE_PARAMS = $route_params;

            if (file_exists($matched_route['file'])) {
                require_once $matched_route['file'];
                exit();
            }
        }

        # Fallback 404 Not Found
        http_response_code(404);
        require HTTP_404;
        exit();
}
