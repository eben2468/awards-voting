<?php
/**
 * Organizer - Categories List
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Manage Categories';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];
$eventId = intval($_GET['event'] ?? 0);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (verifyCSRFToken($_GET['token'])) {
        $id = intval($_GET['delete']);
        try {
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT c.id FROM categories c JOIN events e ON c.event_id = e.id WHERE c.id = ? AND e.organizer_id = ?");
            $checkStmt->execute([$id, $organizer_id]);
            if ($checkStmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Category deleted successfully.');
            } else {
                setFlash('danger', 'Access denied.');
            }
        } catch (PDOException $e) {
            setFlash('danger', 'Cannot delete category.');
        }
    }
    redirect('organizer/categories/' . ($eventId ? "?event=$eventId" : ''));
}

// Fetch events for filter (only this organizer's events)
$eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE organizer_id = ? ORDER BY title");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();

// Fetch categories for this organizer
$sql = "
    SELECT c.*, e.title as event_title, 
           COUNT(n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM categories c
    JOIN events e ON c.event_id = e.id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.organizer_id = ?
";
$params = [$organizer_id];

if ($eventId > 0) {
    $sql .= " AND c.event_id = ?";
    $params[] = $eventId;
}

$sql .= " GROUP BY c.id ORDER BY e.title, c.display_order";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll();
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Categories</h2>
                    <p class="text-muted mb-0">Manage award categories</p>
                </div>
                <a href="add.php<?php echo $eventId ? "?event=$eventId" : ''; ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Add Category
                </a>
            </div>

            <?php $flash = getFlash(); if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Event</label>
                            <select name="event" class="form-select" onchange="this.form.submit()">
                                <option value="">All My Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Categories Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($categories)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-collection display-1 text-muted mb-3"></i>
                            <h4>No Categories</h4>
                            <p class="text-muted">Create categories to organize nominees.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th>Event</th>
                                        <th>Access Code</th>
                                        <th>Nominees</th>
                                        <th>Votes</th>
                                        <th>Status</th>
                                        <th>Order</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                                <?php if ($cat['description']): ?>
                                                    <br><small class="text-muted"><?php echo substr($cat['description'], 0, 50); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($cat['event_title']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo $cat['access_code']; ?></span></td>
                                            <td><?php echo $cat['nominee_count']; ?></td>
                                            <td><strong class="text-primary"><?php echo number_format($cat['total_votes']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo ucfirst($cat['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $cat['display_order']; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $cat['id']; ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                    <a href="../nominees/?category=<?php echo $cat['id']; ?>" class="btn btn-outline-secondary"><i class="bi bi-people"></i></a>
                                                    <a href="?delete=<?php echo $cat['id']; ?>&event=<?php echo $eventId; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                                       class="btn btn-outline-danger" data-confirm="Delete this category?"><i class="bi bi-trash"></i></a>
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
