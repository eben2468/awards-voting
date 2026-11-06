<?php
/**
 * Admin - Nominees List
 * Awards Voting System
 */

require_once '../../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Manage Nominees';
$pdo = getDBConnection();

$eventId = intval($_GET['event'] ?? 0);
$categoryId = intval($_GET['category'] ?? 0);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (verifyCSRFToken($_GET['token'])) {
        $id = intval($_GET['delete']);
        try {
            $stmt = $pdo->prepare("DELETE FROM nominees WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Nominee deleted successfully.');
        } catch (PDOException $e) {
            setFlash('danger', 'Cannot delete nominee.');
        }
    }
    redirect('admin/nominees/');
}

// Fetch events and categories for filter
$eventsStmt = $pdo->query("SELECT id, title FROM events ORDER BY title");
$events = $eventsStmt->fetchAll();

// A category always belongs to one event. A link may send only the category, so
// work out its event, and drop a category left over from a different event.
if ($categoryId > 0) {
    $ownerStmt = $pdo->prepare("SELECT event_id FROM categories WHERE id = ?");
    $ownerStmt->execute([$categoryId]);
    $categoryEventId = (int) $ownerStmt->fetchColumn();

    if ($categoryEventId === 0 || ($eventId > 0 && $eventId !== $categoryEventId)) {
        $categoryId = 0;
    } else {
        $eventId = $categoryEventId;
    }
}

$categories = [];
if ($eventId > 0) {
    $categoriesStmt = $pdo->prepare("SELECT id, name FROM categories WHERE event_id = ? ORDER BY name");
    $categoriesStmt->execute([$eventId]);
    $categories = $categoriesStmt->fetchAll();
}

// Fetch nominees
$sql = "
    SELECT n.*, c.name as category_name, e.title as event_title, e.id as event_id
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE 1=1
";
if ($categoryId > 0) {
    $sql .= " AND n.category_id = $categoryId";
} elseif ($eventId > 0) {
    $sql .= " AND e.id = $eventId";
}
$sql .= " ORDER BY e.title, c.name, n.vote_count DESC";
$nominees = $pdo->query($sql)->fetchAll();
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
                    <h2 class="mb-1">Nominees</h2>
                    <p class="text-muted mb-0">Manage all nominees</p>
                </div>
                <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-2"></i>Add Nominee</a>
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
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Filter by Event</label>
                            <select name="event" class="form-select" onchange="this.form.submit()">
                                <option value="">All Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()" <?php echo ($eventId === 0 || empty($categories)) ? 'disabled' : ''; ?>>
                                <?php if ($eventId === 0): ?>
                                    <option value="">Select an event first</option>
                                <?php elseif (empty($categories)): ?>
                                    <option value="">No categories in this event</option>
                                <?php else: ?>
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Nominees Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if (empty($nominees)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people display-1 text-muted mb-3"></i>
                            <h4>No Nominees</h4>
                            <p class="text-muted">Add nominees to start collecting votes.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nominee</th>
                                        <th>Shortcode</th>
                                        <th>Category</th>
                                        <th>Event</th>
                                        <th>Votes</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($nominees as $nominee): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?php echo $nominee['photo'] ? '../../uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']); ?>" 
                                                         class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit: cover;">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($nominee['name']); ?></strong>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><code><?php echo $nominee['shortcode']; ?></code></td>
                                            <td><?php echo htmlspecialchars($nominee['category_name']); ?></td>
                                            <td><?php echo htmlspecialchars($nominee['event_title']); ?></td>
                                            <td><strong class="text-primary"><?php echo number_format($nominee['vote_count']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-<?php echo $nominee['status'] === 'active' ? 'success' : ($nominee['status'] === 'disqualified' ? 'danger' : 'secondary'); ?>">
                                                    <?php echo ucfirst($nominee['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="edit.php?id=<?php echo $nominee['id']; ?>" class="btn btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                    <a href="?delete=<?php echo $nominee['id']; ?>&token=<?php echo generateCSRFToken(); ?>" 
                                                       class="btn btn-outline-danger" data-confirm="Delete this nominee?"><i class="bi bi-trash"></i></a>
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
