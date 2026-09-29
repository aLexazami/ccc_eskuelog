<?php
// select_inquiry.php - MUST BE AT THE VERY TOP BEFORE HEADER
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Capture and persist department ID immediately
if (isset($_GET['dept_id'])) {
    $_SESSION['dept_id'] = (int)$_GET['dept_id'];
}

// 2. Fallback check - if no dept_id exists at all, fallback safely to 1
$deptId = $_SESSION['dept_id'] ?? 1;

// Mock database mapping for department offices & their respective transactions
$deptData = [
    1 => [
        'name' => 'Registrar',
        'code' => 'REG',
        'icon' => 'bi-file-earmark-text',
        'transactions' => [
            ['id' => 101, 'title' => 'Request Transcript', 'desc' => 'Request an official copy of your academic transcript.'],
            ['id' => 102, 'title' => 'Request Certification', 'desc' => 'Request enrollment or other academic certifications.'],
            ['id' => 103, 'title' => 'Student Records', 'desc' => 'Inquire about student academic records.'],
            ['id' => 104, 'title' => 'Document Verification', 'desc' => 'Submit documents for verification and processing.']
        ]
    ],
    2 => [
        'name' => 'Accounting Office',
        'code' => 'ACC',
        'icon' => 'bi-cash-coin',
        'transactions' => [
            ['id' => 201, 'title' => 'Statement of Account', 'desc' => 'Request an updated copy of your tuition balance.'],
            ['id' => 202, 'title' => 'Payment Clearance', 'desc' => 'Clear outstanding balances for examination permits.'],
            ['id' => 203, 'title' => 'Refund Request', 'desc' => 'Apply for overpayment or tuition fee refunds.']
        ]
    ]
];

// Fallback to Registrar if ID doesn't exist in mock array
$currentDept = $deptData[$deptId] ?? $deptData[1];

// Store department info in session so queue_number.php can display it
$_SESSION['dept_name'] = $currentDept['name'];
$_SESSION['dept_code'] = $currentDept['code'];

$pageTitle = "Select Inquiry - " . $currentDept['name'];
$pageScript = "/assets/js/kiosk.js";

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="portal-bg py-5 min-vh-100">
    <div class="container py-2">

        <!-- Selected Department Header Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card selected-dept-header-card p-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="dept-icon-box flex-shrink-0">
                            <i class="bi <?= htmlspecialchars($currentDept['icon']) ?> fs-2"></i>
                        </div>
                        <div>
                            <h2 class="fw-black mb-1" style="color: var(--color-text-primary);"><?= htmlspecialchars($currentDept['name']) ?></h2>
                            <p class="mb-0 fw-semibold" style="color: var(--color-text-secondary);">Select the type of transaction you need.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Title -->
        <div class="mb-4">
            <h3 class="fw-black mb-1" style="color: var(--color-text-primary);">Select Transaction</h3>
            <p class="mb-0 fw-semibold" style="color: var(--color-text-secondary);">Choose the transaction that best matches your request.</p>
        </div>

        <!-- Transaction Options Grid -->
        <div class="row g-3">
            <?php foreach ($currentDept['transactions'] as $tx): ?>
                <div class="col-12 col-md-6">
                    <button type="button" 
                            class="card transaction-card w-100 p-4 text-start border-0"
                            onclick="openInquiryModal('<?= $tx['id'] ?>', '<?= htmlspecialchars($tx['title'], ENT_QUOTES) ?>')">
                        <div class="d-flex align-items-center justify-content-between w-100">
                            <div class="d-flex align-items-center gap-3">
                                <div class="dept-icon-box flex-shrink-0">
                                    <i class="bi bi-file-text fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-1" style="color: var(--color-text-primary);"><?= htmlspecialchars($tx['title']) ?></h5>
                                    <p class="small mb-0 text-muted-custom"><?= htmlspecialchars($tx['desc']) ?></p>
                                </div>
                            </div>
                            <i class="bi bi-arrow-right fs-4 arrow-icon ms-2"></i>
                        </div>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Modal for Optional Additional Information -->
<div class="modal fade" id="inquiryModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-card border-0">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTransactionTitle" style="color: var(--color-text-primary);">Additional Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form action="queue_number.php" method="POST">
                <div class="modal-body py-4">
                    <input type="hidden" name="transaction_id" id="modalTxId">
                    <input type="hidden" name="transaction_title" id="modalTxTitleInput">
                    
                    <p class="small mb-3 fw-semibold" style="color: var(--color-text-secondary);">
                        You may provide specific details or concerns about your transaction (Optional).
                    </p>

                    <div class="mb-3">
                        <label for="inquiryDetails" class="form-label-custom">Notes / Purpose Details</label>
                        <textarea class="form-control custom-glass-control" 
                                  id="inquiryDetails" 
                                  name="inquiry_details" 
                                  rows="4" 
                                  placeholder="e.g. Requesting Transcript of Records for Employment..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-0 pt-0 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-secondary rounded-3 px-3 py-2 text-decoration-none fw-semibold">
                        Skip & Proceed
                    </button>
                    <button type="submit" class="btn btn-submit-action flex-grow-1">
                        <span>Submit & Continue</span>
                        <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openInquiryModal(txId, txTitle) {
    document.getElementById('modalTxId').value = txId;
    document.getElementById('modalTxTitleInput').value = txTitle;
    document.getElementById('modalTransactionTitle').innerText = txTitle;
    document.getElementById('inquiryDetails').value = '';

    const modal = new bootstrap.Modal(document.getElementById('inquiryModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>