<?php
/**
 * FAQ Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Frequently Asked Questions';
$page_description = 'Find answers to common questions about VoteHubGH, voting, and account management.';
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">FAQ</li>
            </ol>
        </nav>
        <h1>Frequently Asked Questions</h1>
        <p>Everything you need to know about VoteHubGH</p>
    </div>
</section>

<!-- FAQ Section -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="accordion" id="faqAccordion">
                    <!-- General Questions -->
                    <div class="mb-4">
                        <h3 class="h4 mb-3 text-primary"><i class="bi bi-info-circle me-2"></i>General Questions</h3>
                        
                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What is VoteHubGH?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    VoteHubGH is a secure and transparent online awards voting platform that allows users to support their favorite nominees in various award events.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Is voting free?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Most events offer free voting. However, some special events may have paid voting options. The cost per vote will be clearly displayed on the event page.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Voting Questions -->
                    <div class="mb-4">
                        <h3 class="h4 mb-3 text-primary"><i class="bi bi-check2-square me-2"></i>Voting</h3>
                        
                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    How many times can I vote?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    You have unlimited voting privileges! You can vote for as many nominees as you like, and as many times as you wish, unless a specific event has its own set of rules.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Can I change my vote?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Once a vote is cast, it cannot be changed or retracted. This ensures the integrity of the voting process.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    How do I know my vote was counted?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    After voting, you will receive a success notification. You can also view your complete voting history in your user dashboard.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Questions -->
                    <div class="mb-4">
                        <h3 class="h4 mb-3 text-primary"><i class="bi bi-person me-2"></i>Account & Security</h3>
                        
                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    Do I need an account to vote?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Yes, creating a free account is required to vote. This helps us maintain platform security and allows you to track your voting history.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                                    Is my personal information safe?
                                </button>
                            </h2>
                            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    Absolutely. We use industry-standard encryption and security measures to protect your data. Please refer to our <a href="privacy.php">Privacy Policy</a> for more details.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Questions -->
                    <div class="mb-4">
                        <h3 class="h4 mb-3 text-primary"><i class="bi bi-credit-card me-2"></i>Payments</h3>
                        
                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq8">
                                    What payment methods are accepted?
                                </button>
                            </h2>
                            <div id="faq8" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    For paid voting, we accept Mobile Money (MTN, Vodafone, AirtelTigo) and Debit/Credit Cards through our secure payment partner, Paystack.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item border-0 mb-3 rounded-3 shadow-sm">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed rounded-3" type="button" data-bs-toggle="collapse" data-bs-target="#faq9">
                                    Are payments refundable?
                                </button>
                            </h2>
                            <div id="faq9" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body text-muted">
                                    No, all payments for votes are final and non-refundable once the transaction is completed and the vote is recorded.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-5">
                    <p class="text-muted">Still have questions?</p>
                    <a href="contact.php" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-envelope me-2"></i>Contact Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.page-header {
    background: var(--gradient-dark);
    padding: 6rem 0 4rem;
    color: var(--white);
    text-align: center;
}

.page-header h1 {
    color: var(--white);
    font-size: clamp(2.5rem, 5vw, 3.5rem);
    margin-bottom: 1rem;
}

.page-header .breadcrumb {
    justify-content: center;
    margin-bottom: 1.5rem;
}

.page-header .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.7);
}

.page-header .breadcrumb-item.active {
    color: var(--white);
}

.accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%);
    color: var(--primary);
}

.accordion-button:focus {
    box-shadow: none;
    border-color: var(--gray-200);
}

.accordion-item {
    overflow: hidden;
}
</style>

<?php include 'includes/footer.php'; ?>
