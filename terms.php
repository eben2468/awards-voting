<?php
/**
 * Terms of Service Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Terms of Service';
$page_description = 'Read the terms and conditions for using the VoteHubGH awards voting platform.';
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Terms of Service</li>
            </ol>
        </nav>
        <h1>Terms of Service</h1>
        <p>Last updated: <?php echo date('F d, Y'); ?></p>
    </div>
</section>

<!-- Content Section -->
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="content-card p-4 p-md-5 bg-white shadow-sm rounded-4">
                    <div class="mb-5">
                        <h2 class="h3 mb-4">1. Acceptance of Terms</h2>
                        <p>By accessing and using VoteHubGH ("the Platform"), you agree to be bound by these Terms of Service. If you do not agree to these terms, please do not use the Platform. These terms apply to all visitors, users, and others who access or use the Platform.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">2. User Accounts</h2>
                        <p>To participate in voting, you must create an account. You are responsible for:</p>
                        <ul class="mb-4">
                            <li>Maintaining the confidentiality of your account and password.</li>
                            <li>Restricting access to your computer or mobile device.</li>
                            <li>All activities that occur under your account.</li>
                            <li>Providing accurate and complete information during registration.</li>
                        </ul>
                        <p>We reserve the right to terminate accounts that provide false information or engage in fraudulent activities.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">3. Voting Rules</h2>
                        <p>To ensure fairness and integrity in our voting process, the following rules apply:</p>
                        <ul class="mb-4">
                            <li><strong>Unlimited Voting:</strong> Users enjoy unlimited voting privileges and can vote for multiple nominees as many times as they wish, unless specified otherwise for a particular event.</li>
                            <li><strong>No Manipulation:</strong> Any attempt to manipulate voting results through the use of bots, multiple accounts, or other technical means is strictly prohibited.</li>
                            <li><strong>Paid Voting:</strong> For events with paid voting, all transactions are final. No refunds will be issued once a vote has been successfully cast and processed.</li>
                            <li><strong>Finality:</strong> Once a vote is cast, it cannot be processed for a refund or changed.</li>
                        </ul>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">4. Intellectual Property</h2>
                        <p>The Platform and its original content, features, and functionality are and will remain the exclusive property of VoteHubGH and its licensors. Our trademarks and trade dress may not be used in connection with any product or service without our prior written consent.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">5. Limitation of Liability</h2>
                        <p>In no event shall VoteHubGH, nor its directors, employees, partners, agents, suppliers, or affiliates, be liable for any indirect, incidental, special, consequential or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from your access to or use of or inability to access or use the Platform.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">6. Changes to Terms</h2>
                        <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material, we will try to provide at least 30 days' notice prior to any new terms taking effect. What constitutes a material change will be determined at our sole discretion.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">7. Contact Us</h2>
                        <p>If you have any questions about these Terms, please contact us at <a href="mailto:support@votehubgh.com">support@votehubgh.com</a> or through our <a href="contact.php">Contact Page</a>.</p>
                    </div>
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

.content-card {
    border: 1px solid var(--gray-200);
}

.content-card h2 {
    color: var(--dark);
    font-weight: 700;
    position: relative;
    padding-bottom: 10px;
}

.content-card h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 50px;
    height: 3px;
    background: var(--gradient-primary);
    border-radius: var(--radius-full);
}

.content-card p, .content-card li {
    color: var(--gray-600);
    line-height: 1.8;
}

.content-card ul {
    padding-left: 1.25rem;
}

.content-card li {
    margin-bottom: 0.5rem;
}
</style>

<?php include 'includes/footer.php'; ?>
