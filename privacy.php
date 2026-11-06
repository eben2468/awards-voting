<?php
/**
 * Privacy Policy Page
 * Awards Voting System
 */

require_once 'config/database.php';

$page_title = 'Privacy Policy';
$page_description = 'Learn how VoteHubGH collects, uses, and protects your personal information.';
include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Privacy Policy</li>
            </ol>
        </nav>
        <h1>Privacy Policy</h1>
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
                        <h2 class="h3 mb-4">1. Introduction</h2>
                        <p>At VoteHubGH, we respect your privacy and are committed to protecting your personal data. This Privacy Policy will inform you about how we look after your personal data when you visit our website and tell you about your privacy rights and how the law protects you.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">2. Data We Collect</h2>
                        <p>We may collect, use, store and transfer different kinds of personal data about you which we have grouped together as follows:</p>
                        <ul class="mb-4">
                            <li><strong>Identity Data:</strong> Includes full name, username or similar identifier.</li>
                            <li><strong>Contact Data:</strong> Includes email address and telephone numbers.</li>
                            <li><strong>Technical Data:</strong> Includes internet protocol (IP) address, approximate location based on that address, the type and model of your device where your browser provides it, your login data, browser type and version, time zone setting and location, browser plug-in types and versions, operating system and platform, and other technology on the devices you use to access this website.</li>
                            <li><strong>Usage Data:</strong> Includes information about how you use our website and services (e.g., voting history).</li>
                        </ul>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">3. How We Use Your Data</h2>
                        <p>We will only use your personal data when the law allows us to. Most commonly, we will use your personal data in the following circumstances:</p>
                        <ul class="mb-4">
                            <li>To register you as a new user.</li>
                            <li>To process and count your votes accurately.</li>
                            <li>To prevent fraudulent voting activities.</li>
                            <li>To manage our relationship with you (e.g., notifying you about changes to our terms or privacy policy).</li>
                            <li>To improve our website, services, and user experience.</li>
                        </ul>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">4. Data Security</h2>
                        <p>We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used or accessed in an unauthorized way, altered or disclosed. In addition, we limit access to your personal data to those employees, agents, contractors and other third parties who have a business need to know.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">5. Data Retention</h2>
                        <p>We will only retain your personal data for as long as necessary to fulfill the purposes we collected it for, including for the purposes of satisfying any legal, accounting, or reporting requirements.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">6. Your Legal Rights</h2>
                        <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to request access, correction, erasure, restriction, transfer, or to object to processing.</p>
                    </div>

                    <div class="mb-5">
                        <h2 class="h3 mb-4">7. Contact Us</h2>
                        <p>If you have any questions about this Privacy Policy or our privacy practices, please contact us at <a href="mailto:support@votehubgh.com">support@votehubgh.com</a> or through our <a href="contact.php">Contact Page</a>.</p>
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
