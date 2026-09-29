<?php
// logbook/index.php
$pageTitle = "Select Client Type";

// Include Header (Auto-loads config.php and starts <body>)
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="portal-bg min-vh-100 py-4 d-flex flex-column justify-content-between">
    
    <!-- MAIN PAGE CONTAINER -->
    <div class="container my-auto py-2">

        <!-- PAGE HEADER TITLE -->
        <div class="text-center mb-5">
            <h1 class="app-title fw-black tracking-tight mb-2">Select Client Type</h1>
        </div>

        <!-- SELECTION CARDS GRID (DIAMOND FROSTED GLASS CARDS) -->
        <div class="row g-4 justify-content-center">
            
            <!-- 1. STUDENT CARD -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card h-100 p-4">
                    <div class="card-body p-0 d-flex flex-column text-center align-items-center justify-content-between">
                        <div>
                            <div class="icon-badge-wrapper my-3">
                                <div class="icon-badge mx-auto">
                                    <i class="bi bi-mortarboard fs-1" aria-hidden="true"></i>
                                </div>
                            </div>
                            <h3 class="fw-extrabold mb-3" style="color: var(--color-text-primary);">Student</h3>
                        </div>
                        <div class="w-100">
                            <a href="<?= BASE_URL; ?>/modules/logbook/student_info.php" class="btn btn-portal btn-oval w-100 py-3 mt-3 shadow-sm" role="button">
                                <span class="btn-label fw-bold">Select Student</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. SCHOOL PERSONNEL CARD -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card h-100 p-4">
                    <div class="card-body p-0 d-flex flex-column text-center align-items-center justify-content-between">
                        <div>
                            <div class="icon-badge-wrapper my-3">
                                <div class="icon-badge mx-auto">
                                    <i class="bi bi-person-badge fs-1" aria-hidden="true"></i>
                                </div>
                            </div>
                            <h3 class="fw-extrabold mb-3" style="color: var(--color-text-primary);">School Personnel</h3>
                        </div>
                        <div class="w-100">
                            <a href="<?= BASE_URL; ?>/modules/logbook/personnel_info.php" class="btn btn-portal btn-oval w-100 py-3 mt-3 shadow-sm" role="button">
                                <span class="btn-label fw-bold">Select Personnel</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. GUEST / VISITOR CARD -->
            <div class="col-md-6 col-lg-4">
                <div class="card custom-card h-100 p-4">
                    <div class="card-body p-0 d-flex flex-column text-center align-items-center justify-content-between">
                        <div>
                            <div class="icon-badge-wrapper my-3">
                                <div class="icon-badge mx-auto">
                                    <i class="bi bi-person fs-1" aria-hidden="true"></i>
                                </div>
                            </div>
                            <h3 class="fw-extrabold mb-3" style="color: var(--color-text-primary);">Guest / Visitor</h3>
                        </div>
                        <div class="w-100">
                            <a href="<?= BASE_URL; ?>/modules/logbook/guest_info.php" class="btn btn-portal btn-oval w-100 py-3 mt-3 shadow-sm" role="button">
                                <span class="btn-label fw-bold">Select Guest</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- IN-PAGE FOOTER CREDIT BLOCK -->
    <div class="text-center py-3 mt-auto">
        <p class="small mb-0 fw-bold" style="color: var(--color-text-muted);">
            &copy; <?= date('Y'); ?> City College of Calamba &bull; All Rights Reserved
        </p>
    </div>

</div>

<?php
// Include Footer
require_once __DIR__ . '/../../includes/footer.php';
?>