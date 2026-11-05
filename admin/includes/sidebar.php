<!-- Admin Sidebar -->
<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <a href="<?php echo SITE_URL; ?>/admin/" class="sidebar-brand">
            <img src="<?php echo assetUrl('assets/logo/favicon.png'); ?>" alt="" class="sidebar-logo me-2">
            <span>VoteHubGH</span>
        </a>
        <button class="btn btn-sm d-lg-none" id="closeSidebar">
            <i class="bi bi-x-lg text-white"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' && !isset($_GET['page']) ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/">
                    <i class="bi bi-speedometer2 me-2"></i><span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-section">Events Management</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/events/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/events/">
                    <i class="bi bi-trophy me-2"></i><span>Events</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/categories/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/categories/">
                    <i class="bi bi-collection me-2"></i><span>Categories</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/nominees/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/nominees/">
                    <i class="bi bi-people me-2"></i><span>Nominees</span>
                </a>
            </li>
            
            <li class="nav-section">Voting</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/votes/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/votes/">
                    <i class="bi bi-check2-square me-2"></i><span>Votes</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/transactions.php">
                    <i class="bi bi-credit-card me-2"></i><span>Transactions</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ticket-analytics.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/ticket-analytics.php">
                    <i class="bi bi-ticket-perforated me-2"></i><span>Ticket Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'pending-notifications.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/pending-notifications.php">
                    <i class="bi bi-bell-fill me-2"></i><span>Pending Notifications</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/results/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/results/">
                    <i class="bi bi-graph-up me-2"></i><span>Results</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/payouts/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/payouts/">
                    <i class="bi bi-cash-stack me-2"></i><span>Payout Requests</span>
                </a>
            </li>
            
            <li class="nav-section">Users & Reports</li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/admin/users/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/users/">
                    <i class="bi bi-people me-2"></i><span>Users</span>
                </a>
            </li>
            <?php $pendingOrganizers = function_exists('getPendingOrganizerCount') ? getPendingOrganizerCount() : 0; ?>
            <li class="nav-item">
                <a class="nav-link d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'organizer-approvals.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/organizer-approvals.php">
                    <i class="bi bi-person-check me-2"></i><span>Organizer Approvals</span>
                    <?php if ($pendingOrganizers > 0): ?>
                        <span class="badge rounded-pill bg-warning text-dark ms-auto"><?php echo $pendingOrganizers; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/reports/') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/reports/">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i><span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subscribers.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/subscribers.php">
                    <i class="bi bi-envelope-check me-2"></i><span>Subscribers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact-messages.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/contact-messages.php">
                    <i class="bi bi-chat-left-text me-2"></i><span>Messages</span>
                </a>
            </li>
            
            <li class="nav-section">System</li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/profile.php">
                    <i class="bi bi-person-circle me-2"></i><span>My Profile</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/settings') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/settings.php">
                    <i class="bi bi-gear me-2"></i><span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'diagnostics.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/diagnostics.php">
                    <i class="bi bi-activity me-2"></i><span>Diagnostics</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/logs') !== false ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/admin/logs.php">
                    <i class="bi bi-journal-text me-2"></i><span>Audit Logs</span>
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
