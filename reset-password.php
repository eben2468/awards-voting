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
</head>
<body class="auth-body">
    <div class="auth-shell">
        <main class="auth-split">
            <?php
            $authHeadline = 'Get back into your account.';
            $authIntro = 'Choose a new password and carry on where you left off.';
            $authImage = authPanelImage('reset');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>

                <?php if ($done): ?>
                    <h1>Password updated</h1>
                    <p class="auth-sub">Your password has been changed. You can now log in with your new password.</p>

                    <a href="<?php echo SITE_URL; ?>/login.php" class="auth-submit">Go to login</a>

                    <p class="auth-note">
                        <i class="bi bi-shield-lock"></i> When you log in we'll email a 6-digit code to
                        <strong><?php echo e(maskEmail($email)); ?></strong> to confirm it's you.
                    </p>

                <?php else: ?>
                    <h1><?php echo $verified ? 'Choose a new password' : 'Reset your password'; ?></h1>
                    <p class="auth-sub">
                        <?php if ($verified): ?>
                            Pick a strong password for <strong><?php echo e($email); ?></strong>.
                        <?php else: ?>
                            Enter the 6-digit code we emailed you, or click the reset link in that email.
                        <?php endif; ?>
                    </p>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo e($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($verified): ?>
                        <!-- Step 2: new password -->
                        <form method="POST" action="" id="passwordForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="set_password">
                            <input type="hidden" name="email" value="<?php echo e($email); ?>">
                            <input type="hidden" name="token" value="<?php echo e($token); ?>">
                            <input type="hidden" name="code" value="<?php echo e($code); ?>">

                            <div class="auth-field auth-password">
                                <label for="password" class="visually-hidden">New password</label>
                                <input type="password" class="auth-input" id="password" name="password" placeholder="New password"
                                       autocomplete="new-password" minlength="8" required autofocus>
                                <button type="button" class="auth-eye" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="auth-meter"><span id="pwMeterBar"></span></div>
                            <p class="auth-hint" id="pwHint">Use at least 8 characters, with letters, numbers and a symbol.</p>

                            <div class="auth-field">
                                <label for="confirm_password" class="visually-hidden">Confirm new password</label>
                                <input type="password" class="auth-input" id="confirm_password" name="confirm_password"
                                       placeholder="Confirm new password" autocomplete="new-password" required>
                            </div>
                            <p class="auth-hint text-danger d-none" id="matchHint">Passwords do not match.</p>

                            <button type="submit" class="auth-submit">Update password</button>
                        </form>

                    <?php else: ?>
                        <!-- Step 1: email + code -->
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="check_code">

                            <div class="auth-field">
                                <label for="email" class="visually-hidden">Email address</label>
                                <input type="email" class="auth-input" id="email" name="email" placeholder="Email address" autocomplete="email"
                                       value="<?php echo e($email); ?>" required>
                            </div>

                            <div class="auth-field">
                                <label for="code" class="visually-hidden">6-digit code</label>
                                <input type="text" class="auth-input auth-code" id="code" name="code"
                                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                       autocomplete="one-time-code" placeholder="000000" required>
                            </div>
                            <p class="auth-hint">The code expires <?php echo RESET_TOKEN_TTL_MINUTES; ?> minutes after it is sent.</p>

                            <button type="submit" class="auth-submit">Verify code</button>
                        </form>

                        <p class="auth-switch">Code expired or missing? <a href="<?php echo SITE_URL; ?>/forgot-password.php">Request a new one</a></p>
                    <?php endif; ?>
                <?php endif; ?>

                <p class="auth-switch">Need help? <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener">WhatsApp us</a></p>
                <a href="<?php echo SITE_URL; ?>/login.php" class="auth-home"><i class="bi bi-arrow-left"></i> Back to login</a>
            </section>
        </main>
    </div>

    <script src="<?php echo assetUrl('assets/js/auth.js'); ?>"></script>
    <script>
        // Numeric-only code field
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
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
                hint.textContent = v ? 'Password strength: ' + level.t : 'Use at least 8 characters, with letters, numbers and a symbol.';
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
