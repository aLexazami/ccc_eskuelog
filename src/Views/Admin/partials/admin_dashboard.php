<?php
// Prevent direct file access
if (!defined('DOMAIN_PATH')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

global $db_connect;

// Helper using connect.php's native mysqli_query_return()
$fetchAll = function (string $sql): array {
    return function_exists('mysqli_query_return') ? mysqli_query_return($sql) : [];
};

$fetchOne = function (string $sql) use ($fetchAll): array {
    $rows = $fetchAll($sql);
    return $rows[0] ?? [];
};

// 1. System Configuration KPIs (flag_status = 0 = active)
$kpiConfig = $fetchOne("
    SELECT
        (SELECT COUNT(*) FROM office WHERE flag_status = 0) AS total_offices,
        (SELECT COUNT(*) FROM counters WHERE flag_status = 0) AS total_counters,
        (SELECT COUNT(*) FROM transaction_types WHERE flag_status = 0) AS total_services,
        (SELECT COUNT(*) FROM transaction_sub_concerns WHERE flag_status = 0) AS total_sub_concerns,
        (SELECT COUNT(*) FROM staff_assignments WHERE flag_status = 0 AND shift_status = 'ON_DUTY') AS on_duty_staff
");

// 2. Today's Operational Queue KPIs
$kpiToday = $fetchOne("
    SELECT
        COUNT(*) AS total_tickets,
        SUM(CASE WHEN status = 'WAITING' THEN 1 ELSE 0 END) AS waiting_count,
        SUM(CASE WHEN status IN ('CALLED', 'SERVING') THEN 1 ELSE 0 END) AS serving_count,
        SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN is_priority = 1 THEN 1 ELSE 0 END) AS priority_count
    FROM queue_tickets
    WHERE queue_date = CURDATE()
");

// 3. Office Operational Overview (Counters, On-Duty Staff, Today's Queue)
$officeOverview = $fetchAll("
    SELECT
        o.office_id,
        o.office_code,
        o.office_name,
        COUNT(DISTINCT c.id) AS counter_count,
        COUNT(DISTINCT CASE WHEN sa.shift_status = 'ON_DUTY' AND sa.flag_status = 0 THEN sa.id END) AS active_staff,
        COUNT(DISTINCT CASE WHEN qt.queue_date = CURDATE() AND qt.status = 'WAITING' THEN qt.id END) AS waiting_today,
        COUNT(DISTINCT CASE WHEN qt.queue_date = CURDATE() AND qt.status = 'COMPLETED' THEN qt.id END) AS completed_today,
        COUNT(DISTINCT CASE WHEN qt.queue_date = CURDATE() THEN qt.id END) AS total_today
    FROM office o
    LEFT JOIN counters c ON c.office_id = o.office_id AND c.flag_status = 0
    LEFT JOIN staff_assignments sa ON sa.office_id = o.office_id
    LEFT JOIN queue_tickets qt ON qt.office_id = o.office_id
    WHERE o.flag_status = 0
    GROUP BY o.office_id, o.office_code, o.office_name
    ORDER BY total_today DESC, o.office_code ASC
");

// 4. Visitor Type Distribution (Today)
$visitorStats = $fetchAll("
    SELECT
        l.visitor_type,
        COUNT(*) AS total
    FROM logbooks l
    INNER JOIN queue_tickets qt ON qt.logbook_id = l.id
    WHERE qt.queue_date = CURDATE()
    GROUP BY l.visitor_type
");

$visitorMap = ['STUDENT' => 0, 'EMPLOYEE' => 0, 'GUEST' => 0];
foreach ($visitorStats as $row) {
    $type = strtoupper($row['visitor_type'] ?? '');
    if (isset($visitorMap[$type])) {
        $visitorMap[$type] = (int) $row['total'];
    }
}

// 5. Recent Queue Transactions (Latest 8)
$recentTickets = $fetchAll("
    SELECT
        qt.ticket_number,
        qt.status,
        qt.is_priority,
        o.office_code,
        l.visitor_type,
        CONCAT(l.first_name, ' ', l.last_name) AS visitor_name,
        tt.transaction_name,
        c.counter_name,
        l.created_at
    FROM queue_tickets qt
    INNER JOIN logbooks l ON l.id = qt.logbook_id
    INNER JOIN office o ON o.office_id = qt.office_id
    LEFT JOIN transaction_types tt ON tt.id = l.transaction_type_id
    LEFT JOIN counters c ON c.id = qt.counter_id
    ORDER BY qt.id DESC
    LIMIT 8
");

// Prepare Chart.js JSON payloads
$chartOfficeLabels = array_column($officeOverview, 'office_code');
$chartOfficeWaiting = array_map('intval', array_column($officeOverview, 'waiting_today'));
$chartOfficeCompleted = array_map('intval', array_column($officeOverview, 'completed_today'));

$adminLayoutDir = SRC_PATH . '/Views/Layouts/Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include $adminLayoutDir . '/meta_data.php'; ?>
    <title>Admin Dashboard | E-ESKUELOG</title>
    <?php include $adminLayoutDir . '/link.php'; ?>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <?php include $adminLayoutDir . '/sidebar.php'; ?>

        <div class="main-panel">
            <!-- Header / Navbar -->
            <?php include $adminLayoutDir . '/header.php'; ?>

            <div class="container">
                <div class="page-inner">
                    <!-- Page Title & Quick Navigation -->
                    <div class="d-flex align-items-left align-items-md-center flex-column flex-md-row pt-2 pb-4">
                        <div>
                            <h3 class="fw-bold mb-1">System Administration Dashboard</h3>
                            <h6 class="op-7 mb-0">Overview of office setups, service configurations, and campus-wide queue operations</h6>
                        </div>
                        <div class="ms-md-auto py-2 py-md-0 d-flex flex-wrap gap-2">
                            <a href="<?php echo BASE_URL . 'admin/office-counters'; ?>" class="btn btn-outline-primary btn-round btn-sm">
                                <i class="bi bi-building-gear me-1"></i> Office Counters
                            </a>
                            <a href="<?php echo BASE_URL . 'admin/transaction-types'; ?>" class="btn btn-outline-primary btn-round btn-sm">
                                <i class="bi bi-card-checklist me-1"></i> Services
                            </a>
                            <a href="<?php echo BASE_URL . 'admin/reports-analytics'; ?>" class="btn btn-primary btn-round btn-sm">
                                <i class="bi bi-bar-chart-line-fill me-1"></i> Reports & Analytics
                            </a>
                        </div>
                    </div>

                    <!-- Top KPI Cards Row -->
                    <div class="row">
                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                                <i class="bi bi-building"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Offices & Counters</p>
                                                <h4 class="card-title">
                                                    <?php echo (int) ($kpiConfig['total_offices'] ?? 0); ?>
                                                    <small class="text-muted fs-6 fw-normal">
                                                        (<?php echo (int) ($kpiConfig['total_counters'] ?? 0); ?> windows)
                                                    </small>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                                <i class="bi bi-list-check"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Services & Concerns</p>
                                                <h4 class="card-title">
                                                    <?php echo (int) ($kpiConfig['total_services'] ?? 0); ?>
                                                    <small class="text-muted fs-6 fw-normal">
                                                        (<?php echo (int) ($kpiConfig['total_sub_concerns'] ?? 0); ?> sub)
                                                    </small>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                                <i class="bi bi-ticket-perforated"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">Tickets Today</p>
                                                <h4 class="card-title">
                                                    <?php echo (int) ($kpiToday['total_tickets'] ?? 0); ?>
                                                    <small class="text-success fs-6 fw-normal">
                                                        (<?php echo (int) ($kpiToday['completed_count'] ?? 0); ?> done)
                                                    </small>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-md-3">
                            <div class="card card-stats card-round">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                        <div class="col-icon">
                                            <div class="icon-big text-center icon-secondary bubble-shadow-small">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                        </div>
                                        <div class="col col-stats ms-3 ms-sm-0">
                                            <div class="numbers">
                                                <p class="card-category">On-Duty Staff</p>
                                                <h4 class="card-title">
                                                    <?php echo (int) ($kpiConfig['on_duty_staff'] ?? 0); ?>
                                                    <small class="text-warning fs-6 fw-normal">
                                                        (<?php echo (int) ($kpiToday['waiting_count'] ?? 0); ?> waiting)
                                                    </small>
                                                </h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Row -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card card-round">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="card-title">Today's Queue Volume by Office</div>
                                    <span class="badge badge-primary"><?php echo date('F d, Y'); ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="min-height: 280px;">
                                        <canvas id="officeQueueChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card card-round">
                                <div class="card-header">
                                    <div class="card-title">Visitors by Type (Today)</div>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="min-height: 220px;">
                                        <canvas id="visitorTypeChart"></canvas>
                                    </div>
                                    <div class="mt-3 d-flex justify-content-around text-center border-top pt-2">
                                        <div>
                                            <span class="text-muted d-block small">Students</span>
                                            <span class="fw-bold"><?php echo $visitorMap['STUDENT']; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-muted d-block small">Employees</span>
                                            <span class="fw-bold"><?php echo $visitorMap['EMPLOYEE']; ?></span>
                                        </div>
                                        <div>
                                            <span class="text-muted d-block small">Guests</span>
                                            <span class="fw-bold"><?php echo $visitorMap['GUEST']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Office Readiness & Recent Transactions Row -->
                    <div class="row">
                        <!-- Office & Counter Readiness Table -->
                        <div class="col-lg-6">
                            <div class="card card-round">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="card-title">Office & Counter Readiness</div>
                                    <a href="<?php echo BASE_URL . 'admin/office-counters'; ?>" class="btn btn-sm btn-link">
                                        Configure <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table align-items-center mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Office</th>
                                                    <th class="text-center">Counters</th>
                                                    <th class="text-center">On-Duty</th>
                                                    <th class="text-center">Waiting</th>
                                                    <th class="text-center">Completed</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($officeOverview)): ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted py-4">No active offices configured.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($officeOverview as $office): ?>
                                                        <tr>
                                                            <td>
                                                                <span class="fw-bold text-primary"><?php echo htmlspecialchars($office['office_code']); ?></span>
                                                                <div class="small text-muted text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($office['office_name']); ?>">
                                                                    <?php echo htmlspecialchars($office['office_name']); ?>
                                                                </div>
                                                            </td>
                                                            <td class="text-center"><?php echo (int) $office['counter_count']; ?></td>
                                                            <td class="text-center">
                                                                <?php if ((int) $office['active_staff'] > 0): ?>
                                                                    <span class="badge badge-success"><?php echo (int) $office['active_staff']; ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-light text-muted">0</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="text-center fw-bold text-warning"><?php echo (int) $office['waiting_today']; ?></td>
                                                            <td class="text-center fw-bold text-success"><?php echo (int) $office['completed_today']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent System Transactions -->
                        <div class="col-lg-6">
                            <div class="card card-round">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="card-title">Recent Queue Transactions</div>
                                    <a href="<?php echo BASE_URL . 'admin/transaction-records'; ?>" class="btn btn-sm btn-link">
                                        View All <i class="bi bi-arrow-right"></i>
                                    </a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table align-items-center mb-0">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Ticket</th>
                                                    <th>Visitor</th>
                                                    <th>Transaction</th>
                                                    <th class="text-end">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentTickets)): ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted py-4">No transaction records found.</td>
                                                    </tr>
                                                <?php else: ?>
                                                    <?php foreach ($recentTickets as $ticket): ?>
                                                        <?php
                                                        $statusBadge = match ($ticket['status']) {
                                                            'WAITING'   => 'badge-warning',
                                                            'CALLED'    => 'badge-info',
                                                            'SERVING'   => 'badge-primary',
                                                            'COMPLETED' => 'badge-success',
                                                            'SKIPPED', 'CANCELLED' => 'badge-danger',
                                                            default     => 'badge-secondary'
                                                        };
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <span class="fw-bold"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
                                                                <?php if ((int) $ticket['is_priority'] === 1): ?>
                                                                    <span class="badge badge-danger ms-1" title="Priority Lane">P</span>
                                                                <?php endif; ?>
                                                                <div class="small text-muted"><?php echo htmlspecialchars($ticket['office_code']); ?></div>
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($ticket['visitor_name']); ?></div>
                                                                <span class="small text-muted"><?php echo htmlspecialchars($ticket['visitor_type']); ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="small text-truncate" style="max-width: 170px;" title="<?php echo htmlspecialchars($ticket['transaction_name'] ?? 'General'); ?>">
                                                                    <?php echo htmlspecialchars($ticket['transaction_name'] ?? 'General Inquiry'); ?>
                                                                </div>
                                                                <span class="small text-muted">
                                                                    <?php echo date('M d, h:i A', strtotime($ticket['created_at'])); ?>
                                                                </span>
                                                            </td>
                                                            <td class="text-end">
                                                                <span class="badge <?php echo $statusBadge; ?>">
                                                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <?php include $adminLayoutDir . '/footer.php'; ?>
        </div>
    </div>

    <!-- Scripts -->
    <?php include $adminLayoutDir . '/script.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const officeCtx = document.getElementById('officeQueueChart');
            if (officeCtx && typeof Chart !== 'undefined') {
                new Chart(officeCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chartOfficeLabels); ?>,
                        datasets: [
                            {
                                label: 'Waiting',
                                backgroundColor: '#ffad46',
                                borderColor: '#ffad46',
                                data: <?php echo json_encode($chartOfficeWaiting); ?>
                            },
                            {
                                label: 'Completed',
                                backgroundColor: '#31ce36',
                                borderColor: '#31ce36',
                                data: <?php echo json_encode($chartOfficeCompleted); ?>
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: false },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });
            }

            const visitorCtx = document.getElementById('visitorTypeChart');
            if (visitorCtx && typeof Chart !== 'undefined') {
                new Chart(visitorCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Students', 'Employees', 'Guests'],
                        datasets: [{
                            data: [
                                <?php echo $visitorMap['STUDENT']; ?>,
                                <?php echo $visitorMap['EMPLOYEE']; ?>,
                                <?php echo $visitorMap['GUEST']; ?>
                            ],
                            backgroundColor: ['#1572e8', '#6861ce', '#f25961'],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>