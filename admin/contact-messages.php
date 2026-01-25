<?php
/**
 * Contact Messages Admin Panel
 * Awards Voting System
 */

$page_title = 'Contact Messages';
require_once '../config/database.php';

// Check admin access
if (!isAdmin()) {
    redirect('login.php');
}

// Handle actions
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

if ($action && $id) {
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'mark_read':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Message marked as read.');
            break;
            
        case 'mark_replied':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Message marked as replied.');
            break;
            
        case 'mark_closed':
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'closed' WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Message closed.');
            break;
            
        case 'delete':
            if (isset($_GET['confirm'])) {
                $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Message deleted successfully.');
            } else {
                // Show confirmation dialog
                $confirm_delete = true;
                $delete_id = $id;
            }
            break;
    }
    
    if (!isset($confirm_delete)) {
        redirect('contact-messages.php');
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
    $where_conditions[] = "(name LIKE ? OR email LIKE ? OR subject LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get messages
$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT * FROM contact_messages 
    $where_clause
    ORDER BY created_at DESC
    LIMIT 50
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

include 'includes/topbar.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="page-header">
        <h1>Contact Messages</h1>
        <p class="text-muted">Manage messages from your contact form</p>
    </div>

    <?php 
    $flash = getFlash();
    if ($flash): 
    ?>
        <div class="alert alert-<?php echo $flash['type'] === 'error' ? 'danger' : $flash['type']; ?>">
            <?php echo $flash['message']; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($confirm_delete)): ?>
        <div class="alert alert-warning">
            <h4>Confirm Deletion</h4>
            <p>Are you sure you want to delete this contact message?</p>
            <a href="?action=delete&id=<?php echo $delete_id; ?>&confirm=1" class="btn btn-danger">Yes, Delete</a>
            <a href="contact-messages.php" class="btn btn-secondary">Cancel</a>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div class="ms-3">
                            <h3><?php echo number_format($stats['total']); ?></h3>
                            <p class="text-muted mb-0">Total Messages</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-envelope-open"></i>
                        </div>
                        <div class="ms-3">
                            <h3><?php echo number_format($stats['new_messages']); ?></h3>
                            <p class="text-muted mb-0">New Messages</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info">
                            <i class="bi bi-eye"></i>
                        </div>
                        <div class="ms-3">
                            <h3><?php echo number_format($stats['read_messages']); ?></h3>
                            <p class="text-muted mb-0">Read Messages</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success">
                            <i class="bi bi-reply"></i>
                        </div>
                        <div class="ms-3">
                            <h3><?php echo number_format($stats['replied_messages']); ?></h3>
                            <p class="text-muted mb-0">Replied Messages</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or subject..." value="<?php echo htmlspecialchars($search); ?>">
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
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Contact Messages</h5>
        </div>
        <div class="card-body">
            <?php if (empty($messages)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-envelope display-4 text-muted"></i>
                    <h4 class="mt-3">No messages found</h4>
                    <p class="text-muted">There are no contact messages matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $msg): ?>
                                <tr>
                                    <td><?php echo $msg['id']; ?></td>
                                    <td><?php echo htmlspecialchars($msg['name']); ?></td>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>">
                                            <?php echo htmlspecialchars($msg['email']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($msg['status']) {
                                            case 'new':
                                                $status_class = 'bg-warning';
                                                $status_text = 'New';
                                                break;
                                            case 'read':
                                                $status_class = 'bg-info';
                                                $status_text = 'Read';
                                                break;
                                            case 'replied':
                                                $status_class = 'bg-success';
                                                $status_text = 'Replied';
                                                break;
                                            case 'closed':
                                                $status_class = 'bg-secondary';
                                                $status_text = 'Closed';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></td>
                                    <td>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li><a class="dropdown-item" href="?action=mark_read&id=<?php echo $msg['id']; ?>"><i class="bi bi-envelope-open me-2"></i>Mark as Read</a></li>
                                                <li><a class="dropdown-item" href="?action=mark_replied&id=<?php echo $msg['id']; ?>"><i class="bi bi-reply me-2"></i>Mark as Replied</a></li>
                                                <li><a class="dropdown-item" href="?action=mark_closed&id=<?php echo $msg['id']; ?>"><i class="bi bi-check-circle me-2"></i>Mark as Closed</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?action=delete&id=<?php echo $msg['id']; ?>"><i class="bi bi-trash me-2"></i>Delete</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.stat-card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}
</style>

<?php include '../includes/footer.php'; ?>