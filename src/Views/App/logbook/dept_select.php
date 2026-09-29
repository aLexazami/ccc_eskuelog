<?php
// dept_select.php - MUST BE AT THE VERY TOP BEFORE HEADER
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store submitted client information in session if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        $_SESSION[$key] = $value;
    }
}

// Determine previous step safely for explicit Back navigation
$clientType = $_SESSION['client_type'] ?? 'student';
$backUrl = 'student_info.php';
if ($clientType === 'personnel') {
    $backUrl = 'personnel_info.php';
} elseif ($clientType === 'guest') {
    $backUrl = 'guest_info.php';
}

$pageTitle = "Select Department";
$pageScript = "/assets/js/kiosk.js";

require_once __DIR__ . '/../../includes/header.php';

// Departments mapped to Admin, Rizal, and JMC Buildings
$departments = [
    // --- ADMIN BUILDING ---
    ['id' => 1, 'name' => 'Admissions Office', 'code' => 'ADM', 'building' => 'Admin Building', 'icon' => 'bi-person-plus'],
    ['id' => 2, 'name' => 'Accounting Office', 'code' => 'ACC', 'building' => 'Admin Building', 'icon' => 'bi-cash-coin'],
    ['id' => 3, 'name' => 'Cashier', 'code' => 'CSH', 'building' => 'Admin Building', 'icon' => 'bi-credit-card'],
    ['id' => 4, 'name' => 'Human Resources', 'code' => 'HR', 'building' => 'Admin Building', 'icon' => 'bi-person-badge'],
    ['id' => 5, 'name' => 'College of Business & Accountancy', 'code' => 'CBA', 'building' => 'Admin Building', 'icon' => 'bi-briefcase'],
    ['id' => 6, 'name' => 'College of Education', 'code' => 'CE', 'building' => 'Admin Building', 'icon' => 'bi-book'],
    ['id' => 7, 'name' => 'MIS / IT Support', 'code' => 'MIS', 'building' => 'Admin Building', 'icon' => 'bi-pc-display'],
    ['id' => 8, 'name' => 'Office of Student Affairs', 'code' => 'OSA', 'building' => 'Admin Building', 'icon' => 'bi-people'],
    ['id' => 9, 'name' => 'Property & Supply Office', 'code' => 'PSO', 'building' => 'Admin Building', 'icon' => 'bi-box-seam'],
    ['id' => 10, 'name' => 'Guidance & Counseling', 'code' => 'GDC', 'building' => 'Admin Building', 'icon' => 'bi-heart-pulse'],
    ['id' => 11, 'name' => 'School Clinic', 'code' => 'CLN', 'building' => 'Admin Building', 'icon' => 'bi-hospital'],
    ['id' => 12, 'name' => "Dean's Office", 'code' => 'DO', 'building' => 'Admin Building', 'icon' => 'bi-building'],

    // --- RIZAL BUILDING ---
    ['id' => 13, 'name' => 'College of Computer Studies', 'code' => 'CCS', 'building' => 'Rizal Building', 'icon' => 'bi-laptop'],
    ['id' => 14, 'name' => 'College of Industrial Technology', 'code' => 'CIT', 'building' => 'Rizal Building', 'icon' => 'bi-gear-wide-connected'],

    // --- JMC BUILDING ---
    ['id' => 15, 'name' => 'Library', 'code' => 'LIB', 'building' => 'JMC Building', 'icon' => 'bi-journal-bookmark'],
    ['id' => 16, 'name' => 'Registrar Office', 'code' => 'REG', 'building' => 'JMC Building', 'icon' => 'bi-file-earmark-text'],
];
?>

<div class="portal-bg py-5 min-vh-100">
    <div class="container py-2">

        <!-- Section Title & Search -->
        <div class="row mb-4 align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <h1 class="fw-black display-6 mb-1" style="color: var(--color-text-primary);">Select Office / Department</h1>
                <p class="mb-0 fw-semibold" style="color: var(--color-text-secondary);">Choose the office or department you need to visit.</p>
            </div>
            
            <!-- Real-time Search Bar -->
            <div class="col-md-6">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3" style="color: var(--color-text-muted);"></i>
                    <input type="text" id="deptSearch" class="form-control custom-glass-control ps-5" placeholder="Search office name or acronym (e.g., CCS, Registrar)...">
                </div>
            </div>
        </div>

        <!-- Filter Building Tabs -->
        <div class="d-flex gap-2 overflow-auto mb-4 pb-2" id="categoryFilters">
            <button class="btn btn-filter active" data-filter="all">All Buildings</button>
            <button class="btn btn-filter" data-filter="Admin Building">Admin Building</button>
            <button class="btn btn-filter" data-filter="Rizal Building">Rizal Building</button>
            <button class="btn btn-filter" data-filter="JMC Building">JMC Building</button>
        </div>

        <!-- Department Cards Grid -->
        <div class="row g-3" id="deptGrid">
            <?php foreach ($departments as $dept): ?>
                <div class="col-6 col-md-4 col-lg-3 dept-item" 
                     data-building="<?= htmlspecialchars($dept['building']) ?>" 
                     data-name="<?= strtolower(htmlspecialchars($dept['name'])) ?>"
                     data-code="<?= strtolower(htmlspecialchars($dept['code'])) ?>">
                     
                    <a href="select_inquiry.php?dept_id=<?= $dept['id'] ?>" class="card dept-card h-100 p-3 text-decoration-none text-center d-flex flex-column align-items-center justify-content-center position-relative">
                        <span class="badge bg-secondary-subtle text-dark position-absolute top-0 end-0 m-2" style="font-size: 0.65rem;">
                            <?= htmlspecialchars($dept['building']) ?>
                        </span>
                        <div class="dept-icon-box mb-3 mt-2">
                            <i class="bi <?= htmlspecialchars($dept['icon']) ?> fs-2"></i>
                        </div>
                        <h6 class="fw-bold mb-1 dept-title" style="color: var(--color-text-primary);"><?= htmlspecialchars($dept['name']) ?></h6>
                        <span class="dept-code-badge"><?= htmlspecialchars($dept['code']) ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results Found State -->
        <div id="noResults" class="text-center py-5 d-none">
            <i class="bi bi-search fs-1 mb-2 d-block" style="color: var(--color-text-muted);"></i>
            <h5 class="fw-bold" style="color: var(--color-text-primary);">No matching offices found</h5>
            <p class="fw-semibold" style="color: var(--color-text-secondary);">Try searching for a different keyword or select another building.</p>
        </div>

    </div>
</div>

<?php
// JS Auto Versioning Injection
$deptSelectJsPath = ROOT_PATH . '/assets/js/dept_select.js';
$deptSelectJsVersion = file_exists($deptSelectJsPath) ? filemtime($deptSelectJsPath) : time();

$extraJs = '<script src="' . $baseUrl . '/assets/js/dept_select.js?v=' . $deptSelectJsVersion . '"></script>';

require_once __DIR__ . '/../../includes/footer.php';
?>