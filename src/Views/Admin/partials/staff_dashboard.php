<?php
# Core Config Dependencies
global $db_connect;
$db_connect  = $db_connect ?? null;
$g_user_role = $g_user_role ?? '';

# Page Header Initialization
require_once ADMIN_PAGE_HEADER_PATH;

$title_page   = "Station Admin Dashboard";
$active_links = [
    ['label' => $title_page, 'url' => '']
];
$page_header = render_page_header((string) $title_page, $active_links) ?? '';

# -------------------------------------------------------------------------
# 1. FETCH TODAY'S LOGBOOK & CHECK-IN METRICS
# -------------------------------------------------------------------------
$total_checkins = 0;
$student_count  = 0;
$employee_count = 0;
$guest_count    = 0;
$priority_count = 0;

if ($db_connect) {
    // Breakdown by Visitor Type
    $stmtStats = $db_connect->query("
        SELECT 
            visitor_type,
            COUNT(*) AS total_count
        FROM logbooks 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY visitor_type
    ");
    if ($stmtStats) {
        while ($row = $stmtStats->fetch_assoc()) {
            $type  = strtoupper($row['visitor_type']);
            $count = (int)$row['total_count'];
            $total_checkins += $count;
            if ($type === 'STUDENT') {
                $student_count = $count;
            } else if ($type === 'EMPLOYEE') {
                $employee_count = $count;
            } else if ($type === 'GUEST') {
                $guest_count = $count;
            }
        }
    }

    // Priority Check-ins Today
    $stmtPriority = $db_connect->query("
        SELECT COUNT(*) AS priority_total 
        FROM logbooks 
        WHERE DATE(created_at) = CURDATE() AND is_priority = 1
    ");
    if ($stmtPriority && $pRow = $stmtPriority->fetch_assoc()) {
        $priority_count = (int)$pRow['priority_total'];
    }
}

# -------------------------------------------------------------------------
# 2. FETCH TOP DESTINATION OFFICES TODAY
# -------------------------------------------------------------------------
$top_offices = [];
if ($db_connect) {
    $stmtOffices = $db_connect->query("
        SELECT 
            o.office_name,
            o.office_code,
            COUNT(l.id) AS visitor_count
        FROM logbooks l
        INNER JOIN office o ON l.office_id = o.office_id
        WHERE DATE(l.created_at) = CURDATE()
        GROUP BY o.office_id, o.office_name, o.office_code
        ORDER BY visitor_count DESC
        LIMIT 5
    ");
    if ($stmtOffices) {
        while ($row = $stmtOffices->fetch_assoc()) {
            $top_offices[] = $row;
        }
    }
}

# -------------------------------------------------------------------------
# 3. FETCH RECENT CHECK-IN ENTRIES TODAY
# -------------------------------------------------------------------------
$recent_checkins = [];
if ($db_connect) {
    $stmtRecent = $db_connect->query("
        SELECT 
            l.id,
            l.visitor_type,
            l.first_name,
            l.last_name,
            l.is_priority,
            l.created_at,
            o.office_code,
            o.office_name,
            q.ticket_number,
            COALESCE(q.status, 'WAITING') AS queue_status
        FROM logbooks l
        LEFT JOIN office o ON l.office_id = o.office_id
        LEFT JOIN queue_tickets q ON l.id = q.logbook_id
        WHERE DATE(l.created_at) = CURDATE()
        ORDER BY l.created_at DESC
        LIMIT 10
    ");
    if ($stmtRecent) {
        while ($row = $stmtRecent->fetch_assoc()) {
            $recent_checkins[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once ADMIN_META_DATA_PATH; ?>
    <?php include_once ADMIN_LINK_PATH; ?>
    
    <style>
        .kiosk-shortcut-card {
            border: 2px dashed #cbd5e1;
            border-radius: 1rem;
            background: #ffffff;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            height: 100%;
        }
        .kiosk-shortcut-card:hover {
            border-color: #1d4ed8;
            transform: translateY(-3px);
            box-shadow: 0 12px 20px -5px rgba(29, 78, 216, 0.12);
        }
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
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

                    <!-- PAGE TITLE & MAIN ACTIONS -->
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold text-dark mb-1">Station Admin Dashboard</h3>
                            <p class="text-muted small mb-0">Monitor campus check-ins and quickly launch kiosks or live display screens.</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="<?= BASE_URL; ?>admin/digital-logbook" target="_blank" class="btn btn-primary rounded-3 fw-bold px-3 py-2 shadow-sm">
                                <i class="bi bi-journal-check me-1.5"></i> Launch Kiosk
                            </a>
                        </div>
                    </div>

                    <!-- 1. SUMMARY STAT CARDS -->
                    <div class="row g-3 mb-4">
                        <!-- Total Check-ins -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold d-block">Total Check-ins Today</span>
                                        <h2 class="fw-bold text-dark mb-0 mt-1"><?= number_format($total_checkins); ?></h2>
                                    </div>
                                    <div class="stat-icon-wrapper bg-primary-subtle text-primary">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Students -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold d-block">Students Check-in</span>
                                        <h2 class="fw-bold text-info mb-0 mt-1"><?= number_format($student_count); ?></h2>
                                    </div>
                                    <div class="stat-icon-wrapper bg-info-subtle text-info">
                                        <i class="bi bi-person-vcard"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employees -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold d-block">Employees Check-in</span>
                                        <h2 class="fw-bold text-success mb-0 mt-1"><?= number_format($employee_count); ?></h2>
                                    </div>
                                    <div class="stat-icon-wrapper bg-success-subtle text-success">
                                        <i class="bi bi-person-badge"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Guests & Priority -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <div class="card border-0 shadow-sm rounded-4 h-100 p-3">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold d-block">Guests / Priority</span>
                                        <h2 class="fw-bold text-warning mb-0 mt-1">
                                            <?= number_format($guest_count); ?>
                                            <span class="fs-6 text-muted fw-normal"> (<?= number_format($priority_count); ?> priority)</span>
                                        </h2>
                                    </div>
                                    <div class="stat-icon-wrapper bg-warning-subtle text-warning">
                                        <i class="bi bi-star-fill"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. QUICK LAUNCH KIOSK & MONITOR CONTROLS -->
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm rounded-4 p-4">
                                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-lightning-charge-fill text-warning me-2"></i>Quick Kiosk & Monitor Displays</h5>
                                <div class="row g-3">
                                    <!-- Logbook Kiosk Shortcut -->
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/digital-logbook" target="_blank" class="text-decoration-none">
                                            <div class="kiosk-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-primary text-white">
                                                    <i class="bi bi-journal-plus"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Digital Kiosk</h6>
                                                    <span class="text-muted small">Full-screen check-in kiosk</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Master Queue Monitor -->
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/live-queue?mode=all" target="_blank" class="text-decoration-none">
                                            <div class="kiosk-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-dark text-white">
                                                    <i class="bi bi-display"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Master Queue</h6>
                                                    <span class="text-muted small">Monitor for all offices</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Student & Guest Monitor -->
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/live-queue?mode=student_guest" target="_blank" class="text-decoration-none">
                                            <div class="kiosk-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-info text-white">
                                                    <i class="bi bi-tv"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Students & Guests</h6>
                                                    <span class="text-muted small">Student/Guest queue monitor</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>

                                    <!-- Employee Queue Monitor -->
                                    <div class="col-12 col-md-6 col-xl-3">
                                        <a href="<?= BASE_URL; ?>admin/live-queue?mode=employee" target="_blank" class="text-decoration-none">
                                            <div class="kiosk-shortcut-card p-3 d-flex align-items-center gap-3">
                                                <div class="stat-icon-wrapper bg-success text-white">
                                                    <i class="bi bi-person-workspace"></i>
                                                </div>
                                                <div>
                                                    <h6 class="fw-bold text-dark mb-0">Employees Queue</h6>
                                                    <span class="text-muted small">Dedicated employee display</span>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. RECENT LOGBOOK CHECK-INS & TOP DESTINATIONS -->
                    <div class="row g-3">
                        <!-- Recent Check-ins Table -->
                        <div class="col-12 col-lg-8">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Check-ins Today</h6>
                                    <span class="badge bg-light text-muted border">Latest 10 Entries</span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0" style="font-size: 0.875rem;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-4">Ticket / Visitor</th>
                                                    <th>Category</th>
                                                    <th>Destination Office</th>
                                                    <th>Time</th>
                                                    <th class="pe-4 text-end">Queue Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($recent_checkins)): ?>
                                                    <?php foreach ($recent_checkins as $item): ?>
                                                        <tr>
                                                            <td class="ps-4">
                                                                <div class="fw-bold text-dark">
                                                                    <?= htmlspecialchars($item['ticket_number'] ?? 'N/A'); ?>
                                                                    <?php if (!empty($item['is_priority'])): ?>
                                                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.68rem;">PRIORITY</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="text-muted small"><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></div>
                                                            </td>
                                                            <td>
                                                                <?php 
                                                                    $vType = strtoupper($item['visitor_type']);
                                                                    $badgeClass = ($vType === 'STUDENT') ? 'bg-info-subtle text-info border-info-subtle' : (($vType === 'EMPLOYEE') ? 'bg-success-subtle text-success border-success-subtle' : 'bg-secondary-subtle text-secondary border-secondary-subtle');
                                                                ?>
                                                                <span class="badge border <?= $badgeClass; ?> fw-semibold"><?= $vType; ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="fw-semibold text-dark"><?= htmlspecialchars($item['office_code'] ?? '---'); ?></span>
                                                                <span class="text-muted small d-block"><?= htmlspecialchars($item['office_name'] ?? ''); ?></span>
                                                            </td>
                                                            <td class="text-muted small">
                                                                <?= date('h:i A', strtotime($item['created_at'])); ?>
                                                            </td>
                                                            <td class="pe-4 text-end">
                                                                <?php 
                                                                    $status = strtoupper($item['queue_status']);
                                                                    $stBadge = ($status === 'COMPLETED') ? 'bg-success' : (($status === 'IN_PROGRESS' || $status === 'CALLED') ? 'bg-primary' : 'bg-warning text-dark');
                                                                ?>
                                                                <span class="badge <?= $stBadge; ?> fw-semibold"><?= $status; ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-4 text-muted">No check-in entries recorded today.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Top Destination Offices Today -->
                        <div class="col-12 col-lg-4">
                            <div class="card border-0 shadow-sm rounded-4 h-100">
                                <div class="card-header bg-white border-bottom py-3 px-4">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-building me-2 text-primary"></i>Top Visited Offices Today</h6>
                                </div>
                                <div class="card-body p-3 p-md-4">
                                    <?php if (!empty($top_offices)): ?>
                                        <div class="d-flex flex-column gap-3">
                                            <?php foreach ($top_offices as $off): ?>
                                                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 border">
                                                    <div class="min-w-0 me-2">
                                                        <h6 class="fw-bold text-dark mb-0 text-truncate"><?= htmlspecialchars($off['office_name']); ?></h6>
                                                        <span class="badge bg-light text-primary border mt-1 small"><?= htmlspecialchars($off['office_code']); ?></span>
                                                    </div>
                                                    <div class="text-end flex-shrink-0">
                                                        <h4 class="fw-bold text-primary mb-0"><?= number_format($off['visitor_count']); ?></h4>
                                                        <span class="text-muted small" style="font-size: 0.72rem;">visitors</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4 text-muted">No office visits recorded today.</div>
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

    <?php include_once ADMIN_SCRIPT_PATH; ?>
</body>

</html>