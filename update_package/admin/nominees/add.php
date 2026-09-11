<?php
/**
 * Admin - Add Nominee
 * Awards Voting System
 */

require_once '../../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Add Nominee';
$pdo = getDBConnection();

// Fetch categories with events
$categoriesStmt = $pdo->query("
    SELECT c.id, c.name, e.title as event_title 
    FROM categories c 
    JOIN events e ON c.event_id = e.id 
    WHERE c.status = 'active' AND e.status = 'active'
    ORDER BY e.title, c.name
");
$categories = $categoriesStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $category_id = intval($_POST['category_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $shortcode = strtoupper(sanitize($_POST['shortcode'] ?? ''));
        $status = $_POST['status'] ?? 'active';
        
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        
        if (empty($category_id)) $errors[] = 'Please select a category.';
        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($shortcode)) $errors[] = 'Shortcode is required.';
        
        // Check unique shortcode
        $checkCode = $pdo->prepare("SELECT id FROM nominees WHERE shortcode = ?");
        $checkCode->execute([$shortcode]);
        if ($checkCode->fetch()) {
            $errors[] = 'Shortcode already exists. Please choose another.';
        }
        
        if ($checkCode->fetch()) {
            $errors[] = 'Shortcode already exists. Please choose another.';
        }
        
        // Handle photo upload
        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['photo']['type'], $allowedTypes)) {
                $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                $photo = $slug . '-' . time() . '.' . $extension;
                
                if (!is_dir('../../uploads/nominees/')) mkdir('../../uploads/nominees/', 0755, true);
                move_uploaded_file($_FILES['photo']['tmp_name'], '../../uploads/nominees/' . $photo);
            }
        }
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO nominees (category_id, name, slug, photo, description, shortcode, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$category_id, $name, $slug, $photo, $description, $shortcode, $status]);
                setFlash('success', 'Nominee created successfully!');
                redirect('admin/nominees/');
            } catch (PDOException $e) {
                $errors[] = 'Failed to create nominee.';
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="index.php">Nominees</a></li>
                            <li class="breadcrumb-item active">Add New</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">Add Nominee</h2>
                </div>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo $e; ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Category *</label>
                                    <select name="category_id" class="form-select" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>">
                                                <?php echo htmlspecialchars($cat['name'] . ' - ' . $cat['event_title']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nominee Name *</label>
                                    <input type="text" name="name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Shortcode * <small class="text-muted">(Unique voting code)</small></label>
                                    <input type="text" name="shortcode" class="form-control text-uppercase" maxlength="20" required placeholder="e.g., NM001">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Photo</label>
                                    <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewImage(this, 'photoPreview')">
                                    <img id="photoPreview" class="mt-2 rounded" style="max-width: 100%; display: none;">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Create Nominee</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo SITE_URL; ?>/assets/js/admin.js"></script>
</body>
</html>
