<?php
// queue_number.php - MUST BE AT THE VERY TOP BEFORE HEADER
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store submitted modal inputs directly into $_SESSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['transaction_id'])) {
        $_SESSION['transaction_id'] = $_POST['transaction_id'];
    }
    if (!empty($_POST['transaction_title'])) {
        $_SESSION['transaction_title'] = $_POST['transaction_title'];
    }
    if (isset($_POST['inquiry_details'])) {
        $_SESSION['inquiry_details'] = $_POST['inquiry_details'];
    }
}

// Generate queue ticket prefix & number if not generated yet
if (!isset($_SESSION['queue_number'])) {
    $deptCode = $_SESSION['dept_code'] ?? 'REG';
    $prefix = strtoupper(substr($deptCode, 0, 1));
    $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
    $_SESSION['queue_number'] = $prefix . '-' . $sequence;
    $_SESSION['issued_at'] = date('Y-m-d h:i A');
}

$deptName = $_SESSION['dept_name'] ?? 'Registrar';
$queueNumber = $_SESSION['queue_number'];
$transactionTitle = $_SESSION['transaction_title'] ?? 'General Inquiry';
$issuedAt = $_SESSION['issued_at'] ?? date('Y-m-d h:i A');

// Data string to encode inside the QR code (e.g., ticket verification data)
$qrData = "Ticket: " . $queueNumber . " | Dept: " . $deptName . " | Trans: " . $transactionTitle;

$pageTitle = "Your Queue Ticket";
$pageScript = "/assets/js/kiosk.js";

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- HTML2PDF Library & QRCodeJS CDN -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    /* Receipt Container Styling */
    .receipt-card {
        background: #ffffff !important;
        color: #111111 !important;
        border-radius: 6px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        border-top: 6px solid var(--color-classic-blue, #052482);
        width: 100%;
        max-width: 380px;
        margin: 0 auto;
    }

    .receipt-dashed-line {
        border-top: 2px dashed #888888;
        margin: 1.25rem 0;
    }

    /* Center QR Code Wrapper */
    #qrcode img {
        margin: 0 auto;
    }
</style>

<div class="portal-bg py-5">
    <div class="container d-flex flex-column align-items-center">
        <!-- TARGET AREA FOR PDF GENERATION -->
        <div id="receipt-ticket" class="receipt-card text-center p-4">
            <!-- Header -->
            <div class="mb-2">
                <h3 class="fw-black text-uppercase tracking-wider mb-0" style="letter-spacing: 1.5px; color: #000;">
                    eSkueLog</h3>
                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Official Queue
                    Ticket</small>
            </div>

            <div class="receipt-dashed-line"></div>

            <!-- Queue Number -->
            <div class="my-2">
                <span class="text-uppercase small fw-bold text-muted d-block mb-1" style="font-size: 0.75rem;">Queue
                    Number</span>
                <h1 class="fw-bolder my-0 text-dark" style="font-size: 3.5rem; line-height: 1;">
                    <?= htmlspecialchars($queueNumber) ?>
                </h1>
            </div>

            <div class="receipt-dashed-line"></div>

            <!-- Ticket Details -->
            <div class="text-start fs-6 my-2" style="font-size: 0.85rem;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold text-muted">Department:</span>
                    <span class="fw-bold text-dark text-end"><?= htmlspecialchars($deptName) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="fw-semibold text-muted">Transaction:</span>
                    <span class="fw-bold text-dark text-end"><?= htmlspecialchars($transactionTitle) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold text-muted">Issued Date:</span>
                    <span class="fw-bold text-dark text-end"><?= htmlspecialchars($issuedAt) ?></span>
                </div>
            </div>

            <div class="receipt-dashed-line"></div>

            <!-- QR Code Section -->
            <div class="my-3 d-flex flex-column align-items-center">
                <div id="qrcode" class="mb-2"></div>
                <small class="text-muted" style="font-size: 0.65rem; letter-spacing: 0.5px;">Scan to verify
                    ticket</small>
            </div>

            <!-- Receipt Footer -->
            <p class="small text-muted mb-0" style="font-size: 0.7rem;">Please keep this slip and wait for your ticket
                to be called.</p>
        </div>

        <!-- Action Control Buttons -->
        <div class="mt-4 d-flex align-items-center justify-content-center gap-2 w-100 mx-auto"
            style="max-width: 380px;">
            <button onclick="downloadPDF()"
                class="btn btn-outline-light w-50 px-3 py-2 fw-semibold d-inline-flex align-items-center justify-content-center">
                <i class="bi bi-file-earmark-pdf me-2"></i>Download PDF
            </button>
            <a href="reset_session.php"
                class="btn btn-submit-action w-50 px-3 py-2 fw-semibold text-decoration-none d-inline-flex align-items-center justify-content-center">
                Done
            </a>
        </div>

    </div>
</div>

<script>
    // Automatically generate QR Code on page load
    window.addEventListener('DOMContentLoaded', (event) => {
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= htmlspecialchars($qrData) ?>",
            width: 90,
            height: 90,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    });

    // Function to generate and download PDF containing the QR code
    function downloadPDF() {
        const element = document.getElementById('receipt-ticket');
        const opt = {
            margin: 0.2,
            filename: 'Queue_Ticket_<?= htmlspecialchars($queueNumber) ?>.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'in', format: [4, 6], orientation: 'portrait' }
        };

        html2pdf().set(opt).from(element).save();
    }

</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>