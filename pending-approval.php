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
</head>
<body class="auth-body">
    <div class="auth-shell">
        <main class="auth-split">
            <?php
            $authHeadline = $isRejected ? 'We\'re here to help.' : 'You\'re almost ready to start.';
            $authIntro = $isRejected
                ? 'Talk to our team and we\'ll take another look at your account.'
                : 'Once you\'re approved, you can create your first event.';
            $authImage = authPanelImage('approval');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>

                <?php if ($isRejected): ?>
                    <h1>Account not approved</h1>
                    <p class="auth-sub">Your organizer account was not approved. Talk to an administrator and we'll be happy to take another look.</p>
                <?php else: ?>
                    <h1>Awaiting approval</h1>
                    <p class="auth-sub">
                        Thanks for registering, <strong><?php echo htmlspecialchars(explode(' ', trim($account['fullname']))[0]); ?></strong>.
                        An administrator needs to approve your organizer account before you can sign in.
                    </p>
                <?php endif; ?>

                <?php if ($notice): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-warning">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($isRejected && !empty($account['note'])): ?>
                    <div class="alert alert-danger">
                        <strong>Note from the admin team</strong><br>
                        <?php echo nl2br(htmlspecialchars($account['note'])); ?>
                    </div>
                <?php endif; ?>

                <div class="auth-details">
                    <div><span>Registered email</span><strong><?php echo htmlspecialchars($account['email']); ?></strong></div>
                    <div><span>Account type</span><strong>Event organizer</strong></div>
                </div>

                <?php if (!$isRejected): ?>
                    <ol class="auth-timeline">
                        <li class="done">
                            <i class="bi bi-check-lg"></i>
                            <div><strong>Account created</strong><span>Your details have been saved.</span></div>
                        </li>
                        <li class="current">
                            <i class="bi bi-hourglass"></i>
                            <div><strong>Admin review, in progress</strong><span>Our team has been notified by email.</span></div>
                        </li>
                        <li>
                            <i class="bi bi-envelope"></i>
                            <div><strong>Approval email</strong><span>We'll email you the moment you're approved.</span></div>
                        </li>
                        <li>
                            <i class="bi bi-box-arrow-in-right"></i>
                            <div><strong>Log in and create your first event</strong><span>Use the email and password you registered with.</span></div>
                        </li>
                    </ol>
                <?php endif; ?>

                <?php echo renderSupportContactPanel($contactContext); ?>

                <?php if (!$isRejected): ?>
                    <form method="POST" action="" class="auth-switch">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="remind">
                        <button type="submit" class="auth-link">Send the admins a reminder by email</button>
                    </form>
                <?php endif; ?>

                <p class="auth-switch">Been approved? <a href="<?php echo SITE_URL; ?>/login.php">Try logging in</a></p>
                <a href="<?php echo SITE_URL; ?>" class="auth-home"><i class="bi bi-arrow-left"></i> Back to home</a>
            </section>
        </main>
    </div>
</body>
</html>
