<?php
// guest_info.php
$pageTitle = "Visitor Information";
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
                        <span class="category-badge mb-3">GUEST / VISITOR</span>
                        <h1 class="fw-black mb-2" style="color: var(--color-text-primary);">Visitor Information</h1>
                        <p class="mb-0 fw-semibold" style="color: var(--color-text-secondary);">
                            Please provide your visitor details before proceeding.
                        </p>
                    </div>

                    <!-- FORM -->
                    <form id="kioskForm" action="dept_select.php" method="POST" novalidate>
                        <input type="hidden" name="client_type" value="guest">

                        <div class="mb-3">
                            <label for="fullName" class="form-label-custom">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-glass-control" id="fullName" name="full_name" placeholder="Enter your full name" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label-custom">Address <span class="text-danger">*</span></label>
                            <input type="text" class="form-control custom-glass-control" id="address" name="address" placeholder="Enter your current address" required>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>