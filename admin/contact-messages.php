<?php
/**
 * Contact Messages Admin Panel
 * Awards Voting System
 */

require_once '../config/database.php';

// Check admin privileges
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'Contact Messages';

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;
$is_ajax = isset($_GET['ajax']);

if ($action && $id && ($action === 'delete' && !isset($_GET['confirm']) && !$is_ajax || verifyCSRFToken($_GET['token'] ?? ''))) {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->execute([$id]);
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => 'read']);
                exit;
            }
            setFlash('success', 'Message marked as read.');
            break;
            
        case 'mark_replied':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
            $stmt->execute([$id]);
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => 'replied']);
                exit;
            }
            setFlash('success', 'Message marked as replied.');
            break;
            
        case 'mark_closed':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'closed' WHERE id = ?");
            $stmt->execute([$id]);
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => 'closed']);
                exit;
            }
            setFlash('success', 'Message closed.');
            break;
            
        case 'delete':
            if (isset($_GET['confirm']) || $is_ajax) {
                $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
                $stmt->execute([$id]);
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
                setFlash('success', 'Message deleted successfully.');
            } else {
                // Show confirmation dialog
                $confirm_delete = true;
                $delete_id = $id;
            }
            break;
    }
    
    if (!$is_ajax && !isset($confirm_delete)) {
        redirect('admin/contact-messages.php');
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get messages count for pagination
$pdo = getDBConnection();
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $where_clause");
$count_stmt->execute($params);
$total_messages = $count_stmt->fetchColumn();

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_messages / $limit);

// Get messages
$stmt = $pdo->prepare("
    SELECT * FROM contact_messages 
    $where_clause
    ORDER BY created_at DESC
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_messages,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_messages,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_messages
    FROM contact_messages
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch();
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
                    <h2 class="mb-1">Contact Messages</h2>
                    <p class="text-muted mb-0">Manage and respond to messages submitted via the contact form.</p>
                </div>
            </div>

            <?php 
            $flash = getFlash();
            if ($flash): 
            ?>
                <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?> alert-dismissible fade show mb-4" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($confirm_delete)): ?>
                <div class="alert alert-warning border-0 shadow-sm mb-4">
                    <h5 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion</h5>
                    <p class="mb-3">Are you sure you want to delete this contact message? This action cannot be undone.</p>
                    <a href="?action=delete&id=<?php echo $delete_id; ?>&confirm=1&token=<?php echo generateCSRFToken(); ?>" class="btn btn-danger btn-sm px-3 me-2">Yes, Delete</a>
                    <a href="contact-messages.php" class="btn btn-secondary btn-sm px-3">Cancel</a>
                </div>
            <?php endif; ?>

            <!-- Stats Row -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3">
                                    <i class="bi bi-envelope fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['total'] ?? 0); ?></h3>
                                    <span class="text-muted">Total Messages</span>
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
                                    <i class="bi bi-envelope-open fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['new_messages'] ?? 0); ?></h3>
                                    <span class="text-muted">New Messages</span>
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
                                    <i class="bi bi-eye fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['read_messages'] ?? 0); ?></h3>
                                    <span class="text-muted">Read Messages</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                                    <i class="bi bi-reply fs-4"></i>
                                </div>
                                <div>
                                    <h3 class="mb-0"><?php echo number_format($stats['replied_messages'] ?? 0); ?></h3>
                                    <span class="text-muted">Replied Messages</span>
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
                                <input type="text" name="search" class="form-control border-start-0" placeholder="Search by name, email, subject, or message..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                                <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="contact-messages.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th style="width: 150px;" class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="bi bi-envelope display-3 d-block mb-3 text-muted" style="opacity: 0.5;"></i>
                                        <h5>No messages found</h5>
                                        <p class="mb-0">There are no contact messages matching your criteria.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr class="<?php echo $msg['status'] === 'new' ? 'table-warning bg-opacity-10 fw-semibold' : ''; ?>" id="msg-row-<?php echo $msg['id']; ?>">
                                        <td>#<?php echo $msg['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar me-2 rounded-circle bg-secondary bg-opacity-10 d-flex align-items-center justify-content-center fw-bold text-secondary" style="width: 35px; height: 35px; font-size: 0.85rem;">
                                                    <?php echo strtoupper(substr($msg['name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($msg['name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($msg['email']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($msg['subject']); ?>">
                                                <?php echo htmlspecialchars($msg['subject']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($msg['status']) {
                                                case 'new':
                                                    $status_class = 'bg-warning text-warning bg-opacity-10';
                                                    $status_text = 'New';
                                                    break;
                                                case 'read':
                                                    $status_class = 'bg-info text-info bg-opacity-10';
                                                    $status_text = 'Read';
                                                    break;
                                                case 'replied':
                                                    $status_class = 'bg-success text-success bg-opacity-10';
                                                    $status_text = 'Replied';
                                                    break;
                                                case 'closed':
                                                    $status_class = 'bg-secondary text-secondary bg-opacity-10';
                                                    $status_text = 'Closed';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                        </td>
                                        <td>
                                            <div class="small"><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></div>
                                            <div class="small text-muted"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="d-inline-flex gap-1">
                                                <button class="btn btn-sm btn-outline-primary view-msg-btn" type="button"
                                                        data-id="<?php echo $msg['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($msg['name']); ?>"
                                                        data-email="<?php echo htmlspecialchars($msg['email']); ?>"
                                                        data-subject="<?php echo htmlspecialchars($msg['subject']); ?>"
                                                        data-message="<?php echo htmlspecialchars($msg['message']); ?>"
                                                        data-status="<?php echo $msg['status']; ?>"
                                                        data-date="<?php echo date('M j, Y h:i A', strtotime($msg['created_at'])); ?>"
                                                        data-ip="<?php echo htmlspecialchars($msg['ip_address'] ?? 'N/A'); ?>"
                                                        data-ua="<?php echo htmlspecialchars($msg['user_agent'] ?? 'N/A'); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewMessageModal">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="?action=mark_read&id=<?php echo $msg['id']; ?>&token=<?php echo generateCSRFToken(); ?>"><i class="bi bi-envelope-open me-2"></i>Mark Read</a></li>
                                                        <li><a class="dropdown-item" href="?action=mark_replied&id=<?php echo $msg['id']; ?>&token=<?php echo generateCSRFToken(); ?>"><i class="bi bi-reply me-2"></i>Mark Replied</a></li>
                                                        <li><a class="dropdown-item" href="?action=mark_closed&id=<?php echo $msg['id']; ?>&token=<?php echo generateCSRFToken(); ?>"><i class="bi bi-check-circle me-2"></i>Mark Closed</a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $msg['id']; ?>"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer bg-white border-0 py-3">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&search=<?php echo $search; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title" id="viewMessageModalLabel">
                        <i class="bi bi-envelope-open me-2 text-primary"></i>Message Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Sender Name</label>
                            <strong id="modalSenderName" class="fs-6 text-dark"></strong>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Email Address</label>
                            <a id="modalSenderEmail" href="" class="fw-bold fs-6 text-primary text-decoration-none"></a>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Subject</label>
                            <span id="modalSubject" class="fw-semibold text-dark"></span>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small d-block mb-1">Date Received</label>
                            <span id="modalDate" class="text-dark"></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="text-muted small d-block mb-1">Message</label>
                        <div class="p-3 bg-light rounded border text-dark" style="white-space: pre-wrap; min-height: 120px;" id="modalMessageContent"></div>
                    </div>

                    <div class="row g-3 pt-3 border-top bg-light p-2 rounded mx-0">
                        <div class="col-md-4">
                            <small class="text-muted d-block"><i class="bi bi-pc-display me-1"></i>IP Address:</small>
                            <small id="modalIpAddress" class="fw-medium text-dark"></small>
                        </div>
                        <div class="col-md-8">
                            <small class="text-muted d-block"><i class="bi bi-browser-chrome me-1"></i>User Agent:</small>
                            <small id="modalUserAgent" class="fw-medium text-dark text-break"></small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <div>
                        <button type="button" class="btn btn-danger" id="modalDeleteBtn">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="" class="btn btn-primary" id="modalReplyBtn">
                            <i class="bi bi-reply me-1"></i>Reply via Email
                        </a>
                        <button type="button" class="btn btn-success" id="modalMarkRepliedBtn">
                            <i class="bi bi-check2 me-1"></i>Mark Replied
                        </button>
                        <button type="button" class="btn btn-secondary" id="modalCloseStatusBtn">
                            <i class="bi bi-x-circle me-1"></i>Close Message
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const viewMessageModalEl = document.getElementById('viewMessageModal');
        if (!viewMessageModalEl) return;
        
        const viewMessageModal = new bootstrap.Modal(viewMessageModalEl);

        const modalSenderName = document.getElementById('modalSenderName');
        const modalSenderEmail = document.getElementById('modalSenderEmail');
        const modalSubject = document.getElementById('modalSubject');
        const modalDate = document.getElementById('modalDate');
        const modalMessageContent = document.getElementById('modalMessageContent');
        const modalIpAddress = document.getElementById('modalIpAddress');
        const modalUserAgent = document.getElementById('modalUserAgent');
        
        const modalDeleteBtn = document.getElementById('modalDeleteBtn');
        const modalReplyBtn = document.getElementById('modalReplyBtn');
        const modalMarkRepliedBtn = document.getElementById('modalMarkRepliedBtn');
        const modalCloseStatusBtn = document.getElementById('modalCloseStatusBtn');

        // Attach event listeners to all View buttons
        document.querySelectorAll('.view-msg-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const email = this.getAttribute('data-email');
                const subject = this.getAttribute('data-subject');
                const message = this.getAttribute('data-message');
                const status = this.getAttribute('data-status');
                const date = this.getAttribute('data-date');
                const ip = this.getAttribute('data-ip');
                const ua = this.getAttribute('data-ua');

                // Populate Modal Fields
                modalSenderName.textContent = name;
                modalSenderEmail.textContent = email;
                modalSenderEmail.href = 'mailto:' + email + '?subject=Re: ' + encodeURIComponent(subject);
                modalSubject.textContent = subject;
                modalDate.textContent = date;
                modalMessageContent.textContent = message;
                modalIpAddress.textContent = ip;
                modalUserAgent.textContent = ua;

                // Configure action buttons in the modal
                modalDeleteBtn.onclick = function() {
                    if (confirm('Are you sure you want to delete this message?')) {
                        window.location.href = 'contact-messages.php?action=delete&id=' + id + '&confirm=1&token=<?php echo generateCSRFToken(); ?>';
                    }
                };

                modalReplyBtn.href = 'mailto:' + email + '?subject=Re: ' + encodeURIComponent(subject);
                
                modalMarkRepliedBtn.onclick = function() {
                    window.location.href = 'contact-messages.php?action=mark_replied&token=<?php echo generateCSRFToken(); ?>&id=' + id;
                };

                modalCloseStatusBtn.onclick = function() {
                    window.location.href = 'contact-messages.php?action=mark_closed&token=<?php echo generateCSRFToken(); ?>&id=' + id;
                };

                // If message status is 'new', mark it as read in the background
                if (status === 'new') {
                    fetch('contact-messages.php?action=mark_read&token=<?php echo generateCSRFToken(); ?>&id=' + id + '&ajax=1')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update the status badge on the table row dynamically without reload
                                const row = document.getElementById('msg-row-' + id);
                                if (row) {
                                    row.classList.remove('table-warning', 'fw-semibold');
                                    const badge = row.querySelector('.badge');
                                    if (badge) {
                                        badge.className = 'badge bg-info text-info bg-opacity-10';
                                        badge.textContent = 'Read';
                                    }
                                }
                                // Also update data-status attribute
                                btn.setAttribute('data-status', 'read');
                                
                                // Decrease the count on "New Messages" stats card dynamically!
                                const newMsgsCountEl = document.querySelector('.bg-warning + div h3');
                                if (newMsgsCountEl) {
                                    let count = parseInt(newMsgsCountEl.textContent.replace(/,/g, ''), 10);
                                    if (!isNaN(count) && count > 0) {
                                        newMsgsCountEl.textContent = (count - 1).toLocaleString();
                                    }
                                }
                                
                                // Increase the count on "Read Messages" stats card dynamically!
                                const readMsgsCountEl = document.querySelector('.bg-info + div h3');
                                if (readMsgsCountEl) {
                                    let count = parseInt(readMsgsCountEl.textContent.replace(/,/g, ''), 10);
                                    if (!isNaN(count)) {
                                        readMsgsCountEl.textContent = (count + 1).toLocaleString();
                                    }
                                }
                            }
                        })
                        .catch(err => console.error('Error marking as read:', err));
                }
            });
        });
    });
    </script>
</body>
</html>