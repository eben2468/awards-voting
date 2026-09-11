<?php
/**
 * Admin - View Payout Request
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$pdo = getDBConnection();
$id = intval($_GET['id'] ?? 0);

// Fetch Payout Request with details
$stmt = $pdo->prepare("
    SELECT p.*, u.fullname as organizer_name, u.email as organizer_email, 
           e.title as event_title, e.slug as event_slug,
           (SELECT COALESCE(SUM(tr.amount), 0) FROM transactions tr WHERE tr.event_id = e.id AND tr.nominee_id IS NOT NULL AND tr.status = 'success') as total_votes_earned,
           (SELECT COALESCE(SUM(tr.amount), 0) FROM transactions tr WHERE tr.event_id = e.id AND tr.ticket_category_id IS NOT NULL AND tr.status = 'success') as total_tickets_earned,
           (SELECT COALESCE(SUM(tr.quantity), 0) FROM transactions tr WHERE tr.event_id = e.id AND tr.nominee_id IS NOT NULL AND tr.status = 'success') as total_votes_cast,
           (SELECT COALESCE(SUM(tr.quantity), 0) FROM transactions tr WHERE tr.event_id = e.id AND tr.ticket_category_id IS NOT NULL AND tr.status = 'success') as total_tickets_sold
    FROM organizer_payouts p 
    JOIN users u ON p.organizer_id = u.id 
    JOIN events e ON p.event_id = e.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payout = $stmt->fetch();

if (!$payout) {
    setFlash('danger', 'Payout request not found.');
    redirect('admin/payouts/');
}

$details = json_decode($payout['payment_details'], true);
$page_title = 'Payout Request #' . $id;

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $action = $_POST['action'];
        $notes = sanitize($_POST['admin_notes'] ?? '');
        
        $status = match($action) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'process' => 'processed',
            default => null
        };
        
        if ($status) {
            try {
                $pdo->beginTransaction();
                
                $updateSQL = "UPDATE organizer_payouts SET status = ?, admin_notes = ?, processed_at = " . ($status === 'processed' ? 'NOW()' : 'processed_at') . " WHERE id = ?";
                $stmt = $pdo->prepare($updateSQL);
                $stmt->execute([$status, $notes, $id]);
                
                // Notify Organizer
                $notifTitle = "Payout Request " . ucfirst($status);
                $notifMessage = "Your payout request for " . $payout['event_title'] . " has been " . $status . ".";
                if ($notes) {
                    $notifMessage .= " Admin notes: " . $notes;
                }
                createNotification(
                    $payout['organizer_id'],
                    $notifTitle,
                    $notifMessage,
                    $status === 'rejected' ? 'danger' : 'success',
                    "organizer/payouts/"
                );

                $pdo->commit();
                setFlash('success', 'Payout request ' . $status . ' successfully.');
                redirect('admin/payouts/view.php?id=' . $id);
            } catch (PDOException $e) {
                $pdo->rollBack();
                setFlash('danger', 'Failed to update payout request.');
            }
        }
    }
}

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
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="index.php">Payout Requests</a></li>
                            <li class="breadcrumb-item active">View Details</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">Payout Request Details</h2>
                </div>
                <div class="d-flex gap-2">
                    <button onclick="window.print()" class="btn btn-outline-secondary">
                        <i class="bi bi-printer me-2"></i>Print
                    </button>
                    <a href="index.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>Back to List
                    </a>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Main Info -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Request Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Organizer</label>
                                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($payout['organizer_name']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($payout['organizer_email']); ?></small>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Event</label>
                                    <p class="fw-bold mb-0"><?php echo htmlspecialchars($payout['event_title']); ?></p>
                                    <a href="<?php echo SITE_URL; ?>/event-details.php?slug=<?php echo $payout['event_slug']; ?>" target="_blank" class="small text-decoration-none">View Event Page <i class="bi bi-box-arrow-up-right"></i></a>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Amount Requested</label>
                                    <h3 class="text-primary mb-0">GHS <?php echo number_format($payout['amount'], 2); ?></h3>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Status</label>
                                    <div>
                                        <span class="badge fs-6 bg-<?php echo match($payout['status']) {
                                            'pending' => 'warning',
                                            'approved' => 'info',
                                            'processed' => 'success',
                                            'rejected' => 'danger',
                                            default => 'secondary'
                                        }; ?>"><?php echo strtoupper($payout['status']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Requested On</label>
                                    <p class="mb-0"><?php echo date('F d, Y H:i:s', strtotime($payout['requested_at'])); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <label class="text-muted small text-uppercase">Processed On</label>
                                    <p class="mb-0"><?php echo $payout['processed_at'] ? date('F d, Y H:i:s', strtotime($payout['processed_at'])) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Event Financial Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center g-4 mb-4">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <label class="text-muted small text-uppercase d-block mb-1">Total Earned</label>
                                        <span class="h5 mb-0">GHS <?php echo number_format($payout['total_votes_earned'] + $payout['total_tickets_earned'], 2); ?></span>
                                        <div class="small text-muted mt-1">
                                            V: GHS <?php echo number_format($payout['total_votes_earned'], 2); ?> | T: GHS <?php echo number_format($payout['total_tickets_earned'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <label class="text-muted small text-uppercase d-block mb-1">Total Votes & Tickets</label>
                                        <span class="h5 mb-0"><?php echo number_format($payout['total_votes_cast'] + $payout['total_tickets_sold']); ?></span>
                                        <div class="small text-muted mt-1">
                                            Votes: <?php echo number_format($payout['total_votes_cast']); ?> | Tkts: <?php echo number_format($payout['total_tickets_sold']); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded border-start border-4 border-primary">
                                        <label class="text-muted small text-uppercase d-block mb-1">This Payout</label>
                                        <span class="h5 mb-0 text-primary">GHS <?php echo number_format($payout['amount'], 2); ?></span>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mb-3">Recent Transactions for this Event</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Ref</th>
                                            <th>Amount</th>
                                            <th>Method</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $pdo->prepare("SELECT * FROM transactions WHERE event_id = ? AND status = 'success' ORDER BY created_at DESC LIMIT 5");
                                        $stmt->execute([$payout['event_id']]);
                                        $recent_txs = $stmt->fetchAll();
                                        
                                        if (empty($recent_txs)): ?>
                                            <tr><td colspan="4" class="text-center text-muted py-3">No transactions found.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($recent_txs as $tx): ?>
                                                <tr>
                                                    <td><small class="text-muted"><?php echo $tx['reference']; ?></small></td>
                                                    <td>GHS <?php echo number_format($tx['amount'], 2); ?></td>
                                                    <td><span class="badge bg-light text-dark border"><?php echo strtoupper($tx['payment_method']); ?></span></td>
                                                    <td><small><?php echo date('M d, H:i', strtotime($tx['created_at'])); ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-end mt-2">
                                <a href="../votes/?event=<?php echo $payout['event_id']; ?>" class="small text-decoration-none">View All Votes & Transactions <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Info -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Payment Method</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                                    <i class="bi bi-<?php echo $payout['payment_method'] === 'bank' ? 'bank' : 'phone'; ?> fs-4"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 text-uppercase"><?php echo htmlspecialchars($payout['payment_method']); ?></h6>
                                    <small class="text-muted">Settlement Account</small>
                                </div>
                            </div>
                            <hr>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted">Provider:</td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars($details['name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Account Name:</td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars($details['account_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Account Number:</td>
                                    <td class="text-end fw-bold"><code><?php echo htmlspecialchars($details['account_number']); ?></code></td>
                                </tr>
                                <?php if (isset($details['branch']) && $details['branch']): ?>
                                <tr>
                                    <td class="text-muted">Branch:</td>
                                    <td class="text-end fw-bold"><?php echo htmlspecialchars($details['branch']); ?></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Admin Actions</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($payout['admin_notes']): ?>
                                <div class="mb-4">
                                    <label class="text-muted small text-uppercase mb-2">Previous Notes</label>
                                    <div class="p-3 bg-light rounded border-start border-4 border-info">
                                        <?php echo nl2br(htmlspecialchars($payout['admin_notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($payout['status'] !== 'processed' && $payout['status'] !== 'rejected'): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Admin Notes</label>
                                        <textarea name="admin_notes" class="form-control" rows="3" placeholder="Add notes for the organizer..."></textarea>
                                    </div>
                                    <div class="d-grid gap-2">
                                        <?php if ($payout['status'] === 'pending'): ?>
                                            <button type="submit" name="action" value="approve" class="btn btn-info text-white">
                                                <i class="bi bi-check-circle me-2"></i>Approve Request
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($payout['status'] === 'approved'): ?>
                                            <button type="submit" name="action" value="process" class="btn btn-success">
                                                <i class="bi bi-cash-stack me-2"></i>Mark as Processed
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" name="action" value="reject" class="btn btn-danger" onclick="return confirm('Are you sure you want to reject this request?')">
                                            <i class="bi bi-x-circle me-2"></i>Reject Request
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-secondary mb-0">
                                    <i class="bi bi-info-circle me-2"></i>This request is <strong><?php echo $payout['status']; ?></strong> and cannot be modified.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
