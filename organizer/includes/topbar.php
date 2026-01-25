<!-- Organizer Topbar -->
<header class="admin-topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-link me-3" id="toggleSidebar">
            <i class="bi bi-list fs-4"></i>
        </button>
        <nav aria-label="breadcrumb" class="d-none d-md-block">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/organizer/">Organizer</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $page_title ?? 'Dashboard'; ?></li>
            </ol>
        </nav>
    </div>

    <div class="d-flex align-items-center gap-3">
        <!-- Notifications -->
        <div class="dropdown" id="notificationDropdownWrapper">
            <button class="btn btn-link position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notificationBadge">
                    0
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow-lg border-0" aria-labelledby="notificationDropdown" style="width: 320px;">
                <div class="dropdown-header d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h6 class="mb-0 fw-bold">Notifications</h6>
                    <button class="btn btn-link btn-sm text-primary p-0 text-decoration-none" id="markAllRead">Mark all as read</button>
                </div>
                <div id="notificationList" style="max-height: 350px; overflow-y: auto;">
                    <div class="text-center py-4 text-muted" id="noNotifications">
                        <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
                        <p class="small mb-0">No new notifications</p>
                    </div>
                </div>
                <div class="dropdown-footer text-center py-2 border-top">
                    <a href="<?php echo SITE_URL; ?>/organizer/reports/" class="small text-primary text-decoration-none fw-semibold">View Reports</a>
                </div>
            </ul>
        </div>

        <input type="hidden" id="global_csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <script>window.SITE_URL = '<?php echo SITE_URL; ?>';</script>
        <script src="<?php echo SITE_URL; ?>/assets/js/notifications.js"></script>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="btn btn-link d-flex align-items-center gap-2 text-decoration-none" type="button" data-bs-toggle="dropdown">
                <?php 
                $avatar = $_SESSION['user_avatar'] ?? null;
                $avatarUrl = $avatar ? SITE_URL . '/uploads/avatars/' . $avatar : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name']) . '&background=6366f1&color=fff';
                ?>
                <img src="<?php echo $avatarUrl; ?>" 
                     class="rounded-circle" style="width: 35px; height: 35px; object-fit: cover;">
                <span class="d-none d-md-inline text-dark"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header"><?php echo htmlspecialchars($_SESSION['user_email']); ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/organizer/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/organizer/settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo SITE_URL; ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</header>
