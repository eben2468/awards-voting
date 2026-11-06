<?php
/**
 * Events Listing Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'All Events';
$page_description = 'Browse all award events and vote for your favorite nominees.';

$pdo = getDBConnection();
$show_vote_counts = getSetting('show_vote_counts', '1') === '1';

// Get filter parameters
$status = sanitize($_GET['status'] ?? 'all');
$search = sanitize($_GET['search'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'newest');

// Build query
$whereConditions = ["e.event_type != 'ticketing'"];
$params = [];

if ($status === 'active') {
    $whereConditions[] = "e.status = 'active' AND e.end_date >= NOW()";
} elseif ($status === 'completed') {
    $whereConditions[] = "(e.status = 'completed' OR e.end_date < NOW())";
} elseif ($status === 'upcoming') {
    $whereConditions[] = "e.status = 'active' AND e.start_date > NOW()";
}

if (!empty($search)) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Sort order
$orderBy = match($sort) {
    'oldest' => 'e.created_at ASC',
    'popular' => 'total_votes DESC',
    'ending_soon' => 'e.end_date ASC',
    default => 'e.created_at DESC'
};

// Fetch events
$sql = "
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    $whereClause
    GROUP BY e.id
    ORDER BY e.is_featured DESC, $orderBy
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header py-5" style="background: var(--gradient-dark); margin-top: 76px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Events</li>
                    </ol>
                </nav>
                <h1 class="text-white mb-3">Award Events</h1>
                <p class="text-white-50 lead mb-3">Discover exciting award events and support your favorite nominees</p>
                <?php if (!$show_vote_counts): ?>
                    <a href="nominee-results.php" class="btn-live">
                        <span class="btn-live-dot"></span>Live Results Portal
                    </a>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="search-box mt-4 mt-lg-0">
                    <form action="" method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search events..." 
                                   value="<?php echo e($search); ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filters & Events List -->
<section class="events-section py-5">
    <div class="container">
        <!-- Filters Bar -->
        <div class="filters-bar bg-white rounded-3 shadow-sm p-3 mb-4">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-3 mb-lg-0">
                    <div class="btn-group" role="group">
                        <a href="?status=all" class="btn <?php echo $status === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-globe me-1"></i>All
                        </a>
                        <a href="?status=active" class="btn <?php echo $status === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-lightning-fill me-1"></i>Active
                        </a>
                        <a href="?status=upcoming" class="btn <?php echo $status === 'upcoming' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-calendar-event me-1"></i>Upcoming
                        </a>
                        <a href="?status=completed" class="btn <?php echo $status === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="bi bi-check-circle me-1"></i>Completed
                        </a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2">
                        <span class="text-muted">Sort by:</span>
                        <select class="form-select" style="width: auto;" onchange="window.location.href='?status=<?php echo $status; ?>&sort=' + this.value">
                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                            <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="ending_soon" <?php echo $sort === 'ending_soon' ? 'selected' : ''; ?>>Ending Soon</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Count -->
        <p class="text-muted mb-4">
            Showing <strong><?php echo count($events); ?></strong> events
            <?php if (!empty($search)): ?>
                for "<strong><?php echo e($search); ?></strong>"
            <?php endif; ?>
        </p>

        <!-- Events Grid -->
        <div class="ev-grid">
            <?php if (empty($events)): ?>
                <div class="col-12">
                    <div class="text-center py-5 bg-white rounded-3">
                        <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
                        <h4>No Events Found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search)): ?>
                                No events match your search criteria. Try different keywords.
                            <?php else: ?>
                                Check back soon for exciting new events!
                            <?php endif; ?>
                        </p>
                        <a href="events.php" class="btn btn-primary">View All Events</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $index => $event) { include 'includes/event-card.php'; } ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.page-header {
    background: var(--gradient-dark);
}

.breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.5);
}

.filters-bar {
    position: sticky;
    top: 90px;
    z-index: 100;
}
</style>

<?php include 'includes/footer.php'; ?>
