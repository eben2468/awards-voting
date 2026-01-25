<?php
/**
 * Admin - Audit Logs
 * Awards Voting System
 */

require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Audit Logs';
$pdo = getDBConnection();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filters
$action = sanitize($_GET['action'] ?? '');
$userId = intval($_GET['user'] ?? 0);

$whereConditions = [];
$params = [];

if (!empty($action)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $action;
}
if ($userId > 0) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userId;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Count total
$countSql = "SELECT COUNT(*) FROM audit_logs al $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Fetch logs
$sql = "
    SELECT al.*, u.fullname as user_name, u.email as user_email
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereClause
    ORDER BY al.created_at DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $pdo->query("SELECT id, fullname FROM users ORDER BY fullname")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Audit Logs</h2>
                    <p class="text-muted mb-0">Track all system activity</p>
                </div>
                <span class="badge bg-secondary fs-6"><?php echo number_format($totalLogs); ?> Records</span>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $act): ?>
                                    <option value="<?php echo $act; ?>" <?php echo $action === $act ? 'selected' : ''; ?>>
                                        <?php echo ucwords(str_replace('_', ' ', $act)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <select name="user" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo $userId == $u['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($u['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>Filter
                            </button>
                            <a href="logs.php" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Logs Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-text display-1 text-muted mb-3"></i>
                            <h4>No Logs Found</h4>
                            <p class="text-muted">Activity logs will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Record ID</th>
                                        <th>IP Address</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>#<?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo $log['user_email']; ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo match($log['action']) {
                                                    'login' => 'success',
                                                    'logout' => 'secondary',
                                                    'vote_cast' => 'primary',
                                                    'registration' => 'info',
                                                    'password_reset_request' => 'warning',
                                                    'login_failed' => 'danger',
                                                    default => 'dark'
                                                }; ?>">
                                                    <?php echo ucwords(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $log['table_name'] ?: '-'; ?></td>
                                            <td><?php echo $log['record_id'] ?: '-'; ?></td>
                                            <td><code class="small"><?php echo $log['ip_address']; ?></code></td>
                                            <td>
                                                <small>
                                                    <?php echo date('M d, Y', strtotime($log['created_at'])); ?>
                                                    <br><?php echo date('H:i:s', strtotime($log['created_at'])); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=<?php echo $action; ?>&user=<?php echo $userId; ?>&page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?action=<?php echo $action; ?>&user=<?php echo $userId; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?action=<?php echo $action; ?>&user=<?php echo $userId; ?>&page=<?php echo $page + 1; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
