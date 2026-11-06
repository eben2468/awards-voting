<?php
/**
 * Organizer - Events List
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

require_once '../../includes/event-list.php';

$page_title = 'My Events';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

// Handle delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (verifyCSRFToken($_GET['token'])) {
        $id = intval($_GET['delete']);
        try {
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT id FROM events WHERE id = ? AND organizer_id = ?");
            $checkStmt->execute([$id, $organizer_id]);
            if ($checkStmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Event deleted successfully.');
            } else {
                setFlash('danger', 'Access denied.');
            }
        } catch (PDOException $e) {
            setFlash('danger', 'Cannot delete event. It may have related data.');
        }
    }
    redirect('organizer/events/');
}

// Fetch events for this organizer
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes,
           " . eventListTicketColumns() . "
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.organizer_id = ?
    GROUP BY e.id
    ORDER BY e.created_at DESC
");
$stmt->execute([$organizer_id]);
$events = $stmt->fetchAll();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
</head>
<body class="admin-body">
    <?php include '../includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">My Events</h2>
                    <p class="text-muted mb-0">Your awards and ticketed events</p>
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
                                        <th>Type &amp; Status</th>
                                        <th>Setup</th>
                                        <th>Activity</th>
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
                                                          class="rounded me-3 flex-shrink-0" style="width: 60px; height: 40px; object-fit: cover;">
                                                    <div>
                                                        <strong><?php echo e($event['title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted text-truncate d-block" style="max-width: 9rem;" title="<?php echo e($event['slug']); ?>"><?php echo e($event['slug']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <?php include '../../includes/event-list-cells.php'; ?>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $event['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if (eventHasVoting($event)): ?>
                                                        <a href="../categories/?event=<?php echo $event['id']; ?>" class="btn btn-outline-secondary" title="Manage Categories">
                                                            <i class="bi bi-collection"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (eventHasTickets($event)): ?>
                                                        <a href="../transactions.php?tab=tickets&event=<?php echo $event['id']; ?>" class="btn btn-outline-success" title="Ticket Sales">
                                                            <i class="bi bi-ticket-perforated"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="<?php echo SITE_URL; ?>/event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-outline-info" title="View" target="_blank">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="?delete=<?php echo $event['id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                                       class="btn btn-outline-danger" 
                                                       data-confirm="<?php echo e(eventDeleteWarning($event)); ?>"
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
