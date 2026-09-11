<?php
/**
 * Notifications Page
 * View all notifications for the logged-in user
 * 
 * Awards Voting System
 */

require_once 'config/database.php';

// Require authentication
if (!isLoggedIn()) {
    setFlash('warning', 'Please log in to view your notifications.');
    redirect('login.php');
}

$page_title = 'Notifications';
$page_description = 'View all your notifications and updates.';

$user_id = getCurrentUserId();
$pdo = getDBConnection();

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notification_id = intval($_POST['notification_id'] ?? 0);
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Invalid request.');
        redirect('notifications.php');
    }
    
    if ($action === 'mark_read' && $notification_id > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
    } elseif ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);
        setFlash('success', 'All notifications marked as read.');
    } elseif ($action === 'delete' && $notification_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notification_id, $user_id]);
    } elseif ($action === 'delete_all_read') {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->execute([$user_id]);
        setFlash('success', 'All read notifications deleted.');
    }
    
    redirect('notifications.php' . (isset($_GET['filter']) ? '?filter=' . $_GET['filter'] : ''));
}

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$whereClause = "WHERE user_id = ?";
$params = [$user_id];

if ($filter === 'unread') {
    $whereClause .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $whereClause .= " AND is_read = 1";
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $whereClause");
$countStmt->execute($params);
$totalNotifications = $countStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Get unread count
$unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadStmt->execute([$user_id]);
$unreadCount = $unreadStmt->fetchColumn();

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="notifications-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Notifications</li>
            </ol>
        </nav>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h1><i class="bi bi-bell me-2"></i>Notifications</h1>
                <p class="mb-0">Stay updated with your latest activities</p>
            </div>
            <?php if ($unreadCount > 0): ?>
            <span class="badge bg-primary rounded-pill fs-6 px-3 py-2">
                <?php echo $unreadCount; ?> unread
            </span>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Notifications Content -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <!-- Filter & Actions Bar -->
                <div class="notifications-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                    <!-- Filters -->
                    <div class="btn-group" role="group" aria-label="Notification filters">
                        <a href="?filter=all" class="btn <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            All
                        </a>
                        <a href="?filter=unread" class="btn <?php echo $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Unread
                        </a>
                        <a href="?filter=read" class="btn <?php echo $filter === 'read' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-check-circle me-1"></i> Read
                        </a>
                    </div>
                    
                    <!-- Actions Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear me-1"></i> Actions
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="dropdown-item">
                                        <i class="bi bi-check-all me-2 text-success"></i> Mark all as read
                                    </button>
                                </form>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete all read notifications?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_all_read">
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash me-2"></i> Delete all read
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Notifications List -->
                <?php if (empty($notifications)): ?>
                <div class="notifications-empty text-center py-5">
                    <div class="empty-icon mb-4">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <h4 class="text-muted mb-2">No notifications</h4>
                    <p class="text-muted">
                        <?php if ($filter === 'unread'): ?>
                            You're all caught up! No unread notifications.
                        <?php elseif ($filter === 'read'): ?>
                            No read notifications yet.
                        <?php else: ?>
                            You don't have any notifications yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($filter !== 'all'): ?>
                    <a href="?filter=all" class="btn btn-outline-primary mt-2">View all notifications</a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="notifications-list">
                    <?php foreach ($notifications as $notification): ?>
                    <?php
                        // Determine icon and color based on type
                        $iconClass = 'bi-info-circle';
                        $bgClass = 'bg-primary';
                        switch ($notification['type']) {
                            case 'success':
                                $iconClass = 'bi-check-circle-fill';
                                $bgClass = 'bg-success';
                                break;
                            case 'warning':
                                $iconClass = 'bi-exclamation-triangle-fill';
                                $bgClass = 'bg-warning';
                                break;
                            case 'danger':
                                $iconClass = 'bi-x-circle-fill';
                                $bgClass = 'bg-danger';
                                break;
                            default:
                                $iconClass = 'bi-info-circle-fill';
                                $bgClass = 'bg-primary';
                        }
                        
                        // Format time ago
                        $createdAt = new DateTime($notification['created_at']);
                        $now = new DateTime();
                        $interval = $now->diff($createdAt);
                        
                        if ($interval->y > 0) {
                            $timeAgo = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->m > 0) {
                            $timeAgo = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->d > 0) {
                            $timeAgo = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->h > 0) {
                            $timeAgo = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                        } elseif ($interval->i > 0) {
                            $timeAgo = $interval->i . ' minute' . ($interval->i > 1 ? 's' : '') . ' ago';
                        } else {
                            $timeAgo = 'Just now';
                        }
                    ?>
                    <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="notification-icon <?php echo $bgClass; ?>">
                            <i class="bi <?php echo $iconClass; ?>"></i>
                        </div>
                        <div class="notification-body">
                            <div class="notification-header">
                                <h5 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                <span class="notification-time">
                                    <i class="bi bi-clock me-1"></i><?php echo $timeAgo; ?>
                                </span>
                            </div>
                            <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-actions">
                                <?php if ($notification['link']): ?>
                                <a href="<?php echo SITE_URL . '/' . $notification['link']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-arrow-right me-1"></i> View Details
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$notification['is_read']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-check me-1"></i> Mark as read
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this notification?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Notifications pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
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
</section>

<style>
.notifications-header {
    background: var(--gradient-dark);
    padding: 6rem 0 3rem;
    color: var(--white);
}

.notifications-header h1 {
    color: var(--white);
    font-size: clamp(2rem, 4vw, 2.5rem);
    margin-bottom: 0.5rem;
}

.notifications-header p {
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.1rem;
}

.notifications-header .breadcrumb {
    margin-bottom: 1.5rem;
}

.notifications-header .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.7);
}

.notifications-header .breadcrumb-item.active {
    color: var(--white);
}

.notifications-toolbar {
    background: var(--white);
    padding: 1rem 1.5rem;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
}

.notifications-empty {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: 4rem 2rem;
    box-shadow: var(--shadow-sm);
}

.notifications-empty .empty-icon {
    width: 100px;
    height: 100px;
    margin: 0 auto;
    background: var(--gray-100);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notifications-empty .empty-icon i {
    font-size: 3rem;
    color: var(--gray-400);
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.notification-card {
    display: flex;
    gap: 1.25rem;
    background: var(--white);
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    transition: all var(--transition);
    border-left: 4px solid transparent;
}

.notification-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.notification-card.unread {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(236, 72, 153, 0.03) 100%);
    border-left-color: var(--primary);
}

.notification-icon {
    width: 48px;
    height: 48px;
    min-width: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--white);
    font-size: 1.25rem;
}

.notification-body {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.notification-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.notification-time {
    font-size: 0.85rem;
    color: var(--gray-500);
    white-space: nowrap;
}

.notification-message {
    color: var(--gray-600);
    margin-bottom: 1rem;
    line-height: 1.5;
}

.notification-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.notification-actions .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}

/* Pagination Styling */
.pagination .page-link {
    border-radius: var(--radius-md);
    margin: 0 0.25rem;
    border: none;
    color: var(--gray-600);
    background: var(--gray-100);
}

.pagination .page-item.active .page-link {
    background: var(--gradient-primary);
    color: var(--white);
}

.pagination .page-link:hover {
    background: var(--primary-light);
    color: var(--white);
}

@media (max-width: 768px) {
    .notification-card {
        flex-direction: column;
        gap: 1rem;
    }
    
    .notification-icon {
        width: 40px;
        height: 40px;
        min-width: 40px;
        font-size: 1rem;
    }
    
    .notification-header {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .notifications-toolbar {
        flex-direction: column;
        gap: 1rem;
    }
    
    .notifications-toolbar .btn-group {
        width: 100%;
    }
    
    .notifications-toolbar .btn-group .btn {
        flex: 1;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
