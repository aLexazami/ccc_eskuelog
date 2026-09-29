<?php
// student_info.php
$pageTitle = "Student Information";
$pageScript = "/assets/js/kiosk.js";

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="portal-bg py-5 min-vh-100">
    <div class="container py-2">

        <!-- MAIN FORM CARD CONTAINER -->
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card custom-card p-4 p-md-5">
                    
                    <!-- TOP CATEGORY BADGE & TITLE -->
                    <div class="mb-4 text-center text-md-start">
                        <span class="category-badge mb-3">STUDENT</span>
                        <h1 class="fw-black mb-2" style="color: var(--color-text-primary);">Student Information</h1>
                        <p class="mb-0 fw-semibold" style="color: var(--color-text-secondary);">
                            Please provide your personal information before proceeding to office selection.
                        </p>
                    </div>

                    <!-- FORM -->
                    <form id="kioskForm" action="dept_select.php" method="POST" novalidate>
                        <input type="hidden" name="client_type" value="student">

                        <div class="mb-3">
                            <label for="studentId" class="form-label-custom">Student ID <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-glass-control" id="studentId" name="student_id" placeholder="Enter your student ID" required>
                        </div>

                        <div class="mb-3">
                            <label for="fullName" class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-glass-control" id="fullName" name="full_name" placeholder="Enter your full name" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="courseProgram" class="form-label-custom">Course / Program <span class="text-danger">*</span></label>
                                <select class="form-select custom-glass-control" id="courseProgram" name="course_program" required>
                                    <option value="" selected disabled>Select course / program</option>
                                    <option value="BSCS">BS Computer Science</option>
                                    <option value="BSIT">BS Information Technology</option>
                                    <option value="BSBA">BS Business Administration</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="yearLevel" class="form-label-custom">Year Level <span class="text-danger">*</span></label>
                                <select class="form-select custom-glass-control" id="yearLevel" name="year_level" required>
                                    <option value="" selected disabled>Select year level</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="contactNumber" class="form-label-custom">Contact Number <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control custom-glass-control" id="contactNumber" name="contact_number" placeholder="09XXXXXXXXX" required>
                        </div>

                        <!-- CONSENT BOX -->
                        <div class="consent-card-box mb-4">
                            <div class="form-check d-flex align-items-start gap-2">
                                <input class="form-check-input mt-1" type="checkbox" id="dataConsent" name="data_consent" required>
                                <label class="form-check-label" for="dataConsent">
                                    I authorize the school to collect and use the information I have provided for 
                                    <strong style="color: var(--color-text-primary);">school-related services, queue management, and record-keeping purposes</strong>. <span class="text-danger">*</span>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-submit-action w-100 py-3">
                            <span>Continue to Office Selection</span>
                            <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
                        </button>
                    </form>

                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>