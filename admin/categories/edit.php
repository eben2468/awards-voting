<?php
/**
 * Admin - Edit Category
 * Awards Voting System
 */

require_once '../../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$pdo = getDBConnection();
$id = intval($_GET['id'] ?? 0);

// Fetch category
$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    setFlash('danger', 'Category not found.');
    redirect('admin/categories/');
}

$page_title = 'Edit Category';

// Fetch events
$eventsStmt = $pdo->query("SELECT id, title FROM events ORDER BY title");
$events = $eventsStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        $event_id = intval($_POST['event_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $display_order = intval($_POST['display_order'] ?? 0);
        $access_code = sanitize($_POST['access_code'] ?? '');
        $status = $_POST['status'] ?? 'active';
        $allow_results = isset($_POST['allow_results']) ? 1 : 0;
        
        if (empty($event_id)) $errors[] = 'Please select an event.';
        if (empty($name)) $errors[] = 'Category name is required.';
        if (empty($access_code)) $errors[] = 'Access code is required.';
        
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE categories 
                    SET event_id = ?, name = ?, description = ?, display_order = ?, access_code = ?, status = ?, allow_results = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$event_id, $name, $description, $display_order, $access_code, $status, $allow_results, $id]);
                setFlash('success', 'Category updated successfully!');
                redirect("admin/categories/?event=$event_id");
            } catch (PDOException $e) {
                $errors[] = 'Failed to update category.';
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
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/logo/share-image.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
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
                            <li class="breadcrumb-item"><a href="index.php">Categories</a></li>
                            <li class="breadcrumb-item active">Edit</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0">Edit Category</h2>
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
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Event *</label>
                                <select name="event_id" class="form-select" required>
                                    <option value="">Select Event</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?php echo $event['id']; ?>" <?php echo $category['event_id'] == $event['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Category Name *</label>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($category['name']); ?>" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($category['description']); ?></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo $category['display_order']; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?php echo $category['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $category['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Access Code * <small class="text-muted">(Shared code for nominees in this category)</small></label>
                                <div class="input-group">
                                    <input type="text" name="access_code" id="access_code" class="form-control" maxlength="20" required 
                                           value="<?php echo htmlspecialchars($category['access_code']); ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateCode()">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="allow_results" id="allow_results" value="1" 
                                           <?php echo $category['allow_results'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-semibold" for="allow_results">
                                        Allow nominees to access results
                                    </label>
                                    <br><small class="text-muted">When enabled, nominees can use their access code to view the live dashboard.</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Update Category</button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
    <script>
        function generateCode() {
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            let result = 'CAT-';
            for (let i = 0; i < 8; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('access_code').value = result;
        }
    </script>
</body>
</html>
