<?php
/**
 * Forgot Password
 * Awards Voting System
 *
 * Emails a password reset link (and a 6-digit backup code) to the address on
 * the account. The response is deliberately identical whether or not the email
 * exists, so nobody can use this page to discover registered addresses.
 */

require_once 'config/database.php';
require_once 'includes/auth-functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : (isOrganizer() ? 'organizer/' : 'user/dashboard.php'));
}

$page_title = 'Forgot Password';
$errors = [];
$sent = false;
$sentTo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            // Simple per-session flood guard
            $attempts = $_SESSION['reset_requests'] ?? 0;
            if ($attempts >= 8) {
                $errors[] = 'Too many reset requests. Please try again later or contact support on ' . WHATSAPP_NUMBER . '.';
            } else {
                $_SESSION['reset_requests'] = $attempts + 1;

                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch();

                    if ($user && $user['status'] !== 'suspended') {
                        $result = issuePasswordReset($pdo, $user);

                        if (!$result['sent']) {
                            error_log('Password reset email failed for ' . $email . ': ' . $result['error']);
                        }

                        logAudit($user['id'], 'password_reset_requested', 'users', $user['id']);
                    } else {
                        // Unknown address - pause briefly so timing does not leak the answer
                        usleep(400000);
                    }

                    $sent = true;
                    $sentTo = $email;
                } catch (PDOException $e) {
                    error_log('Forgot password error: ' . $e->getMessage());
                    $errors[] = 'Something went wrong. Please try again.';
                }
            }
        }
    }
}
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
            $authHeadline = 'Get back into your account.';
            $authIntro = 'We\'ll email you a secure link to choose a new password.';
            $authImage = authPanelImage('forgot');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>

                <?php if ($sent): ?>
                    <h1>Check your email</h1>
                    <p class="auth-sub">
                        If an account exists for <strong><?php echo htmlspecialchars($sentTo); ?></strong>,
                        we've sent a password reset link to that inbox.
                    </p>

                    <ol class="auth-steps">
                        <li>Open the email from <?php echo htmlspecialchars(SITE_NAME); ?> and click <em>Reset My Password</em>.</li>
                        <li>Prefer a code? The email also has a 6-digit code you can enter instead.</li>
                        <li>The link and code expire in <?php echo RESET_TOKEN_TTL_MINUTES; ?> minutes.</li>
                    </ol>

                    <a href="<?php echo SITE_URL; ?>/reset-password.php?email=<?php echo rawurlencode($sentTo); ?>" class="auth-submit">I have a code</a>

                    <p class="auth-switch">Didn't get it? <a href="<?php echo SITE_URL; ?>/forgot-password.php">Send it again</a></p>
                    <p class="auth-note">
                        Check your Spam or Promotions folder if nothing arrives after a minute. Still stuck? WhatsApp or call
                        <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener"><?php echo WHATSAPP_NUMBER; ?></a>.
                    </p>

                <?php else: ?>
                    <h1>Forgot password?</h1>
                    <p class="auth-sub">Enter the email on your account and we'll send you a reset link.</p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="auth-field">
                            <label for="email" class="visually-hidden">Email address</label>
                            <input type="email" class="auth-input" id="email" name="email" placeholder="Email address" autocomplete="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autofocus required>
                        </div>

                        <button type="submit" class="auth-submit">Send reset link</button>
                    </form>

                    <p class="auth-switch">Already have a code? <a href="<?php echo SITE_URL; ?>/reset-password.php">Enter it here</a></p>
                    <p class="auth-switch">Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Sign up</a></p>
                <?php endif; ?>

                <a href="<?php echo SITE_URL; ?>/login.php" class="auth-home"><i class="bi bi-arrow-left"></i> Back to login</a>
            </section>
        </main>
    </div>
</body>
</html>
