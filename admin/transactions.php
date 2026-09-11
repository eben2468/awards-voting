<?php
/**
 * Admin Transactions View
 * Awards Voting System
 */

require_once '../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Payment Transactions';
$pdo = getDBConnection();

// Bulk Delete Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $ids = $_POST['transaction_ids'] ?? [];
    if (!empty($ids) && is_array($ids)) {
        $valid_ids = array_filter(array_map('intval', $ids));
        if (!empty($valid_ids)) {
            $placeholders = str_repeat('?,', count($valid_ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id IN ($placeholders)");
            if ($stmt->execute($valid_ids)) {
                setFlash('success', count($valid_ids) . ' transactions deleted successfully.');
            } else {
                setFlash('danger', 'Failed to delete transactions.');
            }
        } else {
            setFlash('warning', 'Invalid transactions selected.');
        }
    } else {
        setFlash('warning', 'No transactions selected.');
    }
    
    // Refresh the page
    $redirectUrl = 'transactions.php';
    $queryParams = [];
    if (!empty($_GET['page'])) $queryParams['page'] = $_GET['page'];
    if (!empty($_GET['status'])) $queryParams['status'] = $_GET['status'];
    if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
    if (!empty($queryParams)) {
        $redirectUrl .= '?' . http_build_query($queryParams);
    }
    redirect($redirectUrl);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$where = [];
$params = [];

if ($status) {
    $where[] = "t.status = ?";
    $params[] = $status;
}

if ($search) {
    $where[] = "(t.reference LIKE ? OR u.fullname LIKE ? OR u.email LIKE ? OR t.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Count total for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    $whereSql
");
$countStmt->execute($params);
$totalTransactions = $countStmt->fetchColumn();
$totalPages = ceil($totalTransactions / $limit);

// Fetch transactions
$stmt = $pdo->prepare("
    SELECT t.*, u.fullname as user_name, u.email as user_email, 
           e.title as event_title, n.name as nominee_name,
           tc.name as ticket_category_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    LEFT JOIN nominees n ON t.nominee_id = n.id
    LEFT JOIN ticket_categories tc ON t.ticket_category_id = tc.id
    $whereSql
    ORDER BY t.created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Stats
$stats = [
    'total_amount' => $pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'success'")->fetchColumn() ?: 0,
    'total_count' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'success'")->fetchColumn(),
    'pending_count' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'pending'")->fetchColumn(),
    'failed_count' => $pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'failed'")->fetchColumn(),
];
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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                <div>
                    <h2 class="mb-1">Payment Transactions</h2>
                    <p class="text-muted mb-0 small">Monitor and review all payment transactions.</p>
                </div>
                <div class="d-flex flex-row flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-danger d-flex align-items-center" id="bulkDeleteBtn" disabled onclick="if(confirm('Are you sure you want to delete the selected transactions?')) document.getElementById('bulkDeleteForm').submit();">
                        <i class="bi bi-trash me-1"></i><span class="d-none d-sm-inline">Delete</span>
                    </button>
                    <a href="sync_pending.php" class="btn btn-sm btn-warning d-flex align-items-center">
                        <i class="bi bi-arrow-clockwise me-1"></i>Sync
                    </a>
                    <a href="pending-notifications.php" class="btn btn-sm btn-primary d-flex align-items-center">
                        <i class="bi bi-bell me-1"></i>Notify
                    </a>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show mb-4">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-cash-stack fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0">GHS <?php echo number_format($stats['total_amount'], 2); ?></h3>
                                    <span class="text-muted">Total Revenue</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-check-circle fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_count']); ?></h3>
                                    <span class="text-muted">Successful Payments</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                                    <i class="bi bi-clock-history fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['pending_count']); ?></h3>
                                    <span class="text-muted">Pending</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-3 p-3 me-3">
                                    <i class="bi bi-x-circle fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['failed_count']); ?></h3>
                                    <span class="text-muted">Failed</span>
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
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search reference, name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="success" <?php echo $status == 'success' ? 'selected' : ''; ?>>Success</option>
                                <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="transactions.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <form id="bulkDeleteForm" method="POST" action="">
                <input type="hidden" name="action" value="bulk_delete">
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onclick="toggleAllCheckboxes(this)">
                                </th>
                                <th>Reference</th>
                                <th>User / Phone</th>
                                <th>Event / Details</th>
                                <th>Amount</th>
                                <th>Qty</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input tx-checkbox" name="transaction_ids[]" value="<?php echo $tx['id']; ?>" onclick="updateBulkDeleteBtn()">
                                        </td>
                                        <td>
                                            <code class="text-primary fw-bold"><?php echo $tx['reference']; ?></code>
                                        </td>
                                        <td>
                                            <?php if ($tx['user_name']): ?>
                                                <div class="fw-bold"><?php echo htmlspecialchars($tx['user_name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($tx['user_email']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Guest User</span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($tx['phone'])): ?>
                                                <div class="small text-primary mt-1">
                                                    <i class="bi bi-phone me-1"></i><?php echo htmlspecialchars($tx['phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small fw-bold"><?php echo htmlspecialchars($tx['event_title']); ?></div>
                                            <?php if (!empty($tx['ticket_category_id'])): ?>
                                                <div class="small text-muted"><i class="bi bi-ticket-perforated me-1"></i>Ticket: <?php echo htmlspecialchars($tx['ticket_category_name'] ?? 'N/A'); ?></div>
                                            <?php else: ?>
                                                <div class="small text-muted">Nominee: <?php echo htmlspecialchars($tx['nominee_name']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-bold">GHS <?php echo number_format($tx['amount'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info bg-opacity-10 text-info"><?php echo number_format($tx['quantity']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo strtoupper($tx['payment_method'] ?: 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($tx['status']) {
                                                'success' => 'success',
                                                'pending' => 'warning',
                                                'failed' => 'danger',
                                                default => 'secondary'
                                            }; ?>"><?php echo ucfirst($tx['status']); ?></span>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo date('M d, Y', strtotime($tx['created_at'])); ?></div>
                                            <div class="small text-muted"><?php echo date('H:i', strtotime($tx['created_at'])); ?></div>
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
                            <ul class="pagination justify-content-center mb-0 flex-wrap">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" title="First Page">&laquo; First</a>
                                </li>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                                
                                <?php 
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++): 
                                ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php 
                                endfor; 
                                
                                if ($endPage < $totalPages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&status=<?php echo urlencode($status); ?>&search=<?php echo urlencode($search); ?>" title="Last Page">Last &raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
            </form>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
    function toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.tx-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = source.checked;
        });
        updateBulkDeleteBtn();
    }

    function updateBulkDeleteBtn() {
        const checkedBoxes = document.querySelectorAll('.tx-checkbox:checked');
        document.getElementById('bulkDeleteBtn').disabled = checkedBoxes.length === 0;
    }
    </script>
</body>
</html>
