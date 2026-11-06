<?php
/**
 * Organizer - Results Dashboard
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Results Dashboard';
$pdo = getDBConnection();
$organizer_id = $_SESSION['user_id'];

$eventId = intval($_GET['event'] ?? 0);
$categoryId = intval($_GET['category'] ?? 0);

// Fetch events for this organizer
$eventsStmt = $pdo->prepare("SELECT id, title, status FROM events WHERE organizer_id = ? ORDER BY created_at DESC");
$eventsStmt->execute([$organizer_id]);
$events = $eventsStmt->fetchAll();

// Get selected event
$selectedEvent = null;
if ($eventId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND organizer_id = ?");
    $stmt->execute([$eventId, $organizer_id]);
    $selectedEvent = $stmt->fetch();
} elseif (!empty($events)) {
    $selectedEvent = $events[0];
    $eventId = $selectedEvent['id'];
}

// Fetch categories for selected event
$categories = [];
$results = [];
if ($selectedEvent) {
    $catStmt = $pdo->prepare("SELECT * FROM categories WHERE event_id = ? ORDER BY display_order");
    $catStmt->execute([$eventId]);
    $categories = $catStmt->fetchAll();
    
    if ($categoryId > 0) {
        // Verify category belongs to event
        $checkCat = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND event_id = ?");
        $checkCat->execute([$categoryId, $eventId]);
        if (!$checkCat->fetch()) {
            $categoryId = !empty($categories) ? $categories[0]['id'] : 0;
        }
    } elseif (!empty($categories)) {
        $categoryId = $categories[0]['id'];
    }

    if ($categoryId > 0) {
        $resultsStmt = $pdo->prepare("
            SELECT n.*, 
                   (SELECT SUM(vote_count) FROM nominees WHERE category_id = n.category_id) as total_votes
            FROM nominees n 
            WHERE n.category_id = ? 
            ORDER BY n.vote_count DESC
        ");
        $resultsStmt->execute([$categoryId]);
        $results = $resultsStmt->fetchAll();
    }
}
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
                    <h2 class="mb-1">Results Dashboard</h2>
                    <p class="text-muted mb-0">View and analyze voting results for your events</p>
                </div>
            </div>

            <?php if (empty($events)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-trophy display-1 text-muted mb-3"></i>
                        <h4>No Events Found</h4>
                        <p class="text-muted">Create an event first to see results.</p>
                        <a href="../events/add.php" class="btn btn-primary">Create Event</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Event</label>
                                <select name="event" class="form-select" onchange="this.form.submit()">
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>" <?php echo $eventId == $event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                            (<?php echo ucfirst($event['status']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select" onchange="this.form.submit()">
                                    <?php if (empty($categories)): ?>
                                        <option value="">No categories found</option>
                                    <?php else: ?>
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

                <?php if (!empty($results)): ?>
                    <!-- Results -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Rankings</h5>
                        </div>
                        <div class="card-body">
                            <?php 
                            $maxVotes = $results[0]['vote_count'] ?? 1;
                            foreach ($results as $index => $nominee): 
                                $percentage = $nominee['total_votes'] > 0 ? ($nominee['vote_count'] / $nominee['total_votes']) * 100 : 0;
                                $barWidth = $maxVotes > 0 ? ($nominee['vote_count'] / $maxVotes) * 100 : 0;
                            ?>
                                <div class="result-item d-flex align-items-center p-3 mb-3 rounded-3 <?php echo $index === 0 ? 'bg-warning bg-opacity-10 border border-warning' : 'bg-light'; ?>">
                                    <div class="rank-badge me-3 text-center" style="width: 50px;">
                                        <?php if ($index === 0): ?>
                                            <i class="bi bi-trophy-fill text-warning fs-3"></i>
                                        <?php elseif ($index === 1): ?>
                                            <i class="bi bi-trophy-fill text-secondary fs-4"></i>
                                        <?php elseif ($index === 2): ?>
                                            <i class="bi bi-trophy-fill" style="color: #cd7f32; font-size: 1.25rem;"></i>
                                        <?php else: ?>
                                            <span class="badge bg-secondary fs-6">#<?php echo $index + 1; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <img src="<?php echo $nominee['photo'] ? '../../uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']); ?>" 
                                         class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <h5 class="mb-0"><?php echo htmlspecialchars($nominee['name']); ?></h5>
                                                <code class="small"><?php echo $nominee['shortcode']; ?></code>
                                            </div>
                                            <div class="text-end">
                                                <h4 class="mb-0 text-primary"><?php echo number_format($nominee['vote_count']); ?></h4>
                                                <small class="text-muted"><?php echo number_format($percentage, 1); ?>%</small>
                                            </div>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar <?php echo $index === 0 ? 'bg-warning' : 'bg-primary'; ?>" 
                                                 style="width: <?php echo $barWidth; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-graph-up display-1 text-muted mb-3"></i>
                            <h4>No Results</h4>
                            <p class="text-muted">No nominees or votes in this category.</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
