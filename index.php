<?php
/**
 * VoteHub - Home Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Home';
$page_description = 'VoteHubGH - Your trusted online awards voting platform. Vote for your favorite nominees in various award categories.';

// Fetch featured events
$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT c.id) as category_count,
           COUNT(DISTINCT n.id) as nominee_count,
           COALESCE(SUM(n.vote_count), 0) as total_votes
    FROM events e
    LEFT JOIN categories c ON e.id = c.event_id
    LEFT JOIN nominees n ON c.id = n.category_id
    WHERE e.status = 'active' AND e.end_date >= NOW()
    GROUP BY e.id
    ORDER BY e.is_featured DESC, e.start_date ASC
    LIMIT 6
");
$stmt->execute();
$events = $stmt->fetchAll();

// Fetch site statistics
$statsStmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM events WHERE status = 'active') as total_events,
        (SELECT COUNT(*) FROM nominees WHERE status = 'active') as total_nominees,
        (SELECT COALESCE(SUM(vote_count), 0) FROM nominees) as total_votes,
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users
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

<!-- Hero Section -->
<section class="hero-section">
    <!-- Background Animation Elements -->
    <div class="hero-bg-elements">
        <div class="hero-circle hero-circle-1"></div>
        <div class="hero-circle hero-circle-2"></div>
        <div class="hero-circle hero-circle-3"></div>
    </div>
    
    <div class="container">
        <div class="row align-items-center min-vh-100">
            <div class="col-lg-6">
                <div class="hero-content">
                    <span class="badge bg-primary bg-opacity-25 text-white px-3 py-2 rounded-pill mb-2 fs-6">
                        <i class="bi bi-stars me-1"></i> #1 Trusted Voting Platform
                    </span>
                    <h1 class="hero-title">
                        Make Your <span class="highlight">Vote</span> Count
                    </h1>
                    <p class="hero-subtitle">
                        Join millions in celebrating excellence. Vote for your favorite nominees across hundreds of award categories with complete transparency and security.
                    </p>
                    <div class="hero-buttons d-flex flex-wrap gap-3">
                        <a href="events.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-trophy me-2"></i>Explore Events
                        </a>
                        <a href="how-it-works.php" class="btn btn-outline-light btn-lg">
                            <i class="bi bi-play-circle me-2"></i>How It Works
                        </a>
                    </div>
                    
                    <!-- Shortcode Search -->
                    <div class="shortcode-search mt-4">
                        <form action="vote.php" method="GET" class="d-flex gap-2">
                            <div class="input-group" style="max-width: 350px;">
                                <span class="input-group-text bg-white border-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" name="shortcode" class="form-control border-0" placeholder="Enter shortcode to vote..." style="border-radius: 0 50px 50px 0;">
                            </div>
                            <button type="submit" class="btn btn-primary rounded-pill px-4">Vote Now</button>
                        </form>
                    </div>
                    
                    <!-- Hero Stats -->
                    <div class="hero-stats">
                        <div class="stat-item">
                            <span class="stat-number counter" data-target="<?php echo $stats['total_votes']; ?>"><?php echo number_format($stats['total_votes']); ?></span>
                            <span class="stat-label">Total Votes</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number counter" data-target="<?php echo $stats['total_events']; ?>"><?php echo $stats['total_events']; ?></span>
                            <span class="stat-label">Active Events</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number counter" data-target="<?php echo $stats['total_nominees']; ?>"><?php echo $stats['total_nominees']; ?></span>
                            <span class="stat-label">Nominees</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number counter" data-target="<?php echo $stats['total_users']; ?>"><?php echo $stats['total_users']; ?></span>
                            <span class="stat-label">Voters</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <div class="hero-image-wrapper text-center">
                    <div class="hero-image-container">
                        <img src="assets/images/hero-trophy.png" alt="Awards Trophy" class="hero-image pulse" onerror="this.src='https://cdn-icons-png.flaticon.com/512/548/548427.png'; this.style.maxHeight='400px';">
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scroll Indicator -->
    <div class="scroll-indicator text-center">
        <a href="#featured-events" class="text-white">
            <i class="bi bi-chevron-down" style="font-size: 2rem; animation: bounce 2s infinite;"></i>
        </a>
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
                    <div class="col-md-6 col-lg-4">
                        <div class="card event-card">
                            <div class="event-image-wrapper">
                                <img src="<?php echo $event['banner'] ? 'uploads/events/' . $event['banner'] : 'https://picsum.photos/600/400?random=' . $event['id']; ?>" 
                                     alt="<?php echo htmlspecialchars($event['title']); ?>">
                                
                                <div class="event-overlay">
                                    <div class="d-flex gap-2">
                                        <span class="glass-badge">
                                            <i class="bi bi-collection"></i><?php echo $event['category_count']; ?>
                                        </span>
                                        <span class="glass-badge">
                                            <i class="bi bi-people"></i><?php echo $event['nominee_count']; ?>
                                        </span>
                                    </div>
                                </div>

                                <?php if ($event['is_featured']): ?>
                                    <span class="status-badge bg-warning text-dark">
                                        <i class="bi bi-star-fill me-1"></i>Featured
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge bg-success text-white">Active</span>
                                <?php endif; ?>
                            </div>

                            <div class="event-card-body">
                                <h4 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="card-text">
                                    <?php echo strlen($event['description']) > 100 ? substr(htmlspecialchars($event['description']), 0, 100) . '...' : htmlspecialchars($event['description']); ?>
                                </p>
                                
                                <div class="event-stats-grid">
                                    <div class="stat-box">
                                        <span class="label">Ends On</span>
                                        <span class="value"><?php echo date('M d, Y', strtotime($event['end_date'])); ?></span>
                                    </div>
                                    <div class="stat-box">
                                        <span class="label">Total Votes</span>
                                        <span class="value"><?php echo number_format($event['total_votes']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="event-card-footer">
                                <a href="event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-primary btn-modern">
                                    Vote Now
                                    <i class="bi bi-arrow-right"></i>
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

<!-- How It Works Section -->
<section class="how-it-works py-5">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Simple & Secure</span>
            <h2>How It Works</h2>
            <p>Vote for your favorites in just a few simple steps</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="step-card card h-100 text-center p-4">
                    <div class="step-number">1</div>
                    <h4>Find an Event</h4>
                    <p class="text-muted mb-0">Browse active events and select your preferred category</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card card h-100 text-center p-4">
                    <div class="step-number">2</div>
                    <h4>Select Nominee</h4>
                    <p class="text-muted mb-0">Choose your favorite nominee in each category</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card card h-100 text-center p-4">
                    <div class="step-number">3</div>
                    <h4>Pay & Vote</h4>
                    <p class="text-muted mb-0">Complete payment to cast your vote(s) for multiple nominees</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="step-card card h-100 text-center p-4">
                    <div class="step-number">4</div>
                    <h4>Multiple Votes</h4>
                    <p class="text-muted mb-0">Vote for multiple nominees across categories in the same event</p>
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
                                    <?php echo htmlspecialchars($nominee['category_name']); ?> â€¢ <?php echo htmlspecialchars($nominee['event_title']); ?>
                                </small>
                            </div>
                            <span class="ms-auto vote-count fs-4 fw-bold text-white">
                                <?php echo number_format($nominee['vote_count']); ?>
                            </span>
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
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Features Section -->
<section class="features-section py-5 bg-white">
    <div class="container">
        <div class="section-header">
            <span class="subtitle">Why Choose Us</span>
            <h2>Built for Trust & Transparency</h2>
            <p>Our platform ensures fair and secure voting for all</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #6366f1, #818cf8); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-shield-check text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>Secure Voting</h4>
                    <p class="text-muted mb-0">Advanced encryption and verification ensures every vote is protected and counted accurately.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #ec4899, #f472b6); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-graph-up-arrow text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>Real-Time Results</h4>
                    <p class="text-muted mb-0">Watch votes count up in real-time with our live dashboard and analytics.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #10b981, #34d399); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-phone text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>Mobile Friendly</h4>
                    <p class="text-muted mb-0">Vote from anywhere using your smartphone, tablet, or computer with ease.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #f59e0b, #fbbf24); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-lightning-charge text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>Fast & Easy</h4>
                    <p class="text-muted mb-0">Simple shortcode voting makes casting your vote quick and effortless.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #60a5fa); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-people text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>Unlimited Voting</h4>
                    <p class="text-muted mb-0">Vote for your favorite nominees as many times as you wish to show your support.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 p-4 text-center">
                    <div class="feature-icon mx-auto mb-3" style="width: 80px; height: 80px; background: linear-gradient(135deg, #8b5cf6, #a78bfa); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-headset text-white" style="font-size: 2rem;"></i>
                    </div>
                    <h4>24/7 Support</h4>
                    <p class="text-muted mb-0">Our dedicated support team is always ready to help with any questions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2>Ready to Make Your Voice Heard?</h2>
                <p>Join thousands of voters and support your favorite nominees today. Every vote matters!</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="register.php" class="btn btn-light btn-lg">
                        <i class="bi bi-person-plus me-2"></i>Create Free Account
                    </a>
                    <a href="events.php" class="btn btn-outline-light btn-lg">
                        <i class="bi bi-trophy me-2"></i>Browse Events
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
/* Additional Hero Styles */
.hero-bg-elements {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
    pointer-events: none;
}

.hero-circle {
    position: absolute;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(236, 72, 153, 0.3));
    filter: blur(60px);
}

.hero-circle-1 {
    width: 350px;
    height: 350px;
    top: -200px;
    right: -100px;
    animation: float-slow 20s ease-in-out infinite;
}

.hero-circle-2 {
    width: 250px;
    height: 250px;
    bottom: -100px;
    left: -100px;
    animation: float-slow 15s ease-in-out infinite reverse;
}

.hero-circle-3 {
    width: 200px;
    height: 200px;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    animation: float-slow 25s ease-in-out infinite;
}

@keyframes float-slow {
    0%, 100% { transform: translate(0, 0); }
    25% { transform: translate(30px, -30px); }
    50% { transform: translate(-20px, 20px); }
    75% { transform: translate(20px, 30px); }
}

.hero-image-container {
    position: relative;
    display: inline-block;
}

.hero-image {
    max-height: 320px;
    filter: drop-shadow(0 20px 50px rgba(99, 102, 241, 0.4));
}

.scroll-indicator {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-20px); }
    60% { transform: translateY(-10px); }
}

.shortcode-search .input-group {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50px;
    backdrop-filter: blur(10px);
    padding: 4px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.shortcode-search .form-control {
    background: transparent !important;
    color: white !important;
    padding: 0.4rem 0.8rem;
    height: auto;
}

.shortcode-search .form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.shortcode-search .input-group-text {
    background: transparent !important;
    padding-left: 1.25rem;
}

.shortcode-search .btn {
    padding: 0.4rem 1.25rem;
    font-size: 0.9rem;
}

/* Event Card Hover Effects */
.event-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(236, 72, 153, 0.2));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.event-card:hover::before {
    opacity: 1;
}

/* Animated Elements */
.animate-on-scroll {
    opacity: 0;
    transform: translateY(30px);
    transition: all 0.6s ease;
}

.animate-on-scroll.animated {
    opacity: 1;
    transform: translateY(0);
}

/* Empty State */
.empty-state i {
    opacity: 0.3;
}
</style>

<?php include 'includes/vote-modal.php'; ?>
<?php include 'includes/footer.php'; ?>
