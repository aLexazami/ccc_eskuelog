<?php
// src/Views/Admin/queue_controller.php

# Core Config Dependencies
global $session_class, $db_connect, $g_user_role, $g_user_id;
$db_connect = $db_connect ?? null;

require_once ACTIVITY_LOGGER;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Resolve User ID using $g_user_id, $session_class wrapper, or raw session
$user_id = (int)($g_user_id ?? 0);
if ($user_id <= 0 && isset($session_class) && method_exists($session_class, 'getValue')) {
    $user_id = (int)(
        $session_class->getValue('user_id') ??
        $session_class->getValue('id') ??
        $session_class->getValue('sys_user_id') ??
        0
    );
}
if ($user_id <= 0) {
    $user_id = (int)($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

// Fallback: Query user_log via active session_id if session wrapper is unpopulated
if ($user_id <= 0 && $db_connect && session_id()) {
    $current_sess_id = session_id();
    $stmtSess = $db_connect->prepare("SELECT user_id FROM user_log WHERE session_id = ? OR token_id LIKE ? ORDER BY user_log_id DESC LIMIT 1");
    if ($stmtSess) {
        $sess_like = '%' . $current_sess_id . '%';
        $stmtSess->bind_param("ss", $current_sess_id, $sess_like);
        $stmtSess->execute();
        $sessRes = $stmtSess->get_result()->fetch_assoc();
        if ($sessRes) {
            $user_id = (int)$sessRes['user_id'];
        }
        $stmtSess->close();
    }
}

// 2. Resolve User Role for activity_log user_level
$resolved_role = (string)($g_user_role ?? '');
if ($resolved_role === '' && isset($session_class) && method_exists($session_class, 'getValue')) {
    $resolved_role = (string)($session_class->getValue('user_role') ?? $session_class->getValue('system_role') ?? '');
}
if ($resolved_role === '') {
    $resolved_role = (string)($_SESSION['user_role'] ?? $_SESSION['g_user_role'] ?? $_SESSION['role'] ?? '');
}

$role_map = SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['role'] ?? [];
if (isset($role_map[$resolved_role])) {
    $resolved_role = $role_map[$resolved_role];
}

if ($resolved_role === '' && $db_connect && $user_id > 0) {
    $sysName = SYSTEM_ACCESS_NAME;
    $stmtSysRole = $db_connect->prepare("
        SELECT system_role
        FROM system_access
        WHERE user_id = ? AND system_type = ? AND flag_access = 0
        ORDER BY system_role ASC
        LIMIT 1
    ");
    if ($stmtSysRole) {
        $stmtSysRole->bind_param("is", $user_id, $sysName);
        $stmtSysRole->execute();
        $roleRes = $stmtSysRole->get_result()->fetch_assoc();
        if ($roleRes && isset($role_map[(string)$roleRes['system_role']])) {
            $resolved_role = $role_map[(string)$roleRes['system_role']];
        }
        $stmtSysRole->close();
    }
}

$g_user_role_upper = strtoupper(trim($resolved_role)) ?: 'ADMIN_STAFF';

$assigned_office  = null;   // the window being controlled right now
$my_windows       = [];     // every active window of this staff member
$needs_selection  = false;  // several windows and none chosen yet

if ($db_connect && $user_id > 0) {
    // A staff member can hold MANY windows (one staff_assignments row per window)
    $stmtAssign = $db_connect->prepare("
        SELECT
            sa.office_id,
            sa.counter_id,
            o.office_code,
            o.office_name,
            c.counter_name AS counter_label
        FROM staff_assignments sa
        INNER JOIN office o ON sa.office_id = o.office_id AND o.flag_status = 0
        INNER JOIN counters c ON sa.counter_id = c.id AND c.flag_status = 0
        WHERE sa.user_id = ?
          AND sa.flag_status = 0
          AND sa.office_id > 0
          AND sa.counter_id IS NOT NULL
          AND sa.counter_id > 0
        ORDER BY o.office_code ASC, c.counter_name ASC
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $resAssign = $stmtAssign->get_result();
        while ($w = $resAssign->fetch_assoc()) {
            $my_windows[] = $w;
        }
        $stmtAssign->close();
    }

    // ?window=ID picks a window (only if it really belongs to this user);
    // a single window is opened directly; several windows -> selection screen.
    $requested_window = (int)($_GET['window'] ?? 0);
    foreach ($my_windows as $w) {
        if ($requested_window > 0 && (int)$w['counter_id'] === $requested_window) {
            $assigned_office = $w;
            break;
        }
    }
    if (!$assigned_office && count($my_windows) === 1) {
        $assigned_office = $my_windows[0];
    }
    if (!$assigned_office && count($my_windows) > 1) {
        $needs_selection = true;
    }

    if ($assigned_office) {
        $_SESSION['assigned_office_id']  = (int)$assigned_office['office_id'];
        $_SESSION['assigned_counter_id'] = (int)$assigned_office['counter_id'];

        // Log QUEUE_WINDOW_SELECT only when entering or switching counters in this session
        $last_logged_counter = (int)($_SESSION['last_logged_queue_counter'] ?? 0);
        if ($last_logged_counter !== (int)$assigned_office['counter_id']) {
            $action_msg = sprintf(
                'QUEUE_WINDOW_SELECT: Opened Queue Controller for %s (%s)',
                $assigned_office['office_code'],
                $assigned_office['counter_label']
            );
            record_activity_log($db_connect, $user_id, $action_msg, $g_user_role_upper);
            $_SESSION['last_logged_queue_counter'] = (int)$assigned_office['counter_id'];
        }
    } else {
        unset(
            $_SESSION['assigned_office_id'],
            $_SESSION['assigned_counter_id'],
            $_SESSION['last_logged_queue_counter']
        );
    }
}

$has_multiple_windows = count($my_windows) > 1;
$window_page_url      = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>

    <!-- WORKSTATION CUSTOM KIOSK THEME STYLES -->
    <style>
        :root {
            --kiosk-primary: #1d4ed8;
            --kiosk-primary-hover: #1e40af;
            --kiosk-primary-light: #eff6ff;
            --kiosk-border: #e2e8f0;
            --kiosk-bg-subtle: #f8fafc;
            --kiosk-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --kiosk-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.06);
            --kiosk-radius: 1rem;
        }

        body.kiosk-standalone-body {
            min-height: 100vh;
            background: linear-gradient(135deg, rgba(11, 32, 117, 0.85) 0%, rgba(0, 10, 76, 0.92) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            margin: 0;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
        }

        .kiosk-standalone-container {
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
        }

        .kiosk-main-card {
            border: 1px solid var(--kiosk-border);
            border-radius: var(--kiosk-radius);
            box-shadow: var(--kiosk-shadow-lg);
            background: #ffffff;
            overflow: hidden;
            width: 100%;
        }

        .kiosk-header-banner {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            padding: 1.25rem 1.5rem;
        }

        .btn-header-services {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
            border: 1.5px solid rgba(255, 255, 255, 0.35);
            border-radius: 0.75rem;
            padding: 0.5rem 0.75rem;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            backdrop-filter: blur(4px);
        }

        .btn-header-services:hover {
            background: #ffffff;
            color: #1e3a8a;
            border-color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .status-pill-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.65rem 2.25rem;
            border-radius: 50rem;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            box-shadow: 0 6px 18px -3px rgba(0, 0, 0, 0.08);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1.5px solid transparent;
        }

        .status-on-duty {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-color: #93c5fd;
        }

        .status-now-calling {
            background: linear-gradient(135deg, #fffbe3 0%, #fef3c7 100%);
            color: #92400e;
            border-color: #fcd34d;
        }

        .status-now-serving {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-color: #6ee7b7;
        }

        .digital-timer-card {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            background: linear-gradient(180deg, #1e3a8a 0%, #1d4ed8 100%);
            padding: 0.5rem 1.25rem;
            border-radius: 0.65rem;
            box-shadow: none;
            border: 1.5px solid #3b82f6;
            transition: all 0.3s ease;
        }

        .digital-timer-card #digitalElapsedTimer {
            font-family: 'Courier New', SFMono-Regular, Consolas, Monaco, monospace;
            font-size: 1.55rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: #ffffff;
        }

        .digital-timer-card i {
            color: #93c5fd !important;
        }

        .slant-ticket-icon {
            font-size: 1.15rem;
            display: inline-block;
            transform: rotate(-18deg);
            margin-right: 0.35rem;
            vertical-align: -1px;
            transition: transform 0.2s ease;
        }

        .visitor-badge-modern {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 1.25rem;
            border-radius: 50rem;
            font-weight: 800;
            font-size: 0.8rem;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.25);
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
        }

        .window-badge-hero {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.95rem;
            border-radius: 50rem;
            font-weight: 700;
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: rgba(56, 189, 248, 0.16);
            color: #e0f2fe;
            border: 1px solid rgba(56, 189, 248, 0.45);
        }

        .serving-hero-card {
            border: 1px solid var(--kiosk-border);
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: var(--kiosk-shadow-sm);
            overflow: hidden;
        }

        .ticket-hero-left {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #ffffff;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 2.5rem;
            text-align: center;
        }

        .ticket-hero-number {
            font-size: 3.75rem;
            font-weight: 900;
            letter-spacing: 0.02em;
            color: #38bdf8;
            line-height: 1;
            margin: 0;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .ticket-hero-right {
            padding: 1.75rem;
            background: #ffffff;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .detail-tile {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
            height: 100%;
            overflow: hidden;
        }

        .detail-tile:hover {
            background: #ffffff;
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .detail-tile .tile-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 0.65rem;
            background: #eff6ff;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.85rem;
            margin-top: 2px;
        }

        .btn-copy-detail {
            padding: 0.2rem 0.5rem;
            font-size: 0.72rem;
            border-radius: 0.45rem;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #475569;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }

        .btn-copy-detail:hover {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #93c5fd;
        }

        .concern-hero-box {
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            border: 1.5px solid #bfdbfe;
            border-radius: 0.85rem;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.05);
        }

        /* RECEIPT CONCERN STYLES */
        .receipt-concern-item {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 3.5px solid var(--kiosk-primary, #1d4ed8);
            border-radius: 0.5rem;
            padding: 0.55rem 0.85rem;
            margin-bottom: 0.4rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .receipt-concern-title {
            font-weight: 700;
            color: #1e293b;
            font-size: 0.875rem;
            line-height: 1.3;
        }

        .receipt-subconcern-tag {
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.2rem 0.55rem;
            border-radius: 0.35rem;
            display: inline-flex;
            align-items: center;
            border: 1px solid #bfdbfe;
            line-height: 1.2;
        }

        .controller-subcard {
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: var(--kiosk-shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
        }

        /* 3-COLUMN CATEGORY HEADERS */
        .lineup-header-priority {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-bottom: 1.5px solid #fcd34d;
            padding: 0.85rem 1rem;
        }

        .lineup-header-regular {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1.5px solid #bfdbfe;
            padding: 0.85rem 1rem;
        }

        .lineup-header-employee {
            background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%);
            border-bottom: 1.5px solid #ddd6fe;
            padding: 0.85rem 1rem;
        }

        .lineup-badge-pill {
            font-weight: 800;
            font-size: 0.72rem;
            padding: 0.28rem 0.7rem;
            border-radius: 50rem;
            letter-spacing: 0.02em;
        }

        .btn-global-call-next {
            width: 100%;
            border: none;
            padding: 0.5rem 0.85rem;
            border-radius: 0.6rem;
            font-weight: 800;
            font-size: 0.8rem;
            letter-spacing: 0.02em;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.2s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.12);
        }

        .btn-global-call-next:hover:not(:disabled) {
            transform: translateY(-1px);
            filter: brightness(1.06);
            color: #ffffff;
        }

        .btn-global-call-next:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btn-call-priority { background: linear-gradient(135deg, #d97706 0%, #b45309 100%); }
        .btn-call-regular  { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .btn-call-employee { background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%); }

        .ticket-pill-item {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.8rem;
            padding: 0.65rem 0.85rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            width: 100%;
            min-width: 0;
            overflow: hidden;
        }

        .ticket-pill-item:hover {
            border-color: #3b82f6;
            background-color: #ffffff;
            transform: translateX(3px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.08);
        }

        .ticket-pill-number {
            font-family: 'Courier New', SFMono-Regular, Consolas, Monaco, monospace;
            font-size: 1.05rem;
            font-weight: 900;
            letter-spacing: 0.04em;
            color: #0f172a;
        }

        .ticket-pill-pos {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            font-weight: 800;
            font-size: 0.7rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.55rem;
            flex-shrink: 0;
        }

        .ticket-pill-item:hover .ticket-pill-pos {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .visitor-type-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.16rem 0.5rem;
            border-radius: 0.38rem;
            letter-spacing: 0.02em;
            margin-top: 0.22rem;
        }

        .chip-student {
            background-color: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .chip-guest {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .chip-employee {
            background-color: #ede9fe;
            color: #5b21b6;
            border: 1px solid #c4b5fd;
        }

        /* SINGLE-LINE RIGHT-TO-LEFT CONCERN TICKER ON WAITING PILLS */
        .pill-concern-ticker {
            position: relative;
            overflow: hidden;
            height: 1.25rem;
            margin-top: 0.25rem;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            display: block;
        }

        .pill-concern-text {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
            max-width: 100%;
            font-size: 0.72rem;
            font-weight: 600;
            color: #475569;
            line-height: 1.25rem;
        }

        .pill-concern-slide-in {
            animation: pillSlideFromRight 0.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

        .pill-concern-slide-out {
            animation: pillSlideToLeft 0.3s ease-in forwards;
        }

        @keyframes pillSlideFromRight {
            0% {
                opacity: 0;
                transform: translateX(24px);
            }
            100% {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pillSlideToLeft {
            0% {
                opacity: 1;
                transform: translateX(0);
            }
            100% {
                opacity: 0;
                transform: translateX(-20px);
            }
        }

        .btn-preview-visitor {
            background: #ffffff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            padding: 0.35rem 0.55rem;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.18s ease;
            line-height: 1;
            flex-shrink: 0;
        }

        .btn-preview-visitor:hover {
            background: #2563eb;
            color: #ffffff;
            border-color: #1d4ed8;
        }

        .action-bar-card {
            border: 1.5px solid #bfdbfe;
            border-radius: 1rem;
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            box-shadow: 0 8px 20px -4px rgba(37, 99, 235, 0.08), 0 4px 8px -2px rgba(15, 23, 42, 0.04);
            position: relative;
            overflow: hidden;
        }

        .btn-action-modern {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.55rem;
            padding: 0.85rem 1rem;
            border-radius: 0.75rem;
            font-weight: 800;
            font-size: 0.95rem;
            letter-spacing: 0.02em;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1.5px solid transparent;
            text-transform: capitalize;
            cursor: pointer;
            white-space: nowrap;
            width: 100%;
        }

        .btn-action-hero {
            padding: 0.95rem 1.25rem;
            font-size: 1rem;
            border-radius: 0.85rem;
        }

        .btn-action-modern:hover {
            transform: translateY(-2px);
        }

        .btn-action-modern:active {
            transform: translateY(1px);
        }

        .btn-action-modern i {
            font-size: 1.2rem;
            line-height: 1;
        }

        .btn-ctrl-serve {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border-color: #1d4ed8;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.28);
        }

        .btn-ctrl-serve:hover {
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.38);
        }

        .btn-ctrl-complete {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            border-color: #15803d;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.28);
        }

        .btn-ctrl-complete:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.38);
        }

        .btn-ctrl-recall {
            background: #ffffff;
            color: #ea580c;
            border-color: #fed7aa;
            box-shadow: 0 2px 8px rgba(234, 88, 12, 0.06);
        }

        .btn-ctrl-recall:hover {
            background: #fff7ed;
            color: #c2410c;
            border-color: #fb923c;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.12);
        }

        .btn-ctrl-skip {
            background: #ffffff;
            color: #dc2626;
            border-color: #fecaca;
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.06);
        }

        .btn-ctrl-skip:hover {
            background: #fef2f2;
            color: #b91c1c;
            border-color: #f87171;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.12);
        }

        /* BLUE GLASSMORPHISM SQUARE CARD STACK FOR WINDOW SELECTION */
        .window-stack-wrapper {
            position: relative;
            width: 100%;
            max-width: 270px;
            aspect-ratio: 1 / 1;
            margin: 0.75rem auto;
            z-index: 1;
        }

        .window-stack-wrapper::before,
        .window-stack-wrapper::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.22) 0%, rgba(29, 78, 216, 0.12) 100%);
            border: 1.5px solid rgba(96, 165, 250, 0.45);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            transition: transform 0.32s cubic-bezier(0.34, 1.56, 0.64, 1);
            pointer-events: none;
        }

        .window-stack-wrapper::before {
            z-index: -2;
            transform: translate(12px, 12px) rotate(3.5deg);
            opacity: 0.65;
        }

        .window-stack-wrapper::after {
            z-index: -1;
            transform: translate(6px, 6px) rotate(1.75deg);
            opacity: 0.85;
        }

        .window-stack-wrapper:hover::before {
            transform: translate(18px, 16px) rotate(5.5deg);
        }

        .window-stack-wrapper:hover::after {
            transform: translate(9px, 8px) rotate(2.75deg);
        }

        .window-glass-square-card {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.75rem 1.35rem;
            border-radius: 1.5rem;
            text-decoration: none;
            background: linear-gradient(145deg, rgba(30, 58, 138, 0.88) 0%, rgba(37, 99, 235, 0.78) 55%, rgba(56, 189, 248, 0.68) 100%);
            border: 1.5px solid rgba(255, 255, 255, 0.48);
            box-shadow:
                0 16px 32px -8px rgba(30, 58, 138, 0.35),
                inset 0 1px 1px rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            color: #ffffff;
            transition: all 0.28s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .window-glass-square-card::before {
            content: '';
            position: absolute;
            top: -40%;
            left: -40%;
            width: 180%;
            height: 180%;
            background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.28), transparent 55%);
            pointer-events: none;
        }

        .window-glass-square-card:hover {
            transform: translate(-3px, -4px);
            border-color: rgba(255, 255, 255, 0.85);
            box-shadow:
                0 22px 40px -8px rgba(29, 78, 216, 0.45),
                inset 0 1px 2px rgba(255, 255, 255, 0.75);
            color: #ffffff;
        }

        .window-glass-icon {
            width: 64px;
            height: 64px;
            border-radius: 1.15rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.16);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.85rem;
            color: #ffffff;
            margin-bottom: 1.1rem;
            transition: transform 0.25s ease, background 0.25s ease;
        }

        .window-glass-square-card:hover .window-glass-icon {
            transform: scale(1.08);
            background: rgba(255, 255, 255, 0.3);
        }

        .window-glass-title {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.02em;
            color: #ffffff;
            text-align: center;
            line-height: 1.25;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 6px rgba(15, 23, 42, 0.25);
        }

        .window-glass-office {
            font-size: 0.84rem;
            font-weight: 600;
            color: rgba(239, 246, 255, 0.92);
            text-align: center;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Modal Window Switch List Card */
        .window-select-card {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 1rem 1.15rem;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.9rem;
            text-decoration: none;
            box-shadow: var(--kiosk-shadow-sm);
            transition: all 0.2s ease;
        }

        .window-select-card:hover,
        .window-select-card.is-current {
            border-color: #3b82f6;
            background: #eff6ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.12);
        }

        .window-select-icon {
            width: 46px;
            height: 46px;
            min-width: 46px;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .modal-section-divider {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #1e40af;
            padding-top: 0.45rem;
            margin-top: 0.15rem;
            border-top: 1.5px dashed #cbd5e1;
        }

        /* QUEUE LIFECYCLE TIMESTAMPS CARD (MATCHED TO DETAIL-TILE) */
        .lifecycle-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.03);
        }

        .lifecycle-toolbar {
            background: #f8fafc;
            border-bottom: 1.5px solid #e2e8f0;
            padding: 0.65rem 1rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .lifecycle-table {
            width: 100%;
            margin-bottom: 0;
            border-collapse: collapse;
            font-size: 0.84rem;
        }

        .lifecycle-table th,
        .lifecycle-table td {
            padding: 0.6rem 0.9rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .lifecycle-table tr:last-child td {
            border-bottom: none;
        }

        .lifecycle-stage-header td,
        .lifecycle-summary-header td {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 800;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 0.48rem 0.9rem;
            text-align: center;
        }

        .lifecycle-summary-header td {
            border-top: 1.5px solid #cbd5e1;
        }

        .lifecycle-sub-header th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 700;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 0.4rem 0.9rem;
            width: 33.33%;
        }

        .lifecycle-total-row td {
            background: #eff6ff;
            color: #1e3a8a;
            font-weight: 800;
            border-top: 1.5px solid #bfdbfe;
        }

        .lifecycle-status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.75rem;
            border-radius: 50rem;
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.03em;
            line-height: 1.2;
            border: 1.5px solid transparent;
            vertical-align: middle;
        }

        .lifecycle-status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .lc-status-completed {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: #065f46;
            border-color: #34d399;
        }
        .lc-status-completed .lifecycle-status-dot {
            background-color: #10b981;
        }

        .lc-status-calling {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            color: #92400e;
            border-color: #fbbf24;
        }
        .lc-status-calling .lifecycle-status-dot {
            background-color: #f59e0b;
        }

        .lc-status-skipped {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #f87171;
        }
        .lc-status-skipped .lifecycle-status-dot {
            background-color: #ef4444;
        }

        .lc-status-serving {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            color: #1e40af;
            border-color: #60a5fa;
        }
        .lc-status-serving .lifecycle-status-dot {
            background-color: #2563eb;
        }

        .lc-status-waiting {
            background: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
        }
        .lc-status-waiting .lifecycle-status-dot {
            background-color: #64748b;
        }

        .lc-status-cancelled {
            background: #f3f4f6;
            color: #4b5563;
            border-color: #d1d5db;
        }
        .lc-status-cancelled .lifecycle-status-dot {
            background-color: #6b7280;
        }
    </style>
</head>

<body class="kiosk-standalone-body">
    <div class="kiosk-standalone-container">
        <div class="card kiosk-main-card border-0">

            <!-- KIOSK HEADER BANNER WITH ASSIGNED OFFICE, COUNTER NAME & SERVICES MODAL BUTTON -->
            <div class="kiosk-header-banner d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                    <div class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: rgba(255, 255, 255, 0.2); width: 40px; height: 40px;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <div class="min-w-0">
                        <h4 class="fw-bold text-white mb-0 tracking-wide text-truncate fs-6 fs-sm-5" id="headerOfficeName">
                            <?= $assigned_office ? htmlspecialchars($assigned_office['office_name']) . ' (' . htmlspecialchars($assigned_office['office_code']) . ')' : ($needs_selection ? 'Select Your Window' : 'No Workstation Assigned'); ?>
                        </h4>
                        <p class="text-white-50 small mb-0" id="headerCounterName">
                            <?= $assigned_office ? htmlspecialchars($assigned_office['counter_label']) : ($needs_selection ? 'You are assigned to multiple windows' : 'Service Counter Workstation'); ?>
                        </p>
                    </div>
                </div>

                <!-- RIGHT SIDE: 1st = VIEW HANDLED TRANSACTIONS, 2nd = SWITCH WINDOW (ICON ONLY) -->
                <div class="d-flex align-items-center gap-2">
                    <?php if ($assigned_office): ?>
                        <button type="button" class="btn-header-services" id="btnViewWindowServices" onclick="openWindowServicesModal()" title="View Handled Transactions & Sub-Transactions" aria-label="View Handled Transactions & Sub-Transactions">
                            <i class="bi bi-list-ul fs-5"></i>
                        </button>
                        <?php if ($has_multiple_windows): ?>
                            <button type="button" class="btn-header-services" id="btnSwitchWindow" data-bs-toggle="modal" data-bs-target="#windowSwitchModal" title="Switch Window" aria-label="Switch Window">
                                <i class="bi bi-window-stack fs-5"></i>
                            </button>
                        <?php endif; ?>
                        <input type="hidden" id="officeSelect" value="<?= (int)$assigned_office['office_id']; ?>">
                        <input type="hidden" id="counterSelect" value="<?= (int)$assigned_office['counter_id']; ?>">
                    <?php else: ?>
                        <input type="hidden" id="officeSelect" value="">
                        <input type="hidden" id="counterSelect" value="0">
                    <?php endif; ?>
                </div>
            </div>

            <!-- CONTROLLER MAIN BODY -->
            <div class="card-body p-3 p-sm-4">
                
                <?php if ($needs_selection): ?>
                    <!-- WINDOW SELECTION (staff is assigned to more than one window) -->
                    <div class="my-3 py-2" id="windowSelectionScreen">
                        <div class="text-center mb-4">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle"
                                 style="width: 80px; height: 80px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px dashed #93c5fd;">
                                <i class="bi bi-window-stack text-primary" style="font-size: 2.3rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-1">Select a Window</h4>
                            <p class="text-muted mb-0">You are assigned to multiple windows. Choose the one you want to control.</p>
                        </div>
                        <div class="row g-4 justify-content-center py-2">
                            <?php foreach ($my_windows as $w): ?>
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex justify-content-center">
                                    <div class="window-stack-wrapper">
                                        <a href="<?= htmlspecialchars($window_page_url . '?window=' . (int)$w['counter_id']); ?>" class="window-glass-square-card">
                                            <span class="window-glass-icon">
                                                <i class="bi bi-display"></i>
                                            </span>
                                            <span class="window-glass-title">
                                                <?= htmlspecialchars($w['counter_label']); ?>
                                            </span>
                                            <span class="window-glass-office">
                                                <?= htmlspecialchars($w['office_name']); ?> (<?= htmlspecialchars($w['office_code']); ?>)
                                            </span>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php elseif (!$assigned_office): ?>
                    <!-- UNASSIGNED STAFF FALLBACK CARD -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden my-2" id="unassignedFallbackCard">
                        <div class="card-body p-4 p-md-5 text-center">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" 
                                 style="width: 88px; height: 88px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px dashed #93c5fd;">
                                <i class="bi bi-person-workspace text-primary" style="font-size: 2.6rem;"></i>
                            </div>
                            <h4 class="fw-bold text-dark mb-2">Workstation Not Assigned</h4>
                            <p class="text-muted mx-auto mb-4" style="max-width: 500px; font-size: 0.95rem; line-height: 1.65;">
                                Your account is active, but you have not been assigned to both an office and a service counter for today's shift. Please coordinate with your station manager or administrator to configure your workstation assignment.
                            </p>
                            <div>
                                <button type="button" class="btn btn-primary fw-bold px-4 py-2 rounded-3 shadow-sm d-inline-flex align-items-center gap-2" onclick="window.location.reload();">
                                    <i class="bi bi-arrow-clockwise fs-5"></i>
                                    <span>Check Assignment Status</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="d-none" id="controllerMainContent">
                        
                        <!-- GRID TOP BAR: STATUS BADGE & TIMER -->
                        <div class="row g-0 align-items-center mb-3">
                            <div class="col-lg-4 col-md-5 d-flex justify-content-center">
                                <div id="counterStatusContainer" class="status-pill-modern status-on-duty">
                                    <span id="counterStatusText">READY TO SERVE</span>
                                </div>
                            </div>

                            <div class="col-lg-8 col-md-7 d-flex justify-content-center mt-2 mt-md-0">
                                <div class="digital-timer-card">
                                    <i class="bi bi-stopwatch fs-5"></i>
                                    <span id="digitalElapsedTimer">00:00</span>
                                </div>
                            </div>
                        </div>

                        <!-- TOP SECTION: 2-COLUMN SERVING HERO BOX -->
                        <div class="card serving-hero-card mb-4">
                            <div class="row g-0">
                                <div class="col-lg-4 col-md-5 ticket-hero-left">
                                    <div class="d-flex flex-column align-items-center text-center">
                                        <div class="text-info text-uppercase fw-bold small tracking-wider">
                                            <i class="bi bi-ticket-perforated-fill slant-ticket-icon"></i>QUEUE NUMBER
                                        </div>
                                        <div id="ctrlWindowNumberTag" class="mt-2"></div>
                                    </div>

                                    <div class="d-flex flex-column align-items-center my-4">
                                        <div class="ticket-hero-number" id="ctrlServingTicket">---</div>
                                        <div id="ctrlPriorityTag" class="mt-2"></div>
                                    </div>

                                    <div id="ctrlVisitorTypeTag" class="mt-2"></div>
                                </div>

                                <div class="col-lg-8 col-md-7 ticket-hero-right" id="ticketDetailsContainer">
                                </div>
                            </div>
                        </div>

                        <!-- HIGHLIGHTED ACTION CONTROLS BAR CONTAINER (2x2 GRID: Serve & Complete / Recall & Skip) -->
                        <div class="card action-bar-card mb-4">
                            <div class="card-body p-3">
                                <div class="d-flex flex-column gap-2 gap-sm-3">
                                    
                                    <!-- ROW 1: SERVE & COMPLETE -->
                                    <div class="row g-2 g-sm-3">
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-action-modern btn-action-hero btn-ctrl-serve" id="btnStartServe">
                                                <i class="bi bi-play-circle-fill"></i> Serve
                                            </button>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-action-modern btn-action-hero btn-ctrl-complete" id="btnCompleteTicket">
                                                <i class="bi bi-check-circle-fill"></i> Complete
                                            </button>
                                        </div>
                                    </div>

                                    <!-- ROW 2: RECALL & SKIP -->
                                    <div class="row g-2 g-sm-3">
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-action-modern btn-ctrl-recall" id="btnRecallVoice">
                                                <i class="bi bi-arrow-counterclockwise"></i> Recall
                                            </button>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <button type="button" class="btn btn-action-modern btn-ctrl-skip" id="btnSkipTicket">
                                                <i class="bi bi-dash-circle"></i> Skip
                                            </button>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <!-- BOTTOM 3-COLUMN CATEGORY LINEUP SECTION (ALWAYS VISIBLE: PRIORITY, REGULAR, EMPLOYEE) -->
                        <div class="row g-3 align-items-stretch">
                            
                            <!-- COLUMN 1: PRIORITY -->
                            <div class="col-12 col-lg-4">
                                <div class="card controller-subcard">
                                    <div class="lineup-header-priority">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="fw-bold text-warning-emphasis mb-0 d-flex align-items-center">
                                                <i class="bi bi-star-fill me-2 text-warning"></i>Priority
                                            </h6>
                                            <span class="lineup-badge-pill bg-warning text-dark" id="ctrlPriorityBadge">0 Waiting</span>
                                        </div>
                                        <button type="button" class="btn-global-call-next btn-call-priority" id="btnCallNextPriority" onclick="callNextByCategory('PRIORITY')">
                                            <i class="bi bi-megaphone-fill"></i> Call Next
                                        </button>
                                    </div>
                                    <div class="card-body p-2 p-sm-3">
                                        <div class="d-flex flex-column gap-2" id="ctrlPriorityList"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- COLUMN 2: REGULAR (STUDENTS & GUESTS) -->
                            <div class="col-12 col-lg-4">
                                <div class="card controller-subcard">
                                    <div class="lineup-header-regular">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                                <i class="bi bi-people-fill me-2 text-primary"></i>Regular
                                            </h6>
                                            <span class="lineup-badge-pill bg-primary text-white" id="ctrlRegularBadge">0 Waiting</span>
                                        </div>
                                        <button type="button" class="btn-global-call-next btn-call-regular" id="btnCallNextRegular" onclick="callNextByCategory('REGULAR')">
                                            <i class="bi bi-megaphone-fill"></i> Call Next
                                        </button>
                                    </div>
                                    <div class="card-body p-2 p-sm-3">
                                        <div class="d-flex flex-column gap-2" id="ctrlRegularList"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- COLUMN 3: EMPLOYEE -->
                            <div class="col-12 col-lg-4">
                                <div class="card controller-subcard">
                                    <div class="lineup-header-employee">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <h6 class="fw-bold mb-0 d-flex align-items-center" style="color: #5b21b6;">
                                                <i class="bi bi-person-badge-fill me-2" style="color: #7c3aed;"></i>Employee
                                            </h6>
                                            <span class="lineup-badge-pill text-white" style="background-color: #7c3aed;" id="ctrlEmployeeBadge">0 Waiting</span>
                                        </div>
                                        <button type="button" class="btn-global-call-next btn-call-employee" id="btnCallNextEmployee" onclick="callNextByCategory('EMPLOYEE')">
                                            <i class="bi bi-megaphone-fill"></i> Call Next
                                        </button>
                                    </div>
                                    <div class="card-body p-2 p-sm-3">
                                        <div class="d-flex flex-column gap-2" id="ctrlEmployeeList"></div>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                <?php endif; ?>

            </div>

        </div>
    </div>

    <!-- VISITOR / USER FULL DETAILS MODAL -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fw-bold d-flex align-items-center mb-0" id="userDetailsModalLabel">
                            <i class="bi bi-person-lines-fill me-2 fs-4"></i>Visitor Information Details
                        </h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light" id="modalUserDetailsContent">
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary fw-semibold px-4 rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($has_multiple_windows && $assigned_office): ?>
    <!-- SWITCH WINDOW MODAL -->
    <div class="modal fade" id="windowSwitchModal" tabindex="-1" aria-labelledby="windowSwitchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center mb-0" id="windowSwitchModalLabel">
                        <i class="bi bi-window-stack me-2 fs-4"></i>Switch Window
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 bg-light">
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($my_windows as $w): ?>
                            <?php $isCurrent = (int)$w['counter_id'] === (int)$assigned_office['counter_id']; ?>
                            <a href="<?= htmlspecialchars($window_page_url . '?window=' . (int)$w['counter_id']); ?>" class="window-select-card <?= $isCurrent ? 'is-current' : ''; ?>">
                                <span class="window-select-icon"><i class="bi bi-display"></i></span>
                                <span class="min-w-0">
                                    <span class="d-block fw-bold text-dark text-truncate"><?= htmlspecialchars($w['counter_label']); ?></span>
                                    <span class="d-block small text-muted text-truncate"><?= htmlspecialchars($w['office_name']); ?> (<?= htmlspecialchars($w['office_code']); ?>)</span>
                                </span>
                                <?php if ($isCurrent): ?>
                                    <span class="badge bg-primary ms-auto">Current</span>
                                <?php else: ?>
                                    <i class="bi bi-chevron-right ms-auto text-muted"></i>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary fw-semibold px-4 rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- OFFICE & WINDOW HANDLED SERVICES MODAL -->
    <div class="modal fade" id="windowServicesModal" tabindex="-1" aria-labelledby="windowServicesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1.25rem; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center mb-0" id="windowServicesModalLabel">
                        <i class="bi bi-list-check me-2 fs-4"></i>Handled Transactions & Sub-Transactions
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 bg-light" id="windowServicesModalContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="small text-muted mt-2 mb-0">Loading assigned transactions...</p>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top">
                    <button type="button" class="btn btn-secondary fw-semibold px-4 rounded-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

<script>
    (function() {
        const HAS_ASSIGNMENT = <?= json_encode(!empty($assigned_office)); ?>;
        const ASSIGNED_COUNTER_LABEL = <?= json_encode($assigned_office['counter_label'] ?? ''); ?>;

        const officeSelect = document.getElementById('officeSelect');
        const counterSelect = document.getElementById('counterSelect');
        const controllerMainContent = document.getElementById('controllerMainContent');
        
        const counterStatusContainer = document.getElementById('counterStatusContainer');
        const counterStatusText = document.getElementById('counterStatusText');
        const digitalElapsedTimer = document.getElementById('digitalElapsedTimer');

        const ctrlServingTicket = document.getElementById('ctrlServingTicket');
        const ctrlWindowNumberTag = document.getElementById('ctrlWindowNumberTag');
        const ctrlPriorityTag = document.getElementById('ctrlPriorityTag');
        const ctrlVisitorTypeTag = document.getElementById('ctrlVisitorTypeTag');
        const ticketDetailsContainer = document.getElementById('ticketDetailsContainer');

        const modalUserDetailsContent = document.getElementById('modalUserDetailsContent');
        const windowServicesModalContent = document.getElementById('windowServicesModalContent');

        // 3-Column Badges & Lists
        const ctrlPriorityBadge = document.getElementById('ctrlPriorityBadge');
        const ctrlRegularBadge  = document.getElementById('ctrlRegularBadge');
        const ctrlEmployeeBadge = document.getElementById('ctrlEmployeeBadge');

        const ctrlPriorityList = document.getElementById('ctrlPriorityList');
        const ctrlRegularList  = document.getElementById('ctrlRegularList');
        const ctrlEmployeeList = document.getElementById('ctrlEmployeeList');

        // Global Category Call Next Buttons
        const btnCallNextPriority = document.getElementById('btnCallNextPriority');
        const btnCallNextRegular  = document.getElementById('btnCallNextRegular');
        const btnCallNextEmployee = document.getElementById('btnCallNextEmployee');

        // Top Action Controls
        const btnStartServe     = document.getElementById('btnStartServe');
        const btnCompleteTicket = document.getElementById('btnCompleteTicket');
        const btnRecallVoice    = document.getElementById('btnRecallVoice');
        const btnSkipTicket     = document.getElementById('btnSkipTicket');

        const HANDLER_URL = '<?= BASE_URL; ?>admin/ajax/queue';

        let activeOfficeId = officeSelect ? officeSelect.value : '';
        let activeCounterId = counterSelect ? counterSelect.value : '0';

        let dynamicTimerInterval = null;
        let currentTimerBaseTime = null;

        // Cache tickets for modal previewing (active + waiting)
        let currentActiveTicket = null;
        let waitingTicketsMap = {};

        // Live Lifecycle Table State
        let modalLifecycleInterval = null;
        let activeModalTicketRef = null;
        let selectedDurationFormat = 'B'; // Default: B. X hr Y min Z sec

        const DURATION_FORMAT_LABELS = {
            'A': 'HH:MM:SS',
            'B': 'X hr Y min Z sec',
            'C': 'Xh Ym Zs',
            'D': 'Full Words'
        };

        // Track active per-pill concern tickers, current indices & rendered tickets across 4-second polling refreshes
        let pillTickerIntervals = {};
        let pillTickerIndices = {};
        let renderedPillTicketIds = new Set();

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function parseSqlTimestamp(ts) {
            if (!ts) return null;
            const d = new Date(String(ts).replace(' ', 'T'));
            return isNaN(d.getTime()) ? null : d;
        }

        function formatClockTime(dateObj) {
            if (!dateObj) return '---';
            return dateObj.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }

        function formatLifecycleDuration(totalSeconds, formatKey = 'B') {
            if (totalSeconds === null || totalSeconds === undefined || isNaN(totalSeconds)) {
                return '---';
            }
            const secs = Math.max(0, Math.floor(totalSeconds));
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;

            switch (formatKey) {
                case 'A': {
                    const hh = String(h).padStart(2, '0');
                    const mm = String(m).padStart(2, '0');
                    const ss = String(s).padStart(2, '0');
                    return `${hh}:${mm}:${ss}`;
                }
                case 'C': {
                    return h > 0 ? `${h}h ${m}m ${s}s` : `${m}m ${s}s`;
                }
                case 'D': {
                    const parts = [];
                    if (h > 0) parts.push(`${h} ${h === 1 ? 'hour' : 'hours'}`);
                    parts.push(`${m} ${m === 1 ? 'minute' : 'minutes'}`);
                    parts.push(`and ${s} ${s === 1 ? 'second' : 'seconds'}`);
                    return parts.join(', ');
                }
                case 'B':
                default: {
                    return h > 0 ? `${h} hr ${m} min ${s} sec` : `${m} min ${s} sec`;
                }
            }
        }

        function computeQueueLifecycleMetrics(ticket) {
            const now = new Date();
            const status = (ticket.status || 'WAITING').toUpperCase();

            const entryDate = parseSqlTimestamp(ticket.queue_entry_time || ticket.created_at);
            const calledDate = parseSqlTimestamp(ticket.called_at);
            const servedDate = parseSqlTimestamp(ticket.served_at);
            const completedDate = parseSqlTimestamp(ticket.completed_at);

            // 1. Waiting Time (Entry -> Called)
            let waitStart = formatClockTime(entryDate);
            let waitEnd = '---';
            let waitSecs = null;
            let waitLive = false;

            if (entryDate) {
                if (calledDate) {
                    waitEnd = formatClockTime(calledDate);
                    waitSecs = Math.floor((calledDate - entryDate) / 1000);
                } else if (completedDate) {
                    waitEnd = formatClockTime(completedDate);
                    waitSecs = Math.floor((completedDate - entryDate) / 1000);
                } else if (status === 'WAITING') {
                    waitEnd = 'Ongoing';
                    waitSecs = Math.floor((now - entryDate) / 1000);
                    waitLive = true;
                }
            }

            // 2. Calling Time (Called -> Served, or Completed if skipped while called)
            let callStart = formatClockTime(calledDate);
            let callEnd = '---';
            let callSecs = null;
            let callLive = false;

            if (calledDate) {
                if (servedDate) {
                    callEnd = formatClockTime(servedDate);
                    callSecs = Math.floor((servedDate - calledDate) / 1000);
                } else if (completedDate) {
                    callEnd = formatClockTime(completedDate);
                    callSecs = Math.floor((completedDate - calledDate) / 1000);
                } else if (status === 'CALLED') {
                    callEnd = 'Ongoing';
                    callSecs = Math.floor((now - calledDate) / 1000);
                    callLive = true;
                }
            }

            // 3. Processing Time (Served -> Completed)
            let procStart = formatClockTime(servedDate);
            let procEnd = '---';
            let procSecs = null;
            let procLive = false;

            if (servedDate) {
                if (completedDate) {
                    procEnd = formatClockTime(completedDate);
                    procSecs = Math.floor((completedDate - servedDate) / 1000);
                } else if (status === 'SERVING') {
                    procEnd = 'Ongoing';
                    procSecs = Math.floor((now - servedDate) / 1000);
                    procLive = true;
                }
            }

            const totalSecs = (waitSecs || 0) + (callSecs || 0) + (procSecs || 0);
            const hasAnyTime = (waitSecs !== null || callSecs !== null || procSecs !== null);

            return {
                status,
                wait: { start: waitStart, end: waitEnd, secs: waitSecs, live: waitLive },
                call: { start: callStart, end: callEnd, secs: callSecs, live: callLive },
                proc: { start: procStart, end: procEnd, secs: procSecs, live: procLive },
                totalSecs: hasAnyTime ? totalSecs : null,
                isAnyLive: waitLive || callLive || procLive
            };
        }

        function buildLifecycleTableHtml(ticket) {
            const m = computeQueueLifecycleMetrics(ticket);
            const fmt = selectedDurationFormat;

            const formatStatusBadge = (st) => {
                const config = {
                    'WAITING':   { cls: 'lc-status-waiting',   label: 'Waiting' },
                    'CALLED':    { cls: 'lc-status-calling',   label: 'Calling' },
                    'SERVING':   { cls: 'lc-status-serving',   label: 'Serving' },
                    'COMPLETED': { cls: 'lc-status-completed', label: 'Completed' },
                    'SKIPPED':   { cls: 'lc-status-skipped',   label: 'Skipped' },
                    'CANCELLED': { cls: 'lc-status-cancelled', label: 'Cancelled' }
                };
                const item = config[st] || {
                    cls: 'lc-status-waiting',
                    label: st.charAt(0) + st.slice(1).toLowerCase()
                };
                return `
                    <span class="lifecycle-status-badge ${item.cls}">
                        ${escapeHtml(item.label)}
                    </span>
                `;
            };

            return `
                <div class="lifecycle-card">
                    <div class="lifecycle-toolbar">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle fw-semibold d-inline-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 0.76rem; border-radius: 0.5rem;">
                                <i class="bi bi-sliders"></i>
                                <span>Time Format: ${escapeHtml(DURATION_FORMAT_LABELS[fmt])}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="font-size: 0.82rem;">
                                <li><a class="dropdown-item ${fmt === 'A' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('A')">A. HH:MM:SS <small class="opacity-75">(e.g., 01:05:30)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'B' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('B')">B. X hr Y min Z sec <small class="opacity-75">(Default)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'C' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('C')">C. Xh Ym Zs <small class="opacity-75">(e.g., 2h 45m 45s)</small></a></li>
                                <li><a class="dropdown-item ${fmt === 'D' ? 'active' : ''}" href="javascript:void(0)" onclick="setLifecycleTimeFormat('D')">D. X hours, Y minutes, and Z seconds</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="lifecycle-table">
                            <tbody>
                                <!-- WAITING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Waiting Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.wait.start)}</td>
                                    <td class="fw-semibold ${m.wait.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.wait.end)}</td>
                                    <td class="fw-bold ${m.wait.live ? 'text-primary' : 'text-dark'}" id="lcWaitDuration">${escapeHtml(formatLifecycleDuration(m.wait.secs, fmt))}</td>
                                </tr>

                                <!-- CALLING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Calling Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.call.start)}</td>
                                    <td class="fw-semibold ${m.call.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.call.end)}</td>
                                    <td class="fw-bold ${m.call.live ? 'text-primary' : 'text-dark'}" id="lcCallDuration">${escapeHtml(formatLifecycleDuration(m.call.secs, fmt))}</td>
                                </tr>

                                <!-- PROCESSING TIME -->
                                <tr class="lifecycle-stage-header">
                                    <td colspan="3">Processing Time</td>
                                </tr>
                                <tr class="lifecycle-sub-header">
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Duration</th>
                                </tr>
                                <tr>
                                    <td class="fw-semibold text-dark">${escapeHtml(m.proc.start)}</td>
                                    <td class="fw-semibold ${m.proc.live ? 'text-primary' : 'text-dark'}">${escapeHtml(m.proc.end)}</td>
                                    <td class="fw-bold ${m.proc.live ? 'text-primary' : 'text-dark'}" id="lcProcDuration">${escapeHtml(formatLifecycleDuration(m.proc.secs, fmt))}</td>
                                </tr>

                                <!-- SUMMARY -->
                                <tr class="lifecycle-summary-header">
                                    <td colspan="3">Summary</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Waiting Time :</td>
                                    <td class="fw-bold ${m.wait.live ? 'text-primary' : 'text-dark'}" id="lcSumWait">${escapeHtml(formatLifecycleDuration(m.wait.secs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Calling Time :</td>
                                    <td class="fw-bold ${m.call.live ? 'text-primary' : 'text-dark'}" id="lcSumCall">${escapeHtml(formatLifecycleDuration(m.call.secs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-muted fw-semibold">Processing Time :</td>
                                    <td class="fw-bold ${m.proc.live ? 'text-primary' : 'text-dark'}" id="lcSumProc">${escapeHtml(formatLifecycleDuration(m.proc.secs, fmt))}</td>
                                </tr>
                                <tr class="lifecycle-total-row">
                                    <td colspan="2">Total Duration</td>
                                    <td class="${m.isAnyLive ? 'text-primary' : ''}" id="lcSumTotal">${escapeHtml(formatLifecycleDuration(m.totalSecs, fmt))}</td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="text-muted fw-semibold">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <span>Status:</span>
                                            ${formatStatusBadge(m.status)}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        function startModalLifecycleTicker() {
            stopModalLifecycleTicker();
            if (!activeModalTicketRef) return;

            const setLiveTextColor = (el, isLive) => {
                if (!el) return;
                el.classList.toggle('text-primary', isLive);
                el.classList.toggle('text-dark', !isLive);
            };

            const updateLiveCells = () => {
                if (!activeModalTicketRef) return;
                const m = computeQueueLifecycleMetrics(activeModalTicketRef);
                const fmt = selectedDurationFormat;

                const elWait = document.getElementById('lcWaitDuration');
                const elCall = document.getElementById('lcCallDuration');
                const elProc = document.getElementById('lcProcDuration');
                const elSumWait = document.getElementById('lcSumWait');
                const elSumCall = document.getElementById('lcSumCall');
                const elSumProc = document.getElementById('lcSumProc');
                const elSumTotal = document.getElementById('lcSumTotal');

                if (elWait) {
                    elWait.textContent = formatLifecycleDuration(m.wait.secs, fmt);
                    setLiveTextColor(elWait, m.wait.live);
                }
                if (elCall) {
                    elCall.textContent = formatLifecycleDuration(m.call.secs, fmt);
                    setLiveTextColor(elCall, m.call.live);
                }
                if (elProc) {
                    elProc.textContent = formatLifecycleDuration(m.proc.secs, fmt);
                    setLiveTextColor(elProc, m.proc.live);
                }
                if (elSumWait) {
                    elSumWait.textContent = formatLifecycleDuration(m.wait.secs, fmt);
                    setLiveTextColor(elSumWait, m.wait.live);
                }
                if (elSumCall) {
                    elSumCall.textContent = formatLifecycleDuration(m.call.secs, fmt);
                    setLiveTextColor(elSumCall, m.call.live);
                }
                if (elSumProc) {
                    elSumProc.textContent = formatLifecycleDuration(m.proc.secs, fmt);
                    setLiveTextColor(elSumProc, m.proc.live);
                }
                if (elSumTotal) {
                    elSumTotal.textContent = formatLifecycleDuration(m.totalSecs, fmt);
                    elSumTotal.classList.toggle('text-primary', m.isAnyLive);
                }
            };

            const initialMetrics = computeQueueLifecycleMetrics(activeModalTicketRef);
            if (initialMetrics.isAnyLive) {
                modalLifecycleInterval = setInterval(updateLiveCells, 1000);
            }
        }

        function stopModalLifecycleTicker() {
            if (modalLifecycleInterval) {
                clearInterval(modalLifecycleInterval);
                modalLifecycleInterval = null;
            }
        }

        window.setLifecycleTimeFormat = function(fmtKey) {
            selectedDurationFormat = fmtKey;
            const container = document.getElementById('queueLifecycleTableContainer');
            if (container && activeModalTicketRef) {
                container.innerHTML = buildLifecycleTableHtml(activeModalTicketRef);
                startModalLifecycleTicker();
            }
        };

        function syncOpenModalTicket(latestTicket) {
            const modalEl = document.getElementById('userDetailsModal');
            if (!modalEl || !modalEl.classList.contains('show') || !activeModalTicketRef) return;

            if (String(latestTicket.id) === String(activeModalTicketRef.id)) {
                const stateChanged =
                    latestTicket.status !== activeModalTicketRef.status ||
                    latestTicket.called_at !== activeModalTicketRef.called_at ||
                    latestTicket.served_at !== activeModalTicketRef.served_at ||
                    latestTicket.completed_at !== activeModalTicketRef.completed_at;

                activeModalTicketRef = latestTicket;
                if (stateChanged) {
                    const container = document.getElementById('queueLifecycleTableContainer');
                    if (container) {
                        container.innerHTML = buildLifecycleTableHtml(activeModalTicketRef);
                        startModalLifecycleTicker();
                    }
                }
            }
        }

        document.getElementById('userDetailsModal')?.addEventListener('hidden.bs.modal', () => {
            stopModalLifecycleTicker();
            activeModalTicketRef = null;
        });

        // One-click Copy Utility
        window.copyDetailValue = function(btnEl, encodedValue) {
            const textToCopy = decodeURIComponent(encodedValue || '');
            if (!textToCopy || textToCopy === 'N/A' || textToCopy === '---') return;

            const onCopySuccess = () => {
                const originalHtml = btnEl.innerHTML;
                btnEl.innerHTML = '<i class="bi bi-check2 text-success fw-bold"></i> Copied';
                btnEl.classList.add('border-success', 'bg-success-subtle');
                setTimeout(() => {
                    btnEl.innerHTML = originalHtml;
                    btnEl.classList.remove('border-success', 'bg-success-subtle');
                }, 1500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(textToCopy).then(onCopySuccess).catch(() => fallbackCopy(textToCopy, onCopySuccess));
            } else {
                fallbackCopy(textToCopy, onCopySuccess);
            }
        };

        function fallbackCopy(text, callback) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                if (callback) callback();
            } catch (err) {
                console.error('Fallback copy failed:', err);
            }
            document.body.removeChild(textArea);
        }

        function showSwalAlert(title, message, iconType = 'info') {
            const swalObj = typeof swal !== 'undefined' ? swal : (typeof Swal !== 'undefined' ? Swal : null);
            if (!swalObj) {
                alert(`${title}: ${message}`);
                return;
            }

            if (typeof swalObj === 'function') {
                try {
                    swalObj(title, message, iconType);
                    return;
                } catch (e) {
                    try {
                        new swalObj({
                            title: title,
                            text: message,
                            icon: iconType,
                            buttons: {
                                confirm: {
                                    className: iconType === 'error' || iconType === 'warning' ? 'btn btn-danger' : 'btn btn-primary'
                                }
                            }
                        });
                        return;
                    } catch (e2) {}
                }
            }

            if (typeof swalObj.fire === 'function') {
                swalObj.fire({ title: title, text: message, icon: iconType });
            } else {
                alert(`${title}: ${message}`);
            }
        }

        function showSwalToast(title, message, iconType = 'success', duration = 2500) {
            const swalObj = typeof swal !== 'undefined' ? swal : (typeof Swal !== 'undefined' ? Swal : null);
            if (!swalObj) return;

            if (typeof swalObj.fire === 'function') {
                swalObj.fire({
                    toast: true,
                    position: 'top-end',
                    icon: iconType,
                    title: title,
                    text: message,
                    showConfirmButton: false,
                    timer: duration,
                    timerProgressBar: true
                });
                return;
            }

            // Kaiadmin SweetAlert v1 auto-closing toast-style alert
            swalObj({
                title: title,
                text: message,
                icon: iconType,
                buttons: false,
                timer: duration
            });
        }

        function toTitleCase(str) {
            if (!str) return '';
            return str.toLowerCase().replace(/\b\w/g, function(char) {
                return char.toUpperCase();
            });
        }

        function resolveTicketWindow(ticket) {
            if (!ticket) return '';
            if (ticket.window_number && String(ticket.window_number).trim() !== '') {
                return String(ticket.window_number).trim();
            }
            if (ticket.ticket_window_name && String(ticket.ticket_window_name).trim() !== '') {
                return String(ticket.ticket_window_name).trim();
            }
            return ASSIGNED_COUNTER_LABEL || 'N/A';
        }

        function buildLogbookStyleConcerns(ticket) {
            let html = '';
            const otherText = ticket.other_concern_text ? ticket.other_concern_text.trim() : '';

            const renderConcernCard = (mainTitle, subs = []) => {
                let subSectionHtml = '';
                if (Array.isArray(subs) && subs.length > 0) {
                    const subRows = subs.map(sub => `
                        <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">
                            ${escapeHtml(sub)}
                        </div>
                    `).join('');

                    subSectionHtml = `
                        <div class="mt-2 pt-2 border-top d-flex flex-column gap-1">
                            <div class="text-muted fw-bold" style="font-size: 0.875rem; line-height: 1.35;">Sub-Transaction</div>
                            ${subRows}
                        </div>
                    `;
                }

                return `
                    <div class="receipt-concern-item">
                        <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">${escapeHtml(mainTitle)}</div>
                        ${subSectionHtml}
                    </div>
                `;
            };

            if (Array.isArray(ticket.concerns_list) && ticket.concerns_list.length > 0) {
                let grouped = {};

                ticket.concerns_list.forEach(item => {
                    const main = (item.transaction_name || 'General Concern').trim();
                    const sub = item.sub_concern_name ? item.sub_concern_name.trim() : null;

                    if (main.toLowerCase().includes('other') && otherText) {
                        return;
                    }

                    if (!grouped[main]) {
                        grouped[main] = [];
                    }

                    if (sub && !grouped[main].includes(sub)) {
                        grouped[main].push(sub);
                    }
                });

                Object.entries(grouped).forEach(([mainTitle, subs]) => {
                    html += renderConcernCard(mainTitle, subs);
                });
            } else if (ticket.transaction_name) {
                const mainConcerns = ticket.transaction_name.split(',').map(s => s.trim()).filter(Boolean);
                const subConcerns = (ticket.sub_concern_name || '').split(',').map(s => s.trim()).filter(Boolean);

                mainConcerns.forEach((main, idx) => {
                    if (main.toLowerCase().includes('other') && otherText) return;
                    const subs = (idx === 0 && subConcerns.length > 0) ? subConcerns : [];
                    html += renderConcernCard(main, subs);
                });
            }

            if (otherText) {
                html += `
                    <div class="receipt-concern-item">
                        <div class="text-dark fw-bold" style="font-size: 0.875rem; line-height: 1.35;">
                            <i class="bi bi-pen me-1 text-primary"></i>Others / Custom Concern
                        </div>
                        <div class="text-dark fw-bold mt-1" style="font-size: 0.875rem; line-height: 1.35; word-break: break-word;">
                            ${escapeHtml(otherText)}
                        </div>
                    </div>
                `;
            }

            if (!html) {
                html = renderConcernCard('General Concern', []);
            }

            return html;
        }

        if (HAS_ASSIGNMENT && activeOfficeId && parseInt(activeCounterId, 10) > 0) {
            loadCounterData();
        }

        function loadCounterData() {
            if (!HAS_ASSIGNMENT || !activeOfficeId || parseInt(activeCounterId, 10) <= 0) {
                if (controllerMainContent) controllerMainContent.classList.add('d-none');
                return;
            }

            if (controllerMainContent) controllerMainContent.classList.remove('d-none');

            const formData = new FormData();
            formData.append('action', 'fetch_counter_status');
            formData.append('office_id', activeOfficeId);
            formData.append('counter_id', activeCounterId);

            fetch(HANDLER_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status} (${res.statusText})`);
                return res.json();
            })
            .then(res => {
                if (res.success && res.data) {
                    const ticket = res.data.now_serving;
                    currentActiveTicket = ticket || null;

                    if (counterStatusText && counterStatusContainer) {
                        if (ticket && ticket.status === 'CALLED') {
                            counterStatusText.textContent = 'NOW CALLING';
                            counterStatusContainer.className = 'status-pill-modern status-now-calling';
                        } else if (ticket && ticket.status === 'SERVING') {
                            counterStatusText.textContent = 'NOW SERVING';
                            counterStatusContainer.className = 'status-pill-modern status-now-serving';
                        } else {
                            counterStatusText.textContent = 'READY TO SERVE';
                            counterStatusContainer.className = 'status-pill-modern status-on-duty';
                        }
                    }

                    if (ticket && ticket.ticket_number) {
                        if (ctrlServingTicket) ctrlServingTicket.textContent = ticket.ticket_number;

                        if (ctrlWindowNumberTag) {
                            const windowLabel = resolveTicketWindow(ticket);
                            if (windowLabel && windowLabel !== 'N/A') {
                                ctrlWindowNumberTag.innerHTML = `<span class="window-badge-hero"><i class="bi bi-display"></i> ${escapeHtml(windowLabel)}</span>`;
                            } else {
                                ctrlWindowNumberTag.innerHTML = '';
                            }
                        }

                        if (ctrlPriorityTag) {
                            if (ticket.is_priority == 1) {
                                ctrlPriorityTag.innerHTML = '<span class="badge bg-warning text-dark fw-bold px-3 py-1.5 border-0"><i class="bi bi-star-fill text-danger me-1"></i>Priority</span>';
                            } else {
                                ctrlPriorityTag.innerHTML = '';
                            }
                        }

                        if (ctrlVisitorTypeTag) {
                            const visitorType = (ticket.visitor_type || 'VISITOR').toUpperCase();
                            let visitorIcon = 'bi-person-heart';

                            if (visitorType === 'STUDENT') {
                                visitorIcon = 'bi-mortarboard-fill';
                            } else if (visitorType === 'EMPLOYEE') {
                                visitorIcon = 'bi-person-badge-fill';
                            }

                            ctrlVisitorTypeTag.innerHTML = `<span class="visitor-badge-modern"><i class="bi ${visitorIcon}"></i> ${visitorType}</span>`;
                        }

                        renderDynamicTicketDetails(ticket);

                        if (ticket.status === 'CALLED' && ticket.called_at) {
                            currentTimerBaseTime = parseSqlTimestamp(ticket.called_at);
                            startDynamicTimer();
                        } else if (ticket.status === 'SERVING' && ticket.served_at) {
                            currentTimerBaseTime = parseSqlTimestamp(ticket.served_at);
                            startDynamicTimer();
                        } else {
                            stopDynamicTimer();
                            if (digitalElapsedTimer) digitalElapsedTimer.textContent = '00:00';
                        }

                    } else {
                        if (ctrlServingTicket) ctrlServingTicket.textContent = '---';
                        if (ctrlWindowNumberTag) ctrlWindowNumberTag.innerHTML = '';
                        if (ctrlPriorityTag) ctrlPriorityTag.innerHTML = '';
                        if (ctrlVisitorTypeTag) ctrlVisitorTypeTag.innerHTML = '';
                        stopDynamicTimer();
                        if (digitalElapsedTimer) digitalElapsedTimer.textContent = '00:00';
                        renderEmptyDetailsState();
                    }

                    // Categorize Waiting Tickets into 3 FIFO Lanes: Priority, Regular (Student & Guest), Employee
                    const waiting = res.data.up_next || [];
                    waitingTicketsMap = {};
                    const activeWaitingIds = new Set();
                    waiting.forEach(item => {
                        waitingTicketsMap[item.id] = item;
                        activeWaitingIds.add(String(item.id));
                    });

                    // Sync open modal if ticket status/timestamps changed during 4-second poll
                    if (activeModalTicketRef) {
                        if (currentActiveTicket && String(currentActiveTicket.id) === String(activeModalTicketRef.id)) {
                            syncOpenModalTicket(currentActiveTicket);
                        } else if (waitingTicketsMap[activeModalTicketRef.id]) {
                            syncOpenModalTicket(waitingTicketsMap[activeModalTicketRef.id]);
                        }
                    }

                    // Clean up stored ticker indices & seen IDs for tickets no longer waiting
                    Object.keys(pillTickerIndices).forEach(tid => {
                        if (!activeWaitingIds.has(tid)) {
                            delete pillTickerIndices[tid];
                        }
                    });
                    renderedPillTicketIds.forEach(tid => {
                        if (!activeWaitingIds.has(tid)) {
                            renderedPillTicketIds.delete(tid);
                        }
                    });

                    const priorityList = waiting.filter(t => Number(t.is_priority) === 1);
                    const regularList  = waiting.filter(t => Number(t.is_priority) !== 1 && ['STUDENT', 'GUEST'].includes((t.visitor_type || '').toUpperCase()));
                    const employeeList = waiting.filter(t => Number(t.is_priority) !== 1 && (t.visitor_type || '').toUpperCase() === 'EMPLOYEE');

                    if (ctrlPriorityBadge) ctrlPriorityBadge.textContent = `${priorityList.length} Waiting`;
                    if (ctrlRegularBadge)  ctrlRegularBadge.textContent  = `${regularList.length} Waiting`;
                    if (ctrlEmployeeBadge) ctrlEmployeeBadge.textContent = `${employeeList.length} Waiting`;

                    if (btnCallNextPriority) btnCallNextPriority.disabled = priorityList.length === 0;
                    if (btnCallNextRegular)  btnCallNextRegular.disabled  = regularList.length === 0;
                    if (btnCallNextEmployee) btnCallNextEmployee.disabled = employeeList.length === 0;

                    if (ctrlPriorityList) renderCategoryPills(ctrlPriorityList, priorityList, 'No priority visitors waiting.', true);
                    if (ctrlRegularList)  renderCategoryPills(ctrlRegularList, regularList, 'No regular visitors waiting.', true);
                    if (ctrlEmployeeList) renderCategoryPills(ctrlEmployeeList, employeeList, 'No employees waiting.', false);

                } else if (!res.success) {
                    if (controllerMainContent) controllerMainContent.classList.add('d-none');
                }
            })
            .catch(err => {
                console.error("Counter status error:", err);
            });
        }

        function renderEmptyDetailsState() {
            if (!ticketDetailsContainer) return;
            ticketDetailsContainer.innerHTML = `
                <div class="text-center py-4 my-auto">
                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-light text-primary" style="width: 60px; height: 60px; border: 2px dashed #cbd5e1;">
                        <i class="bi bi-ticket-detailed fs-2 text-primary opacity-75"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">No Active Ticket Selected</h6>
                    <p class="small text-muted mb-0">Click <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold px-2 py-1"><i class="bi bi-megaphone-fill me-1"></i>Call Next</span> on any category lane below to call the next visitor.</p>
                </div>
            `;
        }

        function formatYearLevel(year) {
            if (!year) return 'N/A';
            const strYear = String(year).trim();
            if (strYear.toLowerCase().includes('year')) return strYear;

            const yearMap = { '1': '1st Year', '2': '2nd Year', '3': '3rd Year', '4': '4th Year', '5': '5th Year' };
            return yearMap[strYear] || `${strYear} Year`;
        }

        function buildFormattedName(ticket) {
            if (ticket.full_name && ticket.full_name.trim() !== '') {
                return toTitleCase(ticket.full_name);
            }

            let firstName   = (ticket.first_name || '').trim();
            let middleName  = (ticket.middle_name || '').trim();
            let lastName    = (ticket.last_name || '').trim();
            let suffix      = (ticket.suffix || '').trim();
            let postNominal = (ticket.post_nominal || '').trim();

            let nameParts = [];
            if (firstName) nameParts.push(firstName);
            if (middleName) nameParts.push(middleName);
            if (lastName) nameParts.push(lastName);

            let baseName = nameParts.length > 0 ? nameParts.join(' ') : 'N/A';

            if (suffix) baseName += ' ' + suffix;
            if (postNominal) baseName += ', ' + postNominal;

            return toTitleCase(baseName);
        }

        function renderDetailTile(iconClass, label, value, customClass = '', allowCopy = true, subtitle = '') {
            const safeVal = value || 'N/A';
            const canCopy = allowCopy && safeVal !== 'N/A' && safeVal !== '---';
            const copyBtnHtml = canCopy ? `
                <button type="button" class="btn-copy-detail ms-2" onclick="copyDetailValue(this, '${encodeURIComponent(safeVal)}')" title="Copy ${escapeHtml(label)}">
                    <i class="bi bi-clipboard"></i>
                </button>
            ` : '';

            const subtitleHtml = subtitle ? `
                <span class="text-primary fw-semibold d-block mt-1" style="font-size: 0.7rem;">
                    <i class="bi bi-tag me-1"></i>${escapeHtml(subtitle)}
                </span>
            ` : '';

            return `
                <div class="col-12 col-sm-6">
                    <div class="detail-tile d-flex align-items-start">
                        <div class="tile-icon flex-shrink-0">
                            <i class="bi ${iconClass} fs-5"></i>
                        </div>
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fw-semibold text-muted d-block" style="font-size: 0.75rem;">${escapeHtml(label)}</span>
                                ${copyBtnHtml}
                            </div>
                            <strong class="text-dark d-block text-break ${customClass}" style="font-size: 0.875rem; line-height: 1.35; word-break: break-word;" title="${escapeHtml(safeVal)}">${escapeHtml(safeVal)}</strong>
                            ${subtitleHtml}
                        </div>
                    </div>
                </div>
            `;
        }

        function buildAdditionalDetailsHtml(ticket) {
            if (!Array.isArray(ticket.additional_details) || ticket.additional_details.length === 0) {
                return '';
            }

            const tilesHtml = ticket.additional_details.map(item => {
                const fieldTitle = (item.field_title || 'Additional Detail').trim();
                const answerVal  = (item.answer_value || '').trim();
                if (!answerVal) return '';

                const iconClass = item.detail_mode === 'SELECT' ? 'bi-list-check' : 'bi-input-cursor-text';
                const contextLabel = item.sub_concern_name 
                    ? `${item.transaction_name || ''} • ${item.sub_concern_name}`
                    : (item.transaction_name || '');

                return renderDetailTile(iconClass, fieldTitle, answerVal, '', true, contextLabel);
            }).join('');

            if (!tilesHtml) {
                return '';
            }

            return `
                <div class="col-12">
                    <div class="modal-section-divider">
                        <i class="bi bi-info-circle-fill text-primary"></i>
                        <span>Additional Details</span>
                    </div>
                </div>
                ${tilesHtml}
            `;
        }

        function renderDynamicTicketDetails(ticket) {
            if (!ticketDetailsContainer) return;
            
            const concernsHtml = buildLogbookStyleConcerns(ticket);

            ticketDetailsContainer.innerHTML = `
                <div class="d-flex flex-column justify-content-between h-100 py-1">
                    <div class="concern-hero-box my-auto">
                        <div class="d-flex align-items-start gap-3">
                            <div class="p-2 bg-primary text-white rounded-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                <i class="bi bi-clipboard-check-fill fs-4"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="mb-2">
                                    <span class="text-uppercase text-muted fw-bold tracking-wider" style="font-size: 0.75rem;">Transaction Concern(s)</span>
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    ${concernsHtml}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 text-end">
                        <button type="button" class="btn btn-outline-primary fw-bold d-inline-flex align-items-center gap-2 px-4 py-2 rounded-3 shadow-sm" onclick="openVisitorDetailsModal(null)">
                            <i class="bi bi-person-bounding-box fs-5"></i>
                            <span>View Visitor Full Details</span>
                        </button>
                    </div>
                </div>
            `;
        }

        // Opens the Visitor Full Details Modal for either the active ticket (null) or a waiting ticket (ticketId)
        window.openVisitorDetailsModal = function(ticketId = null) {
            const targetTicket = ticketId ? waitingTicketsMap[ticketId] : currentActiveTicket;
            if (!targetTicket) {
                showSwalAlert("Notice", "Ticket details are no longer available.", "info");
                return;
            }

            activeModalTicketRef = targetTicket;
            populateVisitorModal(targetTicket);

            const modalEl = document.getElementById('userDetailsModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
                startModalLifecycleTicker();
            }
        };

        function populateVisitorModal(ticket) {
            if (!modalUserDetailsContent) return;

            const visitorType           = (ticket.visitor_type || 'VISITOR').toUpperCase();
            const fullName              = buildFormattedName(ticket);
            const email                 = ticket.email || 'N/A';
            const sex                   = ticket.sex ? ticket.sex.charAt(0).toUpperCase() + ticket.sex.slice(1).toLowerCase() : 'N/A';
            const windowLabel           = resolveTicketWindow(ticket);
            const concernsHtml          = buildLogbookStyleConcerns(ticket);
            const additionalDetailsHtml = buildAdditionalDetailsHtml(ticket);

            // 1. Transaction Concerns & Services (First at Top)
            const concernsBlockHtml = `
                <div class="col-12">
                    <div class="detail-tile border-primary-subtle bg-white">
                        <div class="d-flex align-items-center mb-3">
                            <div class="tile-icon flex-shrink-0 bg-primary text-white" style="width: 36px; height: 36px; min-width: 36px; margin-top:0;">
                                <i class="bi bi-clipboard-check-fill fs-6"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark d-block" style="font-size: 0.875rem;">Transaction Concerns & Services</span>
                            </div>
                        </div>
                        <div class="ps-1">
                            ${concernsHtml}
                        </div>
                    </div>
                </div>
            `;

            // 2. Personal Details Section
            let personalDetailsFieldsHTML = '';

            if (visitorType === 'STUDENT') {
                const studentId = ticket.reference_id || ticket.student_id || 'N/A';
                const yearLevel = formatYearLevel(ticket.year_level);
                
                let courseDisplay = 'N/A';
                if (ticket.program_code && ticket.program_name) {
                    courseDisplay = `${ticket.program_code} - ${ticket.program_name}`;
                } else if (ticket.program_code) {
                    courseDisplay = ticket.program_code;
                } else if (ticket.program_name) {
                    courseDisplay = ticket.program_name;
                }

                personalDetailsFieldsHTML = `
                    ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                    ${renderDetailTile('bi-mortarboard-fill', 'Course / Program', courseDisplay, '', true)}
                    ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                    ${renderDetailTile('bi-journal-bookmark-fill', 'Year Level', yearLevel, '', true)}
                    ${renderDetailTile('bi-card-text', 'Student ID', studentId, '', true)}
                    ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                    ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                `;
            } else if (visitorType === 'EMPLOYEE') {
                const empId      = ticket.reference_id || 'N/A';
                const department = ticket.department_code || ticket.department_name || 'N/A';
                const office     = ticket.office_name || ticket.office_code || 'N/A';

                personalDetailsFieldsHTML = `
                    ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                    ${renderDetailTile('bi-card-text', 'Employee ID', empId, '', true)}
                    ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                    ${renderDetailTile('bi-building', 'Department', department, '', true)}
                    ${renderDetailTile('bi-geo-alt-fill', 'Office', office, '', true)}
                    ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                    ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                `;
            } else {
                personalDetailsFieldsHTML = `
                    ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                    ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                    ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                    ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                `;
            }

            const personalDetailsSectionHtml = `
                <div class="col-12">
                    <div class="modal-section-divider">
                        <i class="bi bi-person-vcard-fill text-primary"></i>
                        <span>Personal Details</span>
                    </div>
                </div>
                ${personalDetailsFieldsHTML}
            `;

            // 3. Additional Details (if present) + 4. Queue Lifecycle Timestamps
            modalUserDetailsContent.innerHTML = `
                <div class="row g-3">
                    ${concernsBlockHtml}
                    ${personalDetailsSectionHtml}
                    ${additionalDetailsHtml}
                    <div class="col-12">
                        <div class="modal-section-divider">
                            <i class="bi bi-clock-history text-primary"></i>
                            <span>Queue Lifecycle Timestamps</span>
                        </div>
                    </div>
                    <div class="col-12" id="queueLifecycleTableContainer">
                        ${buildLifecycleTableHtml(ticket)}
                    </div>
                </div>
            `;
        }

        function formatVisitorTypeChip(visitorTypeRaw) {
            const vType = (visitorTypeRaw || '').toUpperCase().trim();
            if (vType === 'STUDENT') {
                return `<span class="visitor-type-chip chip-student"><i class="bi bi-mortarboard-fill"></i>Student</span>`;
            }
            if (vType === 'GUEST') {
                return `<span class="visitor-type-chip chip-guest"><i class="bi bi-person-heart"></i>Guest</span>`;
            }
            if (vType === 'EMPLOYEE') {
                return `<span class="visitor-type-chip chip-employee"><i class="bi bi-person-badge-fill"></i>Employee</span>`;
            }
            return '';
        }

        function clearPillTickersForContainer(container) {
            if (!container) return;
            container.querySelectorAll('[data-ticker-ticket-id]').forEach(el => {
                const tid = el.getAttribute('data-ticker-ticket-id');
                if (pillTickerIntervals[tid]) {
                    clearInterval(pillTickerIntervals[tid]);
                    delete pillTickerIntervals[tid];
                }
            });
        }

        function buildSingleLineConcernLabels(ticket) {
            const labels = [];
            const otherText = ticket.other_concern_text ? ticket.other_concern_text.trim() : '';

            if (Array.isArray(ticket.concerns_list) && ticket.concerns_list.length > 0) {
                ticket.concerns_list.forEach(item => {
                    const main = (item.transaction_name || '').trim();
                    const sub  = (item.sub_concern_name || '').trim();

                    if (main.toLowerCase().includes('other') && otherText) {
                        return;
                    }

                    if (main && sub) {
                        labels.push(`${main} — ${sub}`);
                    } else if (main) {
                        labels.push(main);
                    }
                });
            } else if (ticket.transaction_name) {
                const mains = ticket.transaction_name.split(',').map(s => s.trim()).filter(Boolean);
                mains.forEach(m => {
                    if (!(m.toLowerCase().includes('other') && otherText)) {
                        labels.push(m);
                    }
                });
            }

            if (otherText) {
                labels.push(`Others: ${otherText}`);
            }

            return [...new Set(labels)];
        }

        function initPillConcernTickers(container, list) {
            if (!container) return;

            list.forEach(item => {
                const ticketId = String(item.id);
                const tickerEl = container.querySelector(`[data-ticker-ticket-id="${ticketId}"]`);
                if (!tickerEl) return;

                const labels = buildSingleLineConcernLabels(item);
                if (labels.length === 0) return;

                let currentIndex = (pillTickerIndices[ticketId] !== undefined && pillTickerIndices[ticketId] < labels.length)
                    ? pillTickerIndices[ticketId]
                    : 0;

                const isNewTicket = !renderedPillTicketIds.has(ticketId);
                renderedPillTicketIds.add(ticketId);

                const renderLabel = (idx, animate = true) => {
                    const textSpan = tickerEl.querySelector('.pill-concern-text');
                    if (!textSpan) return;

                    const labelText = labels[idx];
                    textSpan.textContent = labelText;
                    textSpan.title = labelText;

                    if (animate) {
                        textSpan.classList.remove('pill-concern-slide-out', 'pill-concern-slide-in');
                        void textSpan.offsetWidth;
                        textSpan.classList.add('pill-concern-slide-in');
                    }
                };

                renderLabel(currentIndex, isNewTicket);

                if (labels.length > 1) {
                    pillTickerIntervals[ticketId] = setInterval(() => {
                        const textSpan = tickerEl.querySelector('.pill-concern-text');
                        if (!textSpan) return;

                        textSpan.classList.remove('pill-concern-slide-in');
                        textSpan.classList.add('pill-concern-slide-out');

                        setTimeout(() => {
                            currentIndex = (currentIndex + 1) % labels.length;
                            pillTickerIndices[ticketId] = currentIndex;
                            renderLabel(currentIndex, true);
                        }, 280);
                    }, 2800);
                }
            });
        }

        function renderCategoryPills(container, list, emptyMsg, showVisitorLabel = true) {
            clearPillTickersForContainer(container);

            if (list.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4 my-2">
                        <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 44px; height: 44px; border: 1.5px dashed #cbd5e1;">
                            <i class="bi bi-inbox fs-5 opacity-50"></i>
                        </div>
                        <span class="small fw-semibold text-muted d-block">${emptyMsg}</span>
                    </div>
                `;
                return;
            }

            container.innerHTML = list.map((item, index) => {
                const visitorLabelHtml = showVisitorLabel ? formatVisitorTypeChip(item.visitor_type) : '';
                const concernLabels = buildSingleLineConcernLabels(item);
                const initialIdx = (pillTickerIndices[String(item.id)] !== undefined && pillTickerIndices[String(item.id)] < concernLabels.length)
                    ? pillTickerIndices[String(item.id)]
                    : 0;
                const tickerHtml = concernLabels.length > 0 ? `
                    <div class="pill-concern-ticker" data-ticker-ticket-id="${item.id}">
                        <span class="pill-concern-text">${escapeHtml(concernLabels[initialIdx])}</span>
                    </div>
                ` : '';

                return `
                    <div class="ticket-pill-item">
                        <div class="d-flex align-items-center min-w-0 overflow-hidden" style="flex: 1 1 0%;">
                            <span class="ticket-pill-pos">${index + 1}</span>
                            <div class="min-w-0 overflow-hidden" style="flex: 1 1 0%;">
                                <span class="ticket-pill-number d-block lh-1">${escapeHtml(item.ticket_number)}</span>
                                ${visitorLabelHtml}
                                ${tickerHtml}
                            </div>
                        </div>
                        <button type="button" class="btn-preview-visitor" onclick="openVisitorDetailsModal(${item.id})" title="View Visitor Information Details" aria-label="View Visitor Information Details">
                            <i class="bi bi-person-bounding-box fs-5"></i>
                        </button>
                    </div>
                `;
            }).join('');

            initPillConcernTickers(container, list);
        }

        // Opens the Handled Transactions & Sub-Transactions Modal for the active Office & Window
        window.openWindowServicesModal = function() {
            if (!HAS_ASSIGNMENT || !activeOfficeId) return;

            const modalEl = document.getElementById('windowServicesModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
            }

            if (windowServicesModalContent) {
                windowServicesModalContent.innerHTML = `
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="small text-muted mt-2 mb-0">Loading assigned transactions...</p>
                    </div>
                `;
            }

            const formData = new FormData();
            formData.append('action', 'fetch_window_services');
            formData.append('office_id', activeOfficeId);
            formData.append('counter_id', activeCounterId);

            fetch(HANDLER_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (!windowServicesModalContent) return;

                if (!res.success || !Array.isArray(res.data) || res.data.length === 0) {
                    windowServicesModalContent.innerHTML = `
                        <div class="text-center py-5">
                            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-white text-muted shadow-sm" style="width: 60px; height: 60px;">
                                <i class="bi bi-folder-x fs-3"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1">No Assigned Services Found</h6>
                            <p class="small text-muted mb-0">No active transaction types or sub-transactions are configured for this window.</p>
                        </div>
                    `;
                    return;
                }

                windowServicesModalContent.innerHTML = `
                    <div class="d-flex flex-column gap-3">
                        ${res.data.map(trans => {
                            const subs = Array.isArray(trans.sub_concerns) ? trans.sub_concerns : [];
                            const hasSubs = subs.length > 0;

                            let subsHtml = '';
                            if (hasSubs) {
                                subsHtml = `
                                    <div class="mt-2 pt-2 border-top d-flex flex-column gap-2">
                                        <span class="text-uppercase text-muted fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.04em;">Sub-Transactions</span>
                                        <div class="d-flex flex-column gap-2">
                                            ${subs.map(sub => `
                                                <div class="text-dark fw-normal" style="font-size: 0.875rem; line-height: 1.35;">
                                                    ${escapeHtml(sub.sub_concern_name)}
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                `;
                            }

                            return `
                                <div class="receipt-concern-item p-3 mb-0">
                                    <div class="text-dark fw-normal" style="font-size: 0.875rem; line-height: 1.35;">${escapeHtml(trans.transaction_name)}</div>
                                    ${subsHtml}
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            })
            .catch(err => {
                console.error('Error fetching window services:', err);
                if (windowServicesModalContent) {
                    windowServicesModalContent.innerHTML = `
                        <div class="alert alert-danger mb-0">Failed to load assigned transactions. Please try again.</div>
                    `;
                }
            });
        };

        function startDynamicTimer() {
            if (dynamicTimerInterval) clearInterval(dynamicTimerInterval);

            dynamicTimerInterval = setInterval(() => {
                if (!currentTimerBaseTime) return;
                const now = new Date();
                const elapsedSecs = Math.max(0, Math.floor((now - currentTimerBaseTime) / 1000));
                
                const mins = String(Math.floor(elapsedSecs / 60)).padStart(2, '0');
                const secs = String(elapsedSecs % 60).padStart(2, '0');
                
                if (digitalElapsedTimer) digitalElapsedTimer.textContent = `${mins}:${secs}`;
            }, 1000);
        }

        function stopDynamicTimer() {
            if (dynamicTimerInterval) {
                clearInterval(dynamicTimerInterval);
                dynamicTimerInterval = null;
            }
        }

        let queueActionInFlight = false;

        function sendQueueAction(actionName, additionalParams = {}, onSuccessCallback = null) {
            if (!HAS_ASSIGNMENT || !activeOfficeId || parseInt(activeCounterId, 10) <= 0) return;
            if (queueActionInFlight) return;
            queueActionInFlight = true;

            const formData = new FormData();
            formData.append('action', actionName);
            formData.append('office_id', activeOfficeId);
            formData.append('counter_id', activeCounterId);

            for (const key in additionalParams) {
                formData.append(key, additionalParams[key]);
            }

            fetch(HANDLER_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    if (typeof onSuccessCallback === 'function') {
                        onSuccessCallback(res);
                    }
                    loadCounterData();
                } else {
                    showSwalAlert("Notice", res.message || 'Failed to process request.', 'warning');
                }
            })
            .catch(err => {
                console.error(`Queue Action (${actionName}) Error:`, err);
                showSwalAlert("Error", "An unexpected error occurred while communicating with the server.", "error");
            })
            .finally(() => {
                queueActionInFlight = false;
            });
        }

        // Global Call Next per Category Lane (FIFO: PRIORITY, REGULAR, EMPLOYEE)
        window.callNextByCategory = function(category) {
            if (!category) return;
            sendQueueAction('call_next_category', { category: category });
        };

        if (btnStartServe)     btnStartServe.addEventListener('click', () => sendQueueAction('serve_ticket'));
        if (btnCompleteTicket) btnCompleteTicket.addEventListener('click', () => sendQueueAction('complete_ticket'));
        if (btnRecallVoice)    btnRecallVoice.addEventListener('click', () => sendQueueAction('recall_ticket'));

        if (btnSkipTicket) {
            btnSkipTicket.addEventListener('click', () => {
                if (!currentActiveTicket || !currentActiveTicket.ticket_number) {
                    showSwalAlert("Notice", "There is no active ticket to skip.", "warning");
                    return;
                }

                const skippedTicketNo = currentActiveTicket.ticket_number;
                const swalObj = typeof swal !== 'undefined' ? swal : (typeof Swal !== 'undefined' ? Swal : null);

                if (swalObj && typeof swalObj.fire === 'function') {
                    // SweetAlert2 fallback if loaded
                    swalObj.fire({
                        title: "Skip Ticket?",
                        text: `Are you sure you want to skip ticket ${skippedTicketNo}?`,
                        icon: "warning",
                        showCancelButton: true,
                        confirmButtonText: "Yes, Skip",
                        cancelButtonText: "Cancel",
                        confirmButtonColor: "#dc2626",
                        cancelButtonColor: "#6c757d"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            sendQueueAction('skip_ticket', {}, (res) => {
                                showSwalToast(
                                    "Ticket Skipped",
                                    res.message || `Ticket ${skippedTicketNo} has been skipped.`,
                                    "warning",
                                    2500
                                );
                            });
                        }
                    });
                } else if (typeof swalObj === 'function') {
                    // Existing Kaiadmin SweetAlert v1 confirmation dialog
                    swalObj({
                        title: "Skip Ticket?",
                        text: `Are you sure you want to skip ticket ${skippedTicketNo}?`,
                        icon: "warning",
                        buttons: {
                            cancel: {
                                text: "Cancel",
                                visible: true,
                                className: "btn btn-secondary"
                            },
                            confirm: {
                                text: "Yes, Skip",
                                className: "btn btn-danger"
                            }
                        },
                        dangerMode: true
                    }).then((willSkip) => {
                        if (willSkip) {
                            sendQueueAction('skip_ticket', {}, (res) => {
                                showSwalToast(
                                    "Ticket Skipped",
                                    res.message || `Ticket ${skippedTicketNo} has been skipped.`,
                                    "warning",
                                    2500
                                );
                            });
                        }
                    });
                } else if (confirm(`Are you sure you want to skip ticket ${skippedTicketNo}?`)) {
                    sendQueueAction('skip_ticket');
                }
            });
        }

        if (HAS_ASSIGNMENT && activeOfficeId && parseInt(activeCounterId, 10) > 0) {
            setInterval(loadCounterData, 4000);
        }
    })();
</script>
</body>
</html>