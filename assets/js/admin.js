/**
 * VoteHub Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initConfirmDialogs();
    initDataTables();
    initFormValidation();
});

/**
 * Sidebar Toggle
 */
function initSidebar() {
    const sidebar = document.getElementById('adminSidebar') || document.getElementById('organizerSidebar');
    const toggleBtn = document.getElementById('toggleSidebar');
    const closeBtn = document.getElementById('closeSidebar');
    const body = document.body;

    if (!sidebar) return;

    // Create overlay for mobile
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);

    // Load collapsed state from localStorage (Desktop only)
    if (window.innerWidth >= 992) {
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            body.classList.add('sidebar-collapsed');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (window.innerWidth < 992) {
                // Mobile behavior: Show/Hide full sidebar
                sidebar.classList.add('show');
                overlay.classList.add('show');
            } else {
                // Desktop behavior: Collapse/Expand sidebar
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', body.classList.contains('sidebar-collapsed'));
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    overlay.addEventListener('click', function () {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
    });
}

/**
 * Confirmation Dialogs
 */
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function (e) {
            const message = this.dataset.confirm || 'Are you sure?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/**
 * Initialize Data Tables (if needed)
 */
function initDataTables() {
    // Add any table sorting/filtering logic here
}

/**
 * Form Validation
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Delete Confirmation
 */
function confirmDelete(message, url) {
    if (confirm(message || 'Are you sure you want to delete this item?')) {
        window.location.href = url;
    }
}

/**
 * Show Toast Notification
 */
function showToast(type, message) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 5000);
}

/**
 * Image Preview
 */
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

/**
 * Generate Slug from Title
 */
function generateSlug(text) {
    return text
        .toLowerCase()
        .trim()
        .replace(/[^\w\s-]/g, '')
        .replace(/[\s_-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * Copy to Clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('success', 'Copied to clipboard!');
    });
}
