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
        redirect('user/dashboard.php');
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
                    $errors[] = 'Invalid email or password.';

                    // Log failed attempt
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (action, ip_address, user_agent, new_values) VALUES ('failed_login', ?, ?, ?)");
                    $logStmt->execute([
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                        json_encode(['email' => $email])
                    ]);
                } else {
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
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
</head>
<body class="auth-body">
    <div class="auth-page">
        <div class="container">
            <div class="auth-card">
                <div class="auth-logo">
                    <a href="<?php echo SITE_URL; ?>">
                        <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" class="brand-logo">
                    </a>
                    <p class="text-muted">Welcome back! Please login to continue.</p>
                </div>

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

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email"
                                   class="form-control"
                                   id="email"
                                   name="email"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                   placeholder="Enter your email"
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password"
                                   class="form-control"
                                   id="password"
                                   name="password"
                                   placeholder="Enter your password"
                                   required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Remember me</label>
                        </div>
                        <a href="forgot-password.php" class="text-primary">Forgot Password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>

                    <div class="alert alert-light border d-flex align-items-start gap-2 small mb-3">
                        <i class="bi bi-shield-lock-fill text-primary mt-1"></i>
                        <div class="text-muted">
                            <strong class="d-block text-dark">Protected by two-step verification</strong>
                            After entering your password we will email a 6-digit code to your inbox to confirm it is really you.
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary fw-semibold">Sign Up</a></p>
                    </div>
                </form>

                <hr class="my-4">

                <div class="text-center">
                    <a href="<?php echo SITE_URL; ?>" class="text-muted">
                        <i class="bi bi-arrow-left me-1"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>
