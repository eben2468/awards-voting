<?php
/**
 * Admin - Audit Logs
 * Awards Voting System
 */

require_once '../config/database.php';
require_once '../includes/device-info.php';

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

$ipLocations = cachedIpLocations();
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
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                                        <?php echo e($u['fullname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-auto d-flex gap-2">
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
                                        <th>Device</th>
                                        <th>Location</th>
                                        <th>Record</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>#<?php echo $log['id']; ?></td>
                                            <td>
                                                <?php if ($log['user_name']): ?>
                                                    <strong><?php echo e($log['user_name']); ?></strong>
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
                                            <td>
                                                <?php $device = describeDevice($log['user_agent'] ?? ''); ?>
                                                <span class="log-device" title="<?php echo e((string) ($log['user_agent'] ?? '')); ?>">
                                                    <i class="bi <?php echo $device['icon']; ?>" aria-hidden="true"></i>
                                                    <span>
                                                        <strong><?php echo e($device['name']); ?></strong>
                                                        <?php if ($device['browser'] !== ''): ?>
                                                            <small class="text-muted"><?php echo e($device['browser']); ?></small>
                                                        <?php endif; ?>
                                                    </span>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $ip = (string) ($log['ip_address'] ?? ''); ?>
                                                <?php if (isPublicIp($ip)): ?>
                                                    <?php if (isset($ipLocations[$ip])): ?>
                                                        <span>Near <?php echo e($ipLocations[$ip]); ?></span>
                                                    <?php else: ?>
                                                        <span data-ip-location="<?php echo e($ip); ?>">Looking up...</span>
                                                    <?php endif; ?>
                                                <?php elseif ($ip === 'SYSTEM' || $ip === ''): ?>
                                                    <span class="text-muted">Not applicable</span>
                                                <?php else: ?>
                                                    <span class="text-muted">This computer</span>
                                                <?php endif; ?>
                                                <?php if ($ip !== '' && $ip !== 'SYSTEM'): ?>
                                                    <code class="small d-block"><?php echo e($ip); ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo e((string) ($log['table_name'] ?: '-')); ?>
                                                <?php if ($log['record_id']): ?>
                                                    <small class="text-muted">#<?php echo (int) $log['record_id']; ?></small>
                                                <?php endif; ?>
                                            </td>
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

                        <p class="small text-muted mt-3 mb-0">
                            Locations from an IP address are approximate and can show the network's area rather than the person's. Phone models are shown only when the browser reveals them. IP location data by <a href="https://db-ip.com" target="_blank" rel="noopener">DB-IP</a>.
                        </p>

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
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script src="<?php echo assetUrl('assets/js/log-locations.js'); ?>" data-url="<?php echo SITE_URL; ?>/admin/ajax/ip_location.php"></script>
</body>
</html>
