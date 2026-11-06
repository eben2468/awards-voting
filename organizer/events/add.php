<?php
/**
 * Organizer - Add Event
 * Awards Voting System
 */

require_once '../../config/database.php';
require_once '../../includes/image-upload.php';

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
        $voting_type = 'paid'; // All voting on VoteHubGH is paid
        $vote_price = floatval($_POST['vote_price'] ?? 0);
        $event_type = sanitize($_POST['event_type'] ?? 'voting');
        $event_code = !empty($_POST['event_code']) ? sanitize(strtoupper(trim($_POST['event_code']))) : null;
        
        // Generate slug
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        
        // Validation
        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($start_date)) $errors[] = 'Start date is required.';
        if (empty($end_date)) $errors[] = 'End date is required.';
        if (strtotime($end_date) <= strtotime($start_date)) $errors[] = 'End date must be after start date.';
        if ($event_type !== 'ticketing' && $voting_type === 'paid' && $vote_price <= 0) $errors[] = 'Enter a vote price above GHS 0.00.';
        
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
        
        // Handle file upload. Oversized images are compressed, not refused.
        $bannerError = '';
        $banner = empty($errors) ? storeImageFromForm('banner', '../../uploads/events', $slug, $bannerError) : null;
        if ($bannerError !== '') {
            $errors[] = $bannerError;
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO events (title, slug, description, banner, start_date, end_date, status, voting_type, vote_price, event_type, event_code, created_by, organizer_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $title, $slug, $description, $banner, $start_date, $end_date, 
                    $status, $voting_type, $vote_price, $event_type, $event_code, $organizer_id, $organizer_id
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
                                           value="<?php echo e($_POST['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo e($_POST['description'] ?? ''); ?></textarea>
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
                                    <input type="file" class="form-control" id="banner" name="banner" accept="image/*" data-compress-max="5242880">
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
                                    <div class="mb-3" id="priceField">
                                        <label for="vote_price" class="form-label">Vote Price (GHS) *</label>
                                        <input type="number" class="form-control" id="vote_price" name="vote_price" step="0.01" min="0.01" placeholder="e.g. 1.00" value="<?php echo e($_POST['vote_price'] ?? ''); ?>">
                                        <div class="invalid-feedback">Enter a vote price above GHS 0.00.</div>
                                    </div>
                                </div>

                                <div id="ticketingFields" class="d-none">
                                    <div class="mb-3">
                                        <label for="event_code" class="form-label">Ticket Purchase Event Code *</label>
                                        <input type="text" class="form-control" id="event_code" name="event_code" placeholder="e.g. CONCERT26" value="<?php echo e($_POST['event_code'] ?? ''); ?>">
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

        document.addEventListener('DOMContentLoaded', function() {
            const eventTypeSelect = document.getElementById('event_type');
            const votingFields = document.getElementById('votingFields');
            const ticketingFields = document.getElementById('ticketingFields');
            const priceField = document.getElementById('priceField');
            const eventCodeInput = document.getElementById('event_code');
            const container = document.getElementById('ticketCategoriesContainer');
            const btnAdd = document.getElementById('btnAddCategory');

            function toggleFields() {
                const type = eventTypeSelect.value;

                // Ticket-only events hide the price, so it must not block the form
                const priceInput = document.getElementById('vote_price');
                priceInput.required = type !== 'ticketing';
                priceInput.min = type !== 'ticketing' ? '0.01' : '0';

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
    <script src="<?php echo assetUrl('assets/js/image-compress.js'); ?>"></script>
</body>
</html>
