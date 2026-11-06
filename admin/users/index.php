<?php
/**
 * Admin - User Management
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied. Admin privileges required.');
    redirect('login.php');
}

$page_title = 'User Management';
$pdo = getDBConnection();

// Handle User Actions (Add and Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid security token.');
    } else {
        $action = $_POST['action'];
        
        if ($action === 'delete_user') {
            $deleteId = intval($_POST['user_id'] ?? 0);
            $currentAdminId = getCurrentUserId();
            
            if ($deleteId <= 0) {
                setFlash('danger', 'Invalid user selection.');
            } elseif ($deleteId === $currentAdminId) {
                setFlash('danger', 'You cannot delete your own account.');
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$deleteId]);
                    setFlash('success', 'User deleted successfully.');
                } catch (PDOException $e) {
                    setFlash('danger', 'Failed to delete user: ' . $e->getMessage());
                }
            }
            redirect('admin/users/');
        }
        
        if ($action === 'add_user') {
            $fullname = sanitize($_POST['fullname'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = sanitize($_POST['role'] ?? 'user');
            $status = sanitize($_POST['status'] ?? 'active');
            
            if (empty($fullname) || empty($email) || empty($password)) {
                setFlash('danger', 'Full Name, Email and Password are required.');
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                setFlash('danger', 'Invalid email format.');
            } else {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    setFlash('danger', 'A user with this email address already exists.');
                } else {
                    try {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$fullname, $email, $phone ?: null, $hashed_password, $role, $status]);
                        setFlash('success', 'User added successfully.');
                    } catch (PDOException $e) {
                        setFlash('danger', 'Failed to add user: ' . $e->getMessage());
                    }
                }
            }
            redirect('admin/users/');
        }
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$role = sanitize($_GET['role'] ?? '');
$search = sanitize($_GET['search'] ?? '');

$where = ["1=1"];
$params = [];

if ($role) {
    $where[] = "role = ?";
    $params[] = $role;
}

if ($search) {
    $where[] = "(fullname LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereSql = implode(" AND ", $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereSql");
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Fetch users
$stmt = $pdo->prepare("
    SELECT * FROM users 
    WHERE $whereSql 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$users = $stmt->fetchAll();

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
    <?php include '../includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">User Management</h2>
                    <p class="text-muted mb-0">Manage system users, organizers, and administrators</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus me-2"></i>Add New User
                    </button>
                </div>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Name, email or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="organizer" <?php echo $role === 'organizer' ? 'selected' : ''; ?>>Organizer</option>
                                <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>User</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="index.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <?php $pendingCount = getPendingOrganizerCount(); if ($pendingCount > 0): ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <i class="bi bi-person-exclamation me-2"></i>
                        <strong><?php echo $pendingCount; ?></strong>
                        organizer account<?php echo $pendingCount === 1 ? '' : 's'; ?>
                        <?php echo $pendingCount === 1 ? 'is' : 'are'; ?> waiting for your approval and cannot log in yet.
                    </div>
                    <a href="<?php echo SITE_URL; ?>/admin/organizer-approvals.php" class="btn btn-warning btn-sm">
                        <i class="bi bi-person-check me-1"></i>Review Now
                    </a>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No users found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $user['avatar'] ? SITE_URL . '/uploads/avatars/' . $user['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['fullname']); ?>" 
                                                     class="rounded-circle me-3" style="width: 40px; height: 40px; object-fit: cover;">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($user['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['phone'])): ?>
                                                <?php $digits = preg_replace('/\D/', '', $user['phone']); ?>
                                                <a href="tel:<?php echo htmlspecialchars($user['phone']); ?>"
                                                   class="text-decoration-none d-inline-block">
                                                    <?php echo htmlspecialchars($user['phone']); ?>
                                                </a>
                                                <?php if (strlen($digits) >= 9): ?>
                                                    <a href="https://wa.me/<?php echo htmlspecialchars($digits); ?>"
                                                       target="_blank" rel="noopener"
                                                       class="text-success ms-2 text-decoration-none"
                                                       title="Message on WhatsApp">
                                                        <i class="bi bi-whatsapp"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">&mdash;</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($user['role']) {
                                                'admin' => 'danger',
                                                'organizer' => 'primary',
                                                default => 'secondary'
                                            }; ?>"><?php echo ucfirst($user['role']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo match($user['status']) {
                                                'active' => 'success',
                                                'suspended' => 'danger',
                                                default => 'warning'
                                            }; ?>"><?php echo ucfirst($user['status']); ?></span>
                                            <?php
                                            // Organizers also need admin approval before they can log in
                                            $approval = $user['approval_status'] ?? 'approved';
                                            if ($user['role'] === 'organizer' && $approval !== 'approved'):
                                            ?>
                                                <br>
                                                <span class="badge bg-<?php echo $approval === 'pending' ? 'warning text-dark' : 'danger'; ?> mt-1">
                                                    <i class="bi bi-<?php echo $approval === 'pending' ? 'hourglass-split' : 'x-circle'; ?> me-1"></i>
                                                    <?php echo $approval === 'pending' ? 'Awaiting approval' : 'Rejected'; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($user['role'] === 'organizer' && ($user['approval_status'] ?? 'approved') !== 'approved'): ?>
                                                    <a href="<?php echo SITE_URL; ?>/admin/organizer-approvals.php?filter=<?php echo htmlspecialchars($user['approval_status']); ?>"
                                                       class="btn btn-outline-success" title="Review approval">
                                                        <i class="bi bi-person-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="reset-password.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-warning" title="Reset Password">
                                                    <i class="bi bi-key"></i>
                                                </a>
                                                <?php if ($user['id'] !== getCurrentUserId()): ?>
                                                    <button type="button" class="btn btn-outline-danger btn-delete-user" data-user-id="<?php echo $user['id']; ?>" title="Delete User">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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
                        <nav>
                            <ul class="pagination pagination-sm justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&role=<?php echo $role; ?>&search=<?php echo $search; ?>">Previous</a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&role=<?php echo $role; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&role=<?php echo $role; ?>&search=<?php echo $search; ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="add_user">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="fullname" class="form-control" placeholder="Enter full name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="Enter email address" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 0244000000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="user" selected>Regular User</option>
                                <option value="organizer">Organizer</option>
                                <option value="admin">System Administrator</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Delete User Form -->
    <form id="deleteUserForm" method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserIdInput" value="">
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle delete user confirmation and form submission
        document.querySelectorAll('.btn-delete-user').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                if (confirm('Are you sure you want to delete this user? This will remove all associated votes and transactions.')) {
                    document.getElementById('deleteUserIdInput').value = userId;
                    document.getElementById('deleteUserForm').submit();
                }
            });
        });
    });
    </script>
</body>
</html>
