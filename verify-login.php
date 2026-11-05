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
        redirect('user/dashboard.php');
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

    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">

    <style>
        .otp-input {
            font-size: 2rem;
            letter-spacing: 1rem;
            text-align: center;
            font-weight: 700;
            padding: .75rem 1rem;
            text-indent: 1rem;
        }
        .otp-input::placeholder { letter-spacing: .75rem; font-weight: 400; opacity: .35; }
        .verify-icon {
            width: 74px; height: 74px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 50%;
            background: rgba(42, 82, 152, .1);
            color: #2a5298;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
    </style>
</head>
<body class="auth-body">
    <div class="auth-page">
        <div class="container">
            <div class="auth-card">
                <div class="text-center mb-4">
                    <div class="verify-icon"><i class="bi bi-shield-lock-fill"></i></div>
                    <h3 class="fw-bold mb-2">Two-Step Verification</h3>
                    <p class="text-muted mb-0">
                        We emailed a 6-digit code to<br>
                        <strong class="text-dark"><?php echo htmlspecialchars($maskedEmail); ?></strong>
                    </p>
                </div>

                <?php if ($notice): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <div><?php echo htmlspecialchars($notice); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="verifyForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="verify">

                    <div class="mb-3">
                        <label for="code" class="form-label">Verification Code</label>
                        <input type="text"
                               class="form-control otp-input"
                               id="code"
                               name="code"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               placeholder="000000"
                               autofocus
                               required>
                        <div class="form-text text-center mt-2">
                            The code expires in <?php echo LOGIN_CODE_TTL_MINUTES; ?> minutes.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                        <i class="bi bi-check2-circle me-2"></i>Verify &amp; Continue
                    </button>
                </form>

                <div class="text-center mb-3">
                    <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="resend">
                        <span class="text-muted small">Didn't get the email?</span>
                        <button type="submit" class="btn btn-link p-0 align-baseline fw-semibold" id="resendBtn">
                            Resend code
                        </button>
                    </form>
                </div>

                <div class="alert alert-light border small text-muted">
                    <i class="bi bi-info-circle me-1 text-primary"></i>
                    Check your <strong>Spam</strong> or <strong>Promotions</strong> folder if the email has not
                    arrived after a minute. Still stuck? Call or WhatsApp
                    <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener" class="fw-semibold"><?php echo WHATSAPP_NUMBER; ?></a>.
                </div>

                <hr class="my-4">

                <div class="text-center">
                    <a href="<?php echo SITE_URL; ?>/logout.php?cancel=1" class="text-muted">
                        <i class="bi bi-arrow-left me-1"></i>Cancel and use a different account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
