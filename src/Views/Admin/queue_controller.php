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

$assigned_office = null;

if ($db_connect && $user_id > 0) {
    // STRICT Priority 1 ONLY: Require active staff_assignments row with BOTH office_id and counter_id
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
        ORDER BY sa.id DESC
        LIMIT 1
    ");
    if ($stmtAssign) {
        $stmtAssign->bind_param("i", $user_id);
        $stmtAssign->execute();
        $assigned_office = $stmtAssign->get_result()->fetch_assoc();
    }

    // Update or clear active session values strictly based on staff_assignments
    if ($assigned_office) {
        $_SESSION['assigned_office_id']  = (int)$assigned_office['office_id'];
        $_SESSION['assigned_counter_id'] = (int)$assigned_office['counter_id'];
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
        }

        /* 4-COLUMN CATEGORY HEADERS */
        .lineup-header-priority {
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            border-bottom: 1.5px solid #fcd34d;
            padding: 0.85rem 1rem;
        }

        .lineup-header-student {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-bottom: 1.5px solid #bfdbfe;
            padding: 0.85rem 1rem;
        }

        .lineup-header-guest {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border-bottom: 1.5px solid #bbf7d0;
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

        .btn-lineup-collapse {
            width: 26px;
            height: 26px;
            padding: 0;
            border-radius: 50%;
            border: 1px solid rgba(15, 23, 42, 0.15);
            background: rgba(255, 255, 255, 0.8);
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.25s ease, background-color 0.2s ease;
        }

        .btn-lineup-collapse:hover {
            background: #ffffff;
        }

        .btn-lineup-collapse:not(.collapsed) i {
            transform: rotate(180deg);
        }

        .btn-lineup-collapse i {
            transition: transform 0.25s ease;
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
        .btn-call-student  { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); }
        .btn-call-guest    { background: linear-gradient(135deg, #16a34a 0%, #15803d 100%); }
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
            margin-right: 0.5rem;
            flex-shrink: 0;
        }

        .ticket-pill-item:hover .ticket-pill-pos {
            background: #dbeafe;
            color: #1d4ed8;
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

        .additional-details-divider {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.78rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #1e40af;
            padding-top: 0.35rem;
            margin-top: 0.25rem;
            border-top: 1.5px dashed #cbd5e1;
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
                                                <?= $assigned_office ? htmlspecialchars($assigned_office['office_name']) . ' (' . htmlspecialchars($assigned_office['office_code']) . ')' : 'No Workstation Assigned'; ?>
                                            </h4>
                                            <p class="text-white-50 small mb-0" id="headerCounterName">
                                                <?= $assigned_office ? htmlspecialchars($assigned_office['counter_label']) : 'Service Counter Workstation'; ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- HIDDEN WORKSTATION INPUTS -->
                                    <div>
                                        <?php if ($assigned_office): ?>
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
                                    
                                    <?php if (!$assigned_office): ?>
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

                                            <!-- BOTTOM 4-COLUMN CATEGORY LINEUP SECTION (COLLAPSIBLE, DEFAULT COLLAPSED) -->
                                            <div class="row g-3 align-items-start">
                                                
                                                <!-- COLUMN 1: PRIORITY -->
                                                <div class="col-12 col-md-6 col-xl-3">
                                                    <div class="card controller-subcard">
                                                        <div class="lineup-header-priority">
                                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                                <h6 class="fw-bold text-warning-emphasis mb-0 d-flex align-items-center">
                                                                    <i class="bi bi-star-fill me-2 text-warning"></i>Priority
                                                                </h6>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="lineup-badge-pill bg-warning text-dark" id="ctrlPriorityBadge">0 Waiting</span>
                                                                    <button type="button" class="btn-lineup-collapse collapsed" data-bs-toggle="collapse" data-bs-target="#collapsePriorityList" aria-expanded="false" aria-controls="collapsePriorityList" title="Toggle Priority Waiting Line">
                                                                        <i class="bi bi-chevron-down small"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn-global-call-next btn-call-priority" id="btnCallNextPriority" onclick="callNextByCategory('PRIORITY')">
                                                                <i class="bi bi-megaphone-fill"></i> Call Next
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="collapsePriorityList">
                                                            <div class="card-body p-2 p-sm-3">
                                                                <div class="d-flex flex-column gap-2" id="ctrlPriorityList"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- COLUMN 2: STUDENTS -->
                                                <div class="col-12 col-md-6 col-xl-3">
                                                    <div class="card controller-subcard">
                                                        <div class="lineup-header-student">
                                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                                <h6 class="fw-bold text-primary mb-0 d-flex align-items-center">
                                                                    <i class="bi bi-mortarboard-fill me-2 text-primary"></i>Students
                                                                </h6>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="lineup-badge-pill bg-primary text-white" id="ctrlStudentBadge">0 Waiting</span>
                                                                    <button type="button" class="btn-lineup-collapse collapsed" data-bs-toggle="collapse" data-bs-target="#collapseStudentList" aria-expanded="false" aria-controls="collapseStudentList" title="Toggle Students Waiting Line">
                                                                        <i class="bi bi-chevron-down small"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn-global-call-next btn-call-student" id="btnCallNextStudent" onclick="callNextByCategory('STUDENT')">
                                                                <i class="bi bi-megaphone-fill"></i> Call Next
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="collapseStudentList">
                                                            <div class="card-body p-2 p-sm-3">
                                                                <div class="d-flex flex-column gap-2" id="ctrlStudentList"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- COLUMN 3: GUESTS -->
                                                <div class="col-12 col-md-6 col-xl-3">
                                                    <div class="card controller-subcard">
                                                        <div class="lineup-header-guest">
                                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                                <h6 class="fw-bold text-success mb-0 d-flex align-items-center">
                                                                    <i class="bi bi-person-heart me-2 text-success"></i>Guest
                                                                </h6>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="lineup-badge-pill bg-success text-white" id="ctrlGuestBadge">0 Waiting</span>
                                                                    <button type="button" class="btn-lineup-collapse collapsed" data-bs-toggle="collapse" data-bs-target="#collapseGuestList" aria-expanded="false" aria-controls="collapseGuestList" title="Toggle Guest Waiting Line">
                                                                        <i class="bi bi-chevron-down small"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn-global-call-next btn-call-guest" id="btnCallNextGuest" onclick="callNextByCategory('GUEST')">
                                                                <i class="bi bi-megaphone-fill"></i> Call Next
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="collapseGuestList">
                                                            <div class="card-body p-2 p-sm-3">
                                                                <div class="d-flex flex-column gap-2" id="ctrlGuestList"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- COLUMN 4: EMPLOYEES -->
                                                <div class="col-12 col-md-6 col-xl-3">
                                                    <div class="card controller-subcard">
                                                        <div class="lineup-header-employee">
                                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                                <h6 class="fw-bold mb-0 d-flex align-items-center" style="color: #5b21b6;">
                                                                    <i class="bi bi-person-badge-fill me-2" style="color: #7c3aed;"></i>Employee
                                                                </h6>
                                                                <div class="d-flex align-items-center gap-1">
                                                                    <span class="lineup-badge-pill text-white" style="background-color: #7c3aed;" id="ctrlEmployeeBadge">0 Waiting</span>
                                                                    <button type="button" class="btn-lineup-collapse collapsed" data-bs-toggle="collapse" data-bs-target="#collapseEmployeeList" aria-expanded="false" aria-controls="collapseEmployeeList" title="Toggle Employee Waiting Line">
                                                                        <i class="bi bi-chevron-down small"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <button type="button" class="btn-global-call-next btn-call-employee" id="btnCallNextEmployee" onclick="callNextByCategory('EMPLOYEE')">
                                                                <i class="bi bi-megaphone-fill"></i> Call Next
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="collapseEmployeeList">
                                                            <div class="card-body p-2 p-sm-3">
                                                                <div class="d-flex flex-column gap-2" id="ctrlEmployeeList"></div>
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

            // 4-Column Badges & Lists
            const ctrlPriorityBadge = document.getElementById('ctrlPriorityBadge');
            const ctrlStudentBadge  = document.getElementById('ctrlStudentBadge');
            const ctrlGuestBadge    = document.getElementById('ctrlGuestBadge');
            const ctrlEmployeeBadge = document.getElementById('ctrlEmployeeBadge');

            const ctrlPriorityList = document.getElementById('ctrlPriorityList');
            const ctrlStudentList  = document.getElementById('ctrlStudentList');
            const ctrlGuestList    = document.getElementById('ctrlGuestList');
            const ctrlEmployeeList = document.getElementById('ctrlEmployeeList');

            // Global Category Call Next Buttons
            const btnCallNextPriority = document.getElementById('btnCallNextPriority');
            const btnCallNextStudent  = document.getElementById('btnCallNextStudent');
            const btnCallNextGuest    = document.getElementById('btnCallNextGuest');
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

            function escapeHtml(str) {
                if (str === null || str === undefined) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

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
                        let subTagsHtml = '';
                        if (subs && subs.length > 0) {
                            const tags = subs.map(sub => `
                                <span class="receipt-subconcern-tag me-1 mb-1">
                                    <i class="bi bi-tag-fill me-1"></i>${escapeHtml(sub)}
                                </span>
                            `).join('');
                            subTagsHtml = `<div class="mt-1 d-flex flex-wrap align-items-center">${tags}</div>`;
                        }

                        html += `
                            <div class="receipt-concern-item">
                                <div class="receipt-concern-title">${escapeHtml(mainTitle)}</div>
                                ${subTagsHtml}
                            </div>
                        `;
                    });
                } else if (ticket.transaction_name) {
                    const mainConcerns = ticket.transaction_name.split(',').map(s => s.trim()).filter(Boolean);
                    const subConcerns = (ticket.sub_concern_name || '').split(',').map(s => s.trim()).filter(Boolean);

                    mainConcerns.forEach((main, idx) => {
                        if (main.toLowerCase().includes('other') && otherText) return;

                        let subTagsHtml = '';
                        if (idx === 0 && subConcerns.length > 0) {
                            const tags = subConcerns.map(sub => `
                                <span class="receipt-subconcern-tag me-1 mb-1">
                                    <i class="bi bi-tag-fill me-1"></i>${escapeHtml(sub)}
                                </span>
                            `).join('');
                            subTagsHtml = `<div class="mt-1 d-flex flex-wrap align-items-center">${tags}</div>`;
                        }

                        html += `
                            <div class="receipt-concern-item">
                                <div class="receipt-concern-title">${escapeHtml(main)}</div>
                                ${subTagsHtml}
                            </div>
                        `;
                    });
                }

                if (otherText) {
                    html += `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title"><i class="bi bi-pen me-1 text-primary"></i>Others / Custom Concern</div>
                            <div class="text-muted small mt-1 fw-semibold" style="word-break: break-word;">${escapeHtml(otherText)}</div>
                        </div>
                    `;
                }

                if (!html) {
                    html = `
                        <div class="receipt-concern-item">
                            <div class="receipt-concern-title">General Concern</div>
                        </div>
                    `;
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
                            if (ctrlWindowNumberTag) ctrlWindowNumberTag.innerHTML = '';
                            if (ctrlPriorityTag) ctrlPriorityTag.innerHTML = '';
                            if (ctrlVisitorTypeTag) ctrlVisitorTypeTag.innerHTML = '';
                            stopDynamicTimer();
                            if (digitalElapsedTimer) digitalElapsedTimer.textContent = '00:00';
                            renderEmptyDetailsState();
                        }

                        // Categorize Waiting Tickets into 4 FIFO Lanes: Priority, Student, Guest, Employee
                        const waiting = res.data.up_next || [];
                        waitingTicketsMap = {};
                        waiting.forEach(item => {
                            waitingTicketsMap[item.id] = item;
                        });

                        const priorityList = waiting.filter(t => Number(t.is_priority) === 1);
                        const studentList  = waiting.filter(t => Number(t.is_priority) !== 1 && (t.visitor_type || '').toUpperCase() === 'STUDENT');
                        const guestList    = waiting.filter(t => Number(t.is_priority) !== 1 && (t.visitor_type || '').toUpperCase() === 'GUEST');
                        const employeeList = waiting.filter(t => Number(t.is_priority) !== 1 && (t.visitor_type || '').toUpperCase() === 'EMPLOYEE');

                        if (ctrlPriorityBadge) ctrlPriorityBadge.textContent = `${priorityList.length} Waiting`;
                        if (ctrlStudentBadge)  ctrlStudentBadge.textContent  = `${studentList.length} Waiting`;
                        if (ctrlGuestBadge)    ctrlGuestBadge.textContent    = `${guestList.length} Waiting`;
                        if (ctrlEmployeeBadge) ctrlEmployeeBadge.textContent = `${employeeList.length} Waiting`;

                        if (btnCallNextPriority) btnCallNextPriority.disabled = priorityList.length === 0;
                        if (btnCallNextStudent)  btnCallNextStudent.disabled  = studentList.length === 0;
                        if (btnCallNextGuest)    btnCallNextGuest.disabled    = guestList.length === 0;
                        if (btnCallNextEmployee) btnCallNextEmployee.disabled = employeeList.length === 0;

                        if (ctrlPriorityList) renderCategoryPills(ctrlPriorityList, priorityList, 'No priority visitors waiting.');
                        if (ctrlStudentList)  renderCategoryPills(ctrlStudentList, studentList, 'No students waiting.');
                        if (ctrlGuestList)    renderCategoryPills(ctrlGuestList, guestList, 'No guests waiting.');
                        if (ctrlEmployeeList) renderCategoryPills(ctrlEmployeeList, employeeList, 'No employees waiting.');

                    } else if (!res.success) {
                        if (controllerMainContent) controllerMainContent.classList.add('d-none');
                    }
                })
                .catch(err => {
                    console.error("Counter status error:", err);
                });
            }

            function computeEntryTimeString(ticket) {
                if (!ticket || !ticket.queue_entry_time) return '---';
                const entryDate = new Date(ticket.queue_entry_time);
                const timeFormatted = entryDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                const refDate = ticket.called_at ? new Date(ticket.called_at) : new Date();
                const waitSecs = Math.max(0, Math.floor((refDate - entryDate) / 1000));
                const waitMins = Math.floor(waitSecs / 60);
                return `${timeFormatted} (${ticket.called_at ? 'Waited' : 'Waiting'}: ${waitMins} min${waitMins !== 1 ? 's' : ''})`;
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
                        <div class="additional-details-divider">
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

                populateVisitorModal(targetTicket, computeEntryTimeString(targetTicket));

                const modalEl = document.getElementById('userDetailsModal');
                if (modalEl && typeof bootstrap !== 'undefined') {
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();
                }
            };

            function populateVisitorModal(ticket, entryTimeString) {
                if (!modalUserDetailsContent) return;

                const visitorType           = (ticket.visitor_type || 'VISITOR').toUpperCase();
                const fullName              = buildFormattedName(ticket);
                const email                 = ticket.email || 'N/A';
                const sex                   = ticket.sex ? ticket.sex.charAt(0).toUpperCase() + ticket.sex.slice(1).toLowerCase() : 'N/A';
                const windowLabel           = resolveTicketWindow(ticket);
                const concernsHtml          = buildLogbookStyleConcerns(ticket);
                const additionalDetailsHtml = buildAdditionalDetailsHtml(ticket);

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
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                        ${renderDetailTile('bi-mortarboard-fill', 'Course / Program', courseDisplay, '', true)}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                        ${renderDetailTile('bi-journal-bookmark-fill', 'Year Level', yearLevel, '', true)}
                        ${renderDetailTile('bi-card-text', 'Student ID', studentId, '', true)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                        ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                        ${additionalDetailsHtml}
                    `;
                } else if (visitorType === 'EMPLOYEE') {
                    const empId      = ticket.reference_id || 'N/A';
                    const department = ticket.department_code || ticket.department_name || 'N/A';
                    const office     = ticket.office_name || ticket.office_code || 'N/A';

                    dynamicFieldsHTML = `
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                        ${renderDetailTile('bi-card-text', 'Employee ID', empId, '', true)}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                        ${renderDetailTile('bi-building', 'Department', department, '', true)}
                        ${renderDetailTile('bi-geo-alt-fill', 'Office', office, '', true)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                        ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                        ${additionalDetailsHtml}
                    `;
                } else {
                    dynamicFieldsHTML = `
                        ${renderDetailTile('bi-person-fill', 'Name', fullName, 'fs-6', true)}
                        ${renderDetailTile('bi-envelope-fill', 'Email', email, '', true)}
                        ${renderDetailTile('bi-gender-ambiguous', 'Sex', sex, '', true)}
                        ${renderDetailTile('bi-display', 'Window Number', windowLabel, '', true)}
                        ${additionalDetailsHtml}
                    `;
                }

                let concernsBlockHtml = `
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

                modalUserDetailsContent.innerHTML = `
                    <div class="row g-3">
                        ${dynamicFieldsHTML}
                        ${concernsBlockHtml}
                        ${renderDetailTile('bi-clock-history', 'Queue Entry Time', entryTimeString, '', false)}
                    </div>
                `;
            }

            function renderCategoryPills(container, list, emptyMsg) {
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
                    return `
                        <div class="ticket-pill-item">
                            <div class="d-flex align-items-center min-w-0">
                                <span class="ticket-pill-pos">${index + 1}</span>
                                <div class="min-w-0">
                                    <span class="ticket-pill-number d-block lh-1">${escapeHtml(item.ticket_number)}</span>
                                </div>
                            </div>
                            <button type="button" class="btn-preview-visitor" onclick="openVisitorDetailsModal(${item.id})" title="View Visitor Information Details" aria-label="View Visitor Information Details">
                                <i class="bi bi-person-bounding-box fs-5"></i>
                            </button>
                        </div>
                    `;
                }).join('');
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
                if (!HAS_ASSIGNMENT || !activeOfficeId || parseInt(activeCounterId, 10) <= 0) return;

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

            // Global Call Next per Category Lane (FIFO)
            window.callNextByCategory = function(category) {
                if (!category) return;
                sendQueueAction('call_next_category', { category: category });
            };

            if (btnStartServe)     btnStartServe.addEventListener('click', () => sendQueueAction('serve_ticket'));
            if (btnCompleteTicket) btnCompleteTicket.addEventListener('click', () => sendQueueAction('complete_ticket'));
            if (btnRecallVoice)    btnRecallVoice.addEventListener('click', () => sendQueueAction('recall_ticket'));
            if (btnSkipTicket)     btnSkipTicket.addEventListener('click', () => sendQueueAction('skip_ticket'));

            if (HAS_ASSIGNMENT && activeOfficeId && parseInt(activeCounterId, 10) > 0) {
                setInterval(loadCounterData, 4000);
            }
        })();
    </script>
</body>
</html>