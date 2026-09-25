<?php
$g_user_role = $g_user_role ?? '';

## activation on each pages
function navigation_active($pages, $class = 'active', $conditions = [])
{
    global $route;

    $pageArray = array_map('trim', explode(',', $pages));

    $route_path = preg_replace('#^/?admin($|/)#', '', $route ?? '');

    # Check if current page matches
    if (!in_array($route_path, $pageArray)) return '';

    # If no GET conditions required
    if (empty($conditions)) return $class;

    # Check GET conditions
    foreach ($conditions as $key => $values) {
        if (!isset($_GET[$key])) return '';

        $values = (array) $values; # ensure array

        if (!in_array($_GET[$key], $values)) return '';
    }

    return $class;
}

function render_header_button($buttons = [])
{
    $html = '<div>';

    if (!empty($buttons)) {
        $html .= '<div class="d-none d-md-flex gap-2">';
        foreach ($buttons as $btn) {
            $type = $btn['type'] ?? 'button';
            $class = htmlspecialchars($btn['class'] ?? '');
            $label = htmlspecialchars($btn['label']);

            $html .= '<button type="' . $type . '" class="btn btn-outline-primary btn-rounded btn-sm ' . $class . '">';
            if (!empty($btn['icon'])) {
                $html .= '<i class="' . htmlspecialchars($btn['icon']) . '"></i>&ensp;';
            }
            $html .= $label;
            $html .= '</button>';
        }
        $html .= '</div>';

        $html .= '<div class="dropdown d-md-none">';
        $html .= '<button class="btn btn-link text-muted p-0 border-0" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown" aria-expanded="false">';
        $html .= '<i class="bi bi-three-dots-vertical fs-4"></i>';
        $html .= '</button>';
        $html .= '<ul class="dropdown-menu dropdown-menu-end w-100" aria-labelledby="dropdownMenuButton">';
        foreach ($buttons as $btn) {
            $type = $btn['type'] ?? 'button';
            $class = htmlspecialchars($btn['class'] ?? '');
            $label = htmlspecialchars($btn['label']);

            $html .= '<li class="px-2 py-1">';
            $html .= '<button type="' . $type . '" class="btn btn-outline-primary btn-rounded btn-sm w-100 text-start border-0 ' . $class . '">';
            if (!empty($btn['icon'])) {
                $html .= '<i class="' . htmlspecialchars($btn['icon']) . '"></i>&ensp;';
            }
            $html .= $label;
            $html .= '</button>';
            $html .= '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    }

    $html .= '</div>';

    echo $html;
}

function render_action_links($links = [])
{
    if (empty($links)) {
        return;
    }

    $html = '<div class="d-flex align-items-center justify-content-end w-100">';

    $html .= '<div class="d-none d-sm-flex gap-3 align-items-center">';
    foreach ($links as $link) {
        $href = $link['href'] ?? '#';
        $id = !empty($link['id']) ? 'id="' . htmlspecialchars($link['id']) . '" ' : '';
        $class = htmlspecialchars($link['class'] ?? '');
        $target = htmlspecialchars($link['target'] ?? '_self');
        $label = htmlspecialchars($link['label']);

        $html .= '<a href="' . $href . '" ' . $id . 'class="' . $class . '" target="' . $target . '">';
        if (!empty($link['icon'])) {
            $html .= '<i class="' . htmlspecialchars($link['icon']) . '"></i>&ensp;';
        }
        $html .= $label;
        $html .= '</a>';
    }
    $html .= '</div>';

    $html .= '<div class="dropdown d-sm-none">';
    $html .= '<button class="btn btn-link text-muted p-0 border-0" type="button" id="actionLinksMenu" data-bs-toggle="dropdown" aria-expanded="false">';
    $html .= '<i class="bi bi-three-dots-vertical fs-4"></i>';
    $html .= '</button>';
    $html .= '<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionLinksMenu">';
    foreach ($links as $link) {
        $href = $link['href'] ?? '#';
        $id = !empty($link['id']) ? 'id="' . htmlspecialchars($link['id']) . '_mobile" ' : '';
        $class = htmlspecialchars($link['class'] ?? '');
        $target = htmlspecialchars($link['target'] ?? '_self');
        $label = htmlspecialchars($link['label']);

        $html .= '<li>';
        $html .= '<a href="' . $href . '" ' . $id . 'class="dropdown-item ' . $class . '" target="' . $target . '">';
        if (!empty($link['icon'])) {
            $html .= '<i class="' . htmlspecialchars($link['icon']) . '"></i>&ensp;';
        }
        $html .= $label;
        $html .= '</a>';
        $html .= '</li>';
    }
    $html .= '</ul>';
    $html .= '</div>';

    $html .= '</div>';

    echo $html;
}
?>
<div class="sidebar" data-background-color="dark">
    <div class="sidebar-logo">
        <!-- Logo Header -->
        <div class="logo-header" data-background-color="dark">
            <a href="<?php echo BASE_URL . 'Admin/'; ?>" class="logo pt-2">
                <img src="<?php echo DISPLAY_LOGO; ?>" alt="navbar brand" class="navbar-brand" height="80%">
            </a>
            <div class="nav-toggle">
                <button class="btn btn-toggle toggle-sidebar">
                    <i class="gg-menu-right"></i>
                </button>
                <button class="btn btn-toggle sidenav-toggler">
                    <i class="gg-menu-left"></i>
                </button>
            </div>
            <button class="topbar-toggler more">
                <i class="gg-more-vertical-alt"></i>
            </button>
        </div>
        <!-- End Logo Header -->
    </div>

    <div class="sidebar-wrapper scrollbar scrollbar-inner">
        <div class="sidebar-content">
            <ul class="nav nav-secondary">
                <?php if ($g_user_role == 'ADMIN'): ?>
                    <li class="nav-section">
                        <span class="sidebar-mini-icon">
                            <i class="fa fa-ellipsis-h"></i>
                        </span>
                        <h4 class="text-section"><?php echo ACCESS_NAME[$g_user_role]; ?></h4> <!-- change based on role -->
                    </li>

                    <?php if (SYSTEM_FLAG === 'DEV'): ?>
                        <li class="nav-item <?php echo navigation_active("dashboard"); ?>">
                            <a href="<?php echo BASE_URL . "admin/dashboard" ?>">
                                <i class="bi bi-house-door-fill"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                    <?php endif ?>

                    <li class="nav-item <?php echo navigation_active("user-information"); ?>">
                        <a href="<?php echo BASE_URL . "admin/user-information" ?>">
                            <i class="bi bi-people-fill"></i>
                            <p>User Information</p>
                        </a>
                    </li>

                <?php endif; ?>


                <?php if (SYSTEM_FLAG === "DEV"): ?>
                    <li class="nav-item <?php echo navigation_active("activity_log", "active submenu", ["user" => ["admin", "admin_staff"]]); ?>">
                        <a class="collapsed" aria-expanded="false" data-bs-toggle="collapse" href="#activity_log_nav">
                            <i class="fas fa-list"></i>
                            <p>Activity Log</p>
                            <span class="caret"></span>
                        </a>
                        <div class="collapse <?php echo navigation_active("activity_log", "show", ["user" => ["admin", "admin_staff"]]); ?>" id="activity_log_nav">
                            <ul class="nav nav-collapse">
                                <li class="<?php echo navigation_active("activity_log", "active", ["user" => ["admin"]]); ?>">
                                    <a href="<?php echo BASE_URL . "admin/activity_log.php?user=admin" ?>">
                                        <span class="sub-item">Administrator</span>
                                    </a>
                                </li>
                                <li class="<?php echo navigation_active("activity_log", "active", ["user" => ["admin_staff"]]); ?>">
                                    <a href="<?php echo BASE_URL . "admin/activity_log.php?user=admin_staff"; ?>">
                                        <span class="sub-item">Administrator Staff</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>

                    <li class="nav-item <?php echo navigation_active("user_log", "active submenu", ["user" => ["admin", "admin_staff"]]); ?>">
                        <a class="collapsed" aria-expanded="false" data-bs-toggle="collapse" href="#user_log_nav">
                            <i class="fas fa-list"></i>
                            <p>User Log</p>
                            <span class="caret"></span>
                        </a>
                        <div class="collapse <?php echo navigation_active("user_log", "show", ["user" => ["admin", "admin_staff"]]); ?>" id="user_log_nav">
                            <ul class="nav nav-collapse">
                                <li class="<?php echo navigation_active("user_log", "active", ["user" => ["admin"]]); ?>">
                                    <a href="<?php echo BASE_URL . "admin/user_log.php?user=admin" ?>">
                                        <span class="sub-item">Administrator</span>
                                    </a>
                                </li>
                                <li class="<?php echo navigation_active("user_log", "active", ["user" => ["admin_staff"]]); ?>">
                                    <a href="<?php echo BASE_URL . "admin/user_log.php?user=admin_staff"; ?>">
                                        <span class="sub-item">Administrator Staff</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>