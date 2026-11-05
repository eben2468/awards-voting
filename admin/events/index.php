<?php
/**
 * Admin - Events List
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Manage Events';
$pdo = getDBConnection();

// Handle delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (verifyCSRFToken($_GET['token'])) {
        $id = intval($_GET['delete']);
        try {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Event deleted successfully.');
        } catch (PDOException $e) {
            setFlash('danger', 'Cannot delete event. It may have related data.');
        }
    }
    redirect('admin/events/');
}

// Fetch events
$stmt = $pdo->query("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes,
           u.fullname as created_by_name
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    LEFT JOIN users u ON e.created_by = u.id
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
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
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Events</h2>
                    <p class="text-muted mb-0">Manage all voting events</p>
                </div>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Add Event
                </a>
            </div>

            <!-- Flash Messages -->
            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Events Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-trophy display-1 text-muted mb-3"></i>
                            <h4>No Events Yet</h4>
                            <p class="text-muted">Create your first event to get started.</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-2"></i>Add Event
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event</th>
                                        <th>Status</th>
                                        <th>Categories</th>
                                        <th>Nominees</th>
                                        <th>Votes</th>
                                        <th>End Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $event['banner'] ? '../../uploads/events/' . $event['banner'] : 'https://picsum.photos/60/40?random=' . $event['id']; ?>" 
                                                         class="rounded me-3" style="width: 60px; height: 40px; object-fit: cover;">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                                        <?php if ($event['is_featured']): ?>
                                                            <span class="badge bg-warning text-dark ms-2">Featured</span>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small class="text-muted"><?php echo $event['slug']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo match($event['status']) {
                                                    'active' => 'success',
                                                    'completed' => 'secondary',
                                                    'draft' => 'warning',
                                                    default => 'danger'
                                                }; ?>"><?php echo ucfirst($event['status']); ?></span>
                                            </td>
                                            <td><?php echo $event['category_count']; ?></td>
                                            <td><?php echo $event['nominee_count']; ?></td>
                                            <td><strong class="text-primary"><?php echo number_format($event['total_votes']); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($event['end_date'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="../categories/?event=<?php echo $event['id']; ?>" class="btn btn-outline-secondary" title="Manage Categories">
                                                        <i class="bi bi-collection"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>/event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-outline-info" title="View" target="_blank">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $event['id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                                       class="btn btn-outline-danger" 
                                                       data-confirm="Are you sure you want to delete this event? This will also delete all categories, nominees, and votes."
                                                       title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
