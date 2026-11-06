<?php
/**
 * Organizer Account - Pending / Rejected Approval Notice
 * Awards Voting System
 *
 * Shown after an organizer registers, and whenever an unapproved organizer
 * tries to log in. Gives them a direct line to an administrator.
 */

require_once 'config/database.php';
require_once 'includes/auth-functions.php';

// Signed-in users have nothing to wait for
if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : (isOrganizer() ? 'organizer/' : 'user/dashboard.php'));
}

// The details are placed in the session by register.php / login.php
$account = $_SESSION['pending_approval_user'] ?? null;
if (!$account) {
    setFlash('info', 'Please log in to check your account status.');
    redirect('login.php');
}

$isRejected = !empty($account['rejected']);
$page_title = $isRejected ? 'Account Not Approved' : 'Awaiting Approval';
$notice = '';
$errors = [];

// Let them nudge the admins once more (rate limited to one per 10 minutes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isRejected) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif (($_POST['action'] ?? '') === 'remind') {
        $lastReminder = $_SESSION['last_approval_reminder'] ?? 0;

        if (time() - $lastReminder < 600) {
            $errors[] = 'A reminder was just sent. Please wait a few minutes before sending another, '
                . 'or contact us directly on WhatsApp for a faster response.';
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND approval_status = 'pending'");
                $stmt->execute([$account['id']]);
                $user = $stmt->fetch();

                if (!$user) {
                    // Approved while they were on this page
                    unset($_SESSION['pending_approval_user']);
                    setFlash('success', 'Good news - your account has been reviewed. Please log in.');
                    redirect('login.php');
                }

                if (notifyAdminsOfPendingOrganizer($user)) {
                    $_SESSION['last_approval_reminder'] = time();
                    $notice = 'We have reminded our administrators about your account. '
                        . 'You will get an email as soon as it is approved.';
                } else {
                    $errors[] = 'We could not send the reminder email right now. '
                        . 'Please use the WhatsApp or phone options below.';
                }
            } catch (PDOException $e) {
                error_log('Approval reminder error: ' . $e->getMessage());
                $errors[] = 'Something went wrong. Please contact us using the options below.';
            }
        }
    }
}

$contactContext = $isRejected
    ? 'Hello VoteHubGH, my organizer account (' . ($account['email'] ?? '') . ') was not approved. Please can you review it again?'
    : 'Hello VoteHubGH, I registered as an organizer with ' . ($account['email'] ?? '') . '. Please can you approve my account?';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title . ' - ' . SITE_NAME; ?></title>

    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/apple-touch-icon.png?v=2">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/theme.css'); ?>">

    <style>
        .status-icon {
            width: 86px; height: 86px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            font-size: 2.4rem;
            margin: 0 auto 1.25rem;
        }
        .status-icon.pending { background: rgba(253, 126, 20, .12); color: #fd7e14; }
        .status-icon.rejected { background: rgba(220, 53, 69, .12); color: #dc3545; }
        .timeline-step { display: flex; gap: .9rem; padding: .55rem 0; }
        .timeline-step .dot {
            flex: 0 0 26px; height: 26px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
        }
        .timeline-step .dot.done { background: #d1e7dd; color: #0f5132; }
        .timeline-step .dot.current { background: #fff3cd; color: #664d03; }
        .timeline-step .dot.todo { background: #e9ecef; color: #adb5bd; }
        .auth-card-wide { max-width: 620px; margin: 0 auto; }
    </style>
</head>
<body class="auth-body">
    <div class="auth-page">
        <div class="container">
            <div class="auth-card auth-card-wide">

                <div class="text-center mb-4">
                    <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                        <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" class="brand-logo brand-logo-sm">
                    </a>

                    <?php if ($isRejected): ?>
                        <div class="status-icon rejected"><i class="bi bi-x-circle-fill"></i></div>
                        <h3 class="fw-bold mb-2">Account Not Approved</h3>
                        <p class="text-muted mb-0">
                            Unfortunately your organizer account was not approved. Talk to an administrator below
                            and we will be happy to take another look.
                        </p>
                    <?php else: ?>
                        <div class="status-icon pending"><i class="bi bi-hourglass-split"></i></div>
                        <h3 class="fw-bold mb-2">Your Account Is Awaiting Approval</h3>
                        <p class="text-muted mb-0">
                            Thanks for registering, <strong><?php echo htmlspecialchars(explode(' ', trim($account['fullname']))[0]); ?></strong>!
                            An administrator needs to approve your organizer account before you can sign in.
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($notice): ?>
                    <div class="alert alert-success d-flex align-items-start gap-2">
                        <i class="bi bi-check-circle-fill mt-1"></i>
                        <div><?php echo htmlspecialchars($notice); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-warning">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($isRejected && !empty($account['note'])): ?>
                    <div class="alert alert-danger">
                        <h6 class="fw-bold mb-1"><i class="bi bi-chat-left-text me-1"></i>Note from the admin team</h6>
                        <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($account['note'])); ?></p>
                    </div>
                <?php endif; ?>

                <div class="card border-0 bg-light rounded-4 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Registered email</span>
                            <strong class="small"><?php echo htmlspecialchars($account['email']); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Account type</span>
                            <span class="badge bg-primary">Event Organizer</span>
                        </div>
                    </div>
                </div>

                <?php if (!$isRejected): ?>
                    <div class="mb-4">
                        <h6 class="fw-bold mb-2">What happens next</h6>
                        <div class="timeline-step">
                            <span class="dot done"><i class="bi bi-check-lg"></i></span>
                            <div>
                                <div class="fw-semibold small">Account created</div>
                                <div class="text-muted small">Your details have been saved successfully.</div>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <span class="dot current"><i class="bi bi-hourglass"></i></span>
                            <div>
                                <div class="fw-semibold small">Admin review <span class="badge bg-warning text-dark ms-1">In progress</span></div>
                                <div class="text-muted small">Our team has been notified by email and is reviewing your account.</div>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <span class="dot todo"><i class="bi bi-envelope"></i></span>
                            <div>
                                <div class="fw-semibold small">Approval email</div>
                                <div class="text-muted small">You will get an email at
                                    <?php echo htmlspecialchars($account['email']); ?> the moment you are approved.</div>
                            </div>
                        </div>
                        <div class="timeline-step">
                            <span class="dot todo"><i class="bi bi-box-arrow-in-right"></i></span>
                            <div>
                                <div class="fw-semibold small">Sign in &amp; create your first event</div>
                                <div class="text-muted small">Log in with the same email and password you just registered.</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php echo renderSupportContactPanel($contactContext); ?>

                <?php if (!$isRejected): ?>
                    <form method="POST" action="" class="d-grid mt-3">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="remind">
                        <button type="submit" class="btn btn-link text-decoration-none">
                            <i class="bi bi-bell me-1"></i>Send the admins another reminder by email
                        </button>
                    </form>
                <?php endif; ?>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo SITE_URL; ?>" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Home
                    </a>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="text-primary fw-semibold text-decoration-none">
                        Try logging in again <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
