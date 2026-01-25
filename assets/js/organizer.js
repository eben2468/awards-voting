/**
 * Organizer Portal JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const toggleBtn = document.getElementById('toggleOrganizerSidebar');
    const closeBtn = document.getElementById('closeOrganizerSidebar');
    const sidebar = document.getElementById('organizerSidebar');
    const overlay = document.getElementById('organizerOverlay');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.add('show');
            if (overlay) overlay.classList.add('show');
        });
    }
    
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        });
    }
    
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }
    
    // Auto-hide sidebar on larger screens
    window.addEventListener('resize', function() {
        if (window.innerWidth > 991.98) {
            if (sidebar) sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }
    });
});
