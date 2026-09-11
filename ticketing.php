<?php
/**
 * Ticketing Page - Buy event tickets
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Event Ticketing';
$page_description = 'Browse ticketed events and purchase tickets securely with Mobile Money or Card.';

$pdo = getDBConnection();

// Get filter parameters
$search = sanitize($_GET['search'] ?? '');

// Build query - only events with ticketing enabled
$whereConditions = ["e.status = 'active'", "e.end_date >= NOW()", "e.event_type IN ('ticketing', 'both')"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.event_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Fetch ticketed events with their categories
$sql = "
    SELECT e.*, 
           u.fullname as organizer_name,
           (SELECT MIN(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as min_price,
           (SELECT MAX(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as max_price,
           (SELECT COUNT(*) FROM ticket_categories tc WHERE tc.event_id = e.id) as category_count,
           (SELECT COALESCE(SUM(t.quantity), 0) FROM tickets t WHERE t.event_id = e.id) as tickets_sold
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    $whereClause
    GROUP BY e.id
    ORDER BY e.is_featured DESC, e.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="ticketing-hero py-5" style="margin-top: 76px;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                        <li class="breadcrumb-item active text-white" aria-current="page">Ticketing</li>
                    </ol>
                </nav>
                <h1 class="text-white mb-3 fw-bold">
                    <i class="bi bi-ticket-perforated-fill me-2"></i>Event Ticketing
                </h1>
                <p class="text-white-50 lead mb-0">Secure your spot at the hottest events. Pay with Mobile Money or Card.</p>
            </div>
            <div class="col-lg-6">
                <div class="search-box mt-4 mt-lg-0">
                    <form action="" method="GET" class="d-flex gap-2">
                        <div class="input-group">
                            <input type="text" 
                                   name="search" 
                                   class="form-control" 
                                   placeholder="Search events or enter event code..." 
                                   value="<?php echo htmlspecialchars($search); ?>"
                                   id="ticketingSearchInput">
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

<!-- Events Grid -->
<section class="ticketing-events py-5">
    <div class="container">
        <!-- Results Count -->
        <p class="text-muted mb-4">
            Showing <strong><?php echo count($events); ?></strong> ticketed event<?php echo count($events) !== 1 ? 's' : ''; ?>
            <?php if (!empty($search)): ?>
                for "<strong><?php echo htmlspecialchars($search); ?></strong>"
            <?php endif; ?>
        </p>

        <div class="row g-4">
            <?php if (empty($events)): ?>
                <div class="col-12">
                    <div class="text-center py-5 bg-white rounded-3 shadow-sm">
                        <i class="bi bi-ticket-perforated display-1 text-muted mb-3 d-block"></i>
                        <h4>No Ticketed Events Found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search)): ?>
                                No events match your search. Try different keywords or an event code.
                            <?php else: ?>
                                Check back soon for exciting ticketed events!
                            <?php endif; ?>
                        </p>
                        <a href="ticketing.php" class="btn btn-primary">View All Events</a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <?php
                        $daysLeft = floor((strtotime($event['end_date']) - time()) / (60 * 60 * 24));
                        
                        // Fetch ticket categories for this event
                        $catStmt = $pdo->prepare("SELECT * FROM ticket_categories WHERE event_id = ? ORDER BY price ASC");
                        $catStmt->execute([$event['id']]);
                        $categories = $catStmt->fetchAll();
                        
                        $priceRange = '';
                        if ($event['min_price'] == $event['max_price']) {
                            $priceRange = 'GHS ' . number_format($event['min_price'], 2);
                        } else {
                            $priceRange = 'GHS ' . number_format($event['min_price'], 2) . ' - ' . number_format($event['max_price'], 2);
                        }
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card ticket-event-card h-100">
                            <div class="ticket-image-wrapper">
                                <img src="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                                     class="card-img-top">
                                
                                <div class="ticket-overlay">
                                    <span class="ticket-badge-price"><?php echo $priceRange; ?></span>
                                </div>

                                <?php if ($event['is_featured']): ?>
                                    <span class="ticket-badge-featured">
                                        <i class="bi bi-star-fill me-1"></i>Featured
                                    </span>
                                <?php endif; ?>

                                <?php if ($event['event_code']): ?>
                                    <span class="ticket-badge-code">
                                        <i class="bi bi-qr-code me-1"></i><?php echo htmlspecialchars($event['event_code']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title fw-bold mb-2"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="card-text text-muted small flex-grow-1">
                                    <?php echo strlen($event['description']) > 100 ? substr(htmlspecialchars($event['description']), 0, 100) . '...' : htmlspecialchars($event['description']); ?>
                                </p>
                                
                                <div class="ticket-meta mb-3">
                                    <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                        <span><i class="bi bi-calendar3 me-1"></i><?php echo date('M d, Y', strtotime($event['start_date'])); ?></span>
                                        <span><i class="bi bi-clock me-1"></i><?php echo $daysLeft; ?> days left</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                        <span><i class="bi bi-tags me-1"></i><?php echo $event['category_count']; ?> tier<?php echo $event['category_count'] > 1 ? 's' : ''; ?></span>
                                        <span><i class="bi bi-ticket me-1"></i><?php echo number_format($event['tickets_sold']); ?> sold</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-transparent border-0 pb-3 px-3">
                                <button type="button" class="btn btn-ticket-buy w-100 rounded-pill py-2 fw-bold"
                                        data-event-id="<?php echo $event['id']; ?>"
                                        data-event-title="<?php echo htmlspecialchars($event['title']); ?>"
                                        data-event-banner="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>"
                                        data-categories='<?php echo json_encode($categories); ?>'>
                                    <i class="bi bi-ticket-perforated me-2"></i>Buy Ticket
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/ticket-modal.php'; ?>


<?php include 'includes/footer.php'; ?>
