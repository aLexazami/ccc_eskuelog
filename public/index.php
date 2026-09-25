<?php
// ======================================================================
// [Appsite] COMPLETE SYSTEM FRONT CONTROLLER
// Location: /public/index.php
// ======================================================================

defined('DOMAIN_PATH') || define('DOMAIN_PATH', dirname(__DIR__, 1));
require_once DOMAIN_PATH . '/config/config.php';
require_once CL_SESSION_PATH;
require_once CONNECT_PATH;
require_once GLOBAL_FUNC;
require_once HELPER;

# Grab session configurations
$g_system_role    = $session_class->getValue('system_role');
$g_system_role_id = $session_class->getValue('user_role_id');
$g_user_role      = $session_class->getValue('user_role');

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
$route = strtolower($route); // Keep path comparisons strictly lowercase

# Structural folder isolation shield (Blocks direct access to hidden/system dirs)
if (preg_match('#^/(config|src|db|storage|env)(?:/|$)#i', $route)) {
    # Fallback 404
    http_response_code(404);
    require HTTP_404;
    exit();
}

switch ($route) {
    # ==================================================================================
    # Main Access
    case '/':
    case '/home':
    case '/index':
    case '/index.php':
        header("Location: " . API_URL);
        exit();

    case '/login-process':
        require_once SRC_PATH . 'Handlers/App/login_process.php';
        exit();

    case '/logout':
        require_once SRC_PATH . 'Handlers/App/logout_process.php';
        exit();

    case '/profile':
        if (empty($g_system_role)) {
            header("Location: " . BASE_URL . "login");
            exit();
        }
        require_once SRC_PATH . 'Views/App/profile.php';
        exit();

        # ==================================================================================
        # API System Access
    case '/api/system-login':
        require_once SRC_PATH . '/Handlers/Api/system_login.php';
        exit();

    case '/api/system-access':
        require_once SRC_PATH . '/Handlers/Api/system_access.php';
        exit();

        # ==================================================================================
        # DYNAMIC ROUTE Fallback
    default:
        # Pass-through execution for actual static browser assets (CSS, JS, Images)
        if (file_exists(__DIR__ . $route) && is_file(__DIR__ . $route)) {
            return false;
        }

        # FLEXIBLE ROLE ACCESS MAP
        $custom_routes = [];

        # Dynamic Route Matching Engine
        $matched_route = null;
        $route_params  = [];

        foreach ($custom_routes as $defined_route => $info) {
            $defined_route_lower = strtolower($defined_route);

            # Convert place-holders like '/news/{slug}' into regex: '#^/news/([^/]+)$#'
            $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $defined_route_lower);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $route, $matches)) {
                $matched_route = $info;

                # Extract wildcard parameter names from defined route
                preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $defined_route_lower, $param_names);

                foreach ($param_names[1] as $index => $name) {
                    if (isset($matches[$index + 1])) {
                        $route_params[$name] = $matches[$index + 1];
                    }
                }
                break;
            }
        }

        # Execute Route if Matched
        if ($matched_route) {
            # ACCESS CONTROL CHECK
            if (!empty($matched_route['roles'])) {
                if (empty($g_user_role_upper) || !in_array($g_user_role_upper, $matched_route['roles'])) {
                    http_response_code(403);

                    # Comprehensive AJAX/API check
                    $is_ajax = str_contains($route, '/ajax/')
                        || str_contains($route, '/api/')
                        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'error' => 'Forbidden: Insufficient privileges.']);
                    } else {
                        require HTTP_404;
                    }
                    exit();
                }
            }

            # Inject parameter array for target script use
            $_ROUTE_PARAMS = $route_params;

            if (file_exists($matched_route['file'])) {
                require_once $matched_route['file'];
                exit();
            }
        }

        # Final Fallback 404
        http_response_code(404);
        require HTTP_404;
        exit();
}
