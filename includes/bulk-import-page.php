<?php
/**
 * The bulk upload page, shared by the admin and organizer portals.
 *
 * The including page checks who is signed in, then sets:
 *   $bulkType        'categories' or 'nominees'
 *   $bulkPortal      'admin' or 'organizer'
 *   $bulkOrganizerId The organizer's id, or null for an admin
 */

require_once __DIR__ . '/bulk-import.php';

$isCategories = $bulkType === 'categories';
$singular = $isCategories ? 'category' : 'nominee';
$page_title = 'Bulk Upload ' . ($isCategories ? 'Categories' : 'Nominees');
$columns = bulkColumns($bulkType);
$pdo = getDBConnection();

// Only events this person may add to. Nominees also need a category to go into.
$conditions = [];
$params = [];
if ($bulkOrganizerId !== null) {
    $conditions[] = 'e.organizer_id = ?';
    $params[] = $bulkOrganizerId;
}
if (!$isCategories) {
    $conditions[] = "e.status = 'active'";
    $conditions[] = "EXISTS (SELECT 1 FROM categories c WHERE c.event_id = e.id AND c.status = 'active')";
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$eventsStmt = $pdo->prepare("SELECT e.id, e.title FROM events e $where ORDER BY e.title");
$eventsStmt->execute($params);
$events = array_column($eventsStmt->fetchAll(), 'title', 'id');

$selectedEvent = intval($_POST['event_id'] ?? $_GET['event'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token. Please try again.';
    } elseif (!isset($events[$selectedEvent])) {
        $errors[] = 'Please choose an event.';
    } else {
        $rows = readBulkCsv($_FILES['csv_file'] ?? [], $columns, $errors);

        if ($rows && !$errors) {
            $prepared = $isCategories
                ? prepareBulkCategories($pdo, $selectedEvent, $rows, $errors)
                : prepareBulkNominees($pdo, $selectedEvent, $rows, $errors);
        }

        if (!$errors) {
            try {
                saveBulkRows($pdo, $bulkType, $selectedEvent, $prepared);
                $count = count($prepared);
                logAudit(getCurrentUserId(), 'bulk_upload_' . $bulkType, $bulkType, null, null, ['event_id' => $selectedEvent, 'rows' => $count]);

                $label = $count === 1 ? $singular : ($isCategories ? 'categories' : 'nominees');
                setFlash('success', "$count $label added." . ($isCategories ? '' : ' Open a nominee\'s Edit page to add their photo.'));
                redirect("$bulkPortal/$bulkType/?event=$selectedEvent");
            } catch (PDOException $e) {
                error_log('Bulk upload error: ' . $e->getMessage());
                $errors[] = 'The rows could not be saved, so nothing was added. Please try again.';
            }
        }
    }
}

$hiddenErrors = max(0, count($errors) - BULK_MAX_REPORTED_ERRORS);
$shownErrors = array_slice($errors, 0, BULK_MAX_REPORTED_ERRORS);
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="index.php"><?php echo $isCategories ? 'Categories' : 'Nominees'; ?></a></li>
                            <li class="breadcrumb-item active">Bulk Upload</li>
                        </ol>
                    </nav>
                    <h2 class="mb-0"><?php echo $page_title; ?></h2>
                </div>
                <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-2"></i>Back</a>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger">
                    <strong>Nothing was added.</strong> Fix these and upload the file again:
                    <ul class="mb-0 mt-2">
                        <?php foreach ($shownErrors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                        <?php if ($hiddenErrors): ?>
                            <li>And <?php echo $hiddenErrors; ?> more.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <?php if (!$events): ?>
                                <p class="mb-0 text-muted">
                                    <?php echo $isCategories
                                        ? 'Create an event first, then you can upload its categories here.'
                                        : 'You need an active event with at least one active category before you can upload nominees.'; ?>
                                </p>
                            <?php else: ?>
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                                    <div class="mb-3">
                                        <label class="form-label" for="event_id">Event *</label>
                                        <select name="event_id" id="event_id" class="form-select" required>
                                            <option value="">Select Event</option>
                                            <?php foreach ($events as $id => $title): ?>
                                                <option value="<?php echo (int) $id; ?>" <?php echo (int) $id === $selectedEvent ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" for="csv_file">CSV file *</label>
                                        <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,text/csv" required>
                                        <div class="form-text">Up to <?php echo BULK_MAX_ROWS; ?> rows and 2MB.</div>
                                    </div>

                                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Upload <?php echo $isCategories ? 'Categories' : 'Nominees'; ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <h5 class="mb-0">File layout</h5>
                                <a href="<?php echo assetUrl("assets/samples/$bulkType-sample.csv"); ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center" download="<?php echo $bulkType; ?>-sample.csv">
                                    <i class="bi bi-download me-2"></i>Download sample CSV
                                </a>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-3">
                                    <thead>
                                        <tr><th>Column</th><th>What to enter</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($columns as $name => $column): ?>
                                            <tr>
                                                <td><code><?php echo $name; ?></code><?php echo $column['required'] ? ' *' : ''; ?></td>
                                                <td><?php echo htmlspecialchars($column['help']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-muted small mb-0">
                                Keep the headings in the first row. If any row has a problem, nothing is added and you'll see which rows to fix.
                                <?php if (!$isCategories): ?>
                                    Add the categories first. Photos can't go in a CSV, so add them from each nominee's Edit page.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo assetUrl('assets/js/admin.js'); ?>"></script>
</body>
</html>
