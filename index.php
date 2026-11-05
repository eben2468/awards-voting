<?php
/**
 * VoteHub - Home Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Home';
$page_description = 'VoteHubGH - Your trusted online awards voting platform. Vote for your favorite nominees in various award categories.';

// Fetch featured events (voting component only)
$pdo = getDBConnection();
$show_vote_counts = getSetting('show_vote_counts', '1') === '1';
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.event_type != 'ticketing'
    GROUP BY e.id
    ORDER BY (e.status = 'active' AND e.end_date >= NOW()) DESC, e.is_featured DESC, e.created_at DESC
    LIMIT 6
");
$stmt->execute();
$events = $stmt->fetchAll();

// Fetch active ticketed events for ticketing section
$tktStmt = $pdo->prepare("
    SELECT e.*, 
           u.fullname as organizer_name,
           (SELECT MIN(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as min_price,
           (SELECT MAX(tc.price) FROM ticket_categories tc WHERE tc.event_id = e.id) as max_price,
           (SELECT COUNT(*) FROM ticket_categories tc WHERE tc.event_id = e.id) as category_count,
           (SELECT COALESCE(SUM(t.quantity), 0) FROM tickets t WHERE t.event_id = e.id) as tickets_sold
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.id
    WHERE e.status = 'active' AND e.end_date >= NOW() AND e.event_type IN ('ticketing', 'both')
    GROUP BY e.id
    ORDER BY e.is_featured DESC, e.created_at DESC
    LIMIT 3
");
$tktStmt->execute();
$ticketEvents = $tktStmt->fetchAll();

// Fetch site statistics
$statsStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM events) as total_events,
        (SELECT COUNT(*) FROM nominees) as total_nominees,
        (SELECT COALESCE(SUM(vote_count), 0) FROM nominees) as total_votes,
        (SELECT COUNT(*) FROM categories) as total_categories
");
$stats = $statsStmt->fetch();

// Fetch top nominees for live results preview
$topNomineesStmt = $pdo->prepare("
    SELECT n.*, c.name as category_name, e.title as event_title
    FROM nominees n
    JOIN categories c ON n.category_id = c.id
    JOIN events e ON c.event_id = e.id
    WHERE e.status = 'active' AND n.status = 'active'
    ORDER BY n.vote_count DESC
    LIMIT 5
");
$topNomineesStmt->execute();
$topNominees = $topNomineesStmt->fetchAll();

include 'includes/header.php';
?>

<!-- Hero Section with Image Slider -->
<section class="hero-home" id="heroHome">
    <!-- Slider Images -->
    <div class="hero-slider">
        <div class="hero-slide active" style="background-image: url('uploads/home/home (2).jpg')"></div>
        <div class="hero-slide" style="background-image: url('uploads/home/home (3).jpg')"></div>
        <div class="hero-slide" style="background-image: url('uploads/home/home (4).jpg')"></div>
    </div>
    
    <!-- Gradient Overlay -->
    <div class="hero-overlay"></div>
    
    <!-- Hero Content -->
    <div class="hero-home-content">
        <div class="container">
            <div class="hero-center-content">
                <span class="hero-badge-pill">
                    <i class="bi bi-stars"></i> #1 Trusted Voting Platform
                </span>
                <h1 class="hero-main-title">
                    Make Your <span>Vote</span> Count
                </h1>
                <p class="hero-desc">
                    Join millions in celebrating excellence. Vote for your favorite nominees across hundreds of award categories with complete transparency and security.
                </p>
                <div class="hero-actions">
                    <a href="events.php" class="btn-hero-primary">
                        <i class="bi bi-trophy me-2"></i>Explore Events <span class="btn-dots">•••</span>
                    </a>
                    <a href="how-it-works.php" class="btn-hero-outline">
                        <i class="bi bi-play-circle me-2"></i>How It Works
                    </a>
                </div>
                
                <!-- Shortcode Search -->
                <div class="hero-search-box">
                    <form action="vote" method="GET" class="hero-search-form">
                        <div class="search-input-wrap">
                            <i class="bi bi-search"></i>
                            <input type="text" name="shortcode" placeholder="Enter shortcode to vote...">
                        </div>
                        <button type="submit" class="btn-search-submit">Vote Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Slider Navigation Dots -->
    <div class="slider-dots">
        <span class="dot active" data-slide="0"></span>
        <span class="dot" data-slide="1"></span>
        <span class="dot" data-slide="2"></span>
    </div>
    
    <!-- Slider Arrows -->
    <button class="slider-arrow slider-prev"><i class="bi bi-chevron-left"></i></button>
    <button class="slider-arrow slider-next"><i class="bi bi-chevron-right"></i></button>
    
    <!-- Wave Bottom -->
    <div class="hero-wave">
        <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path d="M0,64 C360,120 720,0 1080,64 C1260,96 1380,80 1440,64 L1440,120 L0,120 Z" fill="#ffffff"/>
        </svg>
    </div>
</section>

<!-- Stats Counter Bar -->
<section class="stats-bar">
    <div class="container">
        <div class="stats-bar-inner">
            <?php if ($show_vote_counts): ?>
            <div class="stat-bar-item">
                <div class="stat-bar-icon"><i class="bi bi-hand-thumbs-up-fill"></i></div>
                <div>
                    <span class="stat-bar-number counter" data-target="<?php echo $stats['total_votes']; ?>"><?php echo number_format($stats['total_votes']); ?></span>
                    <span class="stat-bar-label">Total Votes</span>
                </div>
            </div>
            <?php endif; ?>
            <div class="stat-bar-item">
                <div class="stat-bar-icon"><i class="bi bi-calendar-event-fill"></i></div>
                <div>
                    <span class="stat-bar-number counter" data-target="<?php echo $stats['total_events']; ?>"><?php echo $stats['total_events']; ?></span>
                    <span class="stat-bar-label">Events</span>
                </div>
            </div>
            <div class="stat-bar-item">
                <div class="stat-bar-icon"><i class="bi bi-people-fill"></i></div>
                <div>
                    <span class="stat-bar-number counter" data-target="<?php echo $stats['total_nominees']; ?>"><?php echo $stats['total_nominees']; ?></span>
                    <span class="stat-bar-label">Nominees</span>
                </div>
            </div>
            <div class="stat-bar-item">
                <div class="stat-bar-icon"><i class="bi bi-grid-fill"></i></div>
                <div>
                    <span class="stat-bar-number counter" data-target="<?php echo $stats['total_categories']; ?>"><?php echo $stats['total_categories']; ?></span>
                    <span class="stat-bar-label">Categories</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Events Section -->
<section class="featured-events py-5" id="featured-events">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Hot & Trending</span>
            <h2>Featured Events</h2>
            <p>Discover and vote in the most exciting award events happening right now</p>
        </div>
        
        <div class="row g-4">
            <?php if (empty($events)): ?>
                <div class="col-12 text-center py-5">
                    <div class="empty-state">
                        <i class="bi bi-calendar-x display-1 text-muted mb-3"></i>
                        <h4>No Active Events</h4>
                        <p class="text-muted">Check back soon for exciting new voting events!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($events as $index => $event): ?>
                    <?php
                        $isEnded = strtotime($event['end_date']) < time();
                        $isUpcoming = strtotime($event['start_date']) > time();
                        $daysLeft = floor((strtotime($event['end_date']) - time()) / (60 * 60 * 24));
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card ticket-event-card h-100 bg-white">
                            <div class="ticket-image-wrapper">
                                <img src="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>"
                                     class="card-img-top">
                                
                                <div class="ticket-overlay">
                                    <?php if ($show_vote_counts): ?>
                                        <span class="ticket-badge-price"><?php echo number_format($event['total_votes']); ?> votes</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($event['is_featured']): ?>
                                    <span class="ticket-badge-featured">
                                        <i class="bi bi-star-fill me-1"></i>Featured
                                    </span>
                                <?php endif; ?>

                                <?php if ($isEnded): ?>
                                    <span class="ticket-badge-code bg-secondary text-white">
                                        <i class="bi bi-check-circle me-1"></i>Ended
                                    </span>
                                <?php elseif ($isUpcoming): ?>
                                    <span class="ticket-badge-code bg-info text-white">
                                        <i class="bi bi-calendar-event me-1"></i>Upcoming
                                    </span>
                                <?php else: ?>
                                    <span class="ticket-badge-code bg-success text-white">
                                        <i class="bi bi-lightning-fill me-1"></i>Active
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="card-body d-flex flex-column p-4">
                                <h5 class="card-title fw-bold mb-2 text-dark"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="card-text text-muted small flex-grow-1">
                                    <?php echo strlen($event['description']) > 100 ? substr(htmlspecialchars($event['description']), 0, 100) . '...' : htmlspecialchars($event['description']); ?>
                                </p>
                                
                                <div class="ticket-meta mb-3">
                                    <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                        <span><i class="bi bi-calendar3 me-1"></i><?php echo date('M d, Y', strtotime($event['start_date'])); ?></span>
                                        <span>
                                            <i class="bi bi-clock me-1"></i>
                                            <?php if (!$isEnded && !$isUpcoming && $daysLeft >= 0): ?>
                                                <?php echo $daysLeft; ?> days left
                                            <?php elseif ($isEnded): ?>
                                                Completed
                                            <?php elseif ($isUpcoming): ?>
                                                Starts <?php echo date('M d', strtotime($event['start_date'])); ?>
                                            <?php else: ?>
                                                Ended
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center text-muted small">
                                        <span><i class="bi bi-collection me-1"></i><?php echo $event['category_count']; ?> Category<?php echo $event['category_count'] > 1 ? 'ies' : ''; ?></span>
                                        <span><i class="bi bi-people me-1"></i><?php echo $event['nominee_count']; ?> Nominee<?php echo $event['nominee_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-transparent border-0 pb-4 px-4">
                                <a href="event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-ticket-buy w-100 rounded-pill py-2.5 fw-bold text-center text-white d-block">
                                    <?php 
                                        if ($isEnded) echo 'View Results';
                                        elseif ($isUpcoming) echo 'View Details';
                                        else echo 'Vote Now';
                                    ?>
                                    <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($events)): ?>
            <div class="text-center mt-5">
                <a href="events.php" class="btn btn-primary btn-lg">
                    View All Events <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Event Ticketing Section -->
<section class="ticketing-section py-5" style="background: #f8fafc; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0;">
    <div class="container">
        <div class="section-header text-center mb-5">
            <span class="subtitle text-primary" style="font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 0.85rem;">
                <i class="bi bi-ticket-perforated-fill me-1"></i> Fast & Secure Entry
            </span>
            <h2 class="fw-bold mb-3" style="color: #0f172a;">Event Ticketing</h2>
            <p class="text-muted col-lg-6 mx-auto">Purchase tickets directly from your device using Mobile Money or Debit/Credit Cards. Instant confirmation codes sent via SMS.</p>
        </div>
        
        <div class="row g-4">
            <?php if (empty($ticketEvents)): ?>
                <div class="col-12 text-center py-5">
                    <div class="empty-state">
                        <i class="bi bi-ticket-perforated display-1 text-muted mb-3 d-block"></i>
                        <h4>No Upcoming Ticketed Events</h4>
                        <p class="text-muted">Stay tuned! Exciting ticketed events will be posted here soon.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($ticketEvents as $event): ?>
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
                        <div class="card ticket-event-card h-100 bg-white">
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

                            <div class="card-body d-flex flex-column p-4">
                                <h5 class="card-title fw-bold mb-2 text-dark"><?php echo htmlspecialchars($event['title']); ?></h5>
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

                            <div class="card-footer bg-transparent border-0 pb-4 px-4">
                                <button type="button" class="btn btn-ticket-buy w-100 rounded-pill py-2.5 fw-bold"
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
        
        <?php if (!empty($ticketEvents)): ?>
            <div class="text-center mt-5">
                <a href="ticketing.php" class="btn btn-outline-primary btn-lg rounded-pill px-4">
                    Browse All Tickets <i class="bi bi-arrow-right ms-2"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- How It Works Section -->
<section class="how-it-works-v2 py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <span class="subtitle">Simple & Secure</span>
            <h2>How It Works</h2>
            <p>Support nominees or secure event tickets in just a few simple steps</p>
        </div>

        <!-- Tab Pills Switcher -->
        <div class="d-flex justify-content-center mb-5">
            <div class="nav nav-pills bg-light rounded-pill p-1 shadow-sm" id="how-pills-tab" role="tablist">
                <button class="nav-link active rounded-pill px-4 py-2 fw-bold" id="pills-voting-tab" data-bs-toggle="pill" data-bs-target="#pills-voting" type="button" role="tab" aria-controls="pills-voting" aria-selected="true">
                    <i class="bi bi-award me-1"></i>Awards Voting
                </button>
                <button class="nav-link rounded-pill px-4 py-2 fw-bold" id="pills-ticketing-tab" data-bs-toggle="pill" data-bs-target="#pills-ticketing" type="button" role="tab" aria-controls="pills-ticketing" aria-selected="false">
                    <i class="bi bi-ticket-perforated me-1"></i>Event Tickets
                </button>
            </div>
        </div>

        <div class="tab-content" id="how-pills-tabContent">
            <!-- Awards Voting Flow -->
            <div class="tab-pane fade show active" id="pills-voting" role="tabpanel" aria-labelledby="pills-voting-tab">
                <div class="steps-timeline">
                    <div class="step-v2" data-aos="fade-up">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>1</span></div></div>
                        <h4>Find an Event</h4>
                        <p>Browse active events and select your preferred category</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>2</span></div></div>
                        <h4>Select Nominee</h4>
                        <p>Choose your favorite nominee in each category</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="200">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>3</span></div></div>
                        <h4>Pay & Vote</h4>
                        <p>Complete payment to cast your vote(s) for multiple nominees</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="300">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>4</span></div></div>
                        <h4>Multiple Votes</h4>
                        <p>Vote for multiple nominees across categories in the same event</p>
                    </div>
                </div>
            </div>

            <!-- Event Tickets Flow -->
            <div class="tab-pane fade" id="pills-ticketing" role="tabpanel" aria-labelledby="pills-ticketing-tab">
                <div class="steps-timeline">
                    <div class="step-v2" data-aos="fade-up">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>1</span></div></div>
                        <h4>Select Event</h4>
                        <p>Browse active ticketed events on the homepage or ticketing page</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="100">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>2</span></div></div>
                        <h4>Choose Tier</h4>
                        <p>Pick your preferred ticket category (Regular, VIP, etc.) and quantity</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="200">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>3</span></div></div>
                        <h4>Pay Securely</h4>
                        <p>Complete transaction using Mobile Money (MoMo Push) or Debit/Credit Cards</p>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-v2" data-aos="fade-up" data-aos-delay="300">
                        <div class="step-icon-wrap"><div class="step-icon-circle"><span>4</span></div></div>
                        <h4>Receive Ticket</h4>
                        <p>Get your unique ticket reference code instantly via SMS to present at entry</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- Live Results Preview Section -->
<?php if (!empty($topNominees)): ?>
<section class="live-results py-5">
    <div class="container">
        <div class="section-header">
            <span class="subtitle text-warning"><i class="bi bi-broadcast me-1"></i> Live Updates</span>
            <h2>Trending Nominees</h2>
            <p>See who's leading the race in real-time</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php 
                $maxVotes = !empty($topNominees) ? $topNominees[0]['vote_count'] : 1;
                foreach ($topNominees as $index => $nominee): 
                    $percentage = $maxVotes > 0 ? ($nominee['vote_count'] / $maxVotes) * 100 : 0;
                ?>
                    <div class="result-item animate-on-scroll" data-nominee-id="<?php echo $nominee['id']; ?>">
                        <div class="nominee-info">
                            <span class="rank-badge rank-<?php echo ($index < 3) ? ($index + 1) : 'other'; ?> me-3">
                                #<?php echo $index + 1; ?>
                            </span>
                            <img src="<?php echo $nominee['photo'] ? 'uploads/nominees/' . $nominee['photo'] : 'https://ui-avatars.com/api/?name=' . urlencode($nominee['name']) . '&background=6366f1&color=fff'; ?>" 
                                 alt="<?php echo htmlspecialchars($nominee['name']); ?>" 
                                 class="nominee-avatar">
                            <div>
                                <h5 class="mb-0 text-white"><?php echo htmlspecialchars($nominee['name']); ?></h5>
                                <small class="text-white-50">
                                    <?php echo htmlspecialchars($nominee['category_name']); ?> • <?php echo htmlspecialchars($nominee['event_title']); ?>
                                </small>
                            </div>
                            <?php if ($show_vote_counts): ?>
                            <span class="ms-auto vote-count fs-4 fw-bold text-white">
                                <?php echo number_format($nominee['vote_count']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <a href="results.php" class="btn btn-outline-light btn-lg">
                        View Full Results <i class="bi bi-arrow-right ms-2"></i>
                    </a>
                    <?php if (!$show_vote_counts): ?>
                        <div class="mt-3">
                            <a href="nominee-results.php" class="btn btn-warning btn-sm rounded-pill px-4 fw-bold shadow-sm">
                                <i class="bi bi-graph-up-arrow me-2"></i>Nominee Results Portal
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="features-v2 py-5">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Why Choose Us</span>
            <h2>Built for Trust & Transparency</h2>
            <p>Our platform ensures fair and secure voting for all</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #6366f1, #818cf8);">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h4>Secure Voting</h4>
                    <p>Advanced encryption and verification ensures every vote is protected and counted accurately.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #ec4899, #f472b6);">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h4>Real-Time Results</h4>
                    <p>Watch votes count up in real-time with our live dashboard and analytics.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #10b981, #34d399);">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h4>Mobile Friendly</h4>
                    <p>Vote from anywhere using your smartphone, tablet, or computer with ease.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #f59e0b, #fbbf24);">
                        <i class="bi bi-lightning-charge"></i>
                    </div>
                    <h4>Fast & Easy</h4>
                    <p>Simple shortcode voting makes casting your vote quick and effortless.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #3b82f6, #60a5fa);">
                        <i class="bi bi-ticket-perforated"></i>
                    </div>
                    <h4>Seamless Ticketing</h4>
                    <p>Secure tickets online or via USSD using Mobile Money or Card. Receive instant SMS confirmations.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="feature-card-v2">
                    <div class="feature-icon-v2" style="--icon-gradient: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                        <i class="bi bi-headset"></i>
                    </div>
                    <h4>24/7 Support</h4>
                    <p>Our dedicated support team is always ready to help with any questions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section-v2">
    <div class="container">
        <div class="cta-inner">
            <h2>Ready to Make Your Voice Heard?</h2>
            <p>Join thousands of supporters and support your favorite nominees today. Every vote matters!</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn-cta-light">
                    <i class="bi bi-person-plus me-2"></i>Create Free Account
                </a>
                <a href="events.php" class="btn-cta-outline">
                    <i class="bi bi-trophy me-2"></i>Browse Events
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Hero Slider Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.hero-slide');
    const dots = document.querySelectorAll('.slider-dots .dot');
    const prevBtn = document.querySelector('.slider-prev');
    const nextBtn = document.querySelector('.slider-next');
    let current = 0;
    let interval;

    function goToSlide(n) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        current = (n + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
    }

    function nextSlide() { goToSlide(current + 1); }
    function prevSlide() { goToSlide(current - 1); }

    function startAuto() { interval = setInterval(nextSlide, 5000); }
    function stopAuto() { clearInterval(interval); }

    if (prevBtn) prevBtn.addEventListener('click', function() { stopAuto(); prevSlide(); startAuto(); });
    if (nextBtn) nextBtn.addEventListener('click', function() { stopAuto(); nextSlide(); startAuto(); });
    dots.forEach(dot => {
        dot.addEventListener('click', function() {
            stopAuto();
            goToSlide(parseInt(this.dataset.slide));
            startAuto();
        });
    });

    startAuto();

    // Animate on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.animate-on-scroll, .feature-card-v2, .step-v2, .stat-bar-item').forEach(el => observer.observe(el));
});
</script>

<?php include 'includes/vote-modal.php'; ?>
<?php include 'includes/ticket-modal.php'; ?>
<?php include 'includes/footer.php'; ?>
