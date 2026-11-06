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

    <style>
        .auth-icon {
            width: 74px; height: 74px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(42, 82, 152, .1);
            color: #2a5298;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
        .auth-icon.success { background: rgba(25, 135, 84, .12); color: #198754; }
    </style>
</head>
<body class="auth-body">
    <div class="auth-page">
        <div class="container">
            <div class="auth-card">

                <?php if ($sent): ?>
                    <div class="text-center mb-4">
                        <div class="auth-icon success"><i class="bi bi-envelope-check-fill"></i></div>
                        <h3 class="fw-bold mb-2">Check Your Email</h3>
                        <p class="text-muted mb-0">
                            If an account exists for<br>
                            <strong class="text-dark"><?php echo htmlspecialchars($sentTo); ?></strong><br>
                            we have sent a password reset link to that inbox.
                        </p>
                    </div>

                    <div class="alert alert-light border small">
                        <p class="mb-2"><strong><i class="bi bi-1-circle me-1 text-primary"></i>Open the email</strong>
                            from <?php echo htmlspecialchars(SITE_NAME); ?> and click <em>Reset My Password</em>.</p>
                        <p class="mb-2"><strong><i class="bi bi-2-circle me-1 text-primary"></i>Prefer a code?</strong>
                            The email also contains a 6-digit code you can enter manually.</p>
                        <p class="mb-0 text-muted">
                            <i class="bi bi-clock me-1"></i>The link and code expire in
                            <?php echo RESET_TOKEN_TTL_MINUTES; ?> minutes.
                        </p>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo SITE_URL; ?>/reset-password.php?email=<?php echo rawurlencode($sentTo); ?>"
                           class="btn btn-primary btn-lg">
                            <i class="bi bi-key me-2"></i>I have a code - continue
                        </a>
                        <a href="<?php echo SITE_URL; ?>/forgot-password.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-repeat me-1"></i>Send it again
                        </a>
                    </div>

                    <div class="alert alert-light border small text-muted mt-3 mb-0">
                        <i class="bi bi-info-circle me-1 text-primary"></i>
                        Nothing after a minute? Check your <strong>Spam</strong> or <strong>Promotions</strong> folder.
                        Still stuck? WhatsApp or call
                        <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener" class="fw-semibold"><?php echo WHATSAPP_NUMBER; ?></a>.
                    </div>

                <?php else: ?>
                    <div class="text-center mb-4">
                        <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                            <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" class="brand-logo brand-logo-sm">
                        </a>
                        <div class="auth-icon"><i class="bi bi-question-circle-fill"></i></div>
                        <h3 class="fw-bold mb-2">Forgot Your Password?</h3>
                        <p class="text-muted mb-0">
                            Enter the email address on your account and we will send you a link to choose a new password.
                        </p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                       placeholder="you@example.com"
                                       autofocus
                                       required>
                            </div>
                            <div class="form-text">This is the email you signed up with.</div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </button>
                    </form>

                    <div class="text-center">
                        <p class="mb-0 small text-muted">
                            Already have a code?
                            <a href="<?php echo SITE_URL; ?>/reset-password.php" class="text-primary fw-semibold">Enter it here</a>
                        </p>
                    </div>
                <?php endif; ?>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                    <a href="<?php echo SITE_URL; ?>/register.php" class="text-primary fw-semibold text-decoration-none">
                        Create an account
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
