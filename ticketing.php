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
                                   value="<?php echo e($search); ?>"
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
                for "<strong><?php echo e($search); ?></strong>"
            <?php endif; ?>
        </p>

        <div class="ev-grid">
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
                <?php $ticketIndex = 0; foreach ($events as $event): ?>
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
                    <div class="rv" style="--d:<?php echo ($ticketIndex++ % 4) * 70; ?>ms">
                        <article class="ev">
                            <span class="ev-img">
                                <img src="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>" alt="<?php echo e($event['title']); ?>" loading="lazy">
                                <?php if ($event['is_featured']): ?>
                                    <span class="ev-star" title="Featured"><i class="bi bi-star-fill" aria-hidden="true"></i><span class="visually-hidden">Featured</span></span>
                                <?php endif; ?>
                            </span>
                            <span class="ev-body">
                                <span class="ev-state is-open"><?php echo $daysLeft < 1 ? 'Last day' : number_format($daysLeft) . ($daysLeft == 1 ? ' day left' : ' days left'); ?></span>
                                <span class="ev-title"><?php echo e($event['title']); ?></span>
                                <span class="ev-meta">
                                    <span><?php echo $event['category_count']; ?> tier<?php echo $event['category_count'] == 1 ? '' : 's'; ?></span>
                                    <span><?php echo number_format($event['tickets_sold']); ?> sold</span>
                                    <?php if ($event['event_code']): ?>
                                        <span>Code <?php echo e($event['event_code']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="ev-price"><?php echo $priceRange; ?></span>
                                <button type="button" class="btn btn-ticket-buy ev-buy"
                                        data-event-id="<?php echo $event['id']; ?>"
                                        data-event-title="<?php echo e($event['title']); ?>"
                                        data-event-banner="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>"
                                        data-categories='<?php echo json_encode($categories); ?>'>
                                    Buy ticket
                                </button>
                            </span>
                        </article>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/ticket-modal.php'; ?>


<?php include 'includes/footer.php'; ?>
