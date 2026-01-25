<?php
/**
 * Admin - Edit Event
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$pdo = getDBConnection();
$id = intval($_GET['id'] ?? 0);

// Fetch event
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$id]);
$event = $stmt->fetch();

if (!$event) {
    setFlash('danger', 'Event not found.');
    redirect('admin/events/');
}

$page_title = 'Edit Event';
$errors = [];

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
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (strtotime($end_date) <= strtotime($start_date)) $errors[] = 'End date must be after start date.';
        
        // Handle file upload
        $banner = $event['banner'];
        if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024;
            
            if (!in_array($_FILES['banner']['type'], $allowedTypes)) {
                $errors[] = 'Invalid image type.';
            } elseif ($_FILES['banner']['size'] > $maxSize) {
                $errors[] = 'Image size must be less than 5MB.';
            } else {
                $extension = pathinfo($_FILES['banner']['name'], PATHINFO_EXTENSION);
                $banner = $event['slug'] . '-' . time() . '.' . $extension;
                $uploadPath = '../../uploads/events/' . $banner;
                
                if (!is_dir('../../uploads/events/')) {
                    mkdir('../../uploads/events/', 0755, true);
                }
                
                if (move_uploaded_file($_FILES['banner']['tmp_name'], $uploadPath)) {
                    // Delete old banner
                    if ($event['banner'] && file_exists('../../uploads/events/' . $event['banner'])) {
                        unlink('../../uploads/events/' . $event['banner']);
                    }
                } else {
                    $errors[] = 'Failed to upload image.';
                    $banner = $event['banner'];
                }
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET title = ?, description = ?, banner = ?, start_date = ?, end_date = ?, 
                        status = ?, voting_type = ?, vote_price = ?, is_featured = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $description, $banner, $start_date, $end_date,
                    $status, $voting_type, $vote_price, $is_featured, $id
                ]);
                
                setFlash('success', 'Event updated successfully!');
                redirect('admin/events/');
            } catch (PDOException $e) {
                $errors[] = 'Failed to update event.';
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
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">Edit Event</h2>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?php echo SITE_URL; ?>/event-details.php?slug=<?php echo $event['slug']; ?>" class="btn btn-outline-info" target="_blank">
                        <i class="bi bi-eye me-2"></i>View
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </a>
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

            <!-- Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-4">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Event Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($event['title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($event['description']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="datetime-local" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="end_date" class="form-label">End Date *</label>
                                        <input type="datetime-local" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>" required>
                                    </div>
                                </div>

                                <!-- Quick Links -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">Quick Actions</h6>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <a href="../categories/?event=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-collection me-1"></i>Manage Categories
                                            </a>
                                            <a href="../nominees/?event=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-people me-1"></i>Manage Nominees
                                            </a>
                                            <a href="../votes/?event=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-check2-square me-1"></i>View Votes
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="banner" class="form-label">Banner Image</label>
                                    <?php if ($event['banner']): ?>
                                        <div class="mb-2">
                                            <img src="../../uploads/events/<?php echo $event['banner']; ?>" class="img-fluid rounded" id="bannerPreview">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*" onchange="previewImage(this, 'bannerPreview')">
                                    <small class="text-muted">Leave empty to keep current image</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="draft" <?php echo $event['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="active" <?php echo $event['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                                    <input type="number" class="form-control" id="vote_price" name="vote_price" step="0.01" min="0" 
                                           value="<?php echo $event['vote_price']; ?>" required>
                                </div>
                                
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?php echo $event['is_featured'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        <i class="bi bi-star-fill text-warning me-1"></i>Featured Event
                                    </label>
                                </div>

                                <hr>

                                <div class="text-muted small">
                                    <p class="mb-1"><strong>Slug:</strong> <?php echo $event['slug']; ?></p>
                                    <p class="mb-1"><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($event['created_at'])); ?></p>
                                    <p class="mb-0"><strong>Updated:</strong> <?php echo date('M d, Y H:i', strtotime($event['updated_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-2"></i>Update Event
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
