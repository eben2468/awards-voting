<?php
/**
 * Organizer - Add Event
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require organizer
if (!isLoggedIn() || !isOrganizer()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Add Event';
$pdo = getDBConnection();
$errors = [];
$organizer_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $voting_type = $_POST['voting_type'] ?? 'paid';
        $vote_price = floatval($_POST['vote_price'] ?? 0);
        
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (strtotime($end_date) <= strtotime($start_date)) $errors[] = 'End date must be after start date.';
        
        // Check unique slug
        $checkSlug = $pdo->prepare("SELECT id FROM events WHERE slug = ?");
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            $slug .= '-' . time();
        }
        
        // Handle file upload
        $banner = null;
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($_FILES['banner']['type'], $allowedTypes)) {
                $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WebP';
            } elseif ($_FILES['banner']['size'] > $maxSize) {
                $errors[] = 'Image size must be less than 5MB.';
            } else {
                $extension = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
                $banner = $slug . '-' . time() . '.' . $extension;
                $uploadPath = '../../uploads/events/' . $banner;
                
                if (!is_dir('../../uploads/events/')) {
                    mkdir('../../uploads/events/', 0755, true);
                }
                
                if (!move_uploaded_file($_FILES['banner']['tmp_name'], $uploadPath)) {
                    $errors[] = 'Failed to upload image.';
                    $banner = null;
                }
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO events (title, slug, description, banner, start_date, end_date, status, voting_type, vote_price, created_by, organizer_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $slug, $description, $banner, $start_date, $end_date, 
                    $status, $voting_type, $vote_price, $organizer_id, $organizer_id
                ]);
                
                // Notify Admin
                createNotification(
                    1, // Admin ID
                    "New Event Created",
                    "A new event ($title) has been created by " . ($_SESSION['user_name'] ?? 'an organizer'),
                    "info",
                    "admin/events/"
                );

                setFlash('success', 'Event created successfully!');
                redirect('organizer/events/');
            } catch (PDOException $e) {
                $errors[] = 'Failed to create event: ' . $e->getMessage();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/admin.css">
</head>
<body class="admin-body">
    <?php include '../includes/sidebar.php'; ?>

    <main class="admin-main">
        <?php include '../includes/topbar.php'; ?>

        <div class="admin-content p-4">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="index.php">Events</a></li>
                            <li class="breadcrumb-item active">Add New</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">Add New Event</h2>
                </div>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back
                </a>
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

            <!-- Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_date" class="form-label">End Date *</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="banner" class="form-label">Banner Image</label>
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?php echo ($_POST['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="active" <?php echo ($_POST['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo ($_POST['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="voting_type" class="form-label">Voting Type</label>
                                    <select class="form-select" id="voting_type" name="voting_type" readonly>
                                        <option value="paid" selected>Paid Voting</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="priceField">
                                    <label for="vote_price" class="form-label">Vote Price (GHS)</label>
                                    <input type="number" class="form-control" id="vote_price" name="vote_price" step="0.01" min="0" value="<?php echo $_POST['vote_price'] ?? '0'; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Create Event
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
    <script>
        // Preview image
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById(previewId).src = e.target.result;
                    document.getElementById(previewId).style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
