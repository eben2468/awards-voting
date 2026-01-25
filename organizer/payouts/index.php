<?php
/**
 * Organizer - Payout Management
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Payout Management';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

// Handle Settlement Method Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_method') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $type = sanitize($_POST['method_type'] ?? '');
        $name = sanitize($_POST['method_name'] ?? '');
        $acc_name = sanitize($_POST['account_name'] ?? '');
        $acc_num = sanitize($_POST['account_number'] ?? '');
        $branch = sanitize($_POST['bank_branch'] ?? '');
        
        if (empty($type) || empty($name) || empty($acc_name) || empty($acc_num)) {
            setFlash('danger', 'All required fields must be filled.');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO organizer_settlement_methods (organizer_id, method_type, method_name, account_name, account_number, bank_branch) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$organizer_id, $type, $name, $acc_name, $acc_num, $branch]);
                setFlash('success', 'Settlement method added successfully.');
                redirect('organizer/payouts/');
            } catch (PDOException $e) {
                setFlash('danger', 'Failed to add settlement method.');
            }
        }
    }
}

// Handle Payout Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_payout') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $event_id = intval($_POST['event_id'] ?? 0);
        $method_id = intval($_POST['method_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        
        // Verify ownership and balance
        $eventStmt = $pdo->prepare("
            SELECT e.id, e.title, 
                   (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.event_id = e.id AND t.status = 'success') as total_earned,
                   (SELECT COALESCE(SUM(p.amount), 0) FROM organizer_payouts p WHERE p.event_id = e.id AND p.status != 'rejected') as total_withdrawn
            FROM events e 
            WHERE e.id = ? AND e.organizer_id = ?
        ");
        $eventStmt->execute([$event_id, $organizer_id]);
        $eventData = $eventStmt->fetch();
        
        if (!$eventData) {
            setFlash('danger', 'Invalid event selected.');
        } else {
            $available = $eventData['total_earned'] - $eventData['total_withdrawn'];
            if ($amount <= 0 || $amount > $available) {
                setFlash('danger', 'Invalid payout amount requested.');
            } else {
                // Get method details
                $methodStmt = $pdo->prepare("SELECT * FROM organizer_settlement_methods WHERE id = ? AND organizer_id = ?");
                $methodStmt->execute([$method_id, $organizer_id]);
                $method = $methodStmt->fetch();
                
                if (!$method) {
                    setFlash('danger', 'Invalid settlement method.');
                } else {
                    try {
                        $pdo->beginTransaction();
                        
                        $method_details = json_encode([
                            'type' => $method['method_type'],
                            'name' => $method['method_name'],
                            'account_name' => $method['account_name'],
                            'account_number' => $method['account_number'],
                            'branch' => $method['bank_branch']
                        ]);
                        
                        $stmt = $pdo->prepare("INSERT INTO organizer_payouts (organizer_id, event_id, amount, payment_method, payment_details, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $stmt->execute([$organizer_id, $event_id, $amount, $method['method_type'], $method_details]);
                        
                        // Update event table flags (optional but good for quick check)
                        $updateEvent = $pdo->prepare("UPDATE events SET payout_requested = TRUE, payout_request_date = NOW() WHERE id = ?");
                        $updateEvent->execute([$event_id]);
                        
                        $pdo->commit();
                        setFlash('success', 'Payout request submitted successfully.');
                        redirect('organizer/payouts/');
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        setFlash('danger', 'Failed to submit payout request.');
                    }
                }
            }
        }
    }
}

// Fetch Settlement Methods
$methodsStmt = $pdo->prepare("SELECT * FROM organizer_settlement_methods WHERE organizer_id = ? ORDER BY is_default DESC, created_at DESC");
$methodsStmt->execute([$organizer_id]);
$methods = $methodsStmt->fetchAll();

// Fetch Events with Balances
$eventsStmt = $pdo->prepare("
    SELECT e.id, e.title, 
           (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.event_id = e.id AND t.status = 'success') as total_earned,
           (SELECT COALESCE(SUM(p.amount), 0) FROM organizer_payouts p WHERE p.event_id = e.id AND p.status != 'rejected') as total_withdrawn
    FROM events e 
    WHERE e.organizer_id = ?
    ORDER BY e.created_at DESC
");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();

// Fetch Payout History
$historyStmt = $pdo->prepare("
    SELECT p.*, e.title as event_title 
    FROM organizer_payouts p 
    JOIN events e ON p.event_id = e.id 
    WHERE p.organizer_id = ? 
    ORDER BY p.requested_at DESC
");
$historyStmt->execute([$organizer_id]);
$history = $historyStmt->fetchAll();

// Calculate Global Stats
$totalEarned = 0;
$totalWithdrawn = 0;
foreach ($events as $e) {
    $totalEarned += $e['total_earned'];
    $totalWithdrawn += $e['total_withdrawn'];
}
$availableBalance = $totalEarned - $totalWithdrawn;

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
    <?php include '../includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>
        
        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Payout Management</h2>
                    <p class="text-muted mb-0">Manage your earnings and withdrawal requests</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestPayoutModal">
                        <i class="bi bi-cash-stack me-2"></i>Request Payout
                    </button>
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMethodModal">
                        <i class="bi bi-plus-lg me-2"></i>Add Method
                    </button>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body py-4">
                            <h6 class="text-white-50 mb-2">Available Balance</h6>
                            <h2 class="mb-0">GHS <?php echo number_format($availableBalance, 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-4">
                            <h6 class="text-muted mb-2">Total Earned</h6>
                            <h2 class="mb-0">GHS <?php echo number_format($totalEarned, 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body py-4">
                            <h6 class="text-muted mb-2">Total Withdrawn</h6>
                            <h2 class="mb-0">GHS <?php echo number_format($totalWithdrawn, 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Settlement Methods -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Settlement Methods</h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($methods)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-credit-card text-muted display-4"></i>
                                    <p class="text-muted mt-2">No settlement methods added.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($methods as $m): ?>
                                        <div class="list-group-item p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($m['method_name']); ?></h6>
                                                    <p class="small text-muted mb-1"><?php echo htmlspecialchars($m['account_name']); ?></p>
                                                    <code class="small"><?php echo htmlspecialchars($m['account_number']); ?></code>
                                                </div>
                                                <span class="badge bg-<?php echo $m['method_type'] === 'bank' ? 'info' : 'warning'; ?> text-dark">
                                                    <?php echo strtoupper($m['method_type']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payout History -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Payout History</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Event</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($history)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">No payout history found.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($history as $h): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y', strtotime($h['requested_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($h['event_title']); ?></td>
                                                <td><strong>GHS <?php echo number_format($h['amount'], 2); ?></strong></td>
                                                <td><span class="text-uppercase small"><?php echo $h['payment_method']; ?></span></td>
                                                <td>
                                                    <span class="badge bg-<?php echo match($h['status']) {
                                                        'pending' => 'warning',
                                                        'approved' => 'info',
                                                        'processed' => 'success',
                                                        'rejected' => 'danger',
                                                        default => 'secondary'
                                                    }; ?>"><?php echo ucfirst($h['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Method Modal -->
    <div class="modal fade" id="addMethodModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_method">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Settlement Method</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Method Type</label>
                            <select name="method_type" class="form-select" required onchange="toggleBankFields(this.value)">
                                <option value="momo">Mobile Money</option>
                                <option value="bank">Bank Account</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Provider/Bank Name</label>
                            <input type="text" name="method_name" class="form-control" placeholder="e.g. MTN, Telecel, GCB Bank" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Name</label>
                            <input type="text" name="account_name" class="form-control" placeholder="Full name on account" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account/Phone Number</label>
                            <input type="text" name="account_number" class="form-control" required>
                        </div>
                        <div class="mb-3 d-none" id="bankBranchField">
                            <label class="form-label">Bank Branch</label>
                            <input type="text" name="bank_branch" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Method</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Request Payout Modal -->
    <div class="modal fade" id="requestPayoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="request_payout">
                    <div class="modal-header">
                        <h5 class="modal-title">Request Payout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($methods)): ?>
                            <div class="alert alert-warning">Please add a settlement method first.</div>
                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label">Select Event</label>
                                <select name="event_id" class="form-select" required onchange="updateAvailableAmount(this)">
                                    <option value="">Choose Event</option>
                                    <?php foreach ($events as $e): 
                                        $avail = $e['total_earned'] - $e['total_withdrawn'];
                                        if ($avail > 0):
                                    ?>
                                        <option value="<?php echo $e['id']; ?>" data-available="<?php echo $avail; ?>">
                                            <?php echo htmlspecialchars($e['title']); ?> (Avail: GHS <?php echo number_format($avail, 2); ?>)
                                        </option>
                                    <?php endif; endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Settlement Method</label>
                                <select name="method_id" class="form-select" required>
                                    <?php foreach ($methods as $m): ?>
                                        <option value="<?php echo $m['id']; ?>">
                                            <?php echo htmlspecialchars($m['method_name'] . ' - ' . $m['account_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Amount to Withdraw (GHS)</label>
                                <input type="number" name="amount" id="payoutAmount" class="form-control" step="0.01" min="1" required>
                                <div class="form-text" id="amountHelp">Select an event to see available funds.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" <?php echo empty($methods) ? 'disabled' : ''; ?>>Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        function toggleBankFields(type) {
            const branchField = document.getElementById('bankBranchField');
            if (type === 'bank') {
                branchField.classList.remove('d-none');
            } else {
                branchField.classList.add('d-none');
            }
        }

        function updateAvailableAmount(select) {
            const selectedOption = select.options[select.selectedIndex];
            const available = selectedOption.getAttribute('data-available');
            const amountInput = document.getElementById('payoutAmount');
            const helpText = document.getElementById('amountHelp');
            
            if (available) {
                amountInput.max = available;
                amountInput.value = available;
                helpText.innerText = `Maximum available: GHS ${parseFloat(available).toLocaleString()}`;
            } else {
                amountInput.removeAttribute('max');
                amountInput.value = '';
                helpText.innerText = 'Select an event to see available funds.';
            }
        }
    </script>
</body>
</html>
