/**
 * VoteHub Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {
    initSidebar();
    initSidebarAutoClose();
    initConfirmDialogs();
    initDataTables();
    initFormValidation();
    initResponsiveTables();
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

/**
 * Responsive Tables
 *
 * Admin tables are too wide for a phone. Rather than editing every one of the
 * 24 pages that renders a table, this copies each column heading onto its cells
 * as data-label and tags the table as .table-stacked. The CSS in admin.css then
 * renders each row as a labelled card below 768px.
 *
 * Opt a table out with class="table-no-stack" (useful for narrow key/value
 * tables, which already read fine on a phone).
 */
function initResponsiveTables() {
    var tables = document.querySelectorAll('.admin-content table.table, .organizer-content table.table');

    Array.prototype.forEach.call(tables, function (table) {
        if (table.classList.contains('table-no-stack') ||
            table.classList.contains('table-stacked')) {
            return;
        }

        var headRow = table.querySelector('thead tr');
        if (!headRow) return;

        var headers = Array.prototype.map.call(
            headRow.querySelectorAll('th, td'),
            function (cell) {
                // Strip any icons/badges that live inside the heading
                return (cell.textContent || '').replace(/\s+/g, ' ').trim();
            }
        );

        // Two-column tables (System Info, Database Stats...) already read fine
        if (headers.length < 3) return;

        var bodyRows = table.querySelectorAll('tbody tr');
        if (!bodyRows.length) return;

        Array.prototype.forEach.call(bodyRows, function (row) {
            var cells = row.children;

            // A full-width "no results" row: leave it unlabelled so the CSS
            // can centre it across the card.
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                return;
            }

            var column = 0;
            for (var i = 0; i < cells.length; i++) {
                var cell = cells[i];

                if (!cell.hasAttribute('data-label') && headers[column]) {
                    cell.setAttribute('data-label', headers[column]);
                }

                // Respect colspan so later cells stay aligned with their heading
                var span = parseInt(cell.getAttribute('colspan') || '1', 10);
                column += (isNaN(span) || span < 1) ? 1 : span;
            }
        });

        table.classList.add('table-stacked');
    });
}

/**
 * Close the mobile sidebar after tapping a link, so the next page does not
 * load with the overlay still covering it on slow connections.
 */
function initSidebarAutoClose() {
    var sidebar = document.getElementById('adminSidebar') || document.getElementById('organizerSidebar');
    if (!sidebar) return;

    sidebar.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a.nav-link') : null;
        if (!link) return;
        if (window.innerWidth >= 992) return;

        sidebar.classList.remove('show');
        var overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.classList.remove('show');
    });
}
