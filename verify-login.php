<?php
/**
 * Login Page (Step 2: email verification code)
 * Awards Voting System
 *
 * Reached only after login.php has verified the user's password. Expects a
 * 6-digit code that was emailed to the account's address.
 */

require_once 'config/database.php';
require_once 'includes/auth-functions.php';

// Already signed in? Nothing to verify.
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/');
    } elseif (isOrganizer()) {
        redirect('organizer/');
    } else {
        redirect('');
    }
}

// Must have completed step 1
if (empty($_SESSION['pending_login']['user_id'])) {
    setFlash('warning', 'Please sign in with your email and password first.');
    redirect('login.php');
}

$pending = $_SESSION['pending_login'];

// The whole two-step attempt expires after 20 minutes
if (time() - ($pending['started'] ?? 0) > 20 * 60) {
    unset($_SESSION['pending_login']);
    setFlash('warning', 'Your sign-in session timed out. Please log in again.');
    redirect('login.php');
}

$page_title = 'Verify Your Login';
$pdo = getDBConnection();
$errors = [];
$notice = '';

// A throttled code request lands here with an explanation: the previous code
// is still valid, so the user just needs to check their inbox.
if (!empty($pending['notice'])) {
    $notice = $pending['notice'] . ' The code we already sent is still valid.';
    unset($_SESSION['pending_login']['notice']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'verify';

        // ---- Resend the code ----
        if ($action === 'resend') {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$pending['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                unset($_SESSION['pending_login']);
                setFlash('danger', 'Account not found. Please log in again.');
                redirect('login.php');
            }

            $result = issueLoginCode($pdo, $user);
            if ($result['sent']) {
                $notice = 'A new verification code has been sent to ' . maskEmail($user['email']) . '.';
            } else {
                $errors[] = $result['error'];
            }
        }

        // ---- Verify the submitted code ----
        if ($action === 'verify') {
            $code = trim($_POST['code'] ?? '');
            $result = verifyLoginCode($pdo, $pending['user_id'], $code, 'login');

            if ($result['ok']) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$pending['user_id']]);
                $user = $stmt->fetch();

                if (!$user) {
                    unset($_SESSION['pending_login']);
                    setFlash('danger', 'Account not found. Please log in again.');
                    redirect('login.php');
                }

                // Re-check the account is still allowed in
                $approvalStatus = $user['approval_status'] ?? 'approved';
                if ($user['status'] !== 'active' || $approvalStatus !== 'approved') {
                    unset($_SESSION['pending_login']);
                    setFlash('danger', 'This account is not able to sign in. Please contact support on ' . WHATSAPP_NUMBER . '.');
                    redirect('login.php');
                }

                $destination = establishUserSession($pdo, $user);
                setFlash('success', 'Welcome back, ' . $user['fullname'] . '!');
                redirect($destination);
            }

            $errors[] = $result['error'];
        }
    }
}

$maskedEmail = maskEmail($pending['email']);
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
            $authHeadline = 'One more step to sign in.';
            $authIntro = 'This code keeps your account safe, even if someone else has your password.';
            $authImage = authPanelImage('login');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>
                <h1>Enter your code</h1>
                <p class="auth-sub">We emailed a 6-digit code to <strong><?php echo htmlspecialchars($maskedEmail); ?></strong></p>

                <?php if ($notice): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($notice); ?></div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="verifyForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="verify">

                    <div class="auth-field">
                        <label for="code" class="visually-hidden">Verification code</label>
                        <input type="text" class="auth-input auth-code" id="code" name="code"
                               inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                               autocomplete="one-time-code" placeholder="000000" autofocus required>
                    </div>
                    <p class="auth-hint">The code expires in <?php echo LOGIN_CODE_TTL_MINUTES; ?> minutes.</p>

                    <button type="submit" class="auth-submit">Verify and continue</button>
                </form>

                <form method="POST" action="" class="auth-switch">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="resend">
                    Didn't get the email? <button type="submit" class="auth-link" id="resendBtn">Resend code</button>
                </form>

                <p class="auth-note">
                    Check your Spam or Promotions folder if nothing arrives after a minute. Still stuck? Call or WhatsApp
                    <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener"><?php echo WHATSAPP_NUMBER; ?></a>.
                </p>

                <a href="<?php echo SITE_URL; ?>/logout.php?cancel=1" class="auth-home"><i class="bi bi-arrow-left"></i> Use a different account</a>
            </section>
        </main>
    </div>

    <script>
        // Keep the code field numeric and auto-submit once six digits are entered
        const codeInput = document.getElementById('code');
        codeInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
            if (this.value.length === 6) {
                document.getElementById('verifyForm').submit();
            }
        });

        // Allow pasting the code straight from the email
        codeInput.addEventListener('paste', function (e) {
            const text = (e.clipboardData || window.clipboardData).getData('text') || '';
            const digits = text.replace(/\D/g, '').slice(0, 6);
            if (digits.length === 6) {
                e.preventDefault();
                this.value = digits;
                document.getElementById('verifyForm').submit();
            }
        });

        // Brief cool-down on the resend button so people don't spam it
        const resendBtn = document.getElementById('resendBtn');
        if (resendBtn) {
            let seconds = 45;
            const original = resendBtn.textContent.trim();
            resendBtn.disabled = true;
            const tick = setInterval(function () {
                resendBtn.textContent = original + ' (' + seconds + 's)';
                if (--seconds < 0) {
                    clearInterval(tick);
                    resendBtn.disabled = false;
                    resendBtn.textContent = original;
                }
            }, 1000);
        }
    </script>
</body>
</html>
