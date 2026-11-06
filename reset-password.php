<?php
/**
 * Reset Password
 * Awards Voting System
 *
 * Accepts either the emailed reset link (?token=...&email=...) or the 6-digit
 * code from the same email, then lets the user set a new password.
 */

require_once 'config/database.php';
require_once 'includes/auth-functions.php';

if (isLoggedIn()) {
    redirect(isAdmin() ? 'admin/' : (isOrganizer() ? 'organizer/' : 'user/dashboard.php'));
}

$page_title = 'Reset Password';
$pdo = getDBConnection();

$errors = [];
$done = false;

$email = sanitize($_GET['email'] ?? $_POST['email'] ?? '');
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$code  = trim($_POST['code'] ?? '');

// A valid link takes the user straight to the new-password form
$verified = false;
$resetRow = null;

if ($token !== '' && $email !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $resetRow = findValidPasswordReset($pdo, $email, $token);
    if ($resetRow) {
        $verified = true;
    } else {
        $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'check_code';

        // ---- Step 1: confirm the code (or the token from the link) ----
        if ($action === 'check_code') {
            if ($email === '') {
                $errors[] = 'Please enter your email address.';
            } elseif ($token === '' && $code === '') {
                $errors[] = 'Please enter the 6-digit code from your email.';
            } else {
                $resetRow = findValidPasswordReset($pdo, $email, $token ?: null, $code ?: null);

                if ($resetRow) {
                    $verified = true;
                } else {
                    $errors[] = 'That code is incorrect or has expired. Please check the email again, '
                        . 'or request a new reset link.';
                }
            }
        }

        // ---- Step 2: save the new password ----
        if ($action === 'set_password') {
            $newPassword = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            $resetRow = findValidPasswordReset($pdo, $email, $token ?: null, $code ?: null);

            if (!$resetRow) {
                $errors[] = 'Your reset request has expired. Please start again.';
            } else {
                $verified = true;

                if (strlen($newPassword) < 8) {
                    $errors[] = 'Password must be at least 8 characters long.';
                }
                if ($newPassword !== $confirmPassword) {
                    $errors[] = 'The two passwords do not match.';
                }

                if (empty($errors)) {
                    try {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $user = $stmt->fetch();

                        if (!$user) {
                            $errors[] = 'Account not found. Please contact support on ' . WHATSAPP_NUMBER . '.';
                        } else {
                            clearFailedLogins($pdo, $email);
                            $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?")
                                ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);

                            // Burn the reset request and any half-finished logins
                            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                                ->execute([$resetRow['id']]);
                            $pdo->prepare("UPDATE login_verification_codes SET used_at = NOW()
                                           WHERE user_id = ? AND used_at IS NULL")
                                ->execute([$user['id']]);

                            logAudit($user['id'], 'password_reset', 'users', $user['id']);
                            sendPasswordChangedEmail($user);

                            $done = true;
                        }
                    } catch (PDOException $e) {
                        error_log('Reset password error: ' . $e->getMessage());
                        $errors[] = 'Could not update your password. Please try again.';
                    }
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
        .otp-input {
            font-size: 1.75rem; letter-spacing: .8rem; text-align: center;
            font-weight: 700; text-indent: .8rem;
        }
        .pw-meter { height: 5px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
        .pw-meter span { display: block; height: 100%; width: 0; transition: width .25s, background .25s; }
    </style>
</head>
<body class="auth-body">
    <div class="auth-page">
        <div class="container">
            <div class="auth-card">

                <?php if ($done): ?>
                    <div class="text-center mb-4">
                        <div class="auth-icon success"><i class="bi bi-check-circle-fill"></i></div>
                        <h3 class="fw-bold mb-2">Password Updated</h3>
                        <p class="text-muted mb-0">
                            Your password has been changed. You can now sign in with your new password.
                        </p>
                    </div>

                    <div class="alert alert-light border small text-muted">
                        <i class="bi bi-shield-lock me-1 text-primary"></i>
                        When you log in we will email a 6-digit verification code to
                        <strong><?php echo htmlspecialchars(maskEmail($email)); ?></strong> to confirm it is you.
                    </div>

                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-primary w-100 btn-lg">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>

                <?php else: ?>
                    <div class="text-center mb-4">
                        <a href="<?php echo SITE_URL; ?>" class="text-decoration-none">
                            <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" class="brand-logo brand-logo-sm">
                        </a>
                        <div class="auth-icon"><i class="bi bi-key-fill"></i></div>
                        <h3 class="fw-bold mb-2"><?php echo $verified ? 'Choose a New Password' : 'Reset Your Password'; ?></h3>
                        <p class="text-muted mb-0">
                            <?php if ($verified): ?>
                                Almost done &mdash; pick a strong password for
                                <strong class="text-dark"><?php echo htmlspecialchars($email); ?></strong>.
                            <?php else: ?>
                                Enter the 6-digit code we emailed you, or click the reset link in that email.
                            <?php endif; ?>
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

                    <?php if ($verified): ?>
                        <!-- Step 2: new password -->
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="set_password">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="code" value="<?php echo htmlspecialchars($code); ?>">

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password"
                                           placeholder="At least 8 characters" minlength="8" required autofocus>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="pw-meter mt-2"><span id="pwMeterBar"></span></div>
                                <div class="form-text" id="pwHint">Use letters, numbers and a symbol for a stronger password.</div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                                    <input type="password" class="form-control" id="confirm_password"
                                           name="confirm_password" placeholder="Repeat your new password" required>
                                </div>
                                <div class="form-text text-danger d-none" id="matchHint">Passwords do not match.</div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                                <i class="bi bi-shield-check me-2"></i>Update Password
                            </button>
                        </form>

                    <?php else: ?>
                        <!-- Step 1: email + code -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="check_code">

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($email); ?>"
                                           placeholder="you@example.com" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="code" class="form-label">6-Digit Code</label>
                                <input type="text" class="form-control otp-input" id="code" name="code"
                                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                       autocomplete="one-time-code" placeholder="000000" required>
                                <div class="form-text text-center mt-2">
                                    The code expires <?php echo RESET_TOKEN_TTL_MINUTES; ?> minutes after it is sent.
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                                <i class="bi bi-check2-circle me-2"></i>Verify Code
                            </button>
                        </form>

                        <div class="text-center">
                            <p class="mb-0 small text-muted">
                                Code expired or missing?
                                <a href="<?php echo SITE_URL; ?>/forgot-password.php" class="text-primary fw-semibold">Request a new one</a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <hr class="my-4">

                <div class="d-flex justify-content-between align-items-center">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="text-muted text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Back to Login
                    </a>
                    <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener" class="text-success fw-semibold text-decoration-none">
                        <i class="bi bi-whatsapp me-1"></i>Need help?
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Numeric-only code field
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        }

        // Show/hide password
        const toggle = document.getElementById('togglePassword');
        if (toggle) {
            toggle.addEventListener('click', function () {
                const field = document.getElementById('password');
                const icon = this.querySelector('i');
                const showing = field.type === 'text';
                field.type = showing ? 'password' : 'text';
                icon.className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        // Password strength meter + match check
        const pw = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const bar = document.getElementById('pwMeterBar');
        const hint = document.getElementById('pwHint');
        const matchHint = document.getElementById('matchHint');

        if (pw && bar) {
            pw.addEventListener('input', function () {
                const v = this.value;
                let score = 0;
                if (v.length >= 8) score++;
                if (v.length >= 12) score++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
                if (/\d/.test(v)) score++;
                if (/[^A-Za-z0-9]/.test(v)) score++;

                const levels = [
                    { w: '20%', c: '#dc3545', t: 'Too weak' },
                    { w: '40%', c: '#fd7e14', t: 'Weak' },
                    { w: '60%', c: '#ffc107', t: 'Fair' },
                    { w: '80%', c: '#20c997', t: 'Good' },
                    { w: '100%', c: '#198754', t: 'Strong' }
                ];
                const level = levels[Math.max(0, Math.min(score - 1, 4))];
                bar.style.width = v ? level.w : '0';
                bar.style.background = level.c;
                hint.textContent = v ? 'Password strength: ' + level.t : 'Use letters, numbers and a symbol for a stronger password.';
            });
        }

        if (confirm && matchHint) {
            const check = function () {
                matchHint.classList.toggle('d-none', !confirm.value || confirm.value === pw.value);
            };
            confirm.addEventListener('input', check);
            pw.addEventListener('input', check);
        }
    </script>
</body>
</html>
