<?php
/**
 * Admin - Edit Event
 * Awards Voting System
 */

require_once '../../config/database.php';
require_once '../../includes/image-upload.php';

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

// Fetch existing ticket categories
$stmtCat = $pdo->prepare("SELECT * FROM ticket_categories WHERE event_id = ? ORDER BY id ASC");
$stmtCat->execute([$id]);
$ticket_categories = $stmtCat->fetchAll();

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
        // Voting is paid only. An event that was already free can stay free, but none can become free.
        $voting_type = ($event['voting_type'] === 'free' && ($_POST['voting_type'] ?? '') === 'free') ? 'free' : 'paid';
        $vote_price = floatval($_POST['vote_price'] ?? 0);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $event_type = sanitize($_POST['event_type'] ?? 'voting');
        $event_code = !empty($_POST['event_code']) ? sanitize(strtoupper(trim($_POST['event_code']))) : null;
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (strtotime($end_date) <= strtotime($start_date)) $errors[] = 'End date must be after start date.';
        if ($event_type !== 'ticketing' && $voting_type === 'paid' && $vote_price <= 0) $errors[] = 'Enter a vote price above GHS 0.00.';
        
        $category_ids = $_POST['category_ids'] ?? [];
        $category_names = $_POST['category_names'] ?? [];
        $category_prices = $_POST['category_prices'] ?? [];
        
        if ($event_type === 'ticketing' || $event_type === 'both') {
            if (empty($event_code)) {
                $errors[] = 'Event code is required for ticketing events.';
            } else {
                $checkCode = $pdo->prepare("SELECT id FROM events WHERE event_code = ? AND id != ?");
                $checkCode->execute([$event_code, $id]);
                if ($checkCode->fetch()) {
                    $errors[] = 'Event code must be unique. This code is already in use.';
                }
            }
            
            if (empty($category_names)) {
                $errors[] = 'At least one ticket category is required for ticketing events.';
            }
            
            // Check if any deleted category has tickets sold
            $stmtExist = $pdo->prepare("SELECT id, name FROM ticket_categories WHERE event_id = ?");
            $stmtExist->execute([$id]);
            $existingCats = $stmtExist->fetchAll(PDO::FETCH_KEY_PAIR); // id => name
            
            $submitted_ids_clean = array_filter(array_map('intval', $category_ids));
            $toDelete = array_diff(array_keys($existingCats), $submitted_ids_clean);
            
            foreach ($toDelete as $catIdToDelete) {
                $stmtTkt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_category_id = ?");
                $stmtTkt->execute([$catIdToDelete]);
                if ($stmtTkt->fetchColumn() > 0) {
                    $errors[] = "Cannot delete category '" . $existingCats[$catIdToDelete] . "' because tickets have already been sold for it.";
                }
            }
        }
        
        // Handle file upload. Oversized images are compressed, not refused.
        $banner = $event['banner'];
        $bannerError = '';
        $newBanner = empty($errors) ? storeImageFromForm('banner', '../../uploads/events', $event['slug'], $bannerError) : null;
        if ($bannerError !== '') {
            $errors[] = $bannerError;
        } elseif ($newBanner !== null) {
            deleteStoredImage('../../uploads/events', $event['banner']);
            $banner = $newBanner;
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                // Automatically switch to active if previously completed and end date is set to future
                if ($event['status'] === 'completed' && strtotime($end_date) > time()) {
                    $status = 'active';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET title = ?, description = ?, banner = ?, start_date = ?, end_date = ?, 
                        status = ?, voting_type = ?, vote_price = ?, is_featured = ?, event_type = ?, event_code = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $title, $description, $banner, $start_date, $end_date,
                    $status, $voting_type, $vote_price, $is_featured, $event_type, $event_code, $id
                ]);
                
                // If event status is active and end date is in the future, activate all nominees
                if ($status === 'active' && strtotime($end_date) > time()) {
                    $actNomStmt = $pdo->prepare("
                        UPDATE nominees n
                        JOIN categories c ON n.category_id = c.id
                        SET n.status = 'active'
                        WHERE c.event_id = ?
                    ");
                    $actNomStmt->execute([$id]);
                }
                
                if ($event_type === 'ticketing' || $event_type === 'both') {
                    // 1. Delete categories that were removed
                    if (!empty($toDelete)) {
                        $placeholders = str_repeat('?,', count($toDelete) - 1) . '?';
                        $delStmt = $pdo->prepare("DELETE FROM ticket_categories WHERE id IN ($placeholders) AND event_id = ?");
                        $params = array_merge(array_values($toDelete), [$id]);
                        $delStmt->execute($params);
                    }
                    
                    // 2. Insert or update submitted categories
                    $updateStmt = $pdo->prepare("UPDATE ticket_categories SET name = ?, price = ? WHERE id = ? AND event_id = ?");
                    $insertStmt = $pdo->prepare("INSERT INTO ticket_categories (event_id, name, price) VALUES (?, ?, ?)");
                    
                    foreach ($category_names as $i => $catName) {
                        $catPrice = floatval($category_prices[$i] ?? 0);
                        $catId = intval($category_ids[$i] ?? 0);
                        
                        if (empty(trim($catName))) continue;
                        
                        if ($catId > 0 && isset($existingCats[$catId])) {
                            $updateStmt->execute([sanitize($catName), $catPrice, $catId, $id]);
                        } else {
                            $insertStmt->execute([$id, sanitize($catName), $catPrice]);
                        }
                    }
                }
                
                $pdo->commit();
                
                setFlash('success', 'Event updated successfully!');
                redirect('admin/events/');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Failed to update event: ' . $e->getMessage();
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Hanken+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/portal.css'); ?>">
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
                                        <label class="form-label fw-semibold"><i class="bi bi-calendar-event me-1"></i>Voting Start Date & Time *</label>
                                        <input type="hidden" id="start_date" name="start_date" value="<?php echo date('Y-m-d\TH:i', strtotime($event['start_date'])); ?>">
                                        <div class="input-group mb-2">
                                            <input type="date" class="form-control" id="start_date_date" 
                                                   value="<?php echo date('Y-m-d', strtotime($event['start_date'])); ?>" required>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" id="start_date_hour" style="width: auto;">
                                                <?php 
                                                $startH = intval(date('g', strtotime($event['start_date'])));
                                                for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $h === $startH ? 'selected' : ''; ?>><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <span class="d-flex align-items-center fw-bold">:</span>
                                            <select class="form-select" id="start_date_minute" style="width: auto;">
                                                <?php 
                                                $startM = intval(date('i', strtotime($event['start_date'])));
                                                for ($m = 0; $m < 60; $m += 5): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m === $startM || ($m <= $startM && $startM < $m + 5) ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <select class="form-select" id="start_date_ampm" style="width: auto;">
                                                <?php $startAmpm = date('A', strtotime($event['start_date'])); ?>
                                                <option value="AM" <?php echo $startAmpm === 'AM' ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo $startAmpm === 'PM' ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold"><i class="bi bi-calendar-x me-1 text-danger"></i>Voting End Date & Time *</label>
                                        <input type="hidden" id="end_date" name="end_date" value="<?php echo date('Y-m-d\TH:i', strtotime($event['end_date'])); ?>">
                                        <div class="input-group mb-2">
                                            <input type="date" class="form-control" id="end_date_date" 
                                                   value="<?php echo date('Y-m-d', strtotime($event['end_date'])); ?>" required>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" id="end_date_hour" style="width: auto;">
                                                <?php 
                                                $endH = intval(date('g', strtotime($event['end_date'])));
                                                for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $h === $endH ? 'selected' : ''; ?>><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <span class="d-flex align-items-center fw-bold">:</span>
                                            <select class="form-select" id="end_date_minute" style="width: auto;">
                                                <?php 
                                                $endM = intval(date('i', strtotime($event['end_date'])));
                                                for ($m = 0; $m < 60; $m += 5): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m === $endM || ($m <= $endM && $endM < $m + 5) ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <select class="form-select" id="end_date_ampm" style="width: auto;">
                                                <?php $endAmpm = date('A', strtotime($event['end_date'])); ?>
                                                <option value="AM" <?php echo $endAmpm === 'AM' ? 'selected' : ''; ?>>AM</option>
                                                <option value="PM" <?php echo $endAmpm === 'PM' ? 'selected' : ''; ?>>PM</option>
                                            </select>
                                        </div>
                                        <small class="text-danger mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Voting will automatically stop at this exact date and time.</small>
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
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*" data-compress-max="5242880" onchange="previewImage(this, 'bannerPreview')">
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
                                    <label for="event_type" class="form-label">Event Type *</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="voting" <?php echo $event['event_type'] === 'voting' ? 'selected' : ''; ?>>Award Voting Only</option>
                                        <option value="ticketing" <?php echo $event['event_type'] === 'ticketing' ? 'selected' : ''; ?>>Event Ticketing Only</option>
                                        <option value="both" <?php echo $event['event_type'] === 'both' ? 'selected' : ''; ?>>Both (Voting & Ticketing)</option>
                                    </select>
                                </div>

                                <div id="votingFields">
                                    <?php if ($event['voting_type'] === 'free'): ?>
                                    <div class="mb-3">
                                        <label for="voting_type" class="form-label">Voting Type</label>
                                        <select class="form-select" id="voting_type" name="voting_type">
                                            <option value="free" <?php echo $event['voting_type'] === 'free' ? 'selected' : ''; ?>>Free Voting</option>
                                            <option value="paid" <?php echo $event['voting_type'] === 'paid' ? 'selected' : ''; ?>>Paid Voting</option>
                                        </select>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mb-3" id="priceField">
                                        <label for="vote_price" class="form-label">Vote Price (GHS) *</label>
                                        <input type="number" class="form-control" id="vote_price" name="vote_price" step="0.01" min="0.01" value="<?php echo htmlspecialchars($_POST['vote_price'] ?? $event['vote_price']); ?>">
                                    </div>
                                </div>

                                <div id="ticketingFields" class="d-none">
                                    <div class="mb-3">
                                        <label for="event_code" class="form-label">Ticket Purchase Event Code *</label>
                                        <input type="text" class="form-control" id="event_code" name="event_code" placeholder="e.g. CONCERT26" value="<?php echo htmlspecialchars($event['event_code'] ?? ''); ?>">
                                        <div class="form-text small text-muted">A unique code used to buy tickets. E.g. "CONCERT26"</div>
                                    </div>
                                    
                                    <div class="card border-0 bg-light mb-3" style="border-radius: 0.75rem;">
                                        <div class="card-body p-3">
                                            <div class="mb-3 border-bottom pb-2">
                                                <h6 class="card-title mb-0 fw-bold text-dark d-flex align-items-center">
                                                    <i class="bi bi-tags-fill me-2 text-primary"></i>Ticket Categories & Prices
                                                </h6>
                                            </div>
                                            <div id="ticketCategoriesContainer" class="d-flex flex-column gap-2">
                                                <!-- Dynamic categories go here -->
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 rounded-pill mt-3 d-flex align-items-center justify-content-center gap-1 py-2 fw-semibold" id="btnAddCategory" style="border-style: dashed; border-width: 2px;">
                                                <i class="bi bi-plus-lg"></i> Add New Category
                                            </button>
                                        </div>
                                    </div>
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
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
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

        // Combine date + time + AM/PM into hidden datetime-local input
        function combineDatetime(prefix) {
            const dateVal = document.getElementById(prefix + '_date').value;
            let hour = parseInt(document.getElementById(prefix + '_hour').value);
            const minute = parseInt(document.getElementById(prefix + '_minute').value);
            const ampm = document.getElementById(prefix + '_ampm').value;

            if (!dateVal) return;

            // Convert 12-hour to 24-hour
            if (ampm === 'PM' && hour !== 12) hour += 12;
            if (ampm === 'AM' && hour === 12) hour = 0;

            const h = hour.toString().padStart(2, '0');
            const m = minute.toString().padStart(2, '0');
            document.getElementById(prefix).value = dateVal + 'T' + h + ':' + m;
        }

        // Attach listeners to all date/time fields
        ['start_date', 'end_date'].forEach(function(prefix) {
            ['_date', '_hour', '_minute', '_ampm'].forEach(function(suffix) {
                const el = document.getElementById(prefix + suffix);
                if (el) {
                    el.addEventListener('change', function() { combineDatetime(prefix); });
                }
            });
            // Initialize on page load
            combineDatetime(prefix);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const eventTypeSelect = document.getElementById('event_type');
            const votingFields = document.getElementById('votingFields');
            const ticketingFields = document.getElementById('ticketingFields');
            const priceField = document.getElementById('priceField');
            const votingTypeSelect = document.getElementById('voting_type');
            const eventCodeInput = document.getElementById('event_code');
            const container = document.getElementById('ticketCategoriesContainer');
            const btnAdd = document.getElementById('btnAddCategory');

            function toggleFields() {
                const type = eventTypeSelect.value;

                // Ticket-only events hide the price, so it must not block the form.
                // The same goes for an older event that is still free.
                const priceInput = document.getElementById('vote_price');
                const needsPrice = type !== 'ticketing' && !(votingTypeSelect && votingTypeSelect.value === 'free');
                priceInput.required = needsPrice;
                priceInput.min = needsPrice ? '0.01' : '0';

                if (type === 'voting') {
                    votingFields.classList.remove('d-none');
                    ticketingFields.classList.add('d-none');
                    eventCodeInput.removeAttribute('required');
                } else if (type === 'ticketing') {
                    votingFields.classList.add('d-none');
                    ticketingFields.classList.remove('d-none');
                    eventCodeInput.setAttribute('required', 'required');
                } else if (type === 'both') {
                    votingFields.classList.remove('d-none');
                    ticketingFields.classList.remove('d-none');
                    eventCodeInput.setAttribute('required', 'required');
                }
            }

            if (eventTypeSelect) {
                eventTypeSelect.addEventListener('change', toggleFields);
                toggleFields();
            }

            if (votingTypeSelect) {
                votingTypeSelect.addEventListener('change', function() {
                    toggleFields();
                    if (this.value === 'free') {
                        priceField.classList.add('d-none');
                    } else {
                        priceField.classList.remove('d-none');
                    }
                });
            }

            function addCategoryRow(id = '', name = '', price = '') {
                const row = document.createElement('div');
                row.className = 'd-flex align-items-center gap-2 category-row bg-white p-2 rounded shadow-sm border border-light';
                row.innerHTML = `
                    <input type="hidden" name="category_ids[]" value="${id}">
                    <div style="flex: 1; min-width: 0;">
                        <input type="text" class="form-control form-control-sm border-0 bg-light fw-medium px-2 py-1.5" name="category_names[]" placeholder="Category (e.g. VIP)" value="${name}" required style="font-size: 0.85rem;">
                    </div>
                    <div style="width: 120px; flex-shrink: 0;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light text-muted border-0 fw-bold px-2" style="font-size: 0.7rem; min-width: 38px; justify-content: center;">GHS</span>
                            <input type="number" class="form-control form-control-sm border-0 bg-light text-end fw-bold px-2" name="category_prices[]" placeholder="0.00" step="0.01" min="0" value="${price}" required style="font-size: 0.85rem;">
                        </div>
                    </div>
                    <button type="button" class="btn text-danger btn-remove-category p-0 flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; border-radius: 50%;" title="Remove Category">
                        <i class="bi bi-trash-fill" style="font-size: 0.95rem;"></i>
                    </button>
                `;
                container.appendChild(row);
                
                row.querySelector('.btn-remove-category').addEventListener('click', function() {
                    row.remove();
                });
            }

            if (btnAdd) {
                btnAdd.addEventListener('click', () => addCategoryRow());
            }

            // Load categories from database or old inputs if validation failed
            <?php 
            $oldNames = $_POST['category_names'] ?? [];
            $oldPrices = $_POST['category_prices'] ?? [];
            $oldIds = $_POST['category_ids'] ?? [];
            
            if (!empty($oldNames)) {
                foreach ($oldNames as $idx => $name) {
                    $price = $oldPrices[$idx] ?? '';
                    $catId = $oldIds[$idx] ?? '';
                    echo "addCategoryRow(" . json_encode($catId) . ", " . json_encode($name) . ", " . json_encode($price) . ");\n";
                }
            } else {
                // Load from DB
                if (!empty($ticket_categories)) {
                    foreach ($ticket_categories as $cat) {
                        echo "addCategoryRow(" . json_encode($cat['id']) . ", " . json_encode($cat['name']) . ", " . json_encode($cat['price']) . ");\n";
                    }
                } else {
                    echo "addCategoryRow('', 'Regular', '50.00');\n";
                }
            }
            ?>
        });
    </script>
    <script src="<?php echo assetUrl('assets/js/image-compress.js'); ?>"></script>
</body>
</html>
