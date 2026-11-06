<?php
/**
 * Registration Page
 * Awards Voting System
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

$page_title = 'Create Account';
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } else {
        $fullname = sanitize($_POST['fullname'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $terms = isset($_POST['terms']);

        // Validation
        if (empty($fullname)) {
            $errors[] = 'Full name is required.';
        }

        if (empty($email)) {
            $errors[] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        // Phone number is required: admins use it to reach organizers about
        // approval, and payouts are settled on a mobile money number.
        $phoneDigits = preg_replace('/\D/', '', $phone);
        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (strlen($phoneDigits) < 9 || strlen($phoneDigits) > 15) {
            $errors[] = 'Please enter a valid phone number, e.g. 0244000000 or +233244000000.';
        }

        if (empty($password)) {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        if (!$terms) {
            $errors[] = 'You must agree to the terms and conditions.';
        }

        // Process registration
        if (empty($errors)) {
            try {
                $pdo = getDBConnection();
                
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errors[] = 'Email address is already registered.';
                } else {
                    // New organizers must be approved by an administrator before
                    // they can sign in (unless approval has been switched off).
                    $needsApproval = isOrganizerApprovalRequired();
                    $approvalStatus = $needsApproval ? 'pending' : 'approved';

                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (fullname, email, phone, password, role, status, approval_status, approved_at)
                                           VALUES (?, ?, ?, ?, 'organizer', 'active', ?, ?)");

                    if ($stmt->execute([
                        $fullname,
                        $email,
                        $phone ?: null,
                        $hashed_password,
                        $approvalStatus,
                        $needsApproval ? null : date('Y-m-d H:i:s')
                    ])) {
                        $user_id = $pdo->lastInsertId();

                        // Log the action
                        $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'register', ?, ?)");
                        $logStmt->execute([$user_id, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);

                        $newUser = [
                            'id'         => $user_id,
                            'fullname'   => $fullname,
                            'email'      => $email,
                            'phone'      => $phone,
                            'created_at' => date('Y-m-d H:i:s'),
                        ];

                        // In-app notification for every administrator
                        $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                        foreach ($adminStmt->fetchAll() as $admin) {
                            createNotification(
                                $admin['id'],
                                $needsApproval ? 'Organizer Approval Needed' : 'New Organizer Registered',
                                $needsApproval
                                    ? "$fullname ($email) registered as an organizer and is waiting for approval."
                                    : "$fullname ($email) registered as an organizer.",
                                $needsApproval ? 'warning' : 'info',
                                'admin/organizer-approvals.php'
                            );
                        }

                        if ($needsApproval) {
                            // Email the admins so they can approve, and confirm to the organizer
                            notifyAdminsOfPendingOrganizer($newUser);
                            sendOrganizerPendingEmail($newUser);

                            $_SESSION['pending_approval_user'] = [
                                'id'       => $user_id,
                                'fullname' => $fullname,
                                'email'    => $email,
                            ];
                            redirect('pending-approval.php');
                        }

                        setFlash('success', 'Account created successfully! You can now login.');
                        redirect('login.php');
                    } else {
                        $errors[] = 'Failed to create account. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'An error occurred. Please try again.';
                error_log("Registration error: " . $e->getMessage());
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
            $authHeadline = 'Bring your awards night to VoteHubGH.';
            $authIntro = 'Create your organizer account and start setting up your event.';
            $authImage = authPanelImage('register');
            include 'includes/auth-panel.php';
            ?>

            <section class="auth-form">
                <a href="<?php echo SITE_URL; ?>" class="auth-brand">
                    <img src="<?php echo assetUrl('assets/logo/site-logo.png'); ?>" alt="VoteHubGH" width="150" height="72">
                </a>
                <h1>Create your account</h1>
                <p class="auth-sub">For event organizers. Voters don't need an account.</p>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="registerForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="auth-field">
                        <label for="fullname" class="visually-hidden">Full name</label>
                        <input type="text" class="auth-input" id="fullname" name="fullname" placeholder="Full name" autocomplete="name"
                               value="<?php echo isset($_POST['fullname']) ? e($_POST['fullname']) : ''; ?>" required>
                    </div>

                    <div class="auth-field">
                        <label for="email" class="visually-hidden">Email address</label>
                        <input type="email" class="auth-input" id="email" name="email" placeholder="Email address" autocomplete="email"
                               value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>" required>
                    </div>

                    <div class="auth-field">
                        <label for="phone" class="visually-hidden">Phone number</label>
                        <input type="tel" class="auth-input" id="phone" name="phone" placeholder="Phone number, e.g. 0244000000" inputmode="tel" autocomplete="tel"
                               value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>" required>
                    </div>

                    <div class="auth-pair">
                        <div class="auth-field auth-password">
                            <label for="password" class="visually-hidden">Password</label>
                            <input type="password" class="auth-input" id="password" name="password" placeholder="Password" autocomplete="new-password" required>
                            <button type="button" class="auth-eye" data-toggle-password="password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="auth-field auth-password">
                            <label for="confirm_password" class="visually-hidden">Confirm password</label>
                            <input type="password" class="auth-input" id="confirm_password" name="confirm_password" placeholder="Confirm password" autocomplete="new-password" required>
                            <button type="button" class="auth-eye" data-toggle-password="confirm_password" aria-label="Show password"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <p class="auth-hint">At least 8 characters.</p>

                    <div class="form-check auth-terms">
                        <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php">Terms &amp; Conditions</a> and <a href="privacy.php">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="auth-submit">Create account</button>
                </form>

                <p class="auth-note">
                    <i class="bi bi-shield-check"></i>
                    An admin reviews every new account, and we'll email you once it's approved. To speed things up, message us on WhatsApp at
                    <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener"><?php echo WHATSAPP_NUMBER; ?></a>.
                </p>

                <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
                <a href="<?php echo SITE_URL; ?>" class="auth-home"><i class="bi bi-arrow-left"></i> Back to home</a>
            </section>
        </main>
    </div>

    <script src="<?php echo assetUrl('assets/js/auth.js'); ?>"></script>
</body>
</html>
