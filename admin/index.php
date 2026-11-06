<?php
/**
 * Admin Dashboard
 * Awards Voting System
 */

require_once '../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Admin Dashboard';
$pdo = getDBConnection();

// Fetch statistics
$stats = [
    'total_events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'active_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn(),
    'total_categories' => $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'total_nominees' => $pdo->query("SELECT COUNT(*) FROM nominees")->fetchColumn(),
    'total_votes' => $pdo->query("SELECT COALESCE(SUM(vote_count), 0) FROM nominees")->fetchColumn(),
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'new_users_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'votes_today' => $pdo->query("SELECT COUNT(*) FROM votes WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    'pending_payouts' => $pdo->query("SELECT COUNT(*) FROM organizer_payouts WHERE status = 'pending'")->fetchColumn(),
];

// Fetch recent events
$recentEventsStmt = $pdo->query("
    SELECT e.*, COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    GROUP BY e.id
    ORDER BY e.created_at DESC
    LIMIT 5
");
$recentEvents = $recentEventsStmt->fetchAll();

// Fetch top nominees
$topNomineesStmt = $pdo->query("
    SELECT n.*, c.name as category_name, e.title as event_title
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE e.status = 'active'
    ORDER BY n.vote_count DESC
    LIMIT 5
");
$topNominees = $topNomineesStmt->fetchAll();

// Fetch recent activity
$recentActivityStmt = $pdo->query("
    SELECT al.*, u.fullname as user_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$recentActivity = $recentActivityStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/apple-touch-icon.png?v=2">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <!-- Welcome Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Dashboard Overview</h2>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="events/add.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-2"></i>New Event
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-trophy fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_events']); ?></h3>
                                    <span class="text-muted">Total Events</span>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="text-success"><i class="bi bi-lightning-fill"></i> <?php echo $stats['active_events']; ?></span>
                                <span class="text-muted">active</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-check2-square fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_votes']); ?></h3>
                                    <span class="text-muted">Total Votes</span>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="text-success"><i class="bi bi-arrow-up"></i> <?php echo $stats['votes_today']; ?></span>
                                <span class="text-muted">today</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                                    <i class="bi bi-people fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_nominees']); ?></h3>
                                    <span class="text-muted">Nominees</span>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="text-muted"><?php echo $stats['total_categories']; ?> categories</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                                    <i class="bi bi-person-check fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_users']); ?></h3>
                                    <span class="text-muted">Users</span>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <span class="text-success"><i class="bi bi-person-plus"></i> <?php echo $stats['new_users_today']; ?></span>
                                <span class="text-muted">new today</span>
                                <a href="users/" class="ms-2 text-primary text-decoration-none">View all <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                                    <i class="bi bi-cash-stack fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['pending_payouts']); ?></h3>
                                    <span class="text-muted">Pending Payouts</span>
                                </div>
                            </div>
                            <div class="mt-3 small">
                                <a href="payouts/?status=pending" class="text-danger text-decoration-none">View requests <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Recent Events -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Recent Events</h5>
                            <a href="events/" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Status</th>
                                        <th>Votes</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentEvents as $event): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo match($event['status']) {
                                                    'active' => 'success',
                                                    'completed' => 'secondary',
                                                    'draft' => 'warning',
                                                    default => 'danger'
                                                }; ?>"><?php echo ucfirst($event['status']); ?></span>
                                            </td>
                                            <td><?php echo number_format($event['total_votes']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['end_date'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="events/edit.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>/event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-outline-secondary" title="View" target="_blank">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Top Nominees -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-star me-2"></i>Top Nominees</h5>
                            <a href="nominees/" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Nominee</th>
                                        <th>Category</th>
                                        <th>Event</th>
                                        <th>Votes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topNominees as $index => $nominee): ?>
                                        <tr>
                                            <td>
                                                <span class="badge <?php echo $index < 3 ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                                    #<?php echo $index + 1; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $nominee['photo'] ? '../uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']); ?>" 
                                                         class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php echo htmlspecialchars($nominee['name']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($nominee['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($nominee['event_title']); ?></td>
                                            <td><strong class="text-primary"><?php echo number_format($nominee['vote_count']); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Quick Actions -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="events/add.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-2"></i>Create Event
                                </a>
                                <a href="categories/" class="btn btn-outline-primary">
                                    <i class="bi bi-collection me-2"></i>Manage Categories
                                </a>
                                <a href="nominees/" class="btn btn-outline-primary">
                                    <i class="bi bi-people me-2"></i>Manage Nominees
                                </a>
                                <a href="reports/" class="btn btn-outline-secondary">
                                    <i class="bi bi-file-earmark-bar-graph me-2"></i>Export Reports
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Recent Activity</h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <li class="list-group-item">
                                        <div class="d-flex">
                                            <div class="activity-icon me-3">
                                                <i class="bi bi-<?php echo match($activity['action']) {
                                                    'login' => 'box-arrow-in-right text-success',
                                                    'logout' => 'box-arrow-right text-secondary',
                                                    'vote_cast' => 'check2-square text-primary',
                                                    'registration' => 'person-plus text-info',
                                                    default => 'activity text-muted'
                                                }; ?>"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <p class="mb-0 small">
                                                    <strong><?php echo $activity['user_name'] ?? 'System'; ?></strong>
                                                    <?php echo str_replace('_', ' ', $activity['action']); ?>
                                                </p>
                                                <small class="text-muted">
                                                    <?php echo date('M d, H:i', strtotime($activity['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
