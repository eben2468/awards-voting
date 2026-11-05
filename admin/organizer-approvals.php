<?php
/**
 * Admin - Organizer Approvals
 * Awards Voting System
 *
 * New organizer accounts land here as "pending" and cannot log in until an
 * administrator approves them. Approving or rejecting emails the organizer.
 */

require_once '../config/database.php';
require_once '../includes/auth-functions.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Organizer Approvals';
$pdo = getDBConnection();

// Guard against the migration not having been run yet
$migrationReady = true;
try {
    $pdo->query("SELECT approval_status FROM users LIMIT 1");
} catch (PDOException $e) {
    $migrationReady = false;
}

// ---------------------------------------------------------------
// Approve / reject actions
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $migrationReady) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
        redirect('admin/organizer-approvals.php');
    }

    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    $note   = sanitize($_POST['note'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('danger', 'That account no longer exists.');
        redirect('admin/organizer-approvals.php');
    }

    if ($action === 'approve') {
        try {
            $pdo->prepare("UPDATE users SET approval_status = 'approved', approved_at = NOW(),
                           approved_by = ?, approval_note = NULL, status = 'active', updated_at = NOW()
                           WHERE id = ?")
                ->execute([getCurrentUserId(), $userId]);

            logAudit(getCurrentUserId(), 'organizer_approved', 'users', $userId,
                ['approval_status' => $target['approval_status']], ['approval_status' => 'approved']);

            createNotification(
                $userId,
                'Account Approved',
                'Your organizer account has been approved. You can now log in and create events.',
                'success',
                'login.php'
            );

            $emailed = sendOrganizerApprovedEmail($target);

            setFlash('success', htmlspecialchars($target['fullname']) . ' has been approved.'
                . ($emailed ? ' They have been notified by email.'
                            : ' <strong>Note:</strong> the notification email could not be sent &mdash; check your SMTP settings.'));
        } catch (PDOException $e) {
            error_log('Approve organizer error: ' . $e->getMessage());
            setFlash('danger', 'Failed to approve the account.');
        }
        redirect('admin/organizer-approvals.php');
    }

    if ($action === 'reject') {
        try {
            $pdo->prepare("UPDATE users SET approval_status = 'rejected', approval_note = ?,
                           approved_by = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$note ?: null, getCurrentUserId(), $userId]);

            logAudit(getCurrentUserId(), 'organizer_rejected', 'users', $userId,
                ['approval_status' => $target['approval_status']], ['approval_status' => 'rejected', 'note' => $note]);

            sendOrganizerRejectedEmail($target, $note);

            setFlash('warning', htmlspecialchars($target['fullname']) . ' has been rejected and notified by email.');
        } catch (PDOException $e) {
            error_log('Reject organizer error: ' . $e->getMessage());
            setFlash('danger', 'Failed to reject the account.');
        }
        redirect('admin/organizer-approvals.php');
    }

    if ($action === 'revoke') {
        // Move an approved organizer back to pending (blocks further logins)
        try {
            $pdo->prepare("UPDATE users SET approval_status = 'pending', approved_at = NULL,
                           approved_by = ?, updated_at = NOW() WHERE id = ?")
                ->execute([getCurrentUserId(), $userId]);

            logAudit(getCurrentUserId(), 'organizer_access_revoked', 'users', $userId,
                ['approval_status' => $target['approval_status']], ['approval_status' => 'pending']);

            setFlash('warning', htmlspecialchars($target['fullname']) . ' has been moved back to pending and can no longer log in.');
        } catch (PDOException $e) {
            error_log('Revoke organizer error: ' . $e->getMessage());
            setFlash('danger', 'Failed to update the account.');
        }
        redirect('admin/organizer-approvals.php');
    }
}

// ---------------------------------------------------------------
// Load the lists
// ---------------------------------------------------------------
$filter = sanitize($_GET['filter'] ?? 'pending');
if (!in_array($filter, ['pending', 'approved', 'rejected'], true)) {
    $filter = 'pending';
}

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
$accounts = [];

if ($migrationReady) {
    $countStmt = $pdo->query("SELECT approval_status, COUNT(*) AS total FROM users
                              WHERE role = 'organizer' GROUP BY approval_status");
    foreach ($countStmt->fetchAll() as $row) {
        if (isset($counts[$row['approval_status']])) {
            $counts[$row['approval_status']] = (int) $row['total'];
        }
    }

    // The waiting time is measured by MySQL: PHP and the database server may sit
    // in different timezones, so subtracting PHP's time() from a DB timestamp lies.
    $stmt = $pdo->prepare("SELECT u.*, a.fullname AS approver_name,
                                  TIMESTAMPDIFF(MINUTE, u.created_at, NOW()) AS waiting_minutes
                           FROM users u
                           LEFT JOIN users a ON a.id = u.approved_by
                           WHERE u.role = 'organizer' AND u.approval_status = ?
                           ORDER BY u.created_at DESC");
    $stmt->execute([$filter]);
    $accounts = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>

    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                    <h2 class="mb-1">Organizer Approvals</h2>
                    <p class="text-muted mb-0">Review new organizer accounts before they are allowed to sign in.</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/users/" class="btn btn-outline-secondary">
                    <i class="bi bi-people me-2"></i>All Users
                </a>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!$migrationReady): ?>
                <div class="alert alert-danger">
                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>Database update required</h5>
                    <p class="mb-2">The approval columns have not been added to the database yet.</p>
                    <a href="<?php echo SITE_URL; ?>/database/run_auth_migration.php" class="btn btn-danger btn-sm">
                        <i class="bi bi-play-fill me-1"></i>Run the migration now
                    </a>
                </div>
            <?php else: ?>

                <!-- Summary -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <a href="?filter=pending" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 <?php echo $filter === 'pending' ? 'border-start border-4 border-warning' : ''; ?>">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center"
                                         style="width:52px;height:52px;font-size:1.4rem;">
                                        <i class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div>
                                        <div class="h3 mb-0 fw-bold text-dark"><?php echo $counts['pending']; ?></div>
                                        <div class="text-muted small">Awaiting approval</div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="?filter=approved" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 <?php echo $filter === 'approved' ? 'border-start border-4 border-success' : ''; ?>">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center"
                                         style="width:52px;height:52px;font-size:1.4rem;">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div>
                                        <div class="h3 mb-0 fw-bold text-dark"><?php echo $counts['approved']; ?></div>
                                        <div class="text-muted small">Approved organizers</div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <a href="?filter=rejected" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 <?php echo $filter === 'rejected' ? 'border-start border-4 border-danger' : ''; ?>">
                                <div class="card-body d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center"
                                         style="width:52px;height:52px;font-size:1.4rem;">
                                        <i class="bi bi-x-circle"></i>
                                    </div>
                                    <div>
                                        <div class="h3 mb-0 fw-bold text-dark"><?php echo $counts['rejected']; ?></div>
                                        <div class="text-muted small">Rejected</div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- List -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <?php echo ucfirst($filter); ?> organizers
                            <span class="badge bg-secondary ms-1"><?php echo count($accounts); ?></span>
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Organizer</th>
                                    <th>Contact</th>
                                    <th>Registered</th>
                                    <th><?php echo $filter === 'pending' ? 'Waiting' : 'Reviewed'; ?></th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                                        <?php if ($filter === 'pending'): ?>
                                            Nothing to review &mdash; every organizer account has been handled.
                                        <?php else: ?>
                                            No <?php echo $filter; ?> organizer accounts.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($accounts as $account): ?>
                                    <?php
                                    $waitingMinutes = max(0, (int) $account['waiting_minutes']);
                                    $waitingHours = $waitingMinutes / 60;
                                    $waitLabel = $waitingHours < 1
                                        ? $waitingMinutes . ' min'
                                        : ($waitingHours < 48 ? round($waitingHours) . ' hrs' : round($waitingHours / 24) . ' days');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $account['avatar']
                                                        ? SITE_URL . '/uploads/avatars/' . $account['avatar']
                                                        : 'https://ui-avatars.com/api/?name=' . urlencode($account['fullname']); ?>"
                                                     class="rounded-circle me-3" style="width:42px;height:42px;object-fit:cover;" alt="">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($account['fullname']); ?></div>
                                                    <div class="small text-muted">ID #<?php echo (int) $account['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <a href="mailto:<?php echo htmlspecialchars($account['email']); ?>" class="text-decoration-none">
                                                    <i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($account['email']); ?>
                                                </a>
                                            </div>
                                            <?php if (!empty($account['phone'])): ?>
                                                <div class="small text-muted">
                                                    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $account['phone']); ?>"
                                                       target="_blank" rel="noopener" class="text-success text-decoration-none">
                                                        <i class="bi bi-whatsapp me-1"></i><?php echo htmlspecialchars($account['phone']); ?>
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <div class="small text-muted fst-italic">No phone provided</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php echo date('M d, Y', strtotime($account['created_at'])); ?><br>
                                            <span class="text-muted"><?php echo date('g:i A', strtotime($account['created_at'])); ?></span>
                                        </td>
                                        <td class="small">
                                            <?php if ($filter === 'pending'): ?>
                                                <span class="badge bg-<?php echo $waitingHours > 24 ? 'danger' : 'warning'; ?> text-dark">
                                                    <?php echo $waitLabel; ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo $account['approved_at']
                                                    ? date('M d, Y', strtotime($account['approved_at']))
                                                    : '<span class="text-muted">&mdash;</span>'; ?><br>
                                                <span class="text-muted">
                                                    <?php echo $account['approver_name']
                                                        ? 'by ' . htmlspecialchars($account['approver_name'])
                                                        : ''; ?>
                                                </span>
                                                <?php if ($filter === 'rejected' && !empty($account['approval_note'])): ?>
                                                    <div class="text-danger small mt-1" title="<?php echo htmlspecialchars($account['approval_note']); ?>">
                                                        <i class="bi bi-chat-left-text me-1"></i>
                                                        <?php echo htmlspecialchars(mb_strimwidth($account['approval_note'], 0, 42, '...')); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($account['approval_status'] !== 'approved'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $account['id']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-check-lg me-1"></i>Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($account['approval_status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm btn-reject"
                                                        data-user-id="<?php echo (int) $account['id']; ?>"
                                                        data-user-name="<?php echo htmlspecialchars($account['fullname']); ?>">
                                                    <i class="bi bi-x-lg me-1"></i>Reject
                                                </button>
                                            <?php elseif ($account['approval_status'] === 'approved'): ?>
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('Move this organizer back to pending? They will not be able to log in until approved again.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="revoke">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $account['id']; ?>">
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">
                                                        <i class="bi bi-slash-circle me-1"></i>Revoke
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert alert-light border small text-muted mt-4">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Approval emails are sent through the address configured in
                    <a href="<?php echo SITE_URL; ?>/admin/settings.php">Settings &rsaquo; Email (SMTP) Settings</a>.
                    You can switch the approval requirement off entirely on that page.
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="user_id" id="rejectUserId">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Organizer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">You are about to reject <strong id="rejectUserName"></strong>.
                            They will be emailed and told how to reach you.</p>
                        <div class="mb-2">
                            <label for="note" class="form-label">Reason (optional, included in the email)</label>
                            <textarea class="form-control" id="note" name="note" rows="3"
                                      placeholder="e.g. We could not verify your organisation details."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-lg me-1"></i>Reject Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
        document.querySelectorAll('.btn-reject').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('rejectUserId').value = this.dataset.userId;
                document.getElementById('rejectUserName').textContent = this.dataset.userName;
                new bootstrap.Modal(document.getElementById('rejectModal')).show();
            });
        });
    </script>
</body>
</html>
