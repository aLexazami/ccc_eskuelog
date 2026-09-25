// =========================================================================
// LOADER UTILITY HELPERS
// =========================================================================

/**
 * Displays a non-blocking or full-screen loading spinner overlay.
 * Creates the DOM element automatically if it doesn't exist yet.
 */
function showLoader(message = 'Processing...') {
    let loader = document.getElementById('globalAppLoader');
    if (!loader) {
        loader = document.createElement('div');
        loader.id = 'globalAppLoader';
        loader.innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center bg-white p-4 rounded shadow-lg" style="min-width: 200px;">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2.5rem; height: 2.5rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div id="loaderMessage" class="fw-bold text-secondary fs-6">${message}</div>
            </div>
        `;
        Object.assign(loader.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            width: '100vw',
            height: '100vh',
            backgroundColor: 'rgba(0, 0, 0, 0.4)',
            zIndex: '99999',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            backdropFilter: 'blur(2px)'
        });
        document.body.appendChild(loader);
    } else {
        const msgEl = document.getElementById('loaderMessage');
        if (msgEl) msgEl.textContent = message;
        loader.style.display = 'flex';
    }
}

/**
 * Hides the global loading spinner overlay.
 */
function hideLoader() {
    const loader = document.getElementById('globalAppLoader');
    if (loader) {
        loader.style.display = 'none';
    }
}


// =========================================================================
// NOTIFICATION & ALERT UTILITY HELPERS
// =========================================================================

function showNotification(options = {}) {
    var placementFrom = $("#notify_placement_from option:selected").val() || "top";
    var placementAlign = $("#notify_placement_align option:selected").val() || "right";
    var state = $("#notify_state option:selected").val() || "info";

    var content = {
        title: options.title || "",
        message: options.message || "",
        url: options.url || "#",
        target: options.target || "_self",
        icon: options.icon || "none"
    };

    $.notify(content, {
        type: options.type || state,
        placement: {
            from: options.placementFrom || placementFrom,
            align: options.placementAlign || placementAlign,
        },
        time: options.time || 1000,
        delay: options.delay !== undefined ? options.delay : 5000,
        allow_dismiss: true,
        z_index: 10000,
        animate: {
            enter: 'animated fadeInDown',
            exit: 'animated fadeOutUp'
        }
    });
}

function showSuccess(message, title = 'Success', options = {}) {
    showNotification({
        type: 'success',
        title: title,
        message: message,
        icon: options.icon || 'fa fa-check-circle',
        ...options
    });
}

function showError(message, title = 'Error', options = {}) {
    showNotification({
        type: 'danger',
        title: title,
        message: message,
        icon: options.icon || 'fa fa-exclamation-circle',
        ...options
    });
}

function showWarning(message, title = 'Warning', options = {}) {
    showNotification({
        type: 'warning',
        title: title,
        message: message,
        icon: options.icon || 'fa fa-exclamation-triangle',
        ...options
    });
}

function showInfo(message, title = 'Information', options = {}) {
    showNotification({
        type: 'info',
        title: title,
        message: message,
        icon: options.icon || 'fa fa-info-circle',
        ...options
    });
}

/**
 * Modern custom-styled Confirmation Dialog
 */
async function notifyConfirm(title, text, confirmButtonText = 'Yes, proceed', icon = 'warning') {
    const result = await Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonText: confirmButtonText,
        cancelButtonText: 'Cancel',
        reverseButtons: true, // Puts Cancel on left, Confirm on right
        target: 'body', // Fixes z-index issue when triggered inside Bootstrap modals
        buttonsStyling: false,
        customClass: {
            popup: 'custom-confirm-popup',
            icon: 'custom-confirm-icon',
            confirmButton: 'btn btn-primary px-3 py-2 fw-medium shadow-sm',
            cancelButton: 'btn btn-outline-secondary px-3 py-2 fw-medium me-2'
        }
    });
    return result.isConfirmed;
}

// /**
//  * 
//  * 
//  * @param {string} title - Dialog title
//  * @param {string} text - HTML/Text explanation
//  * @param {string} expectedValue - The exact string the user must type to confirm
//  * @param {string} confirmButtonText - Label for the confirm button
//  * @returns {Promise<boolean>} - Resolves true if validated and confirmed
//  */
// async function notifyTypeConfirm(title, text, expectedValue, confirmButtonText = 'Confirm') {
//     const result = await Swal.fire({
//         title: title,
//         html: `
//             <div class="px-2">
//                 <p class="text-secondary mb-3 fs-6">${text}</p>
//                 <div class="p-2 mb-3 bg-light rounded border text-muted small">
//                     Please type <strong class="text-danger user-select-all fw-bold">${expectedValue}</strong> to confirm:
//                 </div>
//             </div>
//         `,
//         icon: 'warning',
//         input: 'text',
//         inputPlaceholder: `Type "${expectedValue}" here`,
//         inputAttributes: {
//             autocapitalize: 'off',
//             autocorrect: 'off',
//             autocomplete: 'off'
//         },
//         showCancelButton: true,
//         confirmButtonText: confirmButtonText,
//         cancelButtonText: 'Cancel',
//         reverseButtons: true, // Puts Cancel on the left
//         target: 'body',
//         buttonsStyling: false,
//         customClass: {
//             popup: 'custom-confirm-popup p-4 rounded-4 shadow-lg border-0',
//             icon: 'custom-confirm-icon mb-2',
//             title: 'fs-4 fw-bold text-dark mb-1',
//             actions: 'd-flex justify-content-end align-items-center gap-2 mt-4 w-100 px-2',
//             confirmButton: 'btn btn-danger px-3 py-2 fw-semibold shadow-sm rounded-2 m-0',
//             cancelButton: 'btn btn-outline-secondary px-3 py-2 fw-semibold rounded-2 m-0',
//             input: 'form-control form-control-lg text-center fw-medium border-secondary-subtle mx-auto shadow-none'
//         },
//         inputValidator: (value) => {
//             if (!value) {
//                 return 'You need to write something!';
//             }
//             if (value.trim() !== expectedValue.trim()) {
//                 return `Input does not match "${expectedValue}"`;
//             }
//         }
//     });

//     return result.isConfirmed;
// }

/**
 * Modern custom-styled GitHub-like Typed Confirmation Dialog
 * Matches the native notifyConfirm design layout with emphasized red title action.
 * 
 * @param {'archive'|'delete'} type - Action type ('archive' or 'delete')
 * @param {string} name - Keyword required to confirm
 * @param {number|string} retentionDays - Number of days stored before auto-delete
 */
async function notifyTypeArchive(type = 'archive', name, retentionDays = 30) {
    const isDelete = type.toLowerCase() === 'delete';
    const expectedValue = name ? name.trim() : (isDelete ? 'DELETE' : 'ARCHIVE');
    const actionLabel = isDelete ? 'delete' : 'archive';

    // Highlighted action in red
    const actionText = isDelete ? 'DELETE' : 'ARCHIVE';
    const title = `<span class="text-danger fw-bold">${actionText}</span>`;

    const result = await Swal.fire({
        title: title,
        html: `
            <p class="mb-2 fs-7">To confirm, type <strong id="swal-target-text" class="text-danger user-select-none pe-none fs-6" style="user-select: none; -webkit-user-select: none;">${expectedValue}</strong> in the box below:</p>
            <input type="text" id="swal-typed-input" class="form-control text-center fw-bold shadow-none mb-3" placeholder="Type here..." autocomplete="off">
            <small class="fs-7"><i class="bi bi-clock-history me-1"></i> Retention Period: <strong>${retentionDays} Days</strong> before permanent removal.</small>
        `,
        icon: 'error',
        showCancelButton: true,
        confirmButtonText: `Yes, ${actionLabel} it!`,
        cancelButtonText: 'Cancel',
        reverseButtons: true,
        target: 'body',
        buttonsStyling: false,
        customClass: {
            popup: 'custom-confirm-popup',
            icon: 'custom-confirm-icon',
            confirmButton: 'btn btn-danger px-3 py-2 fw-medium shadow-sm',
            cancelButton: 'btn btn-outline-secondary px-3 py-2 fw-medium me-2'
        },
        didOpen: () => {
            const inputEl = document.getElementById('swal-typed-input');
            const targetTextEl = document.getElementById('swal-target-text');
            const confirmBtn = Swal.getConfirmButton();

            // Disable copy/cut/context-menu on the target keyword
            if (targetTextEl) {
                ['copy', 'cut', 'contextmenu', 'dragstart'].forEach(evt => {
                    targetTextEl.addEventListener(evt, (e) => e.preventDefault());
                });
            }

            // Disable pasting into input
            inputEl.addEventListener('paste', (e) => e.preventDefault());

            // Disable confirm button by default
            confirmBtn.disabled = true;
            confirmBtn.style.opacity = '0.5';
            confirmBtn.style.cursor = 'not-allowed';

            // Auto-focus input
            inputEl.focus();

            // Live validation check
            inputEl.addEventListener('input', () => {
                if (inputEl.value.trim() === expectedValue) {
                    confirmBtn.disabled = false;
                    confirmBtn.style.opacity = '1';
                    confirmBtn.style.cursor = 'pointer';
                    inputEl.classList.remove('is-invalid');
                    inputEl.classList.add('is-valid');
                } else {
                    confirmBtn.disabled = true;
                    confirmBtn.style.opacity = '0.5';
                    confirmBtn.style.cursor = 'not-allowed';
                    inputEl.classList.remove('is-valid');
                }
            });

            // Allow pressing 'Enter' key inside input to confirm if valid
            inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    if (inputEl.value.trim() === expectedValue) {
                        Swal.clickConfirm();
                    } else {
                        e.preventDefault();
                        inputEl.classList.add('is-invalid');
                    }
                }
            });
        }
    });

    return result.isConfirmed;
}