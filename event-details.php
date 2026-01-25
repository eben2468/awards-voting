<?php
/**
 * Event Details Page
 * Awards Voting System
 */

require_once 'config/database.php';

$slug = sanitize($_GET['slug'] ?? '');

if (empty($slug)) {
    setFlash('danger', 'Event not found.');
    redirect('events.php');
}

$pdo = getDBConnection();

// Fetch event details
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.slug = ?
    GROUP BY e.id
");
$stmt->execute([$slug]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'Event not found.');
    redirect('events.php');
}

// Check voting status
$isEnded = strtotime($event['end_date']) < time();
$isUpcoming = strtotime($event['start_date']) > time();
$isActive = !$isEnded && !$isUpcoming;
$daysLeft = floor((strtotime($event['end_date']) - time()) / (60 * 60 * 24));

// Fetch categories with nominees
$categoriesStmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM categories c
    LEFT JOIN nominees n ON c.id = n.category_id AND n.status = 'active'
    WHERE c.event_id = ? AND c.status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC, c.name ASC
");
$categoriesStmt->execute([$event['id']]);
$categories = $categoriesStmt->fetchAll();

// Unlimited voting allowed - no need to check for existing votes
$userVotes = [];

$page_title = $event['title'];
$page_description = $event['description'];

include 'includes/header.php';
?>

<!-- Event Header -->
<section class="event-header" style="padding: 0;">
    <div class="event-banner" style="background-image: url('<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/1920/600?random=' . $event['id']; ?>');">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                            <li class="breadcrumb-item"><a href="events.php" class="text-white-50">Events</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page"><?php echo htmlspecialchars($event['title']); ?></li>
                        </ol>
                    </nav>
                    
                    <div class="d-flex flex-wrap gap-3 mb-4">
                        <?php if ($event['is_featured']): ?>
                            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill">
                                <i class="bi bi-star-fill me-1"></i>Featured
                            </span>
                        <?php endif; ?>
                        <?php if ($isActive): ?>
                            <span class="badge bg-success px-3 py-2 rounded-pill">
                                <i class="bi bi-lightning-fill me-1"></i>Active
                            </span>
                        <?php elseif ($isEnded): ?>
                            <span class="badge bg-secondary px-3 py-2 rounded-pill">
                                <i class="bi bi-check-circle me-1"></i>Ended
                            </span>
                        <?php else: ?>
                            <span class="badge bg-info px-3 py-2 rounded-pill">
                                <i class="bi bi-clock me-1"></i>Upcoming
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-white display-3 fw-bold mb-2"><?php echo htmlspecialchars($event['title']); ?></h1>
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <span class="badge bg-primary px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-phone me-1"></i> Dial *920*137# to Vote
                        </span>
                    </div>
                    <p class="text-white-75 lead mb-5 fs-4"><?php echo htmlspecialchars($event['description']); ?></p>
                    
                    <div class="d-flex flex-wrap gap-5 text-white fs-5">
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-white bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="bi bi-calendar-event"></i>
                            </div>
                            <div>
                                <small class="text-white-50 d-block">Start Date</small>
                                <strong><?php echo date('M d, Y', strtotime($event['start_date'])); ?></strong>
                            </div>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="icon-box bg-white bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            <div>
                                <small class="text-white-50 d-block">End Date</small>
                                <strong><?php echo date('M d, Y', strtotime($event['end_date'])); ?></strong>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <div class="d-flex align-items-center text-warning">
                                <div class="icon-box bg-warning bg-opacity-10 rounded-circle p-2 me-3">
                                    <i class="bi bi-clock"></i>
                                </div>
                                <div>
                                    <small class="text-warning-50 d-block">Remaining</small>
                                    <strong id="countdown-timer" data-endtime="<?php echo $event['end_date']; ?>">
                                        Calculating...
                                    </strong>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-block">
                    <div class="event-stats-card rounded-4 p-5">
                        <div class="row g-4">
                            <div class="col-6">
                                <div class="stat-box text-center">
                                    <h2 class="text-white fw-bold mb-1"><?php echo number_format($event['total_votes']); ?></h2>
                                    <p class="text-white-50 small text-uppercase mb-0">Total Votes</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box text-center">
                                    <h2 class="text-white fw-bold mb-1"><?php echo $event['category_count']; ?></h2>
                                    <p class="text-white-50 small text-uppercase mb-0">Categories</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box text-center">
                                    <h2 class="text-white fw-bold mb-1"><?php echo $event['nominee_count']; ?></h2>
                                    <p class="text-white-50 small text-uppercase mb-0">Nominees</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-box text-center">
                                    <h2 class="text-white fw-bold mb-1 text-capitalize"><?php echo $event['voting_type']; ?></h2>
                                    <p class="text-white-50 small text-uppercase mb-0">Voting Type</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Categories & Nominees -->
<section class="categories-section py-5 bg-light">
    <div class="container">
        <?php if (empty($categories)): ?>
            <div class="text-center py-5">
                <i class="bi bi-collection display-1 text-muted mb-3"></i>
                <h4>No Categories Yet</h4>
                <p class="text-muted">Categories and nominees will be added soon.</p>
            </div>
        <?php else: ?>
            <!-- Categories Accordion -->
            <div class="accordion category-accordion" id="eventCategories">
                <?php foreach ($categories as $index => $category): ?>
                    <div class="accordion-item mb-3 border-0 rounded-4 overflow-hidden shadow-sm">
                        <h2 class="accordion-header" id="heading-<?php echo $category['id']; ?>">
                            <button class="accordion-button <?php echo $index !== 0 ? 'collapsed' : ''; ?> p-4 bg-white" 
                                    type="button" 
                                    data-bs-toggle="collapse" 
                                    data-bs-target="#collapse-<?php echo $category['id']; ?>" 
                                    aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" 
                                    aria-controls="collapse-<?php echo $category['id']; ?>">
                                <div class="d-flex align-items-center w-100">
                                    <div class="category-icon me-3 bg-primary bg-opacity-10 text-primary rounded-3 p-2">
                                        <i class="bi bi-trophy fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($category['name']); ?></h5>
                                        <div class="d-flex gap-3 small text-muted">
                                            <span><i class="bi bi-people me-1"></i><?php echo $category['nominee_count']; ?> Nominees</span>
                                            <span><i class="bi bi-check2-square me-1"></i><?php echo number_format($category['total_votes']); ?> Total Votes</span>
                                        </div>
                                    </div>
                                    <?php if (isset($userVotes[$category['id']])): ?>
                                        <span class="badge bg-success rounded-pill me-3">
                                            <i class="bi bi-check-circle me-1"></i>Voted
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </button>
                        </h2>
                        <div id="collapse-<?php echo $category['id']; ?>" 
                             class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                             aria-labelledby="heading-<?php echo $category['id']; ?>" 
                             data-bs-parent="#eventCategories">
                            <div class="accordion-body bg-light p-4">
                                <?php if ($category['description']): ?>
                                    <p class="text-muted mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                                <?php endif; ?>

                                <?php
                                // Fetch nominees for this category
                                $nomineesStmt = $pdo->prepare("
                                    SELECT * FROM nominees 
                                    WHERE category_id = ? AND status = 'active'
                                    ORDER BY vote_count DESC
                                ");
                                $nomineesStmt->execute([$category['id']]);
                                $nominees = $nomineesStmt->fetchAll();
                                
                                $maxVotes = !empty($nominees) ? max(array_column($nominees, 'vote_count')) : 1;
                                ?>

                                <div class="row g-4">
                                    <?php foreach ($nominees as $nominee): ?>
                                        <?php 
                                        $percentage = $maxVotes > 0 ? ($nominee['vote_count'] / $maxVotes) * 100 : 0;
                                        $hasVoted = false;
                                        $votedForThis = false;
                                        ?>
                                        <div class="col-md-6 col-lg-4">
                                            <div class="card nominee-card h-100 border-0 shadow-sm rounded-4 <?php echo $votedForThis ? 'ring-success' : ''; ?>">
                                                <?php if ($votedForThis): ?>
                                                    <div class="voted-badge position-absolute top-0 end-0 m-2">
                                                        <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>Your Vote</span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="nominee-photo-wrapper text-center pt-4">
                                                    <div class="position-relative d-inline-block">
                                                        <img src="<?php echo $nominee['photo'] ? 'uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']) . '&background=6366f1&color=fff&size=150'; ?>" 
                                                             alt="<?php echo htmlspecialchars($nominee['name']); ?>"
                                                             class="nominee-photo rounded-circle shadow-sm"
                                                             style="width: 100px; height: 100px; object-fit: cover; border: 4px solid #fff;">
                                                    </div>
                                                </div>
                                                
                                                <div class="card-body text-center">
                                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($nominee['name']); ?></h6>
                                                    <span class="shortcode badge bg-primary bg-opacity-10 text-primary mb-3"><?php echo $nominee['shortcode']; ?></span>
                                                    
                                                    <?php if ($nominee['description']): ?>
                                                        <p class="text-muted small mb-3"><?php echo strlen($nominee['description']) > 60 ? substr(htmlspecialchars($nominee['description']), 0, 60) . '...' : htmlspecialchars($nominee['description']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <div class="vote-progress mb-3">
                                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                                            <span><?php echo number_format($nominee['vote_count']); ?> votes</span>
                                                            <span><?php echo round($percentage); ?>%</span>
                                                        </div>
                                                        <div class="progress rounded-pill" style="height: 6px;">
                                                            <div class="progress-bar bg-gradient-primary" style="width: <?php echo $percentage; ?>%"></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($isActive): ?>
                                                        <form action="vote.php" method="POST" class="vote-form">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                            <input type="hidden" name="nominee_id" value="<?php echo $nominee['id']; ?>">
                                                            <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="vote_price" value="<?php echo $event['vote_price']; ?>">
                                                            <input type="hidden" name="nominee_name" value="<?php echo htmlspecialchars($nominee['name']); ?>">
                                                            
                                                            <?php if ($event['voting_type'] === 'paid'): ?>
                                                                <button type="button" class="btn btn-outline-primary btn-sm w-100 rounded-pill btn-buy-votes" 
                                                                        data-nominee-id="<?php echo $nominee['id']; ?>"
                                                                        data-nominee-name="<?php echo htmlspecialchars($nominee['name']); ?>"
                                                                        data-price="<?php echo $event['vote_price']; ?>">
                                                                    <i class="bi bi-credit-card me-1"></i> Vote (GHS <?php echo number_format($event['vote_price'], 2); ?>)
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" class="btn btn-outline-primary btn-sm w-100 rounded-pill">
                                                                    Vote Now
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>
                                                    <?php elseif ($isEnded): ?>
                                                        <button class="btn btn-light btn-sm w-100 rounded-pill text-muted" disabled>
                                                            Ended
                                                        </button>
                                                    <?php else: ?>
                                                        <button class="btn btn-light btn-sm w-100 rounded-pill text-muted" disabled>
                                                            Upcoming
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<style>
.category-accordion .accordion-button:not(.collapsed) {
    background-color: #fff;
    color: var(--primary);
    box-shadow: none;
}

.category-accordion .accordion-button::after {
    background-size: 1.25rem;
    transition: transform 0.3s ease;
}

.category-accordion .accordion-item {
    transition: all 0.3s ease;
}

.category-accordion .accordion-item:hover {
    transform: translateY(-2px);
}

.nominee-card {
    transition: all 0.3s ease;
}

.nominee-card:hover {
    transform: translateY(-5px);
}

.ring-success {
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2), var(--shadow-sm) !important;
    border: 1px solid var(--success) !important;
}

.bg-gradient-primary {
    background: var(--gradient-primary) !important;
}

.shortcode {
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
}

.category-icon {
    transition: all 0.3s ease;
}

.accordion-button:not(.collapsed) .category-icon {
    background-color: var(--primary) !important;
    color: #fff !important;
}
</style>

<script>
// Countdown Timer Logic
function updateCountdown() {
    const timerElement = document.getElementById('countdown-timer');
    if (!timerElement) return;

    const endTimeStr = timerElement.getAttribute('data-endtime');
    const endTime = new Date(endTimeStr).getTime();
    
    const interval = setInterval(function() {
        const now = new Date().getTime();
        const distance = endTime - now;
        
        if (distance < 0) {
            clearInterval(interval);
            timerElement.innerHTML = "Voting Ended";
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        let countdownStr = "";
        if (days > 0) countdownStr += days + "d ";
        countdownStr += hours.toString().padStart(2, '0') + "h " + 
                       minutes.toString().padStart(2, '0') + "m " + 
                       seconds.toString().padStart(2, '0') + "s";
        
        timerElement.innerHTML = countdownStr;
    }, 1000);
}

document.addEventListener('DOMContentLoaded', updateCountdown);
</script>

<?php include 'includes/vote-modal.php'; ?>

<?php include 'includes/footer.php'; ?>
