<?php
/**
 * How It Works Page
 * Awards Voting System
 */

$page_title = 'How It Works';
$page_description = 'Learn how to vote for your favorite nominees on VoteHubGH. Simple, secure, and transparent voting.';
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header py-5" style="background: var(--gradient-dark); margin-top: 76px;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">How It Works</li>
            </ol>
        </nav>
        <h1 class="text-white mb-2">How It Works</h1>
        <p class="text-white-50 lead mb-0">Voting has never been easier. Follow these simple steps to support your favorite nominees.</p>
    </div>
</section>

<!-- Steps Section -->
<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <!-- Step 1 -->
            <div class="col-lg-3 col-md-6">
                <div class="step-card-full text-center p-4 h-100">
                    <div class="step-icon mb-4">
                        <div class="step-number-lg">1</div>
                    </div>
                    <h4>Find an Event</h4>
                    <p class="text-muted mb-0">Browse through our active award events and select the one you're interested in.</p>
                </div>
            </div>
            
            <!-- Step 2 -->
            <div class="col-lg-3 col-md-6">
                <div class="step-card-full text-center p-4 h-100">
                    <div class="step-icon mb-4">
                        <div class="step-number-lg">2</div>
                    </div>
                    <h4>Browse Events</h4>
                    <p class="text-muted mb-0">Explore active award events and discover nominees across various categories.</p>
                </div>
            </div>
            
            <!-- Step 3 -->
            <div class="col-lg-3 col-md-6">
                <div class="step-card-full text-center p-4 h-100">
                    <div class="step-icon mb-4">
                        <div class="step-number-lg">3</div>
                    </div>
                    <h4>Cast Your Vote</h4>
                    <p class="text-muted mb-0">Click the vote button or use the shortcode to vote for your favorite nominee in each category.</p>
                </div>
            </div>
            
            <!-- Step 4 -->
            <div class="col-lg-3 col-md-6">
                <div class="step-card-full text-center p-4 h-100">
                    <div class="step-icon mb-4">
                        <div class="step-number-lg">4</div>
                    </div>
                    <h4>Watch Results</h4>
                    <p class="text-muted mb-0">Track live results and see how your favorites are doing in real-time.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Voting Methods Section -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Multiple Ways to Vote</h2>
            <p class="text-muted">Choose the method that works best for you</p>
        </div>
        
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <div class="voting-method-card p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="method-icon bg-primary-soft">
                            <i class="bi bi-mouse text-primary fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">One-Click Voting</h5>
                            <p class="text-muted mb-0">Simply browse to any event, select your category, and click the "Vote" button on your preferred nominee. It's that simple!</p>
                        </div>
                    </div>
                </div>
                
                <div class="voting-method-card p-4 mb-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="method-icon bg-success-soft">
                            <i class="bi bi-hash text-success fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Shortcode Voting</h5>
                            <p class="text-muted mb-0">Each nominee has a unique shortcode. Enter it directly in the search box on the homepage to vote instantly.</p>
                        </div>
                    </div>
                </div>
                
                <div class="voting-method-card p-4">
                    <div class="d-flex align-items-start gap-3">
                        <div class="method-icon bg-warning-soft">
                            <i class="bi bi-phone text-warning fs-4"></i>
                        </div>
                        <div>
                            <h5 class="mb-2">Mobile Friendly</h5>
                            <p class="text-muted mb-0">Our platform is fully responsive. Vote from anywhere using your smartphone or tablet.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="shortcode-demo p-4 rounded-4 bg-gradient-dark text-white">
                    <h5 class="mb-3"><i class="bi bi-lightning-charge me-2"></i>Quick Vote Demo</h5>
                    <p class="text-gray-300 mb-4">Enter a nominee's shortcode below to see how quick voting works:</p>
                    
                    <form action="vote" method="GET" class="shortcode-search d-flex align-items-center gap-2 mb-3">
                        <label class="text-white mb-0 flex-shrink-0 d-none d-sm-block">Shortcode:</label>
                        <input type="text" name="shortcode" placeholder="e.g., NOM001" class="form-control border-0 bg-white">
                        <button type="submit" class="btn btn-primary rounded-pill px-4 flex-shrink-0">Vote Now</button>
                    </form>
                    
                    <small class="text-gray-400">
                        <i class="bi bi-info-circle me-1"></i>
                        Shortcodes are displayed on each nominee's card
                    </small>
                </div>
</section>

<!-- Event Ticketing Flow Section -->
<section class="py-5 bg-light border-top border-bottom">
    <div class="container">
        <div class="text-center mb-5">
            <span class="text-primary fw-bold text-uppercase small" style="letter-spacing: 1px;"><i class="bi bi-ticket-perforated-fill me-1"></i> Hassle-Free Bookings</span>
            <h2 class="fw-bold mt-2">How Event Ticketing Works</h2>
            <p class="text-muted">Simple pathways to purchase and validate tickets for your favorite events</p>
        </div>
        
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="timeline-ticketing">
                    <!-- Step 1 -->
                    <div class="d-flex mb-4">
                        <div class="ticketing-step-num me-3">
                            <span>1</span>
                        </div>
                        <div>
                            <h5 class="fw-bold">Select Event & Ticket Tier</h5>
                            <p class="text-muted mb-0">Browse ticketed events on our homepage or the dedicated Ticketing page. Click "Buy Ticket" and choose your preferred tier (Regular, VIP, VVIP, etc.) and number of tickets.</p>
                        </div>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="d-flex mb-4">
                        <div class="ticketing-step-num me-3">
                            <span>2</span>
                        </div>
                        <div>
                            <h5 class="fw-bold">Provide Phone & Pay</h5>
                            <p class="text-muted mb-0">Select your payment method (Mobile Money or Debit/Credit Card). If using Mobile Money, enter your number to receive an instant STK Push prompt on your phone to authorize payment.</p>
                        </div>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="d-flex">
                        <div class="ticketing-step-num me-3">
                            <span>3</span>
                        </div>
                        <div>
                            <h5 class="fw-bold">Receive Instant SMS Ticket</h5>
                            <p class="text-muted mb-0">Once the payment is successful, you will instantly receive a confirmation SMS containing your unique Ticket Code (e.g., TKT-XXXXXX) and details about the event.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <!-- USSD Flow Card -->
                <div class="ussd-flow-card p-4 rounded-4 text-white h-100" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <h5 class="fw-bold mb-3"><i class="bi bi-phone-vibrate me-2 text-warning"></i>USSD Ticket Purchases</h5>
                    <p class="text-gray-300 small mb-4">No internet? You can buy tickets offline using our USSD shortcode service by following these simple steps:</p>
                    
                    <ul class="list-unstyled ussd-steps-list small text-gray-300 mb-0">
                        <li class="mb-3 d-flex align-items-start">
                            <span class="badge bg-warning text-dark me-2 rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">1</span>
                            <span>Dial our official USSD code on your mobile phone.</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <span class="badge bg-warning text-dark me-2 rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">2</span>
                            <span>Choose **Option 2 (Buy ticket)** from the main menu.</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <span class="badge bg-warning text-dark me-2 rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">3</span>
                            <span>Enter the unique event shortcode (e.g., **CONCERT26**) found on the event card.</span>
                        </li>
                        <li class="mb-3 d-flex align-items-start">
                            <span class="badge bg-warning text-dark me-2 rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">4</span>
                            <span>Select your category tier, input quantity, confirm the details, and approve the payment push.</span>
                        </li>
                        <li class="d-flex align-items-start">
                            <span class="badge bg-warning text-dark me-2 rounded-circle" style="width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700;">5</span>
                            <span>A confirmation SMS with your unique ticket code will be sent to your phone.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- Features Section -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Why Choose VoteHubGH?</h2>
            <p class="text-muted">Trusted by thousands for transparent and secure voting</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <h5>Secure Voting</h5>
                    <p class="text-muted mb-0">Advanced security measures ensure your vote is protected and counted accurately.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <h5>Real-Time Results</h5>
                    <p class="text-muted mb-0">Watch vote counts update instantly. See exactly where your favorites stand.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <h5>Unlimited Voting</h5>
                    <p class="text-muted mb-0">Enjoy the freedom to vote for multiple nominees as many times as you want.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-phone"></i>
                    </div>
                    <h5>Mobile Optimized</h5>
                    <p class="text-muted mb-0">Vote from any device - desktop, tablet, or smartphone.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <h5>Vote History</h5>
                    <p class="text-muted mb-0">Track all your votes in one place. Never forget who you supported.</p>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6">
                <div class="feature-card h-100">
                    <div class="feature-icon mb-3">
                        <i class="bi bi-bell"></i>
                    </div>
                    <h5>Notifications</h5>
                    <p class="text-muted mb-0">Stay updated with event alerts and winner announcements.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Frequently Asked Questions</h2>
            <p class="text-muted">Got questions? We've got answers</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                Is voting free?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                No, voting on our platform is strictly paid. This ensures that every vote is verified and supports the nominees and event organizers.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                How many times can I vote?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                You have unlimited voting privileges! You can vote for as many nominees as you like, and as many times as you wish, unless a specific event has its own set of rules.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                Can I change my vote?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Once a vote is cast, it cannot be changed. This is to maintain the integrity of the voting process. Please make sure you're voting for the right nominee before confirming.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Do I need an account to vote?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                No, you do not need an account to vote. Simply find your favorite nominee and cast your vote instantly. Accounts are only required for event organizers.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                When are results announced?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body text-muted">
                                Live results are visible throughout the voting period. Final winners are announced when the event ends. You can view real-time standings on the Results page.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <h2>Ready to Make Your Vote Count?</h2>
        <p>Join thousands of voters supporting their favorite nominees</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="<?php echo SITE_URL; ?>/events.php" class="btn btn-light btn-lg">
                <i class="bi bi-calendar-event me-2"></i>Browse Events
            </a>
            <?php if (!isLoggedIn()): ?>
            <a href="<?php echo SITE_URL; ?>/register.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-person-plus me-2"></i>Organizer Registration
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<style>
.step-card-full {
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow);
    transition: all var(--transition);
}

.step-card-full:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.step-number-lg {
    width: 80px;
    height: 80px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: 800;
    color: var(--white);
    margin: 0 auto;
}

.voting-method-card {
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    transition: all var(--transition);
}

.voting-method-card:hover {
    background: var(--white);
    box-shadow: var(--shadow-md);
}

.method-icon {
    width: 50px;
    height: 50px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.bg-primary-soft {
    background: rgba(var(--primary-rgb), 0.1);
}

.bg-success-soft {
    background: rgba(16, 185, 129, 0.1);
}

.bg-warning-soft {
    background: rgba(245, 158, 11, 0.1);
}

.text-gray-300 {
    color: var(--gray-300);
}

.text-gray-400 {
    color: var(--gray-400);
}

.feature-card {
    background: var(--white);
    border-radius: var(--radius-lg);
    padding: 2rem;
    box-shadow: var(--shadow);
    transition: all var(--transition);
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-xl);
}

.feature-icon {
    width: 60px;
    height: 60px;
    background: var(--gradient-primary);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--white);
}

.accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.1) 0%, rgba(var(--secondary-rgb), 0.1) 100%);
    color: var(--primary);
}

.accordion-button:focus {
    box-shadow: none;
    border-color: var(--gray-200);
}

@media (max-width: 767.98px) {
    .shortcode-demo {
        margin-top: 2rem;
    }
}

.ticketing-step-num {
    width: 40px;
    height: 40px;
    background: var(--gradient-primary);
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    flex-shrink: 0;
    margin-top: 2px;
}
</style>

<?php include 'includes/vote-modal.php'; ?>
<?php include 'includes/footer.php'; ?>
