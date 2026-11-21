<?php
// src/Views/Admin/partials/admin_dashboard.php
global $db_connect, $session_class, $g_user_role, $g_user_id;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Header Initialization
require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "System Administrator Dashboard";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# Resolve Logged-In Administrator First Name & Active Role (from config.php SYSTEM_ACCESS)
$current_user_id = (int) ($g_user_id ?? 0);
if ($current_user_id <= 0 && isset($session_class) && method_exists($session_class, 'getValue')) {
    $current_user_id = (int) (
        $session_class->getValue('user_id') ??
        $session_class->getValue('id') ??
        $session_class->getValue('sys_user_id') ??
        0
    );
}
if ($current_user_id <= 0) {
    $current_user_id = (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['sys_user_id'] ?? 0);
}

$role_map         = SYSTEM_ACCESS[SYSTEM_ACCESS_NAME]['role'] ?? [];
$reverse_role_map = array_map('intval', array_flip($role_map));

$raw_role = (string) ($g_user_role ?? '');
if ($raw_role === '' && isset($session_class) && method_exists($session_class, 'getValue')) {
    $raw_role = (string) (
        $session_class->getValue('user_role') ??
        $session_class->getValue('system_role') ??
        ''
    );
}
if ($raw_role === '') {
    $raw_role = (string) ($_SESSION['user_role'] ?? $_SESSION['role'] ?? '');
}

$raw_role_upper = strtoupper(trim($raw_role));
$active_role_id = isset($role_map[$raw_role_upper])
    ? (int) $raw_role_upper
    : ($reverse_role_map[$raw_role_upper] ?? (int) (isset($session_class) && method_exists($session_class, 'getValue') ? ($session_class->getValue('user_role_id') ?? 0) : 0));

$admin_first_name = 'Administrator';
if ($db_connect && $current_user_id > 0) {
    $stmtUser = $db_connect->prepare("SELECT first_name FROM users WHERE id = ? AND flag_status = 0 LIMIT 1");
    if ($stmtUser) {
        $stmtUser->bind_param("i", $current_user_id);
        $stmtUser->execute();
        if ($uRow = $stmtUser->get_result()->fetch_assoc()) {
            $rawFirstName = trim((string) ($uRow['first_name'] ?? ''));
            if ($rawFirstName !== '') {
                $admin_first_name = ucwords(strtolower($rawFirstName));
            }
        }
        $stmtUser->close();
    }

    // Verify active role against system_access table
    $sysName = SYSTEM_ACCESS_NAME;
    if ($active_role_id > 0) {
        $stmtRole = $db_connect->prepare("
            SELECT system_role
            FROM system_access
            WHERE user_id = ? AND system_type = ? AND system_role = ? AND flag_access = 0
            LIMIT 1
        ");
        if ($stmtRole) {
            $stmtRole->bind_param("isi", $current_user_id, $sysName, $active_role_id);
            $stmtRole->execute();
            if ($roleRow = $stmtRole->get_result()->fetch_assoc()) {
                $active_role_id = (int) $roleRow['system_role'];
            }
            $stmtRole->close();
        }
    } else {
        $stmtRole = $db_connect->prepare("
            SELECT system_role
            FROM system_access
            WHERE user_id = ? AND system_type = ? AND flag_access = 0
            ORDER BY system_role ASC
            LIMIT 1
        ");
        if ($stmtRole) {
            $stmtRole->bind_param("is", $current_user_id, $sysName);
            $stmtRole->execute();
            if ($roleRow = $stmtRole->get_result()->fetch_assoc()) {
                $active_role_id = (int) $roleRow['system_role'];
            }
            $stmtRole->close();
        }
    }
}

$resolved_role_code = $role_map[(string) $active_role_id] ?? ($raw_role_upper !== '' ? $raw_role_upper : 'USER');
$display_role_label = (defined('ACCESS_NAME') && isset(ACCESS_NAME[$resolved_role_code]))
    ? ACCESS_NAME[$resolved_role_code]
    : ucwords(strtolower(str_replace('_', ' ', $resolved_role_code)));

$today_date = defined('DATE_NOW') ? DATE_NOW : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="<?= LANG; ?>">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>

    <style>
        /* E-ESKUELOG | System Administrator Dashboard */
        .admin-dashboard {
            --sd-ink: #172b4d;
            --sd-muted: #718096;
            --sd-line: #e8edf4;
            --sd-blue: #2457c5;
            padding-bottom: 2rem;
            color: var(--sd-ink);
        }
        .admin-dashboard .card {
            border: 1px solid var(--sd-line) !important;
            border-radius: 1rem !important;
            box-shadow: 0 4px 16px rgba(27,46,77,.045) !important;
            transition: box-shadow .18s ease, transform .18s ease;
        }
        .admin-dashboard .card-header {
            background: #fff;
            border-color: var(--sd-line) !important;
            border-radius: 1rem 1rem 0 0 !important;
        }

        /* Live AJAX Status Pill */
        .admin-dashboard .live-sync-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 999px;
            padding: 0.3rem 0.75rem;
            font-size: 0.78rem;
            color: #475569;
            font-weight: 600;
        }
        .admin-dashboard .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #16a34a;
            box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.55);
            animation: livePulse 2s infinite;
        }
        .admin-dashboard .live-dot.is-syncing {
            background: #2563eb;
        }
        @keyframes livePulse {
            0%   { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.55); }
            70%  { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
            100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }

        /* Daily KPI Metric Cards (aligned with reports_analytics.php) */
        .admin-dashboard .kpi-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 0.95rem;
            padding: 1.15rem;
            box-shadow: 0 4px 14px rgba(27, 46, 77, 0.035);
            transition: all 0.2s ease;
            height: 100%;
        }
        .admin-dashboard .kpi-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 6px 16px rgba(27, 46, 77, 0.06);
        }
        .admin-dashboard .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .admin-dashboard .kpi-icon.primary { background: #eff6ff; color: #2563eb; }
        .admin-dashboard .kpi-icon.success { background: #f0fdf4; color: #16a34a; }
        .admin-dashboard .kpi-icon.warning { background: #fefce8; color: #ca8a04; }
        .admin-dashboard .kpi-icon.danger  { background: #fef2f2; color: #dc2626; }

        /* 6 Square Quick-Navigation Wrapper Background Design */
        .admin-dashboard .quick-nav-wrapper {
            position: relative;
            overflow: hidden;
            background: radial-gradient(circle at 95% 15%, rgba(36, 87, 197, 0.12), transparent 40%),
                        linear-gradient(120deg, #ffffff 0%, #f4f8ff 65%, #eaf1ff 100%);
            border: 1px solid #dbe6f8;
            border-radius: 1.15rem;
            padding: 1.25rem;
            box-shadow: 0 4px 16px rgba(27, 46, 77, 0.045);
        }
        .admin-dashboard .quick-nav-wrapper::after {
            content: "";
            position: absolute;
            width: 140px;
            height: 140px;
            right: -45px;
            bottom: -75px;
            border: 18px solid rgba(36, 87, 197, 0.06);
            border-radius: 50%;
            pointer-events: none;
        }

        /* 6 Compact Square Shortcut Cards */
        .admin-dashboard .square-shortcut-card {
            aspect-ratio: 1 / 1;
            width: 100%;
            max-width: 195px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid var(--sd-line);
            border-radius: 1rem;
            box-shadow: 0 3px 12px rgba(27, 46, 77, 0.04);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1rem;
            gap: 0.75rem;
            transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
        }
        .admin-dashboard .square-shortcut-card:hover {
            border-color: #b8cafa;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(36, 87, 197, 0.1);
        }
        .admin-dashboard .square-shortcut-icon {
            width: 54px;
            height: 54px;
            border-radius: 0.95rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.65rem;
            flex-shrink: 0;
        }
        .admin-dashboard .square-shortcut-title {
            color: var(--sd-ink);
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.25;
            margin: 0;
        }

        /* Chart Containers & Horizontal Scroll Wrapper for >12 Offices */
        .admin-dashboard .chart-container {
            position: relative;
            min-height: 300px;
            width: 100%;
        }
        .admin-dashboard .chart-scroll-wrapper {
            width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 0.35rem;
        }
        .admin-dashboard .chart-scroll-wrapper::-webkit-scrollbar {
            height: 7px;
        }
        .admin-dashboard .chart-scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 999px;
        }
        .admin-dashboard .chart-scroll-wrapper::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 999px;
        }
        .admin-dashboard .chart-scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .admin-dashboard .chart-scroll-inner {
            position: relative;
            height: 300px;
            min-width: 100%;
        }
        .admin-dashboard .chart-empty-state {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 0.75rem;
            color: #64748b;
            padding: 1.5rem;
            z-index: 2;
        }
        .admin-dashboard .chart-empty-state i {
            font-size: 2rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        .admin-dashboard .chart-empty-state .empty-title {
            font-weight: 600;
            font-size: 0.9rem;
            color: #475569;
        }
        .admin-dashboard .chart-empty-state .empty-sub {
            font-size: 0.78rem;
            color: #94a3b8;
            margin-top: 0.15rem;
        }

        .admin-dashboard .section-heading {
            color: var(--sd-ink);
            font-size: .96rem;
            font-weight: 750;
            letter-spacing: -.015em;
        }
        .admin-dashboard .text-muted {
            color: var(--sd-muted) !important;
        }
        @media (max-width: 767.98px) {
            .admin-dashboard .square-shortcut-card {
                max-width: 165px;
                padding: 0.85rem;
                gap: 0.6rem;
            }
            .admin-dashboard .square-shortcut-icon {
                width: 46px;
                height: 46px;
                font-size: 1.4rem;
            }
            .admin-dashboard .square-shortcut-title {
                font-size: 0.84rem;
            }
            .admin-dashboard .card-header {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .admin-dashboard .card,
            .admin-dashboard .kpi-card,
            .admin-dashboard .square-shortcut-card,
            .admin-dashboard .live-dot {
                transition: none;
                animation: none;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <?php include_once ADMIN_SIDEBAR_PATH; ?>

        <div class="main-panel">
            <?php include_once ADMIN_HEADER_PATH; ?>

            <div class="container">
                <div class="page-inner px-2 px-sm-3 admin-dashboard">

                    <!-- SYSTEM ADMINISTRATOR GREETING & LIVE AJAX STATUS -->
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-start border-4 border-primary ps-3 py-1 mb-4">
                        <div>
                            <h1 class="fw-bold mb-1" style="color: #1e293b; font-size: 2.15rem; letter-spacing: -0.02em;">
                                Welcome, <span class="text-primary"><?= htmlspecialchars($admin_first_name); ?></span>!
                            </h1>
                            <p class="text-muted mb-0" style="font-size: 0.95rem;">
                                <i class="bi bi-shield-check text-primary me-1"></i>
                                You are logged in as <span class="fw-semibold text-dark"><?= htmlspecialchars($display_role_label); ?></span>
                            </p>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <span class="live-sync-pill" id="liveSyncStatus" title="Automatically updates via AJAX every 10 seconds">
                                <span class="live-dot" id="liveSyncDot"></span>
                                <span id="liveSyncText">Live Sync</span>
                            </span>
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="btnManualRefresh" title="Refresh Now">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>

                    <!-- DAILY KPI CARDS (Mirrors reports_analytics.php) -->
                    <div class="row g-3 mb-4">
                        <?php
                        $kpi_cards = [
                            ['kpiTotalQueue',    'Total Queue',      'primary', 'bi-ticket-perforated-fill', '0'],
                            ['kpiCompleted',     'Completed',        'success', 'bi-check-circle-fill',      '0'],
                            ['kpiSkipped',       'Skipped / Missed', 'danger',  'bi-x-circle-fill',          '0'],
                            ['kpiPriorityQueue', 'Priority Queue',   'warning', 'bi-star-fill',              '0'],
                        ];
                        foreach ($kpi_cards as [$id, $label, $tone, $icon, $default]): ?>
                            <div class="col-6 col-xl-3">
                                <div class="kpi-card d-flex align-items-center">
                                    <div class="kpi-icon <?= $tone; ?> flex-shrink-0 me-3">
                                        <i class="bi <?= $icon; ?>"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="fw-semibold text-muted small d-block mb-1"><?= $label; ?></span>
                                        <h4 class="fw-bold text-dark mb-0 font-monospace" id="<?= $id; ?>"><?= $default; ?></h4>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 6 SQUARE QUICK-NAVIGATION CARDS -->
                    <div class="quick-nav-wrapper mb-4">
                        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-3 position-relative" style="z-index: 1;">
                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/office-counters" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-primary-subtle text-primary">
                                            <i class="bi bi-building-gear"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">Office Counters</h5>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/transaction-types" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-success-subtle text-success">
                                            <i class="bi bi-card-checklist"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">Transaction Services</h5>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/transaction-records" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-info-subtle text-info">
                                            <i class="bi bi-folder-check"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">Transaction Records</h5>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/reports-analytics" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-warning-subtle text-warning">
                                            <i class="bi bi-bar-chart-line-fill"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">Report</h5>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/activity-log" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-danger-subtle text-danger">
                                            <i class="bi bi-clock-history"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">Activity Log</h5>
                                    </div>
                                </a>
                            </div>

                            <div class="col">
                                <a href="<?= BASE_URL; ?>admin/user-log" class="text-decoration-none d-block h-100">
                                    <div class="square-shortcut-card">
                                        <div class="square-shortcut-icon bg-secondary-subtle text-secondary">
                                            <i class="bi bi-person-lines-fill"></i>
                                        </div>
                                        <h5 class="square-shortcut-title">User Log</h5>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- REPORTS & ANALYTICS SUMMARY (2 COLUMNS) -->
                    <div class="row g-4">
                        <!-- Left Column: Queue Volume by Office (Horizontal Scroll when >12 offices) -->
                        <div class="col-12 col-lg-8">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="section-heading mb-0">
                                            <i class="bi bi-building me-2 text-primary"></i>Queue Volume by Office
                                        </h6>
                                        <span class="badge bg-light text-muted border small d-none" id="officeScrollHint">
                                            <i class="bi bi-arrows-expand-vertical me-1" style="transform: rotate(90deg); display:inline-block;"></i>Scroll horizontally
                                        </span>
                                    </div>
                                    <a href="<?= BASE_URL; ?>admin/reports-analytics" class="btn btn-sm btn-outline-secondary">
                                        Full Analytics
                                    </a>
                                </div>
                                <div class="card-body p-3 p-sm-4">
                                    <div class="chart-container" id="officeChartContainer">
                                        <div class="chart-scroll-wrapper" id="officeChartScrollWrapper">
                                            <div class="chart-scroll-inner" id="officeChartScrollInner">
                                                <canvas id="officeVolumeChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Visitor Distribution (Student, Employee, Guest) -->
                        <div class="col-12 col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                                    <h6 class="section-heading mb-0">
                                        <i class="bi bi-pie-chart-fill me-2 text-primary"></i>Visitor Distribution
                                    </h6>
                                    <span class="badge bg-light text-muted border small">Today</span>
                                </div>
                                <div class="card-body p-3 p-sm-4">
                                    <div class="chart-container" id="visitorChartContainer">
                                        <canvas id="visitorTypeChart"></canvas>
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
            const HANDLER_URL       = '<?= BASE_URL; ?>admin/ajax/reports-analytics';
            const POLL_INTERVAL_MS  = 10000; // Auto-refresh via AJAX every 10 seconds
            const MAX_VISIBLE_BARS  = 12;    // Enable horizontal scroll if > 12 offices
            const PX_PER_OFFICE_BAR = 68;    // Minimum width per office group when scrolling

            const charts = {};
            let pollTimer = null;
            let isFetching = false;
            let lastPayloadHash = '';

            const escapeHtml = (s) => String(s ?? '')
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

            const getTodayDate = () => {
                const now = new Date();
                return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
            };

            function setSyncIndicator(state, label) {
                const dot  = document.getElementById('liveSyncDot');
                const text = document.getElementById('liveSyncText');
                if (dot)  dot.classList.toggle('is-syncing', state === 'syncing');
                if (text && label) text.textContent = label;
            }

            function renderKPIs(k) {
                k = k || {};
                const totalEl     = document.getElementById('kpiTotalQueue');
                const completedEl = document.getElementById('kpiCompleted');
                const skippedEl   = document.getElementById('kpiSkipped');
                const priorityEl  = document.getElementById('kpiPriorityQueue');

                if (totalEl)     totalEl.textContent     = (parseInt(k.total_tickets, 10) || 0).toLocaleString();
                if (completedEl) completedEl.textContent = (parseInt(k.completed_tickets, 10) || 0).toLocaleString();
                if (skippedEl)   skippedEl.textContent   = (parseInt(k.skipped_tickets, 10) || 0).toLocaleString();
                if (priorityEl)  priorityEl.textContent  = (parseInt(k.priority_tickets, 10) || 0).toLocaleString();
            }

            function drawChart(key, canvasId, config) {
                if (charts[key]) charts[key].destroy();
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                charts[key] = new Chart(canvas.getContext('2d'), config);
            }

            function toggleChartEmptyState(key, outerContainerId, toggleTargetEl, isEmpty, title = 'No data available', subtitle = 'No records found for today.') {
                const outerContainer = document.getElementById(outerContainerId);
                if (!outerContainer || !toggleTargetEl) return;
                let emptyEl = outerContainer.querySelector('.chart-empty-state');

                if (isEmpty) {
                    if (charts[key]) {
                        charts[key].destroy();
                        delete charts[key];
                    }
                    toggleTargetEl.style.display = 'none';
                    if (!emptyEl) {
                        emptyEl = document.createElement('div');
                        emptyEl.className = 'chart-empty-state';
                        outerContainer.appendChild(emptyEl);
                    }
                    emptyEl.innerHTML = `
                        <i class="bi bi-bar-chart-line"></i>
                        <div class="empty-title">${escapeHtml(title)}</div>
                        <div class="empty-sub">${escapeHtml(subtitle)}</div>
                    `;
                } else {
                    if (emptyEl) emptyEl.remove();
                    toggleTargetEl.style.display = 'block';
                }
            }

            function renderOfficeComparison(rows) {
                const items         = (rows || []).filter(r => (parseInt(r.total_tickets, 10) || 0) > 0);
                const hasData       = items.length > 0;
                const scrollWrapper = document.getElementById('officeChartScrollWrapper');
                const scrollInner   = document.getElementById('officeChartScrollInner');
                const scrollHint    = document.getElementById('officeScrollHint');

                toggleChartEmptyState(
                    'office',
                    'officeChartContainer',
                    scrollWrapper,
                    !hasData,
                    'No office queue volume recorded',
                    'No queue tickets were issued across offices today.'
                );

                if (!hasData) {
                    if (scrollHint) scrollHint.classList.add('d-none');
                    return;
                }

                // Dynamically expand chart width and enable horizontal scrollbar if > 12 offices
                const needsHorizontalScroll = items.length > MAX_VISIBLE_BARS;
                if (scrollInner) {
                    scrollInner.style.width = needsHorizontalScroll
                        ? `${items.length * PX_PER_OFFICE_BAR}px`
                        : '100%';
                }
                if (scrollHint) {
                    scrollHint.classList.toggle('d-none', !needsHorizontalScroll);
                }

                drawChart('office', 'officeVolumeChart', {
                    type: 'bar',
                    data: {
                        labels: items.map(o => o.office_code || o.office_name),
                        datasets: [
                            { label: 'Completed', data: items.map(o => o.total_completed || 0), backgroundColor: '#16a34a' },
                            { label: 'Skipped',   data: items.map(o => o.total_skipped || 0),   backgroundColor: '#dc2626' },
                            { label: 'Total',     data: items.map(o => o.total_tickets || 0),   backgroundColor: '#2563eb' }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        scales: {
                            xAxes: [{
                                ticks: {
                                    autoSkip: false,
                                    maxRotation: 0,
                                    minRotation: 0
                                }
                            }],
                            yAxes: [{
                                ticks: { beginAtZero: true, precision: 0 }
                            }]
                        }
                    }
                });
            }

            function renderVisitorType(data) {
                const counts = { Student: 0, Employee: 0, Guest: 0 };
                (data || []).forEach(d => {
                    const key = String(d.type || '').trim().toLowerCase();
                    if (key === 'student') counts.Student = parseInt(d.total || 0, 10);
                    else if (key === 'employee') counts.Employee = parseInt(d.total || 0, 10);
                    else if (key === 'guest') counts.Guest = parseInt(d.total || 0, 10);
                });

                const labels  = ['Student', 'Employee', 'Guest'];
                const values  = [counts.Student, counts.Employee, counts.Guest];
                const hasData = values.some(v => v > 0);
                const canvas  = document.getElementById('visitorTypeChart');

                toggleChartEmptyState(
                    'visitor',
                    'visitorChartContainer',
                    canvas,
                    !hasData,
                    'No visitor records found',
                    'No student, employee, or guest activity recorded today.'
                );
                if (!hasData) return;

                drawChart('visitor', 'visitorTypeChart', {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: values,
                            backgroundColor: ['#2563eb', '#7c3aed', '#16a34a'],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        legend: { position: 'bottom' }
                    }
                });
            }

            function loadDashboardAnalytics(forceRender = false) {
                if (isFetching) return;
                isFetching = true;
                setSyncIndicator('syncing', 'Syncing...');

                const today = getTodayDate();
                const fd = new FormData();
                fd.append('action', 'fetch_analytics');
                fd.append('office_id', '0');
                fd.append('counter_id', '0');
                fd.append('visitor_type', '');
                fd.append('date_from', today);
                fd.append('date_to', today);

                fetch(HANDLER_URL, {
                    method: 'POST',
                    body: fd,
                    cache: 'no-store'
                })
                    .then(res => res.json())
                    .then(res => {
                        if (!(res.success && res.data)) {
                            if (forceRender) {
                                renderKPIs({});
                                renderOfficeComparison([]);
                                renderVisitorType([]);
                            }
                            return;
                        }

                        // Only re-render charts if underlying data changed (prevents unnecessary chart flicker)
                        const payloadHash = JSON.stringify({
                            kpi: res.data.kpi || {},
                            office: res.data.office_breakdown || [],
                            visitor: res.data.visitor_distribution || []
                        });

                        if (forceRender || payloadHash !== lastPayloadHash) {
                            lastPayloadHash = payloadHash;
                            renderKPIs(res.data.kpi);
                            renderOfficeComparison(res.data.office_breakdown || []);
                            renderVisitorType(res.data.visitor_distribution || []);
                        }

                        const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        setSyncIndicator('idle', `Live • ${timeStr}`);
                    })
                    .catch(() => {
                        setSyncIndicator('idle', 'Retrying...');
                    })
                    .finally(() => {
                        isFetching = false;
                    });
            }

            function startPolling() {
                stopPolling();
                pollTimer = setInterval(() => {
                    if (!document.hidden) {
                        loadDashboardAnalytics(false);
                    }
                }, POLL_INTERVAL_MS);
            }

            function stopPolling() {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            }

            // Pause polling when tab is hidden; immediately refresh when returning to tab
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopPolling();
                } else {
                    loadDashboardAnalytics(false);
                    startPolling();
                }
            });

            // Manual instant refresh button
            document.getElementById('btnManualRefresh')?.addEventListener('click', () => {
                loadDashboardAnalytics(true);
            });

            const bootDashboard = () => {
                loadDashboardAnalytics(true);
                startPolling();
            };

            if (document.readyState === 'complete') {
                bootDashboard();
            } else {
                window.addEventListener('load', bootDashboard);
            }
        })();
    </script>
</body>

</html>