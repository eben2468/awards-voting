<?php
/**
 * Organizer Dashboard
 * Awards Voting System
 */

require_once '../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied. Organizer privileges required.');
    redirect('login.php');
}

$page_title = 'Organizer Dashboard';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

// Fetch statistics for this organizer
$stats = [
    'total_events' => $pdo->prepare("SELECT COUNT(*) FROM events WHERE organizer_id = ?"),
    'active_events' => $pdo->prepare("SELECT COUNT(*) FROM events WHERE organizer_id = ? AND status = 'active'"),
    'total_categories' => $pdo->prepare("SELECT COUNT(c.id) FROM categories c JOIN events e ON c.event_id = e.id WHERE e.organizer_id = ?"),
    'total_nominees' => $pdo->prepare("SELECT COUNT(n.id) FROM nominees n JOIN categories c ON n.category_id = c.id JOIN events e ON c.event_id = e.id WHERE e.organizer_id = ?"),
    'total_votes' => $pdo->prepare("SELECT COALESCE(SUM(n.vote_count), 0) FROM nominees n JOIN categories c ON n.category_id = c.id JOIN events e ON c.event_id = e.id WHERE e.organizer_id = ?"),
];

foreach ($stats as $key => $stmt) {
    $stmt->execute([$organizer_id]);
    $stats[$key] = $stmt->fetchColumn();
}

// Fetch recent events for this organizer
$recentEventsStmt = $pdo->prepare("
    SELECT e.*, COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.organizer_id = ?
    GROUP BY e.id
    ORDER BY e.created_at DESC
    LIMIT 5
");
$recentEventsStmt->execute([$organizer_id]);
$recentEvents = $recentEventsStmt->fetchAll();

// Fetch top nominees for this organizer's events
$topNomineesStmt = $pdo->prepare("
    SELECT n.*, c.name as category_name, e.title as event_title
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE e.organizer_id = ? AND e.status = 'active'
    ORDER BY n.vote_count DESC
    LIMIT 5
");
$topNomineesStmt->execute([$organizer_id]);
$topNominees = $topNomineesStmt->fetchAll();

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
                    <h2 class="mb-1">Organizer Dashboard</h2>
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
                                    <span class="text-muted">My Events</span>
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
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-info bg-opacity-10 text-info rounded-3 p-3 me-3">
                                    <i class="bi bi-collection fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_categories']); ?></h3>
                                    <span class="text-muted">Categories</span>
                                </div>
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
                            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>My Recent Events</h5>
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
                                    <?php if (empty($recentEvents)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-4 text-muted">No events found. Create your first event!</td>
                                        </tr>
                                    <?php else: ?>
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
                                    <?php endif; ?>
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

                    <!-- Top Nominees -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-star me-2"></i>Top Nominees</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($topNominees)): ?>
                                    <li class="list-group-item text-center py-4 text-muted small">No active nominees yet.</li>
                                <?php else: ?>
                                    <?php foreach ($topNominees as $nominee): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex align-items-center">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0 small"><?php echo htmlspecialchars($nominee['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($nominee['event_title']); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-primary rounded-pill"><?php echo number_format($nominee['vote_count']); ?></span>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
