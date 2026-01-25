<?php
/**
 * Admin Profile Management
 * Awards Voting System
 */

require_once '../config/database.php';

// Require admin login
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['redirect_url'] = 'admin/profile.php';
    redirect('../login.php');
}

$page_title = 'My Profile';
$pdo = getDBConnection();
$userId = getCurrentUserId();

// Fetch user data
$userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $action = $_POST['action'] ?? 'update_profile';
        
        if ($action === 'update_profile') {
            $fullname = sanitize($_POST['fullname'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            
            if (empty($fullname)) {
                $errors[] = 'Full name is required.';
            }
            
            // Handle avatar upload
            $avatar = $user['avatar'];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($_FILES['avatar']['type'], $allowedTypes)) {
                    $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.';
                } elseif ($_FILES['avatar']['size'] > $maxSize) {
                    $errors[] = 'Image size must be less than 2MB.';
                } else {
                    $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                    $avatar = 'avatar-' . $userId . '-' . time() . '.' . $extension;
                    $uploadPath = '../uploads/avatars/' . $avatar;
                    
                    if (!is_dir('../uploads/avatars/')) {
                        mkdir('../uploads/avatars/', 0755, true);
                    }
                    
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadPath)) {
                        // Delete old avatar if exists
                        if ($user['avatar'] && file_exists('../uploads/avatars/' . $user['avatar'])) {
                            unlink('../uploads/avatars/' . $user['avatar']);
                        }
                    } else {
                        $errors[] = 'Failed to upload avatar.';
                        $avatar = $user['avatar'];
                    }
                }
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET fullname = ?, phone = ?, avatar = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$fullname, $phone ?: null, $avatar, $userId]);
                    
                    $_SESSION['user_name'] = $fullname;
                    $_SESSION['user_avatar'] = $avatar;
                    setFlash('success', 'Profile updated successfully!');
                    redirect('admin/profile.php');
                } catch (PDOException $e) {
                    $errors[] = 'Failed to update profile.';
                }
            }
        } elseif ($action === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (empty($currentPassword)) {
                $errors[] = 'Current password is required.';
            } elseif (!password_verify($currentPassword, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            }
            
            if (empty($newPassword)) {
                $errors[] = 'New password is required.';
            } elseif (strlen($newPassword) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'New passwords do not match.';
            }
            
            if (empty($errors)) {
                try {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashedPassword, $userId]);
                    
                    setFlash('success', 'Password changed successfully!');
                    redirect('admin/profile.php');
                } catch (PDOException $e) {
                    $errors[] = 'Failed to change password.';
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
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="admin-main">
        <?php include 'includes/topbar.php'; ?>
        
        <!-- Content Area -->
        <div class="admin-content p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1">My Profile</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?php echo SITE_URL; ?>/admin/">Admin</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php 
            $flash = getFlash();
            if ($flash): 
            ?>
                <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $flash['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Profile Card -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <div class="avatar-wrapper mb-3">
                                <img src="<?php echo $user['avatar'] ? SITE_URL . '/uploads/avatars/' . $user['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['fullname']) . '&size=150&background=6366f1&color=fff'; ?>" 
                                     alt="Profile" 
                                     class="rounded-circle mb-3 shadow-sm"
                                     style="width: 120px; height: 120px; object-fit: cover; border: 4px solid #f8f9fa;">
                            </div>
                            <h4 class="mb-1"><?php echo htmlspecialchars($user['fullname']); ?></h4>
                            <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                            <span class="badge bg-danger px-3 py-2 mb-3">
                                <i class="bi bi-shield-lock me-1"></i>Administrator
                            </span>
                            <hr class="my-4">
                            <div class="text-start px-3">
                                <p class="mb-2 small">
                                    <i class="bi bi-phone me-2 text-primary"></i>
                                    <span class="text-muted">Phone:</span> <?php echo $user['phone'] ?: 'Not provided'; ?>
                                </p>
                                <p class="mb-2 small">
                                    <i class="bi bi-calendar me-2 text-primary"></i>
                                    <span class="text-muted">Joined:</span> <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </p>
                                <p class="mb-0 small">
                                    <i class="bi bi-activity me-2 text-primary"></i>
                                    <span class="text-muted">Status:</span> <span class="badge bg-success bg-opacity-10 text-success"><?php echo ucfirst($user['status']); ?></span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Forms -->
                <div class="col-lg-8">
                    <!-- Update Profile Form -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0"><i class="bi bi-person-gear me-2 text-primary"></i>Edit Profile Information</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="row g-3">
                                    <div class="col-md-12 mb-3">
                                        <label for="avatar" class="form-label fw-bold">Profile Picture</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo $user['avatar'] ? SITE_URL . '/uploads/avatars/' . $user['avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($user['fullname']) . '&size=100&background=6366f1&color=fff'; ?>" 
                                                 alt="Current Avatar" 
                                                 id="avatarPreview"
                                                 class="rounded-circle shadow-sm"
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                            <div class="flex-grow-1">
                                                <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*" onchange="previewAvatar(this)">
                                                <div class="form-text">Recommended: Square image, max 2MB.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="fullname" class="form-label fw-bold">Full Name</label>
                                        <input type="text" class="form-control" id="fullname" name="fullname" 
                                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label fw-bold">Email Address</label>
                                        <input type="email" class="form-control bg-light" id="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                                        <div class="form-text">Email address cannot be changed.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label fw-bold">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               placeholder="e.g. +233 24 000 0000">
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-primary px-4">
                                            <i class="bi bi-check-lg me-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Change Password Form -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0"><i class="bi bi-shield-lock me-2 text-primary"></i>Security & Password</h5>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label for="current_password" class="form-label fw-bold">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label fw-bold">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                                        <div class="form-text">Minimum 8 characters.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label fw-bold">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="col-12 mt-4">
                                        <button type="submit" class="btn btn-outline-primary px-4">
                                            <i class="bi bi-key me-2"></i>Update Password
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('avatarPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
