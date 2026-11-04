<?php
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
    ['label' => 'Queue Management', 'url' => 'javascript:void(0)'],
    ['label' => 'Service Counter', 'url' => '']
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

        /* BLUE THEME FLIP-CLOCK TIMER BADGE STYLING */
        .digital-timer-card {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            background: linear-gradient(180deg, #1e3a8a 0%, #1d4ed8 100%);
            padding: 0.5rem 1.25rem;
            border-radius: 0.65rem;
            box-shadow: none;
            border: 1.5px solid #3b82f6;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        /* Middle horizontal split crease simulating a flip-clock / calendar page */
        .digital-timer-card::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: rgba(0, 0, 0, 0.35);
            box-shadow: 0 1px 0 rgba(255, 255, 255, 0.25);
            pointer-events: none;
        }

        .digital-timer-card #digitalElapsedTimer {
            font-family: 'Courier New', SFMono-Regular, Consolas, Monaco, monospace;
            font-size: 1.55rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            color: #ffffff;
            position: relative;
            z-index: 1;
        }

        .digital-timer-card i {
            color: #93c5fd !important;
            position: relative;
            z-index: 1;
        }

        /* SLIGHTLY ENLARGED & DIAGONALLY TIPPED TICKET ICON */
        .slant-ticket-icon {
            font-size: 1.15rem;
            display: inline-block;
            transform: rotate(-18deg);
            margin-right: 0.35rem;
            vertical-align: -1px;
            transition: transform 0.2s ease;
        }

        /* HIGHLIGHTED MODERN VISITOR TYPE BADGES */
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
        }

        .visitor-badge-student {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
        }

        .visitor-badge-employee {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
        }

        .visitor-badge-guest {
            background: linear-gradient(135deg, #d97706 0%, #b45309 100%);
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

        /* MODERN RESPONSIVE DETAIL TILES FOR TICKET DETAILS CONTAINER */
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

        .btn-action-lg {
            font-weight: 700;
            padding: 0.85rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-action-lg:hover {
            transform: translateY(-1px);
        }

        .ticket-pill-item {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .ticket-pill-item:hover {
            border-color: var(--kiosk-primary);
            background-color: var(--kiosk-primary-light);
            color: var(--kiosk-primary);
        }

        .controller-subcard {
            border: 1px solid var(--kiosk-border);
            border-radius: 0.875rem;
            background: #ffffff;
            box-shadow: var(--kiosk-shadow-sm);
        }

        .form-select-kiosk {
            border: 1.5px solid #cbd5e1;
            border-radius: 0.65rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.9rem;
        }

        /* FULL-WIDTH DEDICATED TRANSACTION CONCERN CONTAINER */
        .full-width-concern-wrapper {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            width: 100%;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>
        <div class="main-panel">

            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3">

                    <!-- Page Header -->
                    <div class="kiosk-page-header">
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
                                                <?= $assigned_office ? htmlspecialchars($assigned_office['office_name']) . ' (' . htmlspecialchars($assigned_office['office_code']) . ')' : 'Select Active Office'; ?>
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
                                        <?php else: ?>
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
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- CONTROLLER MAIN BODY -->
                                <div class="card-body p-3 p-sm-4">
                                    <div class="d-none" id="controllerMainContent">
                                        
                                        <!-- GRID TOP BAR: STATUS BADGE OVER LEFT HERO COLUMN & TIMER CENTERED OVER RIGHT DETAILS COLUMN -->
                                        <div class="row g-0 align-items-center mb-3">
                                            
                                            <!-- LEFT COLUMN ALIGNED STATUS BADGE (OVER TICKET HERO LEFT) -->
                                            <div class="col-lg-4 col-md-5 d-flex justify-content-center">
                                                <div id="counterStatusContainer" class="status-pill-modern status-on-duty">
                                                    <span id="counterStatusText">ON DUTY</span>
                                                </div>
                                            </div>

                                            <!-- RIGHT COLUMN ALIGNED FLIP-STYLE TIMER (CENTERED OVER QUEUE DETAILS RIGHT) -->
                                            <div class="col-lg-8 col-md-7 d-flex justify-content-center mt-2 mt-md-0">
                                                <div class="digital-timer-card">
                                                    <i class="bi bi-stopwatch fs-5"></i>
                                                    <span id="digitalElapsedTimer">00:00</span>
                                                </div>
                                            </div>

                                        </div>

                                        <!-- TOP SECTION: 2-COLUMN SERVING HERO BOX WITH FULL-WIDTH BOTTOM CONCERN CONTAINER -->
                                        <div class="card serving-hero-card mb-4">
                                            <div class="row g-0">
                                                
                                                <!-- LEFT COLUMN: CENTERED TICKET NUMBER CONTAINER -->
                                                <div class="col-lg-4 col-md-5 ticket-hero-left">
                                                    
                                                    <!-- 1. QUEUE NUMBER HEADER TITLE AT TOP -->
                                                    <div class="text-info text-uppercase fw-bold small tracking-wider mb-2 text-center">
                                                        <i class="bi bi-ticket-perforated-fill slant-ticket-icon"></i>QUEUE NUMBER
                                                    </div>

                                                    <!-- 2. QUEUE NUMBER & PRIORITY TAG TIED CLOSELY TOGETHER -->
                                                    <div class="d-flex flex-column align-items-center my-auto">
                                                        <div class="ticket-hero-number" id="ctrlServingTicket">---</div>
                                                        <div id="ctrlPriorityTag" class="mt-1"></div>
                                                    </div>

                                                    <!-- 3. VISITOR TYPE BADGE AT THE BOTTOM BELOW PRIORITY TAG -->
                                                    <div id="ctrlVisitorTypeTag" class="mt-2"></div>

                                                </div>

                                                <!-- RIGHT COLUMN: DYNAMIC QUEUE DETAILS / EMPTY STATE -->
                                                <div class="col-lg-8 col-md-7 ticket-hero-right" id="ticketDetailsContainer">
                                                    <!-- Dynamically populated via JavaScript -->
                                                </div>

                                            </div>

                                            <!-- FULL-WIDTH DEDICATED TRANSACTION CONCERN CONTAINER (CONSISTENT WITH TICKET DETAILS TILES) -->
                                            <div class="full-width-concern-wrapper d-none" id="fullWidthConcernBox">
                                                <div class="detail-tile d-flex align-items-start">
                                                    <div class="tile-icon flex-shrink-0">
                                                        <i class="bi bi-clipboard-check-fill fs-5"></i>
                                                    </div>
                                                    <div class="min-w-0 flex-grow-1">
                                                        <span class="fw-semibold text-muted d-block mb-1" style="font-size: 0.75rem;">Transaction Concern</span>
                                                        <strong class="text-dark d-block text-break fs-6" id="fullWidthConcernText" style="line-height: 1.35; word-break: break-word;">
                                                            General Concern
                                                        </strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ACTION CONTROLS BAR CONTAINER -->
                                        <div class="card controller-subcard mb-4">
                                            <div class="card-body p-3">
                                                <div class="row g-2 justify-content-center">
                                                    <div class="col-12 col-sm-6 col-md-auto">
                                                        <button type="button" class="btn btn-success btn-action-lg w-100 shadow-sm" id="btnCallNext">
                                                            <i class="bi bi-megaphone-fill fs-5"></i> Call Next
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-auto">
                                                        <button type="button" class="btn btn-primary btn-action-lg w-100 shadow-sm" id="btnStartServe">
                                                            <i class="bi bi-play-circle-fill fs-5"></i> Serve
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-auto">
                                                        <button type="button" class="btn btn-outline-primary btn-action-lg w-100" id="btnRecallVoice">
                                                            <i class="bi bi-arrow-counterclockwise"></i> Recall
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-auto">
                                                        <button type="button" class="btn btn-dark btn-action-lg w-100" id="btnCompleteTicket">
                                                            <i class="bi bi-check-circle-fill"></i> Complete
                                                        </button>
                                                    </div>
                                                    <div class="col-12 col-sm-6 col-md-auto">
                                                        <button type="button" class="btn btn-outline-danger btn-action-lg w-100" id="btnSkipTicket">
                                                            <i class="bi bi-dash-circle"></i> Skip
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- BOTTOM 2-COLUMN LINEUP SECTION -->
                                        <div class="row g-3">
                                            
                                            <!-- COLUMN 1: STUDENTS & GUESTS -->
                                            <div class="col-lg-6">
                                                <div class="card controller-subcard h-100">
                                                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                                                        <h6 class="fw-bold text-dark mb-0">
                                                            <i class="bi bi-people-fill text-primary me-2"></i>Students & Guests Queue
                                                        </h6>
                                                        <span class="badge bg-primary rounded-pill px-3 py-1.5" id="ctrlStudentBadge">0 Waiting</span>
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <div class="d-flex flex-column gap-2" id="ctrlStudentList">
                                                            <div class="text-center py-4 text-muted small">No students currently waiting.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- COLUMN 2: EMPLOYEES -->
                                            <div class="col-lg-6">
                                                <div class="card controller-subcard h-100">
                                                    <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                                                        <h6 class="fw-bold text-dark mb-0">
                                                            <i class="bi bi-person-badge-fill text-info me-2"></i>Employees Queue
                                                        </h6>
                                                        <span class="badge bg-info text-dark rounded-pill px-3 py-1.5" id="ctrlEmployeeBadge">0 Waiting</span>
                                                    </div>
                                                    <div class="card-body p-3">
                                                        <div class="d-flex flex-column gap-2" id="ctrlEmployeeList">
                                                            <div class="text-center py-4 text-muted small">No employees currently waiting.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>

                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <?php include_once ADMIN_FOOTER_PATH; ?>
        </div>
    </div>

    <?php include_once ADMIN_SCRIPT_PATH; ?>

    <script>
        (function() {
            const officeSelect = document.getElementById('officeSelect');
            const counterSelect = document.getElementById('counterSelect');
            
            const controllerMainContent = document.getElementById('controllerMainContent');
            const headerOfficeName = document.getElementById('headerOfficeName');
            const headerCounterName = document.getElementById('headerCounterName');
            
            const counterStatusContainer = document.getElementById('counterStatusContainer');
            const counterStatusText = document.getElementById('counterStatusText');
            
            // Timer UI Elements
            const digitalElapsedTimer = document.getElementById('digitalElapsedTimer');

            // Hero UI elements
            const ctrlServingTicket = document.getElementById('ctrlServingTicket');
            const ctrlPriorityTag = document.getElementById('ctrlPriorityTag');
            const ctrlVisitorTypeTag = document.getElementById('ctrlVisitorTypeTag');
            const ticketDetailsContainer = document.getElementById('ticketDetailsContainer');

            // Full-Width Concern Box
            const fullWidthConcernBox = document.getElementById('fullWidthConcernBox');
            const fullWidthConcernText = document.getElementById('fullWidthConcernText');

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

            // Helper function to convert text to Title Case
            function toTitleCase(str) {
                if (!str) return '';
                return str.toLowerCase().replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });
            }

            // =========================================================================
            // AUTOMATIC WORKSTATION INITIALIZATION
            // =========================================================================
            if (!isSelectDropdown && officeSelect && officeSelect.value) {
                activeOfficeId = officeSelect.value;
                activeCounterId = counterSelect ? counterSelect.value : '0';
                loadCounterData();
            } else if (isSelectDropdown) {
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
                if (!activeOfficeId) {
                    controllerMainContent.classList.add('d-none');
                    return;
                }

                controllerMainContent.classList.remove('d-none');

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

                        if (ticket && ticket.ticket_number) {
                            ctrlServingTicket.textContent = ticket.ticket_number;

                            // Render Priority Badge Directly Below Queue Number
                            if (ticket.is_priority == 1) {
                                ctrlPriorityTag.innerHTML = '<span class="badge bg-warning text-dark fw-bold px-3 py-1.5 border-0"><i class="bi bi-star-fill text-danger me-1"></i>Priority</span>';
                            } else {
                                ctrlPriorityTag.innerHTML = '';
                            }

                            // Render Highlighted Visitor Type Badge at the Bottom
                            const visitorType = (ticket.visitor_type || 'VISITOR').toUpperCase();
                            let visitorBadgeClass = 'visitor-badge-guest';
                            let visitorIcon = 'bi-person-heart';

                            if (visitorType === 'STUDENT') {
                                visitorBadgeClass = 'visitor-badge-student';
                                visitorIcon = 'bi-mortarboard-fill';
                            } else if (visitorType === 'EMPLOYEE') {
                                visitorBadgeClass = 'visitor-badge-employee';
                                visitorIcon = 'bi-person-badge-fill';
                            }

                            ctrlVisitorTypeTag.innerHTML = `<span class="visitor-badge-modern ${visitorBadgeClass}"><i class="bi ${visitorIcon}"></i> ${visitorType}</span>`;

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

                            // Set up Transaction Concern in Full-Width Container below Hero Box
                            const mainConcern = ticket.transaction_name || 'General Concern';
                            const subConcern  = ticket.sub_concern_name || ticket.sub_concern || null;
                            fullWidthConcernText.innerHTML = subConcern ? `${mainConcern} &rsaquo; <span class="fw-semibold">${subConcern}</span>` : mainConcern;
                            fullWidthConcernBox.classList.remove('d-none');

                            // DYNAMIC TIMER SWITCH LOGIC (CALLED vs SERVING)
                            if (ticket.status === 'CALLED' && ticket.called_at) {
                                currentTimerBaseTime = new Date(ticket.called_at);
                                startDynamicTimer();
                            } else if (ticket.status === 'SERVING' && ticket.served_at) {
                                currentTimerBaseTime = new Date(ticket.served_at);
                                startDynamicTimer();
                            } else {
                                stopDynamicTimer();
                                digitalElapsedTimer.textContent = '00:00';
                            }

                        } else {
                            ctrlServingTicket.textContent = '---';
                            ctrlPriorityTag.innerHTML = '';
                            ctrlVisitorTypeTag.innerHTML = '';
                            fullWidthConcernBox.classList.add('d-none');
                            stopDynamicTimer();
                            digitalElapsedTimer.textContent = '00:00';
                            renderEmptyDetailsState();
                        }

                        const waiting = res.data.up_next || [];
                        const students = waiting.filter(t => t.visitor_type !== 'EMPLOYEE');
                        const employees = waiting.filter(t => t.visitor_type === 'EMPLOYEE');

                        ctrlStudentBadge.textContent = `${students.length} Waiting`;
                        ctrlEmployeeBadge.textContent = `${employees.length} Waiting`;

                        renderNumberOnlyPills(ctrlStudentList, students, 'No students currently waiting.');
                        renderNumberOnlyPills(ctrlEmployeeList, employees, 'No employees currently waiting.');
                    }
                })
                .catch(err => {
                    console.error("Counter status error:", err);
                });
            }

            function renderEmptyDetailsState() {
                ticketDetailsContainer.innerHTML = `
                    <div class="text-center py-4 my-auto">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-light text-primary" style="width: 60px; height: 60px; border: 2px dashed #cbd5e1;">
                            <i class="bi bi-ticket-detailed fs-2 text-primary opacity-75"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">No Active Ticket Selected</h6>
                        <p class="small text-muted mb-0">Click <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold px-2 py-1"><i class="bi bi-megaphone-fill me-1"></i>Call Next</span> to proceed with the next visitor in queue.</p>
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

            // Helper to render modular modern detail cards with multi-line text wrapping and responsive layout
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

                ticketDetailsContainer.innerHTML = `
                    <div class="row g-3 my-auto py-1">
                        ${dynamicFieldsHTML}
                        ${renderDetailTile('bi-clock-history', 'Queue Entry Time', entryTimeString)}
                    </div>
                `;
            }

            function renderNumberOnlyPills(container, list, emptyMsg) {
                if (list.length === 0) {
                    container.innerHTML = `<div class="text-center py-4 text-muted small">${emptyMsg}</div>`;
                    return;
                }

                container.innerHTML = list.map(item => `
                    <div class="ticket-pill-item">
                        <span>${item.ticket_number}</span>
                        ${item.is_priority == 1 ? '<span class="badge bg-warning text-dark small fs-7">★ Priority</span>' : ''}
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

            function sendQueueAction(actionName) {
                if (!activeOfficeId) return;

                const formData = new FormData();
                formData.append('action', actionName);
                formData.append('office_id', activeOfficeId);
                formData.append('counter_id', activeCounterId);

                fetch(HANDLER_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        loadCounterData();
                    } else {
                        alert(res.message || 'Failed to process request.');
                    }
                })
                .catch(err => console.error(`Queue Action (${actionName}) Error:`, err));
            }

            btnCallNext.addEventListener('click', () => sendQueueAction('call_next'));
            btnStartServe.addEventListener('click', () => sendQueueAction('serve_ticket'));
            btnRecallVoice.addEventListener('click', () => sendQueueAction('recall_ticket'));
            btnCompleteTicket.addEventListener('click', () => sendQueueAction('complete_ticket'));
            btnSkipTicket.addEventListener('click', () => sendQueueAction('skip_ticket'));

            setInterval(loadCounterData, 4000);
        })();
    </script>
</body>
</html>