<?php
/**
 * Organizer - Nominees List
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Manage Nominees';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

$eventId = intval($_GET['event'] ?? 0);
$categoryId = intval($_GET['category'] ?? 0);

// Handle delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (verifyCSRFToken($_GET['token'])) {
        $id = intval($_GET['delete']);
        try {
            // Verify ownership
            $checkStmt = $pdo->prepare("SELECT n.id FROM nominees n JOIN categories c ON n.category_id = c.id JOIN events e ON c.event_id = e.id WHERE n.id = ? AND e.organizer_id = ?");
            $checkStmt->execute([$id, $organizer_id]);
            if ($checkStmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM nominees WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'Nominee deleted successfully.');
            } else {
                setFlash('danger', 'Access denied.');
            }
        } catch (PDOException $e) {
            setFlash('danger', 'Cannot delete nominee.');
        }
    }
    redirect('organizer/nominees/');
}

// Fetch events and categories for filter (only this organizer's)
$eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE organizer_id = ? ORDER BY title");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();

$categoriesStmt = $pdo->prepare("SELECT c.id, c.name, e.title as event_title FROM categories c JOIN events e ON c.event_id = e.id WHERE e.organizer_id = ? ORDER BY e.title, c.name");
$categoriesStmt->execute([$organizer_id]);
$categories = $categoriesStmt->fetchAll();

// Fetch nominees for this organizer
$sql = "
    SELECT n.*, c.name as category_name, e.title as event_title, e.id as event_id
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE e.organizer_id = ?
";
$params = [$organizer_id];

if ($categoryId > 0) {
    $sql .= " AND n.category_id = ?";
    $params[] = $categoryId;
} elseif ($eventId > 0) {
    $sql .= " AND e.id = ?";
    $params[] = $eventId;
}

$sql .= " ORDER BY e.title, c.name, n.vote_count DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$nominees = $stmt->fetchAll();
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">Nominees</h2>
                    <p class="text-muted mb-0">Manage your nominees</p>
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
                                <option value="">All My Events</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Filter by Category</label>
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">All My Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name'] . ' (' . $cat['event_title'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
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
