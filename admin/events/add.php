<?php
/**
 * Admin - Add Event
 * Awards Voting System
 */

require_once '../../config/database.php';

// Require admin
if (!isLoggedIn() || !isAdmin()) {
    setFlash('danger', 'Access denied.');
    redirect('login.php');
}

$page_title = 'Add Event';
$pdo = getDBConnection();
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
        $event_type = sanitize($_POST['event_type'] ?? 'voting');
        $event_code = !empty($_POST['event_code']) ? sanitize(strtoupper(trim($_POST['event_code']))) : null;
        
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (strtotime($end_date) <= strtotime($start_date)) $errors[] = 'End date must be after start date.';
        
        if ($event_type === 'ticketing' || $event_type === 'both') {
            if (empty($event_code)) {
                $errors[] = 'Event code is required for ticketing events.';
            } else {
                $checkCode = $pdo->prepare("SELECT id FROM events WHERE event_code = ?");
                $checkCode->execute([$event_code]);
                if ($checkCode->fetch()) {
                    $errors[] = 'Event code must be unique. This code is already in use.';
                }
            }
            
            $category_names = $_POST['category_names'] ?? [];
            $category_prices = $_POST['category_prices'] ?? [];
            if (empty($category_names)) {
                $errors[] = 'At least one ticket category is required for ticketing events.';
            }
        }
        
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
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO events (title, slug, description, banner, start_date, end_date, status, voting_type, vote_price, is_featured, event_type, event_code, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $slug, $description, $banner, $start_date, $end_date, 
                    $status, $voting_type, $vote_price, $is_featured, $event_type, $event_code, getCurrentUserId()
                ]);
                
                $eventId = $pdo->lastInsertId();
                if ($event_type === 'ticketing' || $event_type === 'both') {
                    $insertCat = $pdo->prepare("INSERT INTO ticket_categories (event_id, name, price) VALUES (?, ?, ?)");
                    foreach ($category_names as $i => $catName) {
                        $catPrice = floatval($category_prices[$i] ?? 0);
                        if (!empty(trim($catName))) {
                            $insertCat->execute([$eventId, sanitize($catName), $catPrice]);
                        }
                    }
                }
                
                $pdo->commit();
                
                setFlash('success', 'Event created successfully!');
                redirect('admin/events/');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
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
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/admin.css'); ?>">
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
                                        <label class="form-label fw-semibold"><i class="bi bi-calendar-event me-1"></i>Voting Start Date & Time *</label>
                                        <input type="hidden" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? $_POST['start_date'] : ''; ?>">
                                        <div class="input-group mb-2">
                                            <input type="date" class="form-control" id="start_date_date" 
                                                   value="<?php echo isset($_POST['start_date']) ? substr($_POST['start_date'], 0, 10) : ''; ?>" required>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" id="start_date_hour" style="width: auto;">
                                                <?php for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $h === 12 ? 'selected' : ''; ?>><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <span class="d-flex align-items-center fw-bold">:</span>
                                            <select class="form-select" id="start_date_minute" style="width: auto;">
                                                <?php for ($m = 0; $m < 60; $m += 5): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m === 0 ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <select class="form-select" id="start_date_ampm" style="width: auto;">
                                                <option value="AM" selected>AM</option>
                                                <option value="PM">PM</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold"><i class="bi bi-calendar-x me-1 text-danger"></i>Voting End Date & Time *</label>
                                        <input type="hidden" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? $_POST['end_date'] : ''; ?>">
                                        <div class="input-group mb-2">
                                            <input type="date" class="form-control" id="end_date_date" 
                                                   value="<?php echo isset($_POST['end_date']) ? substr($_POST['end_date'], 0, 10) : ''; ?>" required>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" id="end_date_hour" style="width: auto;">
                                                <?php for ($h = 1; $h <= 12; $h++): ?>
                                                    <option value="<?php echo $h; ?>" <?php echo $h === 11 ? 'selected' : ''; ?>><?php echo str_pad($h, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <span class="d-flex align-items-center fw-bold">:</span>
                                            <select class="form-select" id="end_date_minute" style="width: auto;">
                                                <?php for ($m = 0; $m < 60; $m += 5): ?>
                                                    <option value="<?php echo $m; ?>" <?php echo $m === 55 ? 'selected' : ''; ?>><?php echo str_pad($m, 2, '0', STR_PAD_LEFT); ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <select class="form-select" id="end_date_ampm" style="width: auto;">
                                                <option value="AM">AM</option>
                                                <option value="PM" selected>PM</option>
                                            </select>
                                        </div>
                                        <small class="text-danger mt-1 d-block"><i class="bi bi-info-circle me-1"></i>Voting will automatically stop at this exact date and time.</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="banner" class="form-label">Banner Image</label>
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*" onchange="previewImage(this, 'bannerPreview')">
                                    <img id="bannerPreview" class="mt-2 rounded" style="max-width: 100%; display: none;">
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
                                    <label for="event_type" class="form-label">Event Type *</label>
                                    <select class="form-select" id="event_type" name="event_type" required>
                                        <option value="voting" <?php echo ($_POST['event_type'] ?? '') === 'voting' ? 'selected' : ''; ?>>Award Voting Only</option>
                                        <option value="ticketing" <?php echo ($_POST['event_type'] ?? '') === 'ticketing' ? 'selected' : ''; ?>>Event Ticketing Only</option>
                                        <option value="both" <?php echo ($_POST['event_type'] ?? '') === 'both' ? 'selected' : ''; ?>>Both (Voting & Ticketing)</option>
                                    </select>
                                </div>

                                <div id="votingFields">
                                    <div class="mb-3">
                                        <label for="voting_type" class="form-label">Voting Type</label>
                                        <select class="form-select" id="voting_type" name="voting_type">
                                            <option value="free" <?php echo ($_POST['voting_type'] ?? '') === 'free' ? 'selected' : ''; ?>>Free Voting</option>
                                            <option value="paid" <?php echo ($_POST['voting_type'] ?? 'paid') === 'paid' ? 'selected' : ''; ?>>Paid Voting</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3" id="priceField">
                                        <label for="vote_price" class="form-label">Vote Price (GHS)</label>
                                        <input type="number" class="form-control" id="vote_price" name="vote_price" step="0.01" min="0" value="<?php echo $_POST['vote_price'] ?? '0.00'; ?>">
                                    </div>
                                </div>

                                <div id="ticketingFields" class="d-none">
                                    <div class="mb-3">
                                        <label for="event_code" class="form-label">Ticket Purchase Event Code *</label>
                                        <input type="text" class="form-control" id="event_code" name="event_code" placeholder="e.g. CONCERT26" value="<?php echo htmlspecialchars($_POST['event_code'] ?? ''); ?>">
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
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured" <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_featured">
                                        <i class="bi bi-star-fill text-warning me-1"></i>Featured Event
                                    </label>
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
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
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

        function combineDatetime(prefix) {
            const dateVal = document.getElementById(prefix + '_date').value;
            let hour = parseInt(document.getElementById(prefix + '_hour').value);
            const minute = parseInt(document.getElementById(prefix + '_minute').value);
            const ampm = document.getElementById(prefix + '_ampm').value;
            if (!dateVal) return;
            if (ampm === 'PM' && hour !== 12) hour += 12;
            if (ampm === 'AM' && hour === 12) hour = 0;
            const h = hour.toString().padStart(2, '0');
            const m = minute.toString().padStart(2, '0');
            document.getElementById(prefix).value = dateVal + 'T' + h + ':' + m;
        }

        ['start_date', 'end_date'].forEach(function(prefix) {
            ['_date', '_hour', '_minute', '_ampm'].forEach(function(suffix) {
                const el = document.getElementById(prefix + suffix);
                if (el) el.addEventListener('change', function() { combineDatetime(prefix); });
            });
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

            // Load old inputs if validation failed
            <?php 
            $oldNames = $_POST['category_names'] ?? [];
            $oldPrices = $_POST['category_prices'] ?? [];
            if (!empty($oldNames)) {
                foreach ($oldNames as $idx => $name) {
                    $price = $oldPrices[$idx] ?? '';
                    echo "addCategoryRow('', " . json_encode($name) . ", " . json_encode($price) . ");\n";
                }
            } else {
                // If brand new, add one default row
                echo "addCategoryRow('', 'Regular', '50.00');\n";
            }
            ?>
        });
    </script>
</body>
</html>
