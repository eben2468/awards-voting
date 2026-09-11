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
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/uploads/logo/VoteHubGH.png">
    
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
                        <h1><i class="bi bi-trophy-fill me-2"></i>VoteHubGH</h1>
                    </a>
                    <div class="alert alert-info border-0 shadow-sm rounded-4 p-3 mb-4">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-primary bg-opacity-10 text-primary rounded-circle p-2">
                                <i class="bi bi-info-circle-fill fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Organizer Registration Only</h6>
                                <p class="small text-muted mb-0">Voters do not need an account to vote. This registration is strictly for event organizers.</p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-3 mb-4 text-start">
                        <div class="d-flex align-items-start gap-3">
                            <div class="icon-box bg-warning bg-opacity-25 text-warning-emphasis rounded-circle p-2">
                                <i class="bi bi-shield-check fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold mb-1">Accounts are approved by an admin</h6>
                                <p class="small text-muted mb-0">
                                    After you sign up, an administrator reviews your account before you can log in.
                                    You will get an email as soon as it is approved &mdash; or reach us on WhatsApp at
                                    <a href="<?php echo WHATSAPP_LINK; ?>" target="_blank" rel="noopener" class="fw-semibold text-decoration-none"><?php echo WHATSAPP_NUMBER; ?></a>
                                    to speed things up.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

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
                    
                    <div class="mb-3">
                        <label for="fullname" class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullname" 
                                   name="fullname" 
                                   value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>"
                                   placeholder="Enter your full name"
                                   required>
                        </div>
                    </div>

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
                        <label for="phone" class="form-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                            <input type="tel"
                                   class="form-control"
                                   id="phone"
                                   name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                                   placeholder="e.g. 0244000000"
                                   inputmode="tel"
                                   autocomplete="tel"
                                   required>
                        </div>
                        <div class="form-text">We use this to reach you about your account approval.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Min 8 chars"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-check-circle"></i></span>
                                <input type="password" 
                                       class="form-control" 
                                       id="confirm_password" 
                                       name="confirm_password" 
                                       placeholder="Repeat password"
                                       required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="terms.php" class="text-primary">Terms & Conditions</a> and <a href="privacy.php" class="text-primary">Privacy Policy</a>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 btn-lg mb-3">
                        <i class="bi bi-person-plus me-2"></i>Create Account
                    </button>

                    <div class="text-center">
                        <p class="mb-0">Already have an account? <a href="login.php" class="text-primary fw-semibold">Login</a></p>
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
</body>
</html>
