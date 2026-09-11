<?php
require_once __DIR__ . '/../config/database.php';

// Poor man's cron: Auto-deactivate expired events on page load (max once every 5 minutes)
if (!isset($_SESSION['last_event_check']) || (time() - $_SESSION['last_event_check']) > 300) {
    $_SESSION['last_event_check'] = time();
    try {
        deactivateExpiredEvents();
    } catch (Exception $e) {
        error_log("Auto-deactivate check error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $page_description ?? 'VoteHubGH - Your trusted online awards voting platform. Vote for your favorite nominees in various award categories.'; ?>">
    <meta name="keywords" content="voting, awards, online voting, elections, nominees, Ghana awards">
    <meta name="author" content="VoteHubGH">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:title" content="<?php echo $page_title ?? 'VoteHubGH - Online Awards Voting Platform'; ?>">
    <meta property="og:description" content="<?php echo $page_description ?? 'Your trusted online awards voting platform.'; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo SITE_URL; ?>">
    <meta property="twitter:title" content="<?php echo $page_title ?? 'VoteHubGH - Online Awards Voting Platform'; ?>">
    <meta property="twitter:description" content="<?php echo $page_description ?? 'Your trusted online awards voting platform.'; ?>">
    <meta property="twitter:image" content="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">

    <!-- Schema.org for Google -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "url": "<?php echo SITE_URL; ?>",
      "logo": "<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png"
    }
    </script>
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME . ' - Online Awards Voting Platform'; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    
    
    <script>
        window.SITE_URL = '<?php echo SITE_URL; ?>';
    </script>
    
    <?php if (isset($extra_css)): ?>
        <?php echo $extra_css; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNav">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="bi bi-trophy-fill me-2"></i>VoteHubGH
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'events.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'ticketing.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/ticketing.php">Ticketing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'results.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/results.php">Results</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'how-it-works.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav align-items-center">
                    <?php if (isLoggedIn()): ?>
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown me-3" id="notificationDropdownWrapper">
                            <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notificationBadge">
                                    0
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown shadow-lg border-0" aria-labelledby="notificationDropdown" style="width: 320px;">
                                <li class="dropdown-header d-flex justify-content-between align-items-center py-3">
                                    <span class="fw-bold text-dark">Notifications</span>
                                    <button class="btn btn-link btn-sm text-primary p-0 text-decoration-none" id="markAllRead">Mark all as read</button>
                                </li>
                                <li><hr class="dropdown-divider m-0"></li>
                                <div id="notificationList" style="max-height: 350px; overflow-y: auto;">
                                    <li class="dropdown-item text-center text-muted py-4">
                                        <i class="bi bi-bell-slash d-block fs-2 mb-2"></i>
                                        No new notifications
                                    </li>
                                </div>
                                <li><hr class="dropdown-divider m-0"></li>
                                <li><a class="dropdown-item text-center py-3 fw-semibold text-primary" href="<?php echo SITE_URL; ?>/notifications.php">View All Notifications</a></li>
                            </ul>
                        </li>

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 flex-nowrap" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="user-avatar-sm">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=6366f1&color=fff" alt="Avatar" class="rounded-circle" style="width: 32px; height: 32px;">
                                </div>
                                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'My Account'); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end user-dropdown-menu border-0 shadow-lg p-0" aria-labelledby="userDropdown">
                                <div class="user-dropdown-header p-3 mb-2" style="background: var(--gradient-primary); border-radius: 0.5rem 0.5rem 0 0;">
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=fff&color=6366f1" alt="Avatar" class="rounded-circle border border-2 border-white-50" style="width: 40px; height: 40px;">
                                        <div class="overflow-hidden">
                                            <h6 class="user-name mb-0 text-white text-truncate" style="font-weight: 600;"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
                                            <small class="user-email text-white-50 text-truncate d-block"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></small>
                                        </div>
                                    </div>
                                </div>
                                <div class="pb-2">
                                    <?php if (isAdmin()): ?>
                                        <li>
                                            <a class="dropdown-item py-2 px-3" href="<?php echo SITE_URL; ?>/admin/">
                                                <i class="bi bi-speedometer2 me-2"></i> Admin Panel
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (isOrganizer()): ?>
                                        <li>
                                            <a class="dropdown-item py-2 px-3" href="<?php echo SITE_URL; ?>/organizer/">
                                                <i class="bi bi-calendar-event me-2"></i> Organizer Portal
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    <?php if (getSetting('show_vote_counts', '1') !== '1'): ?>
                                    <li>
                                        <a class="dropdown-item py-2 px-3" href="<?php echo SITE_URL; ?>/nominee-results.php">
                                            <i class="bi bi-person-badge me-2"></i> Live Results Portal
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <li><hr class="dropdown-divider mx-3"></li>
                                    
                                    <li>
                                        <a class="dropdown-item py-2 px-3 text-danger" href="<?php echo SITE_URL; ?>/logout.php">
                                            <i class="bi bi-box-arrow-right me-2"></i> Logout
                                        </a>
                                    </li>
                                </div>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo SITE_URL; ?>/login.php">Login</a>
                        </li>
                        <li class="nav-item ms-2">
                            <a class="btn btn-primary" href="<?php echo SITE_URL; ?>/register.php">Get Started</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php 
    $flash = getFlash();
    if ($flash): 
    ?>
    <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show position-fixed" style="top: 100px; right: 20px; z-index: 9999; max-width: 400px;" role="alert">
        <?php echo $flash['message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Global CSRF Token for AJAX -->
    <input type="hidden" name="csrf_token" id="global_csrf_token" value="<?php echo generateCSRFToken(); ?>">
