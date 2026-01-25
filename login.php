<?php
/**
 * Login Page
 * Awards Voting System
 */

require_once 'config/database.php';

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
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['fullname'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_avatar'] = $user['avatar'];

                    // Update last login
                    $updateStmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);

                    // Log the action
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
                    $logStmt->execute([$user['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                    // Redirect based on role
                    setFlash('success', 'Welcome back, ' . $user['fullname'] . '!');
                    
                    $redirectUrl = $_SESSION['redirect_url'] ?? 
                                 ($user['role'] === 'admin' ? 'admin/' : 
                                  ($user['role'] === 'organizer' ? 'organizer/' : 'user/dashboard.php'));
                    unset($_SESSION['redirect_url']);
                    redirect($redirectUrl);
                } else {
                    $errors[] = 'Invalid email or password.';
                    
                    // Log failed attempt
                    $logStmt = $pdo->prepare("INSERT INTO audit_logs (action, ip_address, user_agent, new_values) VALUES ('failed_login', ?, ?, ?)");
                    $logStmt->execute([$_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], json_encode(['email' => $email])]);
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
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="auth-page">
        <div class="container">
            <div class="auth-card">
                <div class="auth-logo">
                    <a href="<?php echo SITE_URL; ?>">
                        <h1><i class="bi bi-trophy-fill me-2"></i>VoteHubGH</h1>
                    </a>
                    <p class="text-muted">Welcome back! Please login to continue.</p>
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
