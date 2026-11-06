<?php
/**
 * Contact Page
 * Awards Voting System
 */

$page_title = 'Contact Us';
$page_description = 'Get in touch with VoteHubGH. We\'re here to help with any questions about our voting platform.';

require_once 'config/database.php';

$success = false;
$error = '';

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $subject = sanitize($_POST['subject'] ?? '');
        $message = sanitize($_POST['message'] ?? '');
        
        // Validation
        if (empty($name) || empty($email) || empty($subject) || empty($message)) {
            $error = 'Please fill in all fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Store message in database and send email
            $pdo = getDBConnection();
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Store in contact_messages table
                $stmt = $pdo->prepare(
                    "INSERT INTO contact_messages (name, email, subject, message, ip_address, user_agent) 
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                
                $stmt->execute([
                    $name,
                    $email,
                    $subject,
                    $message,
                    $ip,
                    $ua
                ]);
                
                // Send email to support team
                $emailSent = sendContactMessage($name, $email, $subject, $message);
                
                // Mark as success since message is stored in database
                $success = true;
                
                if ($emailSent) {
                    // Log successful contact form submission
                    logAudit(null, 'contact_form_submitted', 'contact_messages', $pdo->lastInsertId());
                } else {
                    // Log the failure for internal tracking
                    error_log("Contact form email delivery failed for message ID: " . $pdo->lastInsertId());
                    logAudit(null, 'contact_form_stored_email_failed', 'contact_messages', $pdo->lastInsertId());
                    
                    // BACKUP: Send SMS notification to admin via Nalo Solutions since email failed
                    // This ensures the admin knows there's a new message to check in the database
                    require_once 'config/nalo.php';
                    $adminPhone = '+233548811774'; // Admin phone from config
                    $smsMessage = "VoteHubGH: New Contact Message from $name. Subject: $subject. Please check the admin panel.";
                    sendNaloSMS(formatPhoneNumberForNalo($adminPhone), $smsMessage);
                }
                
                // Commit transaction
                $pdo->commit();
                
            } catch (Exception $e) {
                // Rollback transaction
                $pdo->rollBack();
                $error = 'Sorry, there was an error processing your message. Please try again.';
                error_log("Contact form error: " . $e->getMessage());
            }
        }
    }
}

include 'includes/header.php';
?>

<!-- Page Header -->
<section class="page-header py-5" style="background: var(--gradient-dark); margin-top: 76px;">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">Contact</li>
            </ol>
        </nav>
        <h1 class="text-white mb-3">Contact Us</h1>
        <p class="text-white-50 lead mb-0">Have questions or need help? We're here for you.</p>
    </div>
</section>

<!-- Contact Section -->
<section class="py-5">
    <div class="container">
        <div class="row g-5">
            <!-- Contact Form -->
            <div class="col-lg-7">
                <div class="contact-form-card">
                    <h3 class="mb-4">Send us a Message</h3>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            Thank you for your message! We'll get back to you as soon as possible.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!$success): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Your Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="Ebenezer Owusu" required
                                       value="<?php echo isset($_POST['name']) ? e($_POST['name']) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" placeholder="ebenezer@example.com" required
                                       value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject <span class="text-danger">*</span></label>
                                <select name="subject" class="form-select" required>
                                    <option value="">Select a subject</option>
                                    <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="Voting Help" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Voting Help') ? 'selected' : ''; ?>>Voting Help</option>
                                    <option value="Account Issue" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Account Issue') ? 'selected' : ''; ?>>Account Issue</option>
                                    <option value="Event Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Event Inquiry') ? 'selected' : ''; ?>>Event Inquiry</option>
                                    <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Partnership') ? 'selected' : ''; ?>>Partnership / Collaboration</option>
                                    <option value="Bug Report" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Bug Report') ? 'selected' : ''; ?>>Report a Bug</option>
                                    <option value="Other" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Your Message <span class="text-danger">*</span></label>
                                <textarea name="message" class="form-control" rows="6" placeholder="Tell us how we can help..." required><?php echo isset($_POST['message']) ? e($_POST['message']) : ''; ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div class="col-lg-5">
                <div class="contact-info-card mb-4">
                    <h3 class="mb-4">Get in Touch</h3>
                    <p class="text-white mb-4">We'd love to hear from you. Here are the ways you can reach us.</p>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Email Us</h6>
                            <a href="mailto:support@votehubgh.com" class="text-muted">support@votehubgh.com</a>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon" style="background: rgba(37, 211, 102, 0.1); color: #25D366;">
                            <i class="bi bi-whatsapp"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">WhatsApp Us</h6>
                            <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" class="text-muted d-block"><?php echo WHATSAPP_NUMBER; ?></a>
                            <a href="https://wa.me/233245849246" target="_blank" class="text-muted">+233245849246</a>
                        </div>
                    </div>
                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Call Us</h6>
                            <a href="tel:<?php echo str_replace(' ', '', WHATSAPP_NUMBER); ?>" class="text-muted d-block"><?php echo WHATSAPP_NUMBER; ?></a>
                            <a href="tel:+233245849246" class="text-muted">+233245849246</a>
                        </div>
                    </div>

                    
                    <div class="contact-info-item">
                        <div class="contact-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <h6 class="mb-1">Business Hours</h6>
                            <span class="text-muted">Mon - Fri: 9:00 AM - 6:00 PM</span>
                        </div>
                    </div>
                </div>
                
                <!-- Social Links -->
                <div class="social-card">
                    <h5 class="mb-3">Follow Us</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" class="social-link" title="WhatsApp" style="color: #25D366;">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <a href="#" class="social-link" title="Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="bi bi-twitter-x"></i>
                        </a>
                        <a href="#" class="social-link" title="Instagram">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="social-link" title="LinkedIn">
                            <i class="bi bi-linkedin"></i>
                        </a>
                        <a href="#" class="social-link" title="YouTube">
                            <i class="bi bi-youtube"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="py-0">
    <div class="container-fluid px-0">
        <div class="map-container">
            <iframe 
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d254124.76914218498!2d-0.2661162674846073!3d5.592470699259968!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xfdf9084b2b7a773%3A0xbed14ed8650e2dd3!2sAccra%2C%20Ghana!5e0!3m2!1sen!2sus!4v1640000000000!5m2!1sen!2sus" 
                width="100%" 
                height="400" 
                style="border:0;" 
                allowfullscreen="" 
                loading="lazy" 
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>

<!-- FAQ Quick Links -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <h2>Quick Help</h2>
            <p class="text-muted">Find answers to common questions</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <a href="<?php echo SITE_URL; ?>/how-it-works.php" class="quick-help-card">
                    <div class="quick-help-icon">
                        <i class="bi bi-question-circle"></i>
                    </div>
                    <h5>How It Works</h5>
                    <p class="text-muted mb-0">Learn how to vote and use our platform</p>
                </a>
            </div>
            
            <div class="col-md-4">
                <a href="<?php echo SITE_URL; ?>/events.php" class="quick-help-card">
                    <div class="quick-help-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <h5>Browse Events</h5>
                    <p class="text-muted mb-0">Explore active voting events</p>
                </a>
            </div>
            
            <div class="col-md-4">
                <a href="<?php echo SITE_URL; ?>/results.php" class="quick-help-card">
                    <div class="quick-help-icon">
                        <i class="bi bi-bar-chart"></i>
                    </div>
                    <h5>View Results</h5>
                    <p class="text-muted mb-0">Check live voting results</p>
                </a>
            </div>
        </div>
    </div>
</section>

<style>
.contact-form-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: 2.5rem;
    box-shadow: var(--shadow-lg);
}

.contact-info-card {
    background: var(--gradient-dark);
    border-radius: var(--radius-xl);
    padding: 2.5rem;
    color: var(--white);
}

.contact-info-card h3 {
    color: var(--white);
}

.contact-info-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.contact-info-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.contact-info-item h6 {
    color: var(--white);
}

.contact-info-item .text-muted {
    color: var(--gray-300) !important;
}

.contact-info-item a.text-muted:hover {
    color: var(--white) !important;
}


.contact-icon {
    width: 50px;
    height: 50px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--primary-light);
    flex-shrink: 0;
}

.contact-icon i {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.social-card {
    background: var(--white);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow);
}

.social-link {
    width: 50px;
    height: 50px;
    background: var(--gray-100);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    color: var(--gray-600);
    transition: all var(--transition);
    text-decoration: none;
}

.social-link i {
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
    margin: 0;
}

.social-link:hover {
    background: var(--gradient-primary);
    color: var(--white);
    transform: translateY(-3px);
}

.map-container {
    width: 100%;
    overflow: hidden;
}

.map-container iframe {
    filter: grayscale(20%);
    transition: filter var(--transition);
}

.map-container:hover iframe {
    filter: grayscale(0%);
}

.quick-help-card {
    display: block;
    background: var(--gray-50);
    border-radius: var(--radius-lg);
    padding: 2rem;
    text-align: center;
    text-decoration: none;
    transition: all var(--transition);
}

.quick-help-card:hover {
    background: var(--white);
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
}

.quick-help-card h5 {
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.quick-help-icon {
    width: 70px;
    height: 70px;
    background: var(--gradient-primary);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    color: var(--white);
    margin: 0 auto 1.5rem;
}

@media (max-width: 991.98px) {
    .contact-form-card,
    .contact-info-card {
        padding: 2rem;
    }
}

@media (max-width: 767.98px) {
    .contact-form-card,
    .contact-info-card {
        padding: 1.5rem;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
