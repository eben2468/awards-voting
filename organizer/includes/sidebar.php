<!-- Organizer Sidebar -->
<aside class="admin-sidebar" id="organizerSidebar">
    <div class="sidebar-header">
        <a href="<?php echo SITE_URL; ?>/organizer/" class="sidebar-brand">
            <i class="bi bi-trophy-fill me-2"></i>
            <span>Organizer Portal</span>
        </a>
        <button class="btn btn-sm d-lg-none" id="closeSidebar">
            <i class="bi bi-x-lg text-white"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && strpos($_SERVER['PHP_SELF'], '/organizer/index.php') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/">
                    <i class="bi bi-speedometer2 me-2"></i><span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-section">My Events</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/events/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/events/">
                    <i class="bi bi-trophy me-2"></i><span>Events</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/categories/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/categories/">
                    <i class="bi bi-collection me-2"></i><span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/nominees/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/nominees/">
                    <i class="bi bi-people me-2"></i><span>Nominees</span>
                </a>
            </li>
            
            <li class="nav-section">Voting & Results</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/votes/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/votes/">
                    <i class="bi bi-check2-square me-2"></i><span>Votes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/results/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/results/">
                    <i class="bi bi-graph-up me-2"></i><span>Results</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/payouts/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/payouts/">
                    <i class="bi bi-cash-stack me-2"></i><span>Payouts</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/reports/">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i><span>Reports</span>
                </a>
            </li>
            
            <li class="nav-section">Account</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/profile.php">
                    <i class="bi bi-person-circle me-2"></i><span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/organizer/settings.php">
                    <i class="bi bi-gear me-2"></i><span>Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo SITE_URL; ?>" class="btn btn-outline-light btn-sm w-100" target="_blank">
            <i class="bi bi-box-arrow-up-right me-2"></i><span>View Site</span>
        </a>
    </div>
</aside>
