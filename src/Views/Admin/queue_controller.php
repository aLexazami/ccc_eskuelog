<?php
// src/Views/Admin/queue_controller.php

# Core Config Dependencies
global $session_class, $db_connect;
$db_connect = $db_connect ?? null;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

# Page Helpers
require_once ADMIN_PAGE_HEADER_PATH;

# Page Header Initialization
$title_page   = "Service Counter";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

// 1. Resolve User ID using $session_class wrapper or raw session
$user_id = 0;
if (isset($session_class) && method_exists($session_class, 'getValue')) {
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
    }
}

// 2. Resolve User Role (1=ADMIN, 2=ADMIN_STAFF, 3=STATION_ADMIN, 4=USER)
$g_user_role = '';
if (isset($session_class) && method_exists($session_class, 'getValue')) {
    $g_user_role = $session_class->getValue('user_role') ?? $session_class->getValue('system_role') ?? '';
}
if (empty($g_user_role)) {
    $g_user_role = $_SESSION['user_role'] ?? $_SESSION['g_user_role'] ?? $_SESSION['role'] ?? '';
}

$role_map = ['1' => 'ADMIN', '2' => 'ADMIN_STAFF', '3' => 'STATION_ADMIN', '4' => 'USER'];

if (isset($role_map[(string)$g_user_role])) {
    $g_user_role = $role_map[(string)$g_user_role];
}

// Only query system_access if role is completely missing
if (empty($g_user_role) && $db_connect && $user_id > 0) {
    $stmtSysRole = $db_connect->prepare("SELECT system_role FROM system_access WHERE user_id = ? AND system_type = 'E-SKUELOG' ORDER BY system_role ASC LIMIT 1");
    if ($stmtSysRole) {
        $stmtSysRole->bind_param("i", $user_id);
        $stmtSysRole->execute();
        $roleRes = $stmtSysRole->get_result()->fetch_assoc();
        if ($roleRes && isset($role_map[(string)$roleRes['system_role']])) {
            $g_user_role = $role_map[(string)$roleRes['system_role']];
        }
    }
}

$g_user_role_upper = strtoupper(trim((string)$g_user_role));

$assigned_office = null;
$offices         = [];

if ($db_connect && $user_id > 0) {
    // 1. ALWAYS priority-check staff_assignments table first for direct workstation assignment
    $stmtAssign = $db_connect->prepare("
        SELECT 
            sa.office_id, 
            sa.counter_id, 
            sa.shift_status,
            o.office_code, 
            o.office_name, 
            COALESCE(c.counter_name, CONCAT('Counter ', sa.counter_id), 'Service Counter Workstation') AS counter_label
        FROM staff_assignments sa
        INNER JOIN office o ON sa.office_id = o.office_id
        LEFT JOIN counters c ON sa.counter_id = c.id
        WHERE sa.user_id = ? AND sa.flag_status = 0
        ORDER BY (sa.shift_status = 'ON_DUTY') DESC, sa.id DESC
        LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $assigned_office = $stmtAssign->get_result()->fetch_assoc();
    }

    // 2. Fallback check on employee table if no staff_assignments record exists
    if (!$assigned_office) {
        $stmtEmp = $db_connect->prepare("
            SELECT e.office_id, o.office_code, o.office_name 
            FROM employee e
            INNER JOIN office o ON e.office_id = o.office_id
            WHERE e.user_id = ? LIMIT 1
        ");
        if ($stmtEmp) {
            $stmtEmp->bind_param("i", $user_id);
            $stmtEmp->execute();
            $empOffice = $stmtEmp->get_result()->fetch_assoc();
            if ($empOffice) {
                $assigned_office = [
                    'office_id'     => (int)$empOffice['office_id'],
                    'counter_id'    => 0,
                    'shift_status'  => 'ON_DUTY',
                    'office_code'   => $empOffice['office_code'],
                    'office_name'   => $empOffice['office_name'],
                    'counter_label' => 'Service Counter Workstation'
                ];
            }
        }
    }

    // Update active session values
    if ($assigned_office) {
        $_SESSION['assigned_office_id']  = (int)$assigned_office['office_id'];
        $_SESSION['assigned_counter_id'] = (int)($assigned_office['counter_id'] ?? 0);
        $_SESSION['shift_status']        = $assigned_office['shift_status'] ?? 'ON_DUTY';
    } else if ($g_user_role_upper === 'ADMIN') {
        // If user is ADMIN and has no specific assignment, populate dropdown options
        $stmt = $db_connect->query("SELECT office_id, office_code, office_name FROM office WHERE flag_status = 0 ORDER BY office_name ASC");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                $offices[] = $row;
            }
        }
    } else {
        unset($_SESSION['assigned_office_id'], $_SESSION['assigned_counter_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>

    <!-- WORKSTATION CUSTOM THEME STYLES -->
    <style>
        :root {
            --bg-wrapper: linear-gradient(135deg, rgba(11, 32, 117, 0.25) 0%, rgba(0, 10, 76, 0.45) 100%), url('<?= BASE_URL; ?>public/assets/main/css/bg-template.png');
            --bg-header: rgba(0, 16, 77, 0.88);
            --border-header: 1px solid rgba(255, 255, 255, 0.15);
            --header-title-color: #ffffff;
            
            --kiosk-primary: #1d4ed8;
            --kiosk-primary-hover: #1e40af;
            --kiosk-primary-light: #eff6ff;
            --kiosk-border: #e2e8f0;
            --kiosk-bg-subtle: #f8fafc;
            --kiosk-shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
            --kiosk-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
            --kiosk-radius: 1rem;
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

        .status-on-break {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-color: #fca5a5;
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

        .concern-hero-box {
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            border: 1.5px solid #bfdbfe;
            border-radius: 0.85rem;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.05);
        }

        .concern-pill-item {
            background-color: #ffffff;
            border: 1px solid #bfdbfe;
            border-left: 3px solid var(--kiosk-primary);
            border-radius: 0.5rem;
            padding: 0.5rem 0.85rem;
            margin-bottom: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .controller-subcard {
            border: 1.5px solid #e2e8f0;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: var(--kiosk-shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .lineup-header-student,
        .lineup-header-employee {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1.5px solid #bfdbfe;
            padding: 0.95rem 1.25rem;
        }

        .lineup-badge-student,
        .lineup-badge-employee {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            font-weight: 800;
            font-size: 0.8rem;
            padding: 0.4rem 1.1rem;
            border-radius: 50rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.22);
            letter-spacing: 0.02em;
        }

        .ticket-pill-item {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.85rem;
            padding: 0.75rem 1.15rem;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .ticket-pill-item:hover {
            border-color: #3b82f6;
            background-color: #ffffff;
            transform: translateX(4px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.08);
        }

        .ticket-pill-number {
            font-family: 'Courier New', SFMono-Regular, Consolas, Monaco, monospace;
            font-size: 1.25rem;
            font-weight: 900;
            letter-spacing: 0.05em;
            color: #0f172a;
        }

        .ticket-pill-pos {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #475569;
            font-weight: 800;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .ticket-pill-item:hover .ticket-pill-pos {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-call-employee-direct {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            border: none;
            padding: 0.35rem 0.85rem;
            border-radius: 0.5rem;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 0.02em;
            box-shadow: 0 2px 6px rgba(22, 163, 74, 0.25);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .btn-call-employee-direct:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            box-shadow: 0 4px 10px rgba(22, 163, 74, 0.38);
            transform: translateY(-1px);
        }

        .form-select-kiosk {
            border: 1.5px solid #cbd5e1;
            border-radius: 0.65rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.9rem;
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
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            font-weight: 800;
            font-size: 0.9rem;
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

        .btn-ctrl-call {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            color: #ffffff;
            border-color: #15803d;
            box-shadow: 0 4px 14px rgba(22, 163, 74, 0.28);
        }

        .btn-ctrl-call:hover {
            background: linear-gradient(135deg, #15803d 0%, #166534 100%);
            color: #ffffff;
            box-shadow: 0 6px 20px rgba(22, 163, 74, 0.38);
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

        .btn-ctrl-complete {
            background: #ffffff;
            color: #16a34a;
            border-color: #bbf7d0;
            box-shadow: 0 2px 8px rgba(22, 163, 74, 0.06);
        }

        .btn-ctrl-complete:hover {
            background: #f0fdf4;
            color: #15803d;
            border-color: #4ade80;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.12);
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
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>
        <div class="main-panel">

            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner">

                    <!-- Page Header -->
                    <div>
                        <?php echo is_array($page_header) ? '' : $page_header; ?>
                    </div>

                    <div class="row justify-content-center w-100 mx-0">
                        <div class="col-12 px-0">
                            <div class="card kiosk-main-card border-0">

                                <!-- KIOSK HEADER BANNER WITH ASSIGNED OFFICE AND COUNTER NAME -->
                                <div class="kiosk-header-banner d-flex flex-wrap align-items-center justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-2 gap-sm-3 min-w-0">
                                        <div class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center flex-shrink-0" style="background-color: rgba(255, 255, 255, 0.2); width: 40px; height: 40px;">
                                            <i class="bi bi-building fs-4"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="fw-bold text-white mb-0 tracking-wide text-truncate fs-6 fs-sm-5" id="headerOfficeName">
                                                <?= $assigned_office ? htmlspecialchars($assigned_office['office_name']) . ' (' . htmlspecialchars($assigned_office['office_code']) . ')' : ($g_user_role_upper === 'ADMIN' ? 'Select Active Office' : 'No Workstation Assigned'); ?>
                                            </h4>
                                            <p class="text-white-50 small mb-0" id="headerCounterName">
                                                <?= $assigned_office ? htmlspecialchars($assigned_office['counter_label'] ?? 'Service Counter Workstation') : 'Service Counter Workstation'; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- OFFICE / COUNTER SELECTOR OR HIDDEN INPUTS -->
                                    <div>
                                        <?php if ($assigned_office): ?>
                                            <input type="hidden" id="officeSelect" value="<?= (int)$assigned_office['office_id']; ?>">
                                            <input type="hidden" id="counterSelect" value="<?= (int)($assigned_office['counter_id'] ?? 0); ?>">
                                        <?php elseif ($g_user_role_upper === 'ADMIN'): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <label for="officeSelect" class="fw-bold text-white text-nowrap mb-0 small">Active Office:</label>
                                                <select id="officeSelect" class="form-select form-select-kiosk fw-bold text-dark border-0 shadow-sm" style="min-width: 220px;">
                                                    <option value="">-- Select Office --</option>
                                                    <?php foreach ($offices as $off): ?>
                                                        <option value="<?= $off['office_id']; ?>"><?= htmlspecialchars($off['office_name']); ?> (<?= htmlspecialchars($off['office_code']); ?>)</option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <input type="hidden" id="counterSelect" value="0">
                                            </div>
                                        <?php else: ?>
                                            <input type="hidden" id="officeSelect" value="">
                                            <input type="hidden" id="counterSelect" value="0">
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- CONTROLLER MAIN BODY -->
                                <div class="card-body p-3 p-sm-4">
                                    
                                    <?php if (!$assigned_office && $g_user_role_upper !== 'ADMIN'): ?>
                                        <!-- UNASSIGNED STAFF FALLBACK CARD -->
                                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden my-2" id="unassignedFallbackCard">
                                            <div class="card-body p-4 p-md-5 text-center">
                                                
                                                <!-- ICON BADGE -->
                                                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle" 
                                                     style="width: 88px; height: 88px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px dashed #93c5fd;">
                                                    <i class="bi bi-person-workspace text-primary" style="font-size: 2.6rem;"></i>
                                                </div>
                                                
                                                <!-- TITLE -->
                                                <h4 class="fw-bold text-dark mb-2">Workstation Not Assigned</h4>
                                                
                                                <!-- DESCRIPTION COPY -->
                                                <p class="text-muted mx-auto mb-4" style="max-width: 500px; font-size: 0.95rem; line-height: 1.65;">
                                                    Your account is active, but you have not been assigned to a service counter for today's shift. Please coordinate with your station manager or administrator to configure your workstation assignment.
                                                </p>

                                                <!-- RE-CHECK ASSIGNMENT BUTTON -->
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
                                                        <span id="counterStatusText">ON DUTY</span>
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
                                                        <div class="text-info text-uppercase fw-bold small tracking-wider mb-2 text-center">
                                                            <i class="bi bi-ticket-perforated-fill slant-ticket-icon"></i>QUEUE NUMBER
                                                        </div>

                                                        <div class="d-flex flex-column align-items-center my-auto">
                                                            <div class="ticket-hero-number" id="ctrlServingTicket">---</div>
                                                            <div id="ctrlPriorityTag" class="mt-1"></div>
                                                        </div>

                                                        <div id="ctrlVisitorTypeTag" class="mt-2"></div>
                                                    </div>

                                                    <div class="col-lg-8 col-md-7 ticket-hero-right" id="ticketDetailsContainer">
                                                    </div>

                                                </div>
                                            </div>

                                            <!-- HIGHLIGHTED ACTION CONTROLS BAR CONTAINER -->
                                            <div class="card action-bar-card mb-4">
                                                <div class="card-body p-3">
                                                    <div class="d-flex flex-column gap-2 gap-sm-3">
                                                        
                                                        <div class="row g-2 g-sm-3">
                                                            <div class="col-12 col-sm-6">
                                                                <button type="button" class="btn btn-action-modern btn-action-hero btn-ctrl-call" id="btnCallNext">
                                                                    <i class="bi bi-megaphone-fill"></i> Call Next
                                                                </button>
                                                            </div>
                                                            <div class="col-12 col-sm-6">
                                                                <button type="button" class="btn btn-action-modern btn-action-hero btn-ctrl-serve" id="btnStartServe">
                                                                    <i class="bi bi-play-circle-fill"></i> Serve
                                                                </button>
                                                            </div>
                                                        </div>

                                                        <div class="row g-2 g-sm-3">
                                                            <div class="col-12 col-sm-4">
                                                                <button type="button" class="btn btn-action-modern btn-ctrl-recall" id="btnRecallVoice">
                                                                    <i class="bi bi-arrow-counterclockwise"></i> Recall
                                                                </button>
                                                            </div>
                                                            <div class="col-12 col-sm-4">
                                                                <button type="button" class="btn btn-action-modern btn-ctrl-complete" id="btnCompleteTicket">
                                                                    <i class="bi bi-check-circle-fill"></i> Complete
                                                                </button>
                                                            </div>
                                                            <div class="col-12 col-sm-4">
                                                                <button type="button" class="btn btn-action-modern btn-ctrl-skip" id="btnSkipTicket">
                                                                    <i class="bi bi-dash-circle"></i> Skip
                                                                </button>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <!-- BOTTOM 2-COLUMN LINEUP SECTION -->
                                            <div class="row g-3">
                                                
                                                <div class="col-lg-6">
                                                    <div class="card controller-subcard h-100">
                                                        <div class="lineup-header-student d-flex align-items-center justify-content-between">
                                                            <h6 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                                                <i class="bi bi-people-fill me-2 fs-5 text-primary"></i>Students & Guests Queue
                                                            </h6>
                                                            <span class="lineup-badge-student" id="ctrlStudentBadge">0 Waiting</span>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-column gap-2" id="ctrlStudentList">
                                                                <div class="text-center py-4 my-2">
                                                                    <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px; border: 1.5px dashed #cbd5e1;">
                                                                        <i class="bi bi-inbox fs-4 opacity-50"></i>
                                                                    </div>
                                                                    <span class="small fw-semibold text-muted">No students currently waiting.</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-lg-6">
                                                    <div class="card controller-subcard h-100">
                                                        <div class="lineup-header-employee d-flex align-items-center justify-content-between">
                                                            <h6 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                                                <i class="bi bi-person-badge-fill me-2 fs-5 text-primary"></i>Employees Queue
                                                            </h6>
                                                            <span class="lineup-badge-employee" id="ctrlEmployeeBadge">0 Waiting</span>
                                                        </div>
                                                        <div class="card-body p-3">
                                                            <div class="d-flex flex-column gap-2" id="ctrlEmployeeList">
                                                                <div class="text-center py-4 my-2">
                                                                    <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px; border: 1.5px dashed #cbd5e1;">
                                                                        <i class="bi bi-inbox fs-4 opacity-50"></i>
                                                                    </div>
                                                                    <span class="small fw-semibold text-muted">No employees currently waiting.</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>

                                        </div>
                                    <?php endif; ?>

                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <!-- VISITOR / USER FULL DETAILS MODAL -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden;">
                <div class="modal-header text-white" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);">
                    <h5 class="modal-title fw-bold d-flex align-items-center" id="userDetailsModalLabel">
                        <i class="bi bi-person-lines-fill me-2 fs-4"></i>Visitor Information Details
                    </h5>
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

    <?php include_once ADMIN_SCRIPT_PATH; ?>

    <script>
        (function() {
            const IS_ADMIN = <?= json_encode($g_user_role_upper === 'ADMIN'); ?>;
            const HAS_ASSIGNMENT = <?= json_encode(!empty($assigned_office)); ?>;

            const officeSelect = document.getElementById('officeSelect');
            const counterSelect = document.getElementById('counterSelect');
            
            const controllerMainContent = document.getElementById('controllerMainContent');
            const headerOfficeName = document.getElementById('headerOfficeName');
            const headerCounterName = document.getElementById('headerCounterName');
            
            const counterStatusContainer = document.getElementById('counterStatusContainer');
            const counterStatusText = document.getElementById('counterStatusText');
            const digitalElapsedTimer = document.getElementById('digitalElapsedTimer');

            const ctrlServingTicket = document.getElementById('ctrlServingTicket');
            const ctrlPriorityTag = document.getElementById('ctrlPriorityTag');
            const ctrlVisitorTypeTag = document.getElementById('ctrlVisitorTypeTag');
            const ticketDetailsContainer = document.getElementById('ticketDetailsContainer');

            const modalUserDetailsContent = document.getElementById('modalUserDetailsContent');

            const ctrlStudentBadge = document.getElementById('ctrlStudentBadge');
            const ctrlEmployeeBadge = document.getElementById('ctrlEmployeeBadge');
            const ctrlStudentList = document.getElementById('ctrlStudentList');
            const ctrlEmployeeList = document.getElementById('ctrlEmployeeList');

            const btnCallNext = document.getElementById('btnCallNext');
            const btnStartServe = document.getElementById('btnStartServe');
            const btnRecallVoice = document.getElementById('btnRecallVoice');
            const btnCompleteTicket = document.getElementById('btnCompleteTicket');
            const btnSkipTicket = document.getElementById('btnSkipTicket');

            const HANDLER_URL = '<?= BASE_URL; ?>admin/ajax/queue';

            const isSelectDropdown = officeSelect && officeSelect.tagName === 'SELECT';
            let activeOfficeId = officeSelect ? officeSelect.value : '';
            let activeCounterId = counterSelect ? counterSelect.value : '0';

            let dynamicTimerInterval = null;
            let currentTimerBaseTime = null;

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

            function updateHeaderBannerText() {
                if (isSelectDropdown && officeSelect.selectedIndex > 0) {
                    const selectedText = officeSelect.options[officeSelect.selectedIndex].text;
                    if (headerOfficeName) headerOfficeName.textContent = selectedText;
                    if (headerCounterName) headerCounterName.textContent = 'Service Counter Workstation';
                } else if (isSelectDropdown && !officeSelect.value) {
                    if (headerOfficeName) headerOfficeName.textContent = 'Select Active Office';
                    if (headerCounterName) headerCounterName.textContent = 'Service Counter Workstation';
                }
            }

            function toTitleCase(str) {
                if (!str) return '';
                return str.toLowerCase().replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });
            }

            if (HAS_ASSIGNMENT && officeSelect && officeSelect.value) {
                activeOfficeId = officeSelect.value;
                activeCounterId = counterSelect ? counterSelect.value : '0';
                loadCounterData();
            } else if (IS_ADMIN && isSelectDropdown) {
                const storedOffice = localStorage.getItem('ctrl_active_office') || '';
                if (storedOffice) {
                    officeSelect.value = storedOffice;
                    activeOfficeId = storedOffice;
                    updateHeaderBannerText();
                }
                
                officeSelect.addEventListener('change', function() {
                    activeOfficeId = this.value;
                    localStorage.setItem('ctrl_active_office', activeOfficeId);
                    updateHeaderBannerText();
                    loadCounterData();
                });

                if (activeOfficeId) {
                    updateHeaderBannerText();
                    loadCounterData();
                }
            }

            function loadCounterData() {
                if (!activeOfficeId || (!HAS_ASSIGNMENT && !IS_ADMIN)) {
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
                        const shiftStatus = res.data.shift_status || 'ON_DUTY';

                        if (counterStatusText && counterStatusContainer) {
                            if (ticket && ticket.status === 'CALLED') {
                                counterStatusText.textContent = 'NOW CALLING';
                                counterStatusContainer.className = 'status-pill-modern status-now-calling';
                            } else if (ticket && ticket.status === 'SERVING') {
                                counterStatusText.textContent = 'NOW SERVING';
                                counterStatusContainer.className = 'status-pill-modern status-now-serving';
                            } else if (shiftStatus === 'ON_BREAK') {
                                counterStatusText.textContent = 'ON BREAK';
                                counterStatusContainer.className = 'status-pill-modern status-on-break';
                            } else {
                                counterStatusText.textContent = 'ON DUTY';
                                counterStatusContainer.className = 'status-pill-modern status-on-duty';
                            }
                        }

                        if (ticket && ticket.ticket_number) {
                            if (ctrlServingTicket) ctrlServingTicket.textContent = ticket.ticket_number;

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

                            let entryTimeString = '---';
                            if (ticket.queue_entry_time) {
                                const entryDate = new Date(ticket.queue_entry_time);
                                const timeFormatted = entryDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                                const calledDate = ticket.called_at ? new Date(ticket.called_at) : new Date();
                                const waitSecs = Math.max(0, Math.floor((calledDate - entryDate) / 1000));
                                const waitMins = Math.floor(waitSecs / 60);
                                entryTimeString = `${timeFormatted} (Waited: ${waitMins} min${waitMins !== 1 ? 's' : ''})`;
                            }

                            renderDynamicTicketDetails(ticket, entryTimeString);

                            if (ticket.status === 'CALLED' && ticket.called_at) {
                                currentTimerBaseTime = new Date(ticket.called_at);
                                startDynamicTimer();
                            } else if (ticket.status === 'SERVING' && ticket.served_at) {
                                currentTimerBaseTime = new Date(ticket.served_at);
                                startDynamicTimer();
                            } else {
                                stopDynamicTimer();
                                if (digitalElapsedTimer) digitalElapsedTimer.textContent = '00:00';
                            }

                        } else {
                            if (ctrlServingTicket) ctrlServingTicket.textContent = '---';
                            if (ctrlPriorityTag) ctrlPriorityTag.innerHTML = '';
                            if (ctrlVisitorTypeTag) ctrlVisitorTypeTag.innerHTML = '';
                            stopDynamicTimer();
                            if (digitalElapsedTimer) digitalElapsedTimer.textContent = '00:00';
                            renderEmptyDetailsState();
                        }

                        const waiting = res.data.up_next || [];
                        const students = waiting.filter(t => t.visitor_type !== 'EMPLOYEE');
                        const employees = waiting.filter(t => t.visitor_type === 'EMPLOYEE');

                        if (ctrlStudentBadge) ctrlStudentBadge.textContent = `${students.length} Waiting`;
                        if (ctrlEmployeeBadge) ctrlEmployeeBadge.textContent = `${employees.length} Waiting`;

                        if (ctrlStudentList) renderStudentPills(ctrlStudentList, students, 'No students currently waiting.');
                        if (ctrlEmployeeList) renderEmployeePills(ctrlEmployeeList, employees, 'No employees currently waiting.');
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
                        <p class="small text-muted mb-0">Click <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1"><i class="bi bi-megaphone-fill me-1"></i>Call Next</span> to proceed with the next visitor in queue.</p>
                    </div>
                `;
                if (modalUserDetailsContent) {
                    modalUserDetailsContent.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-circle text-muted fs-1 mb-2"></i>
                            <p class="text-muted fw-semibold mb-0">No active ticket available to display details.</p>
                        </div>
                    `;
                }
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

            function renderDetailTile(iconClass, label, value, customClass = '') {
                return `
                    <div class="col-12 col-sm-6">
                        <div class="detail-tile d-flex align-items-start">
                            <div class="tile-icon flex-shrink-0">
                                <i class="bi ${iconClass} fs-5"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <span class="fw-semibold text-muted d-block mb-1" style="font-size: 0.75rem;">${label}</span>
                                <strong class="text-dark d-block text-break ${customClass}" style="font-size: 0.875rem; line-height: 1.35; word-break: break-word;" title="${value}">${value}</strong>
                            </div>
                        </div>
                    </div>
                `;
            }

            function renderDynamicTicketDetails(ticket, entryTimeString) {
                if (!ticketDetailsContainer) return;
                
                let concernsHtml = '';
                if (ticket.concerns_list && ticket.concerns_list.length > 0) {
                    concernsHtml = ticket.concerns_list.map(c => {
                        const sub = c.sub_concern_name ? ` &rsaquo; <span class="fw-bold text-primary">${c.sub_concern_name}</span>` : '';
                        return `<div class="concern-pill-item"><div class="fw-bold text-dark small">${c.transaction_name || 'General Concern'}${sub}</div></div>`;
                    }).join('');
                } else if (ticket.transaction_name) {
                    concernsHtml = `<div class="concern-pill-item"><div class="fw-bold text-dark small">${ticket.transaction_name}</div></div>`;
                } else if (ticket.other_concern_text) {
                    concernsHtml = `<div class="concern-pill-item"><div class="fw-bold text-dark small">${ticket.other_concern_text}</div></div>`;
                } else {
                    concernsHtml = `<div class="concern-pill-item"><div class="fw-bold text-dark small">General Administrative Inquiry</div></div>`;
                }

                ticketDetailsContainer.innerHTML = `
                    <div class="d-flex flex-column justify-content-between h-100 py-1">
                        <div class="concern-hero-box my-auto">
                            <div class="d-flex align-items-start gap-3">
                                <div class="p-2 bg-primary text-white rounded-3 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    <i class="bi bi-clipboard-check-fill fs-4"></i>
                                </div>
                                <div class="min-w-0 flex-grow-1">
                                    <span class="text-uppercase text-muted fw-bold tracking-wider d-block mb-2" style="font-size: 0.75rem;">Transaction Concern(s)</span>
                                    <div class="d-flex flex-column gap-2">
                                        ${concernsHtml}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 text-end">
                            <button type="button" class="btn btn-outline-primary fw-bold d-inline-flex align-items-center gap-2 px-4 py-2 rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#userDetailsModal">
                                <i class="bi bi-person-bounding-box fs-5"></i>
                                <span>View Visitor Full Details</span>
                            </button>
                        </div>
                    </div>
                `;

                populateVisitorModal(ticket, entryTimeString);
            }

            function populateVisitorModal(ticket, entryTimeString) {
                if (!modalUserDetailsContent) return;
                const visitorType = (ticket.visitor_type || 'VISITOR').toUpperCase();
                const fullName    = buildFormattedName(ticket);
                const email       = ticket.email || 'N/A';
                const sex         = ticket.sex ? ticket.sex.charAt(0).toUpperCase() + ticket.sex.slice(1).toLowerCase() : 'N/A';
                
                let dynamicFieldsHTML = '';

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

                    dynamicFieldsHTML = `
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6')}
                        ${renderDetailTile('bi-mortarboard-fill', 'Course / Program', courseDisplay)}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email)}
                        ${renderDetailTile('bi-journal-bookmark-fill', 'Year Level', yearLevel)}
                        ${renderDetailTile('bi-card-text', 'Student ID', studentId)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex)}
                    `;
                } else if (visitorType === 'EMPLOYEE') {
                    const department = ticket.department_code || ticket.department_name || 'N/A';
                    const office     = ticket.office_name || ticket.office_code || 'N/A';

                    dynamicFieldsHTML = `
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6')}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email)}
                        ${renderDetailTile('bi-building', 'Department', department)}
                        ${renderDetailTile('bi-geo-alt-fill', 'Office', office)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex)}
                    `;
                } else {
                    const contactNo = ticket.contact_no || 'N/A';

                    dynamicFieldsHTML = `
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6')}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email)}
                        ${renderDetailTile('bi-telephone-fill', 'Contact Number', contactNo)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex)}
                    `;
                }

                modalUserDetailsContent.innerHTML = `
                    <div class="row g-3">
                        ${dynamicFieldsHTML}
                        ${renderDetailTile('bi-clock-history', 'Queue Entry Time', entryTimeString)}
                    </div>
                `;
            }

            function renderStudentPills(container, list, emptyMsg) {
                if (list.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-4 my-2">
                            <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px; border: 1.5px dashed #cbd5e1;">
                                <i class="bi bi-inbox fs-4 opacity-50"></i>
                            </div>
                            <span class="small fw-semibold text-muted">${emptyMsg}</span>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = list.map((item, index) => `
                    <div class="ticket-pill-item">
                        <div class="d-flex align-items-center">
                            <span class="ticket-pill-pos">${index + 1}</span>
                            <span class="ticket-pill-number">${item.ticket_number}</span>
                        </div>
                        ${item.is_priority == 1 ? '<span class="badge bg-warning text-dark fw-bold px-3 py-1.5 rounded-pill"><i class="bi bi-star-fill text-danger me-1"></i>Priority</span>' : '<span class="badge bg-light text-secondary border fw-semibold px-3 py-1 rounded-pill small">Waiting</span>'}
                    </div>
                `).join('');
            }

            function renderEmployeePills(container, list, emptyMsg) {
                if (list.length === 0) {
                    container.innerHTML = `
                        <div class="text-center py-4 my-2">
                            <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-light text-muted" style="width: 48px; height: 48px; border: 1.5px dashed #cbd5e1;">
                                <i class="bi bi-inbox fs-4 opacity-50"></i>
                            </div>
                            <span class="small fw-semibold text-muted">${emptyMsg}</span>
                        </div>
                    `;
                    return;
                }

                container.innerHTML = list.map((item, index) => `
                    <div class="ticket-pill-item">
                        <div class="d-flex align-items-center">
                            <span class="ticket-pill-pos">${index + 1}</span>
                            <span class="ticket-pill-number me-2">${item.ticket_number}</span>
                            ${item.is_priority == 1 ? '<span class="badge bg-warning text-dark fw-bold px-2 py-1 rounded-pill small ms-1"><i class="bi bi-star-fill text-danger me-1"></i>Priority</span>' : ''}
                        </div>
                        <button type="button" class="btn-call-employee-direct" onclick="callSpecificTicket(${item.id})">
                            <i class="bi bi-megaphone-fill me-1"></i> Call Next
                        </button>
                    </div>
                `).join('');
            }

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

            function sendQueueAction(actionName, additionalParams = {}) {
                if (!activeOfficeId || (!HAS_ASSIGNMENT && !IS_ADMIN)) return;

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
                        loadCounterData();
                    } else {
                        showSwalAlert("Notice", res.message || 'Failed to process request.', 'warning');
                    }
                })
                .catch(err => {
                    console.error(`Queue Action (${actionName}) Error:`, err);
                    showSwalAlert("Error", "An unexpected error occurred while communicating with the server.", "error");
                });
            }

            window.callSpecificTicket = function(ticketId) {
                if (!ticketId) return;
                sendQueueAction('call_specific_ticket', { ticket_id: ticketId });
            };

            if (btnCallNext) btnCallNext.addEventListener('click', () => sendQueueAction('call_next'));
            if (btnStartServe) btnStartServe.addEventListener('click', () => sendQueueAction('serve_ticket'));
            if (btnRecallVoice) btnRecallVoice.addEventListener('click', () => sendQueueAction('recall_ticket'));
            if (btnCompleteTicket) btnCompleteTicket.addEventListener('click', () => sendQueueAction('complete_ticket'));
            if (btnSkipTicket) btnSkipTicket.addEventListener('click', () => sendQueueAction('skip_ticket'));

            if (HAS_ASSIGNMENT || IS_ADMIN) {
                setInterval(loadCounterData, 4000);
            }
        })();
    </script>
</body>
</html>