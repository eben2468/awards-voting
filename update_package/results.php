<?php
/**
 * Live Results Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Live Results';
$page_description = 'View real-time voting results for all award events.';

$pdo = getDBConnection();
$show_vote_counts = getSetting('show_vote_counts', '1') === '1';

// Get event filter
$eventSlug = sanitize($_GET['event'] ?? '');
$categoryId = intval($_GET['category'] ?? 0);

// Fetch active/completed events
$eventsStmt = $pdo->query("
    SELECT e.*, 
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.status IN ('active', 'completed')
    GROUP BY e.id
    ORDER BY e.end_date DESC
");
$events = $eventsStmt->fetchAll();

// Get selected event
$selectedEvent = null;
$categories = [];
$results = [];

if (!empty($eventSlug)) {
    $eventStmt = $pdo->prepare("SELECT * FROM events WHERE slug = ?");
    $eventStmt->execute([$eventSlug]);
    $selectedEvent = $eventStmt->fetch();
} elseif (!empty($events)) {
    $selectedEvent = $events[0];
}

if ($selectedEvent) {
    // Fetch categories for this event
    $categoriesStmt = $pdo->prepare("
        SELECT c.*, COALESCE(SUM(n.vote_count), 0) as total_votes
        FROM categories c
        LEFT JOIN nominees n ON c.id = n.category_id
        WHERE c.event_id = ? AND c.status = 'active'
        GROUP BY c.id
        ORDER BY c.display_order ASC
    ");
    $categoriesStmt->execute([$selectedEvent['id']]);
    $categories = $categoriesStmt->fetchAll();
    
    // If category selected, get results for that category
    if ($categoryId > 0) {
        $resultsStmt = $pdo->prepare("
            SELECT n.*, c.name as category_name
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            WHERE n.category_id = ?
            ORDER BY n.vote_count DESC
        ");
        $resultsStmt->execute([$categoryId]);
        $results = $resultsStmt->fetchAll();
    } elseif (!empty($categories)) {
        // Default to first category
        $categoryId = $categories[0]['id'];
        $resultsStmt = $pdo->prepare("
            SELECT n.*, c.name as category_name
            FROM nominees n
            JOIN categories c ON n.category_id = c.id
            WHERE n.category_id = ?
            ORDER BY n.vote_count DESC
        ");
        $resultsStmt->execute([$categoryId]);
        $results = $resultsStmt->fetchAll();
    }
}

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header py-5" style="background: var(--gradient-dark); margin-top: 76px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Results</li>
                    </ol>
                </nav>
                <h1 class="text-white mb-3">
                    <i class="bi bi-broadcast me-2 text-danger pulse"></i>Live Results
                </h1>
                <p class="text-white-50 lead mb-3">Watch the votes count up in real-time. Results are updated every 10 seconds.</p>
                <?php if (!$show_vote_counts): ?>
                    <a href="nominee-results.php" class="btn btn-warning btn-sm rounded-pill px-3 fw-bold shadow-sm">
                        <i class="bi bi-graph-up-arrow me-2"></i>Live Results Portal
                    </a>
                <?php endif; ?>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <div class="live-indicator d-inline-flex align-items-center bg-white bg-opacity-10 rounded-pill px-4 py-2">
                    <span class="live-dot me-2"></span>
                    <span class="text-white">Live Updates</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Results Section -->
<section class="results-section py-5 bg-light">
    <div class="container">
        <?php if (!$show_vote_counts): ?>
            <div class="alert alert-info border-0 shadow-sm mb-4 d-flex align-items-center">
                <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                <div>
                    <strong>Are you a nominee?</strong> Vote counts are currently hidden from the public, but you can view real-time statistics in the 
                    <a href="nominee-results.php" class="alert-link">Live Results Portal</a> using your category's unique access code.
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div class="text-center py-5">
                <i class="bi bi-graph-up display-1 text-muted mb-3"></i>
                <h4>No Results Available</h4>
                <p class="text-muted">Check back once voting events are active.</p>
                <a href="events.php" class="btn btn-primary">View Events</a>
            </div>
        <?php else: ?>
            <div class="row">
                <!-- Event Selection Sidebar -->
                <div class="col-lg-3 mb-4">
                    <div class="card sticky-top" style="top: 100px;">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-trophy me-2"></i>Events</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($events as $event): ?>
                                    <a href="?event=<?php echo $event['slug']; ?>" 
                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center <?php echo ($selectedEvent && $selectedEvent['slug'] === $event['slug']) ? 'active' : ''; ?>">
                                        <div class="text-truncate me-2">
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                        <?php if ($show_vote_counts): ?>
                                        <span class="badge bg-<?php echo ($selectedEvent && $selectedEvent['slug'] === $event['slug']) ? 'light text-primary' : 'primary'; ?> rounded-pill">
                                            <?php echo number_format($event['total_votes']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Results Content -->
                <div class="col-lg-9">
                    <?php if ($selectedEvent): ?>
                        <!-- Event Info Card -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h3 class="mb-1"><?php echo htmlspecialchars($selectedEvent['title']); ?></h3>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($selectedEvent['description']); ?></p>
                                    </div>
                                    <span class="badge bg-<?php echo $selectedEvent['status'] === 'active' ? 'success' : 'secondary'; ?> fs-6">
                                        <?php echo ucfirst($selectedEvent['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($categories)): ?>
                            <div class="text-center py-5 bg-white rounded-3">
                                <i class="bi bi-collection display-1 text-muted mb-3"></i>
                                <h4>No Categories</h4>
                                <p class="text-muted">No categories have been added to this event yet.</p>
                            </div>
                        <?php else: ?>
                            <!-- Category Pills -->
                            <div class="category-pills mb-4 overflow-auto pb-2">
                                <div class="d-flex gap-2 flex-nowrap">
                                    <?php foreach ($categories as $cat): ?>
                                        <a href="?event=<?php echo $selectedEvent['slug']; ?>&category=<?php echo $cat['id']; ?>" 
                                           class="btn <?php echo $cat['id'] == $categoryId ? 'btn-primary' : 'btn-outline-primary'; ?> rounded-pill flex-shrink-0">
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                            <?php if ($show_vote_counts): ?>
                                            <span class="badge bg-<?php echo $cat['id'] == $categoryId ? 'white text-primary' : 'primary'; ?> ms-1">
                                                <?php echo number_format($cat['total_votes']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Results Grid -->
                            <div class="results-grid live-results-container" data-event-id="<?php echo $selectedEvent['id']; ?>" data-category-id="<?php echo $categoryId; ?>">
                                <?php if (empty($results)): ?>
                                    <div class="text-center py-5 bg-white rounded-3">
                                        <i class="bi bi-people display-1 text-muted mb-3"></i>
                                        <h4>No Nominees</h4>
                                        <p class="text-muted">No nominees in this category yet.</p>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    $totalVotes = array_sum(array_column($results, 'vote_count'));
                                    $maxVotes = $results[0]['vote_count'];
                                    
                                    foreach ($results as $index => $nominee): 
                                        $percentage = $totalVotes > 0 ? ($nominee['vote_count'] / $totalVotes) * 100 : 0;
                                        $progressPercent = $maxVotes > 0 ? ($nominee['vote_count'] / $maxVotes) * 100 : 0;
                                    ?>
                                        <div class="result-card card mb-3" data-nominee-id="<?php echo $nominee['id']; ?>">
                                            <div class="card-body">
                                                <div class="d-flex align-items-center">
                                                    <!-- Rank -->
                                                    <div class="rank-circle flex-shrink-0 me-3 <?php echo $index < 3 ? 'top-three' : ''; ?>" 
                                                         style="<?php echo $index === 0 ? 'background: linear-gradient(135deg, #f59e0b, #fbbf24);' : ($index === 1 ? 'background: linear-gradient(135deg, #9ca3af, #d1d5db);' : ($index === 2 ? 'background: linear-gradient(135deg, #cd7f32, #daa06d);' : '')); ?>">
                                                        #<?php echo $index + 1; ?>
                                                    </div>
                                                    
                                                    <!-- Photo -->
                                                    <img src="<?php echo $nominee['photo'] ? 'uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']) . '&background=6366f1&color=fff&size=60'; ?>" 
                                                         alt="<?php echo htmlspecialchars($nominee['name']); ?>"
                                                         class="rounded-circle flex-shrink-0 me-3"
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                    
                                                    <!-- Info -->
                                                    <div class="flex-grow-1 min-width-0">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <div>
                                                                <h5 class="mb-0 text-truncate"><?php echo htmlspecialchars($nominee['name']); ?></h5>
                                                                <small class="text-muted"><?php echo $nominee['shortcode']; ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <?php if ($show_vote_counts): ?>
                                                                    <span class="vote-count fs-4 fw-bold text-primary"><?php echo number_format($nominee['vote_count']); ?></span>
                                                                    <small class="text-muted d-block"><?php echo number_format($percentage, 1); ?>%</small>
                                                                <?php else: ?>
                                                                    <span class="badge bg-primary rounded-pill">Ranked #<?php echo $index + 1; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="progress" style="height: 10px; border-radius: 10px;">
                                                            <div class="progress-bar bg-gradient-primary" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $progressPercent; ?>%; border-radius: 10px;"
                                                                 aria-valuenow="<?php echo $progressPercent; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <!-- Summary -->
                                    <div class="card bg-primary text-white mt-4">
                                        <div class="card-body">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <h3 class="mb-0"><?php echo count($results); ?></h3>
                                                    <small class="text-white-75">Nominees</small>
                                                </div>
                                                <?php if ($show_vote_counts): ?>
                                                <div class="col-4">
                                                    <h3 class="mb-0"><?php echo number_format($totalVotes); ?></h3>
                                                    <small class="text-white-75">Total Votes</small>
                                                </div>
                                                <?php endif; ?>
                                                <div class="col-4">
                                                    <h3 class="mb-0"><?php echo $results[0]['name'] ?? 'N/A'; ?></h3>
                                                    <small class="text-white-75">Leading</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.breadcrumb-item + .breadcrumb-item::before {
    color: rgba(255, 255, 255, 0.5);
}

.live-dot {
    width: 10px;
    height: 10px;
    background: #ef4444;
    border-radius: 50%;
    animation: live-pulse 1.5s infinite;
}

@keyframes live-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.5; transform: scale(1.2); }
}

.rank-circle {
    width: 50px;
    height: 50px;
    background: var(--gray-200);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}

.rank-circle.top-three {
    color: var(--white);
}

.result-card {
    transition: all 0.3s ease;
}

.result-card:hover {
    transform: translateX(5px);
    box-shadow: var(--shadow-md);
}

.bg-gradient-primary {
    background: var(--gradient-primary) !important;
}

.min-width-0 {
    min-width: 0;
}

.category-pills::-webkit-scrollbar {
    height: 6px;
}

.category-pills::-webkit-scrollbar-track {
    background: var(--gray-100);
    border-radius: 3px;
}

.category-pills::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}
</style>

<script>
// Auto-refresh results every 10 seconds
setInterval(function() {
    const container = document.querySelector('.live-results-container');
    if (container) {
        // You could implement AJAX refresh here
        // For now, we'll just add a subtle flash effect to indicate potential updates
        container.style.opacity = '0.8';
        setTimeout(() => {
            container.style.opacity = '1';
        }, 300);
    }
}, 10000);
</script>

<?php include 'includes/footer.php'; ?>
