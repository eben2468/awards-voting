<?php
/**
 * Admin Newsletter Subscribers View
 * Awards Voting System
 */

require_once '../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Newsletter Subscribers';
$pdo = getDBConnection();

// Handle Actions
if (isset($_GET['action']) && isset($_GET['id']) && verifyCSRFToken($_GET['token'] ?? '')) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    
    if ($action === 'unsubscribe') {
        $stmt = $pdo->prepare("UPDATE subscribers SET status = 'unsubscribed' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Subscriber has been unsubscribed.');
    } elseif ($action === 'activate') {
        $stmt = $pdo->prepare("UPDATE subscribers SET status = 'active' WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('success', 'Subscriber has been activated.');
    }
    redirect('admin/subscribers.php');
}

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if ($status) {
    $where[] = "status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "email LIKE ?";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM subscribers $whereSql");
$countStmt->execute($params);
$totalSubscribers = $countStmt->fetchColumn();
$totalPages = ceil($totalSubscribers / $limit);

// Fetch subscribers
$stmt = $pdo->prepare("
    SELECT * FROM subscribers 
    $whereSql 
    ORDER BY created_at DESC 
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$subscribers = $stmt->fetchAll();

// Stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM subscribers")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'active'")->fetchColumn(),
    'unsubscribed' => $pdo->query("SELECT COUNT(*) FROM subscribers WHERE status = 'unsubscribed'")->fetchColumn(),
];
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Newsletter Subscribers</h2>
                    <p class="text-muted mb-0">Manage your email newsletter audience.</p>
                </div>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer me-2"></i>Export List
                </button>
            </div>

            <?php if ($flash = getFlash()): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show mb-4" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-4">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-people fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                                    <span class="text-muted">Total Subscribers</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-check-circle fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['active']); ?></h3>
                                    <span class="text-muted">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-4">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                                    <i class="bi bi-person-x fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['unsubscribed']); ?></h3>
                                    <span class="text-muted">Unsubscribed</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by email..." value="<?php echo e($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="unsubscribed" <?php echo $status == 'unsubscribed' ? 'selected' : ''; ?>>Unsubscribed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="subscribers.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subscribers Table -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Email Address</th>
                                <th>Status</th>
                                <th>Subscribed Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscribers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No subscribers found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscribers as $sub): ?>
                                    <tr>
                                        <td>#<?php echo $sub['id']; ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo e($sub['email']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $sub['status'] == 'active' ? 'success' : 'danger'; ?> bg-opacity-10 text-<?php echo $sub['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo date('M d, Y', strtotime($sub['created_at'])); ?></div>
                                            <div class="small text-muted"><?php echo date('H:i', strtotime($sub['created_at'])); ?></div>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="mailto:<?php echo $sub['email']; ?>"><i class="bi bi-envelope me-2"></i>Send Email</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($sub['status'] == 'active'): ?>
                                                        <li><a class="dropdown-item text-danger" href="?action=unsubscribe&id=<?php echo $sub['id']; ?>&token=<?php echo generateCSRFToken(); ?>" onclick="return confirm('Are you sure you want to unsubscribe this user?')"><i class="bi bi-person-x me-2"></i>Unsubscribe</a></li>
                                                    <?php else: ?>
                                                        <li><a class="dropdown-item text-success" href="?action=activate&id=<?php echo $sub['id']; ?>&token=<?php echo generateCSRFToken(); ?>"><i class="bi bi-person-check me-2"></i>Activate</a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo $search; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
