<?php
/**
 * Admin - Payout Requests Management
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Payout Requests';
$pdo = getDBConnection();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $payout_id = intval($_POST['payout_id'] ?? 0);
        $action = $_POST['action'];
        $notes = sanitize($_POST['admin_notes'] ?? '');
        
        $status = match($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'process' => 'processed',
            default => null
        };
        
        if ($status && $payout_id > 0) {
            try {
                $pdo->beginTransaction();
                
                $updateSQL = "UPDATE organizer_payouts SET status = ?, admin_notes = ?, processed_at = " . ($status === 'processed' ? 'NOW()' : 'processed_at') . " WHERE id = ?";
                $stmt = $pdo->prepare($updateSQL);
                $stmt->execute([$status, $notes, $payout_id]);
                
                // If rejected, we might want to reset the event flag if it was the only pending request
                // But for now, let's keep it simple.
                
                $pdo->commit();
                setFlash('success', 'Payout request ' . $status . ' successfully.');
            } catch (PDOException $e) {
                $pdo->rollBack();
                setFlash('danger', 'Failed to update payout request.');
            }
        }
        redirect('admin/payouts/');
    }
}

// Filters
$status_filter = $_GET['status'] ?? '';
$where_clause = "";
$params = [];

if ($status_filter) {
    $where_clause = " WHERE p.status = ? ";
    $params[] = $status_filter;
}

// Fetch Payout Requests
$sql = "
    SELECT p.*, u.fullname as organizer_name, u.email as organizer_email, e.title as event_title 
    FROM organizer_payouts p 
    JOIN users u ON p.organizer_id = u.id 
    JOIN events e ON p.event_id = e.id 
    $where_clause
    ORDER BY p.requested_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payouts = $stmt->fetchAll();

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
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Payout Requests</h2>
                    <p class="text-muted mb-0">Manage organizer withdrawal requests</p>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-filter me-2"></i>Filter: <?php echo $status_filter ? ucfirst($status_filter) : 'All'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="?">All Requests</a></li>
                        <li><a class="dropdown-item" href="?status=pending">Pending</a></li>
                        <li><a class="dropdown-item" href="?status=approved">Approved</a></li>
                        <li><a class="dropdown-item" href="?status=processed">Processed</a></li>
                        <li><a class="dropdown-item" href="?status=rejected">Rejected</a></li>
                    </ul>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Organizer</th>
                                <th>Event</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payouts)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">No payout requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payouts as $p): 
                                    $details = json_decode($p['payment_details'], true);
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold"><?php echo htmlspecialchars($p['organizer_name']); ?></span>
                                                <small class="text-muted"><?php echo htmlspecialchars($p['organizer_email']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['event_title']); ?></td>
                                        <td><span class="fw-bold text-primary">GHS <?php echo number_format($p['amount'], 2); ?></span></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?php echo strtoupper($p['payment_method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($p['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'processed' => 'success',
                                                'rejected' => 'danger',
                                                default => 'secondary'
                                            }; ?>"><?php echo ucfirst($p['status']); ?></span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($p['requested_at'])); ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $p['id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
