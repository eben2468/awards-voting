<?php
/**
 * Login Page (Step 1: email + password)
 * Awards Voting System
 *
 * After the password is verified the user is sent to verify-login.php where a
 * 6-digit code emailed to their address completes the sign-in (two-step
 * verification). Organizer accounts must be approved by an admin first.
 */

require_once 'config/database.php';
require_once 'includes/auth-functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/');
    } elseif (isOrganizer()) {
        redirect('organizer/');
    } else {
        redirect('');
    }
}

$page_title = 'Login';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // Validation
        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        }

        // A locked login is refused before the password is even looked at
        if (empty($errors)) {
            try {
                $lock = loginLockState(getDBConnection(), $email);
                if ($lock['locked']) {
                    $errors[] = loginLockedMessage($lock['wait']);
                }
            } catch (PDOException $e) {
                error_log('Login lock check error: ' . $e->getMessage());
            }
        }

        // Process login
        if (empty($errors)) {
            try {
                $pdo = getDBConnection();

                // Occasionally tidy up expired codes so the tables stay small
                if (random_int(1, 40) === 1) {
                    purgeExpiredVerificationCodes();
                }
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password'])) {
                    $errors[] = recordFailedLogin($pdo, $email);
                } else {
                    clearFailedLogins($pdo, $email);
                    $approvalStatus = $user['approval_status'] ?? 'approved';

                    if ($user['status'] === 'suspended') {
                        $errors[] = 'This account has been suspended. Please contact support on ' . WHATSAPP_NUMBER . '.';
                    } elseif ($approvalStatus === 'pending') {
                        // Organizer still waiting for an administrator to approve
                        $_SESSION['pending_approval_user'] = [
                            'id'       => $user['id'],
                            'fullname' => $user['fullname'],
                            'email'    => $user['email'],
                        ];
                        redirect('pending-approval.php');
                    } elseif ($approvalStatus === 'rejected') {
                        $_SESSION['pending_approval_user'] = [
                            'id'       => $user['id'],
                            'fullname' => $user['fullname'],
                            'email'    => $user['email'],
                            'rejected' => true,
                            'note'     => $user['approval_note'] ?? '',
                        ];
                        redirect('pending-approval.php');
                    } elseif ($user['status'] !== 'active') {
                        $errors[] = 'This account is not active. Please contact support on ' . WHATSAPP_NUMBER . '.';
                    } else {
                        $twoFactorOn = isTwoFactorRequired() && ((int) ($user['two_factor_enabled'] ?? 1) === 1);

                        if (!$twoFactorOn) {
                            // Two-step verification disabled - sign in directly
                            $destination = establishUserSession($pdo, $user);
                            setFlash('success', 'Welcome back, ' . $user['fullname'] . '!');
                            redirect($destination);
                        }

                        // Issue and email the 6-digit verification code
                        $result = issueLoginCode($pdo, $user);

                        // A throttled request is NOT a delivery failure: a code was
                        // sent moments ago and is still valid, so the second factor
                        // must still be demanded. Skipping it here would let anyone
                        // with a stolen password bypass 2FA just by logging in twice.
                        if ($result['sent'] || !empty($result['throttled'])) {
                            $_SESSION['pending_login'] = [
                                'user_id'  => $user['id'],
                                'email'    => $user['email'],
                                'fullname' => $user['fullname'],
                                'remember' => $remember,
                                'started'  => time(),
                                'notice'   => !empty($result['throttled']) ? $result['error'] : '',
                            ];
                            redirect('verify-login.php');
                        }

                        // The code genuinely could not be emailed. Rather than locking
                        // a legitimate user (and especially the admin) out of the
                        // system, sign them in and warn loudly so SMTP can be fixed.
                        error_log('2FA email failed for ' . $user['email'] . ': ' . $result['error']);

                        if ((string) getSetting('two_factor_fallback', '1') === '1') {
                            $destination = establishUserSession($pdo, $user);
                            setFlash('warning', 'Signed in without two-step verification: the code email could not be sent. '
                                . 'An administrator should check Settings &rsaquo; Email (SMTP) Settings.');
                            redirect($destination);
                        }

                        $errors[] = 'We could not send your verification code. ' . htmlspecialchars($result['error']);
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'An error occurred. Please try again.';
                error_log("Login error: " . $e->getMessage());
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

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/favicon.png?v=2">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/apple-touch-icon.png?v=2">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/theme.css'); ?>">
</head>
<body class="auth-body">
    <div class="auth-shell">
        <main class="auth-split">
            <?php
            $authHeadline = 'Run your awards night from one place.';
            $authIntro = 'Follow votes, ticket sales and payouts as they come in.';
            $authImage = authPanelImage('login');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>
                <h1>Welcome back</h1>
                <p class="auth-sub">Please log in to your account</p>

                <?php if (isset($_GET['timeout'])): ?>
                    <div class="alert alert-warning">You were signed out because you were inactive. Please sign in again.</div>
                <?php endif; ?>

                <?php $flash = getFlash(); if ($flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
                        <?php echo $flash['message']; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="auth-field">
                        <label for="email" class="visually-hidden">Email address</label>
                        <input type="email" class="auth-input" id="email" name="email" placeholder="Email address" autocomplete="email"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>

                    <div class="auth-field auth-password">
                        <label for="password" class="visually-hidden">Password</label>
                        <input type="password" class="auth-input" id="password" name="password" placeholder="Password" autocomplete="current-password" required>
                        <button type="button" class="auth-eye" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                    </div>

                    <div class="auth-forgot">
                        <a href="forgot-password.php">Forgot password?</a>
                    </div>

                    <button type="submit" class="auth-submit">Log in</button>
                </form>

                <p class="auth-note"><i class="bi bi-shield-lock"></i> We'll email you a 6-digit code to confirm it's you.</p>

                <p class="auth-switch">Don't have an account? <a href="register.php">Sign up</a></p>
                <a href="<?php echo SITE_URL; ?>" class="auth-home"><i class="bi bi-arrow-left"></i> Back to home</a>
            </section>
        </main>
    </div>

    <script src="<?php echo assetUrl('assets/js/auth.js'); ?>"></script>
</body>
</html>
