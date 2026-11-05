<?php
/**
 * Admin Pending Transaction Notifications
 * Awards Voting System
 */

require_once '../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Notify Pending Transactions';
$pdo = getDBConnection();

// Fetch all pending transactions with user info
$stmt = $pdo->prepare("
    SELECT t.*, u.fullname as user_name, u.email as user_email, 
           e.title as event_title, n.name as nominee_name
    FROM transactions t
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN events e ON t.event_id = e.id
    LEFT JOIN nominees n ON t.nominee_id = n.id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
");
$stmt->execute();
$pendingTransactions = $stmt->fetchAll();

$defaultMessage = "We noticed that your recent transaction for Niena with nominee code NN002 did not complete successfully. Don't worry - this happens sometimes due to network issues or payment provider delays.\n\nPlease try again to complete your vote using *920*137# or click the link https://votehubgh.com/event-details.php?slug=face-of-ncass-2026-edition.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    
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
    
    <style>
        .user-select-row { cursor: pointer; }
        .user-select-row.selected { background-color: rgba(99, 102, 241, 0.05); }
        .message-preview {
            background: #f8fafc;
            border-radius: 0.5rem;
            padding: 1rem;
            border-left: 4px solid #6366f1;
            font-style: italic;
        }
    </style>
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Notify Pending Transactions</h2>
                    <p class="text-muted mb-0">Send a reminder to users whose payments are still pending.</p>
                </div>
                <a href="transactions.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Transactions
                </a>
            </div>

            <div class="row g-xl-5 g-4">
                <!-- Left Column: User Selection -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Select Users</h5>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                                <label class="form-check-label" for="selectAll">Select All</label>
                            </div>
                        </div>
                        <div class="table-responsive" style="max-height: 700px;">
                            <table class="table table-hover align-middle mb-0" id="usersTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th width="40"></th>
                                        <th>User / Phone</th>
                                        <th>Details</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pendingTransactions)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-check2-circle fs-1 d-block mb-3"></i>
                                                No pending transactions found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pendingTransactions as $tx): ?>
                                            <tr class="user-select-row" data-id="<?php echo $tx['id']; ?>">
                                                <td>
                                                    <input class="form-check-input user-checkbox" type="checkbox" value="<?php echo $tx['id']; ?>" 
                                                           data-event="<?php echo htmlspecialchars($tx['event_title']); ?>"
                                                           data-nominee="<?php echo htmlspecialchars($tx['nominee_name']); ?>">
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($tx['user_name'] ?: 'Guest User'); ?></div>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($tx['phone'] ?: $tx['user_email'] ?: 'No contact'); ?></small>
                                                </td>
                                                <td>
                                                    <div class="small text-truncate" style="max-width: 180px;"><?php echo htmlspecialchars($tx['event_title']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($tx['nominee_name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-nowrap">GHS <?php echo number_format($tx['amount'], 2); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Message Composition -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm sticky-lg-top" style="top: 100px; z-index: 10;">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Compose Message</h5>
                        </div>
                        <div class="card-body">
                            <form id="notificationForm">
                                <div class="mb-3">
                                    <label class="form-label">SMS Message Content</label>
                                    <textarea id="notifMessage" class="form-control" rows="10" required><?php echo $defaultMessage; ?></textarea>
                                    <div class="form-text">
                                        You can customize this message before sending.
                                    </div>
                                </div>

                                <div class="alert alert-info border-0 small mb-4">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Selected: <span id="selectedCount" class="fw-bold">0</span> users
                                </div>

                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold" id="sendBtn" disabled>
                                    <i class="bi bi-send me-2"></i>Send SMS Messages
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Success Modal -->
    <div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-body text-center p-5">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                    </div>
                    <h4 class="mb-3">Notifications Sent!</h4>
                    <p class="text-muted mb-4" id="resultMessage"></p>
                    <button type="button" class="btn btn-primary px-5" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const usersTable = document.getElementById('usersTable');
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const selectedCountSpan = document.getElementById('selectedCount');
            const sendBtn = document.getElementById('sendBtn');
            const notificationForm = document.getElementById('notificationForm');
            const resultModal = new bootstrap.Modal(document.getElementById('resultModal'));
            const resultMessage = document.getElementById('resultMessage');

            // Update selected count and button state
            function updateSelection() {
                const checkedCount = document.querySelectorAll('.user-checkbox:checked').length;
                selectedCountSpan.textContent = checkedCount;
                sendBtn.disabled = checkedCount === 0;
                
                // Update row styling
                document.querySelectorAll('.user-select-row').forEach(row => {
                    const cb = row.querySelector('.user-checkbox');
                    if (cb && cb.checked) {
                        row.classList.add('selected');
                    } else {
                        row.classList.remove('selected');
                    }
                });
            }

            // Initial count
            updateSelection();

            // Row click selection (using event delegation for better performance)
            usersTable.addEventListener('click', function(e) {
                const row = e.target.closest('.user-select-row');
                if (row && e.target.type !== 'checkbox') {
                    const cb = row.querySelector('.user-checkbox');
                    if (cb) {
                        cb.checked = !cb.checked;
                        updateSelection();
                    }
                }
            });

            // Checkbox change
            usersTable.addEventListener('change', function(e) {
                if (e.target.classList.contains('user-checkbox')) {
                    updateSelection();
                }
            });

            // Select All
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.user-checkbox').forEach(cb => {
                    cb.checked = this.checked;
                });
                updateSelection();
            });

            // Form Submission
            notificationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
                const messageTemplate = document.getElementById('notifMessage').value;

                if (selectedIds.length === 0) return;

                if (!confirm(`Are you sure you want to send SMS to ${selectedIds.length} users?`)) return;

                sendBtn.disabled = true;
                sendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending SMS...';

                fetch('<?php echo SITE_URL; ?>/api/admin/send_pending_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        transaction_ids: selectedIds,
                        message: messageTemplate,
                        send_sms: true
                    })
                })
                .then(async response => {
                    const text = await response.text();
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Server response was not JSON:', text);
                        throw new Error('Server returned an invalid response. Please check logs.');
                    }
                })
                .then(data => {
                    if (data.status === 'success') {
                        resultMessage.textContent = data.message;
                        resultModal.show();
                        setTimeout(() => location.reload(), 3000);
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error occurred'));
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = '<i class="bi bi-send me-2"></i>Send SMS Messages';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="bi bi-send me-2"></i>Send SMS Messages';
                });
            });
        });
    </script>
</body>
</html>
